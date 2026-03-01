<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', function () {
        return 'Welcome to your dashboard, ' . auth()->user()->name . '! <form method="POST" action="' . route('logout') . '">' . csrf_field() . '<button type="submit">Logout</button></form>';
    })->name('dashboard');
});
