<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ReadsFirebaseData;
use App\Http\Controllers\Controller;
use App\Services\ConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ConnectionPageController extends Controller
{
    use ReadsFirebaseData;

    public function index(Request $request, ConnectionService $connections): View
    {
        $overview = $this->attempt(
            fn () => $connections->overview($this->uid($request)),
            ['currentUser' => [], 'users' => []]
        );

        return view('connections.index', [
            'currentUser' => $overview['currentUser'],
            'users' => $overview['users'],
        ]);
    }

    public function request(Request $request, string $userId, ConnectionService $connections): RedirectResponse
    {
        try {
            $connections->request($this->uid($request), $userId);

            return back()->with('status', 'Connection request sent.');
        } catch (Throwable $exception) {
            return back()->withErrors(['connection' => $exception->getMessage()]);
        }
    }

    public function accept(Request $request, string $userId, ConnectionService $connections): RedirectResponse
    {
        try {
            $connections->accept($this->uid($request), $userId);

            return back()->with('status', 'Connection accepted.');
        } catch (Throwable $exception) {
            return back()->withErrors(['connection' => $exception->getMessage()]);
        }
    }

    public function decline(Request $request, string $userId, ConnectionService $connections): RedirectResponse
    {
        try {
            $connections->decline($this->uid($request), $userId);

            return back()->with('status', 'Connection request declined.');
        } catch (Throwable $exception) {
            return back()->withErrors(['connection' => $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, string $userId, ConnectionService $connections): RedirectResponse
    {
        try {
            $connections->remove($this->uid($request), $userId);

            return back()->with('status', 'Connection removed.');
        } catch (Throwable $exception) {
            return back()->withErrors(['connection' => $exception->getMessage()]);
        }
    }

    private function uid(Request $request): string
    {
        return (string) $request->session()->get('firebase.uid', '');
    }
}
