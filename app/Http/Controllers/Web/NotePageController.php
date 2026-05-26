<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ReadsFirebaseData;
use App\Http\Controllers\Controller;
use App\Services\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class NotePageController extends Controller
{
    use ReadsFirebaseData;

    public function index(Request $request, NoteService $notes): View
    {
        $uid = (string) $request->session()->get('firebase.uid', '');

        return view('notes.index', [
            'notes' => $this->attempt(fn () => $notes->forUser($uid), []),
        ]);
    }

    public function store(Request $request, NoteService $notes): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'content' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        try {
            $notes->save(array_merge($data, [
                'userId' => (string) $request->session()->get('firebase.uid', ''),
                'date' => now()->format('Y-m-d'),
                'time' => now()->format('H:i'),
            ]), (string) $request->session()->get('firebase.uid', ''));

            return back()->with('status', 'Note saved.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['note' => $exception->getMessage()]);
        }
    }

    public function destroy(string $noteId, NoteService $notes): RedirectResponse
    {
        try {
            $notes->delete($noteId);

            return back()->with('status', 'Note deleted.');
        } catch (Throwable $exception) {
            return back()->withErrors(['note' => $exception->getMessage()]);
        }
    }
}
