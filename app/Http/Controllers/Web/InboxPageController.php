<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\InboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class InboxPageController extends Controller
{
    public function index(Request $request, InboxService $inbox): View
    {
        $uid = (string) $request->session()->get('firebase.uid', '');

        return view('inbox.index', [
            'items' => $this->attempt(fn () => $inbox->list($uid, 'inbox', 80), []),
            'connectionItems' => $this->attempt(fn () => $inbox->list($uid, 'connectionInbox', 40), []),
        ]);
    }

    public function read(Request $request, string $itemId, InboxService $inbox): RedirectResponse
    {
        $box = (string) $request->input('box', 'inbox');

        try {
            $inbox->markRead((string) $request->session()->get('firebase.uid', ''), $itemId, $box);

            return back()->with('status', 'Inbox item marked read.');
        } catch (Throwable $exception) {
            return back()->withErrors(['inbox' => $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, string $itemId, InboxService $inbox): RedirectResponse
    {
        $box = (string) $request->input('box', 'inbox');

        try {
            $inbox->delete((string) $request->session()->get('firebase.uid', ''), $itemId, $box);

            return back()->with('status', 'Inbox item removed.');
        } catch (Throwable $exception) {
            return back()->withErrors(['inbox' => $exception->getMessage()]);
        }
    }

    private function attempt(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
