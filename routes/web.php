<?php

use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Web\InboxPageController;
use App\Http\Controllers\Web\NotePageController;
use App\Http\Controllers\Web\ProjectPageController;
use App\Http\Controllers\Web\TaskPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return session()->has('firebase.uid')
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/login', [FirebaseAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [FirebaseAuthController::class, 'login'])->name('login.store');
Route::get('/register', [FirebaseAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [FirebaseAuthController::class, 'register'])->name('register.store');
Route::post('/logout', [FirebaseAuthController::class, 'logout'])->name('logout');

Route::middleware('firebase.session')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/projects', [ProjectPageController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectPageController::class, 'store'])->name('projects.store');
    Route::get('/projects/{projectId}', [ProjectPageController::class, 'show'])->name('projects.show');
    Route::patch('/projects/{projectId}', [ProjectPageController::class, 'update'])->name('projects.update');
    Route::post('/projects/{projectId}/complete', [ProjectPageController::class, 'complete'])->name('projects.complete');
    Route::post('/projects/{projectId}/archive', [ProjectPageController::class, 'archive'])->name('projects.archive');
    Route::delete('/projects/{projectId}', [ProjectPageController::class, 'destroy'])->name('projects.destroy');

    Route::post('/projects/{projectId}/tasks', [TaskPageController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{taskId}/status', [TaskPageController::class, 'status'])->name('tasks.status');
    Route::post('/tasks/{taskId}/review', [TaskPageController::class, 'review'])->name('tasks.review');
    Route::delete('/tasks/{taskId}', [TaskPageController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/notes', [NotePageController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NotePageController::class, 'store'])->name('notes.store');
    Route::delete('/notes/{noteId}', [NotePageController::class, 'destroy'])->name('notes.destroy');

    Route::get('/inbox', [InboxPageController::class, 'index'])->name('inbox.index');
    Route::patch('/inbox/{itemId}/read', [InboxPageController::class, 'read'])->name('inbox.read');
    Route::delete('/inbox/{itemId}', [InboxPageController::class, 'destroy'])->name('inbox.destroy');
});
