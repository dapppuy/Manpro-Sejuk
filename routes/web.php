<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('cari', 'pages::search')->name('search');
Route::livewire('teknisi/{technician}', 'pages::technicians.show')->name('technicians.show');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn (Request $request): RedirectResponse => redirect($request->user()->homeRoute()))->name('dashboard');
});

Route::prefix('mitra')->name('mitra.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('masuk', fn () => view('pages::auth.login', ['mitra' => true]))->name('login');
    });

    Route::middleware(['auth', 'role:technician'])->group(function () {
        Route::livewire('/', 'pages::mitra.dashboard')->name('dashboard');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
