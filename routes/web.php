<?php

use App\Http\Controllers\AuthController;
use App\Livewire\RiskManagerDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.perform');
});

Route::middleware('auth')->group(function () {
    Route::get('/', RiskManagerDashboard::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
