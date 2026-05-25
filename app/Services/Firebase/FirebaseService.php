<?php

namespace App\Services\Firebase;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Factory;

class FirebaseService
{
    private ?array $serviceAccount = null;

    private ?Auth $auth = null;

    private ?FirestoreClient $firestore = null;

    public function auth(): Auth
    {
        if ($this->auth !== null) {
            return $this->auth;
        }

        $factory = (new Factory)
            ->withServiceAccount($this->serviceAccount())
            ->withProjectId($this->projectId());

        $bucket = config('jettprojekt.firebase.storage_bucket');
        if (is_string($bucket) && $bucket !== '') {
            $factory = $factory->withDefaultStorageBucket($bucket);
        }

        return $this->auth = $factory->createAuth();
    }

    public function firestore(): FirestoreClient
    {
        if ($this->firestore !== null) {
            return $this->firestore;
        }

        $credentials = new ServiceAccountCredentials(
            [FirestoreClient::FULL_CONTROL_SCOPE],
            $this->serviceAccount()
        );

        return $this->firestore = new FirestoreClient([
            'projectId' => $this->projectId(),
            'database' => $this->firestoreDatabase(),
            'credentials' => $credentials,
            'transport' => $this->firestoreTransport(),
        ]);
    }

    public function collectionName(string $key): string
    {
        $name = config("jettprojekt.collections.$key");

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException("Unknown JettProjekt collection [$key].");
        }

        return $name;
    }

    public function projectId(): string
    {
        $projectId = config('jettprojekt.firebase.project_id')
            ?: Arr::get($this->serviceAccount(), 'project_id');

        if (! is_string($projectId) || $projectId === '') {
            throw new InvalidArgumentException('Firebase project id is not configured.');
        }

        return $projectId;
    }

    public function firestoreDatabase(): string
    {
        $database = config('jettprojekt.firebase.firestore_database', '(default)');

        return is_string($database) && $database !== '' ? $database : '(default)';
    }

    public function firestoreTransport(): string
    {
        $transport = strtolower((string) config('jettprojekt.firebase.firestore_transport', 'rest'));

        return in_array($transport, ['rest', 'grpc'], true) ? $transport : 'rest';
    }

    public function nowMillis(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function credentialsConfigured(): bool
    {
        $json = config('jettprojekt.firebase.service_account_json');

        return is_string($json) && trim($json) !== '';
    }

    private function serviceAccount(): array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        $json = config('jettprojekt.firebase.service_account_json');

        if (! is_string($json) || trim($json) === '') {
            throw new InvalidArgumentException('FIREBASE_SERVICE_ACCOUNT_JSON is not configured.');
        }

        $credentials = json_decode($json, true);

        if (! is_array($credentials)) {
            throw new InvalidArgumentException('FIREBASE_SERVICE_ACCOUNT_JSON must be valid JSON.');
        }

        if (isset($credentials['private_key']) && is_string($credentials['private_key'])) {
            $credentials['private_key'] = str_replace('\\n', "\n", $credentials['private_key']);
        }

        foreach (['client_email', 'private_key', 'project_id'] as $key) {
            if (! isset($credentials[$key]) || ! is_string($credentials[$key]) || $credentials[$key] === '') {
                throw new InvalidArgumentException("Firebase service account is missing [$key].");
            }
        }

        return $this->serviceAccount = $credentials;
    }
}
