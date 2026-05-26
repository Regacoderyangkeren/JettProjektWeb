<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsFirebaseData;
use App\Services\InboxService;
use App\Services\NoteService;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ReadsFirebaseData;

    public function __invoke(
        Request $request,
        ProjectService $projects,
        TaskService $tasks,
        InboxService $inbox,
        NoteService $notes,
    ): View {
        $uid = (string) $request->session()->get('firebase.uid', '');
        $projectItems = $this->attempt(fn () => $projects->forMember($uid), []);
        $taskItems = $this->attempt(fn () => $tasks->workload($uid), []);
        $inboxItems = $this->attempt(fn () => $inbox->list($uid, 'inbox', 6), []);
        $noteItems = $this->attempt(fn () => $notes->forUser($uid), []);

        return view('dashboard', [
            'profile' => $request->session()->get('firebase.profile', []),
            'projects' => $projectItems,
            'tasks' => $taskItems,
            'inboxItems' => $inboxItems,
            'notes' => $noteItems,
        ]);
    }
}
