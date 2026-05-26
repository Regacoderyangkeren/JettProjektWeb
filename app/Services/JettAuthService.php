<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use Google\Cloud\Firestore\DocumentReference;
use Kreait\Firebase\Auth\SignInResult;
use Kreait\Firebase\Auth\UserRecord;
use Throwable;

class JettAuthService
{
    public function __construct(private readonly FirebaseService $firebase) {}

    public function register(array $data): array
    {
        $auth = $this->firebase->auth();
        $userRecord = $auth->createUserWithEmailAndPassword($data['email'], $data['password']);

        try {
            $profile = $this->defaultUserData($userRecord, $data);
            $this->userDocument($userRecord->uid)->set($profile, ['merge' => true]);

            return [
                'uid' => $userRecord->uid,
                'profile' => $profile,
            ];
        } catch (Throwable $exception) {
            try {
                $auth->deleteUser($userRecord->uid);
            } catch (Throwable) {
                // Keep the original Firestore error visible to the caller.
            }

            throw $exception;
        }
    }

    public function login(string $email, string $password): array
    {
        $result = $this->firebase->auth()->signInWithEmailAndPassword($email, $password);
        $uid = $this->requireUid($result);

        try {
            $profile = $this->ensureUserDocument($uid, $email);
        } catch (Throwable $exception) {
            report($exception);
            $profile = $this->fallbackProfile($uid, $email);
        }

        $presence = [
            'status' => 'ONLINE',
            'lastActiveAt' => $this->firebase->nowMillis(),
        ];

        try {
            $this->userDocument($uid)->set($presence, ['merge' => true]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return [
            'uid' => $uid,
            'profile' => array_merge($profile, $presence),
            'idToken' => $result->idToken(),
            'refreshToken' => $result->refreshToken(),
            'expiresIn' => $result->ttl(),
        ];
    }

    public function logout(string $uid): void
    {
        $this->userDocument($uid)->set([
            'status' => 'OFFLINE',
            'lastActiveAt' => $this->firebase->nowMillis(),
        ], ['merge' => true]);
    }

    public function user(string $uid): ?array
    {
        $snapshot = $this->userDocument($uid)->snapshot();

        return $snapshot->exists() ? $snapshot->data() : null;
    }

    public function verifyIdToken(string $idToken): array
    {
        $token = $this->firebase->auth()->verifyIdToken($idToken);
        $uid = $token->claims()->get('sub') ?: $token->claims()->get('user_id');

        if (! is_string($uid) || $uid === '') {
            throw new \RuntimeException('Firebase token did not contain a user id.');
        }

        $email = $token->claims()->get('email', '');
        $profile = $this->user($uid);

        if ($profile === null) {
            $profile = $this->ensureUserDocument($uid, is_string($email) ? $email : '');
        }

        return [
            'uid' => $uid,
            'profile' => $profile,
        ];
    }

    public function markOnline(string $uid): void
    {
        $this->userDocument($uid)->set([
            'status' => 'ONLINE',
            'lastActiveAt' => $this->firebase->nowMillis(),
        ], ['merge' => true]);
    }

    private function ensureUserDocument(string $uid, string $email): array
    {
        $existing = $this->user($uid);
        if ($existing !== null) {
            return $existing;
        }

        $userRecord = $this->firebase->auth()->getUser($uid);
        $profile = $this->defaultUserData($userRecord, [
            'email' => $email,
            'firstName' => $this->guessFirstName($userRecord, $email),
            'lastName' => '',
            'alias' => $this->guessAlias($userRecord, $email),
        ]);

        $this->userDocument($uid)->set($profile, ['merge' => true]);

        return $profile;
    }

    private function defaultUserData(UserRecord $userRecord, array $data): array
    {
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $alias = trim((string) ($data['alias'] ?? ''));
        $email = trim((string) ($data['email'] ?? $userRecord->email ?? ''));
        $now = $this->firebase->nowMillis();

        return [
            'id' => $userRecord->uid,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'alias' => $alias !== '' ? $alias : $this->guessAlias($userRecord, $email),
            'email' => $email,
            'appwriteEmail' => '',
            'gmail' => '',
            'github' => '',
            'description' => '',
            'profilePictureUrl' => $userRecord->photoUrl ?? '',
            'bannerUrl' => '',
            'bannerColor' => '',
            'themeColorHex' => '',
            'connectionIds' => [],
            'pendingConnectionIds' => [],
            'sentConnectionRequestIds' => [],
            'twoFactorEnabled' => false,
            'role' => 'member',
            'status' => 'ONLINE',
            'lastActiveAt' => $now,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    private function fallbackProfile(string $uid, string $email): array
    {
        return [
            'id' => $uid,
            'firstName' => '',
            'lastName' => '',
            'alias' => str(explode('@', $email)[0] ?? 'member')->slug('_')->toString() ?: 'member',
            'email' => $email,
            'profilePictureUrl' => '',
        ];
    }

    private function userDocument(string $uid): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('users'))
            ->document($uid);
    }

    private function requireUid(SignInResult $result): string
    {
        $uid = $result->firebaseUserId();

        if (! is_string($uid) || $uid === '') {
            throw new \RuntimeException('Firebase did not return a user id.');
        }

        return $uid;
    }

    private function guessFirstName(UserRecord $userRecord, string $email): string
    {
        if (is_string($userRecord->displayName) && $userRecord->displayName !== '') {
            return trim(explode(' ', $userRecord->displayName)[0]);
        }

        return $this->guessAlias($userRecord, $email);
    }

    private function guessAlias(UserRecord $userRecord, string $email): string
    {
        if (is_string($userRecord->displayName) && $userRecord->displayName !== '') {
            return str($userRecord->displayName)->slug('_')->toString();
        }

        $localPart = explode('@', $email)[0] ?? 'member';

        return str($localPart)->slug('_')->toString() ?: 'member';
    }
}
