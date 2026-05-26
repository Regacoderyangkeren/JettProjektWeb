<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ChatPageController extends Controller
{
    public function connection(Request $request, string $userId, ChatService $chats): View|RedirectResponse
    {
        try {
            return view('chats.connection', array_merge(
                $chats->connectionThread($this->uid($request), $userId),
                ['currentUserId' => $this->uid($request)]
            ));
        } catch (Throwable $exception) {
            return redirect()->route('connections.index')->withErrors(['chat' => $exception->getMessage()]);
        }
    }

    public function sendConnection(Request $request, string $userId, ChatService $chats): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        try {
            $chats->sendConnectionMessage($this->uid($request), $userId, $data['body']);

            return back()->with('status', 'Message sent.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['chat' => $exception->getMessage()]);
        }
    }

    public function team(Request $request, string $teamId, ChatService $chats): View|RedirectResponse
    {
        try {
            return view('chats.team', $chats->teamThread($teamId, $this->uid($request)));
        } catch (Throwable $exception) {
            return redirect()->route('teams.index')->withErrors(['chat' => $exception->getMessage()]);
        }
    }

    public function sendTeam(Request $request, string $teamId, ChatService $chats): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        try {
            $chats->sendTeamMessage($teamId, $this->uid($request), $data['body']);

            return back()->with('status', 'Message sent.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['chat' => $exception->getMessage()]);
        }
    }

    private function uid(Request $request): string
    {
        return (string) $request->session()->get('firebase.uid', '');
    }
}
