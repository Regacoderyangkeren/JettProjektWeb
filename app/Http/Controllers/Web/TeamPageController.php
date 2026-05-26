<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ReadsFirebaseData;
use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TeamPageController extends Controller
{
    use ReadsFirebaseData;

    public function index(Request $request, TeamService $teams): View
    {
        $uid = $this->uid($request);

        return view('teams.index', [
            'teams' => $this->attempt(fn () => $teams->forMember($uid), []),
            'incomingInvites' => $this->attempt(fn () => $teams->incomingInvites($uid), []),
            'connectedUsers' => $this->attempt(fn () => $teams->connectedUsers($uid), []),
        ]);
    }

    public function store(Request $request, TeamService $teams): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'teammateIds' => ['nullable', 'array'],
            'teammateIds.*' => ['string', 'max:120'],
        ]);

        try {
            $team = $teams->create($data, $this->uid($request));

            return redirect()->route('teams.show', $team['id'])->with('status', 'Team created.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function show(Request $request, string $teamId, TeamService $teams, ProjectService $projects): View|RedirectResponse
    {
        try {
            $uid = $this->uid($request);
            $detail = $teams->detail($teamId, $uid);
            $linkedProjects = array_values(array_filter(
                $projects->forMember($uid),
                fn (array $project): bool => ($project['teamId'] ?? '') === $teamId
            ));

            return view('teams.show', array_merge($detail, [
                'linkedProjects' => $linkedProjects,
                'currentUserId' => $uid,
            ]));
        } catch (Throwable $exception) {
            return redirect()->route('teams.index')->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function invite(Request $request, string $teamId, TeamService $teams): RedirectResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'string', 'max:120'],
        ]);

        try {
            $teams->invite($teamId, $this->uid($request), $data['userId']);

            return back()->with('status', 'Team invite sent.');
        } catch (Throwable $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function storeTag(Request $request, string $teamId, TeamService $teams): RedirectResponse
    {
        $data = $this->validateTag($request);

        try {
            $teams->createTag($teamId, $this->uid($request), $data);

            return back()->with('status', 'Tag created.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function updateTag(Request $request, string $teamId, string $tagId, TeamService $teams): RedirectResponse
    {
        $data = $this->validateTag($request);

        try {
            $teams->updateTag($teamId, $this->uid($request), $tagId, $data);

            return back()->with('status', 'Tag updated.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function accept(Request $request, string $inviteId, TeamService $teams): RedirectResponse
    {
        try {
            $teamId = $teams->acceptInvite($inviteId, $this->uid($request));

            return redirect()->route('teams.show', $teamId)->with('status', 'Team invite accepted.');
        } catch (Throwable $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function decline(Request $request, string $inviteId, TeamService $teams): RedirectResponse
    {
        try {
            $teams->declineInvite($inviteId, $this->uid($request));

            return back()->with('status', 'Team invite declined.');
        } catch (Throwable $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function removeMember(Request $request, string $teamId, string $userId, TeamService $teams): RedirectResponse
    {
        try {
            $teams->removeMember($teamId, $this->uid($request), $userId);

            return back()->with('status', 'Member removed.');
        } catch (Throwable $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function leave(Request $request, string $teamId, TeamService $teams): RedirectResponse
    {
        try {
            $teams->leave($teamId, $this->uid($request));

            return redirect()->route('teams.index')->with('status', 'You left the team.');
        } catch (Throwable $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, string $teamId, TeamService $teams): RedirectResponse
    {
        try {
            $teams->delete($teamId, $this->uid($request));

            return redirect()->route('teams.index')->with('status', 'Team deleted.');
        } catch (Throwable $exception) {
            return back()->withErrors(['team' => $exception->getMessage()]);
        }
    }

    private function uid(Request $request): string
    {
        return (string) $request->session()->get('firebase.uid', '');
    }

    private function validateTag(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:48'],
            'colorHex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'assignedMemberIds' => ['nullable', 'array'],
            'assignedMemberIds.*' => ['string', 'max:120'],
        ]);
    }
}
