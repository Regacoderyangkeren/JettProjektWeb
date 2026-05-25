<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeamService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class TeamController extends Controller
{
    public function index(Request $request, TeamService $teams): JsonResponse
    {
        $uid = $this->uid($request);

        return response()->json([
            'ok' => true,
            'teams' => $teams->forMember($uid),
            'incomingInvites' => $teams->incomingInvites($uid),
        ]);
    }

    public function store(Request $request, TeamService $teams): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'inviteCode' => ['nullable', 'string', 'max:160'],
            'teammateIds' => ['nullable', 'array'],
            'teammateIds.*' => ['string', 'max:120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'team' => $teams->create($data, $this->uid($request)),
            ], 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function show(Request $request, string $teamId, TeamService $teams): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'team' => $teams->detail($teamId, $this->uid($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function invite(Request $request, string $teamId, TeamService $teams): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'string', 'max:120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'invite' => $teams->invite($teamId, $this->uid($request), $data['userId']),
            ], 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function accept(Request $request, string $inviteId, TeamService $teams): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'teamId' => $teams->acceptInvite($inviteId, $this->uid($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function decline(Request $request, string $inviteId, TeamService $teams): JsonResponse
    {
        try {
            $teams->declineInvite($inviteId, $this->uid($request));

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function removeMember(Request $request, string $teamId, string $userId, TeamService $teams): JsonResponse
    {
        try {
            $teams->removeMember($teamId, $this->uid($request), $userId);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function leave(Request $request, string $teamId, TeamService $teams): JsonResponse
    {
        try {
            $teams->leave($teamId, $this->uid($request));

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function destroy(Request $request, string $teamId, TeamService $teams): JsonResponse
    {
        try {
            $teams->delete($teamId, $this->uid($request));

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function uid(Request $request): string
    {
        $uid = $request->attributes->get('firebase.uid');

        return is_string($uid) ? $uid : '';
    }

    private function error(Throwable $exception): JsonResponse
    {
        $status = match (true) {
            $exception instanceof RuntimeException => 404,
            $exception instanceof DomainException => 409,
            default => 422,
        };

        return response()->json([
            'ok' => false,
            'message' => $exception->getMessage() ?: 'Team request failed.',
        ], $status);
    }
}
