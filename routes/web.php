<?php

use App\Http\Controllers\Auth\FirebaseAuthController;
use App\Http\Controllers\DashboardController;
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
});
