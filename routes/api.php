<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
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

    Route::get('/teams', [TeamController::class, 'index'])->name('api.teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('api.teams.store');
    Route::get('/teams/{teamId}', [TeamController::class, 'show'])->name('api.teams.show');
    Route::post('/teams/{teamId}/invites', [TeamController::class, 'invite'])->name('api.teams.invites.store');
    Route::post('/team-invites/{inviteId}/accept', [TeamController::class, 'accept'])->name('api.teams.invites.accept');
    Route::post('/team-invites/{inviteId}/decline', [TeamController::class, 'decline'])->name('api.teams.invites.decline');
    Route::delete('/teams/{teamId}/members/{userId}', [TeamController::class, 'removeMember'])->name('api.teams.members.destroy');
    Route::post('/teams/{teamId}/leave', [TeamController::class, 'leave'])->name('api.teams.leave');
    Route::delete('/teams/{teamId}', [TeamController::class, 'destroy'])->name('api.teams.destroy');

    Route::get('/notes', [NoteController::class, 'index'])->name('api.notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('api.notes.store');
    Route::get('/notes/{noteId}', [NoteController::class, 'show'])->name('api.notes.show');
    Route::put('/notes/{noteId}', [NoteController::class, 'update'])->name('api.notes.update');
    Route::delete('/notes/{noteId}', [NoteController::class, 'destroy'])->name('api.notes.destroy');

    Route::get('/inbox', [InboxController::class, 'index'])->name('api.inbox.index');
    Route::post('/inbox', [InboxController::class, 'store'])->name('api.inbox.store');
    Route::patch('/inbox/{itemId}/read', [InboxController::class, 'markRead'])->name('api.inbox.read');
    Route::delete('/inbox/{itemId}', [InboxController::class, 'destroy'])->name('api.inbox.destroy');

    Route::get('/connections', [ConnectionController::class, 'index'])->name('api.connections.index');
    Route::post('/connections/{userId}/request', [ConnectionController::class, 'request'])->name('api.connections.request');
    Route::post('/connections/{userId}/accept', [ConnectionController::class, 'accept'])->name('api.connections.accept');
    Route::post('/connections/{userId}/decline', [ConnectionController::class, 'decline'])->name('api.connections.decline');
    Route::delete('/connections/{userId}', [ConnectionController::class, 'destroy'])->name('api.connections.destroy');
});
