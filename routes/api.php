<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/health/firebase', [HealthController::class, 'firebase'])->name('api.health.firebase');

Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

Route::middleware('firebase.bearer')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('/projects', [ProjectController::class, 'index'])->name('api.projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('api.projects.store');
    Route::get('/projects/{projectId}', [ProjectController::class, 'show'])->name('api.projects.show');
    Route::patch('/projects/{projectId}', [ProjectController::class, 'update'])->name('api.projects.update');
    Route::post('/projects/{projectId}/members', [ProjectController::class, 'addMember'])->name('api.projects.members.store');
    Route::delete('/projects/{projectId}/members/{userId}', [ProjectController::class, 'removeMember'])->name('api.projects.members.destroy');
    Route::post('/projects/{projectId}/complete', [ProjectController::class, 'complete'])->name('api.projects.complete');
    Route::post('/projects/{projectId}/archive', [ProjectController::class, 'archive'])->name('api.projects.archive');
    Route::delete('/projects/{projectId}', [ProjectController::class, 'destroy'])->name('api.projects.destroy');

    Route::get('/tasks', [TaskController::class, 'index'])->name('api.tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::get('/tasks/{taskId}', [TaskController::class, 'show'])->name('api.tasks.show');
    Route::patch('/tasks/{taskId}', [TaskController::class, 'update'])->name('api.tasks.update');
    Route::patch('/tasks/{taskId}/status', [TaskController::class, 'updateStatus'])->name('api.tasks.status');
    Route::post('/tasks/{taskId}/review', [TaskController::class, 'completeReview'])->name('api.tasks.review');
    Route::patch('/tasks/{taskId}/pinned', [TaskController::class, 'setPinned'])->name('api.tasks.pinned');
    Route::patch('/tasks/{taskId}/priority-marked', [TaskController::class, 'setPriorityMarked'])->name('api.tasks.priority_marked');
    Route::post('/tasks/{taskId}/attachments', [TaskController::class, 'addAttachment'])->name('api.tasks.attachments.store');
    Route::delete('/tasks/{taskId}/attachments', [TaskController::class, 'removeAttachment'])->name('api.tasks.attachments.destroy');
    Route::delete('/tasks/{taskId}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');

    Route::get('/notes', [NoteController::class, 'index'])->name('api.notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('api.notes.store');
    Route::get('/notes/{noteId}', [NoteController::class, 'show'])->name('api.notes.show');
    Route::put('/notes/{noteId}', [NoteController::class, 'update'])->name('api.notes.update');
    Route::delete('/notes/{noteId}', [NoteController::class, 'destroy'])->name('api.notes.destroy');

    Route::get('/inbox', [InboxController::class, 'index'])->name('api.inbox.index');
    Route::post('/inbox', [InboxController::class, 'store'])->name('api.inbox.store');
    Route::patch('/inbox/{itemId}/read', [InboxController::class, 'markRead'])->name('api.inbox.read');
    Route::delete('/inbox/{itemId}', [InboxController::class, 'destroy'])->name('api.inbox.destroy');
});
