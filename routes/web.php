<?php

use App\Http\Controllers\Admin\TechnicianDocumentController;
use App\Http\Middleware\EnsureTechnicianIsApproved;
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
        Route::view('masuk', 'pages::auth.mitra-login')->name('login');
        Route::livewire('daftar', 'pages::mitra.register')->name('register');
    });

    Route::middleware(['auth', 'role:technician'])->group(function () {
        Route::livewire('verifikasi', 'pages::mitra.verification')->name('verification');

        Route::middleware(EnsureTechnicianIsApproved::class)->group(function () {
            Route::livewire('/', 'pages::mitra.dashboard')->name('dashboard');
            Route::livewire('profil', 'pages::mitra.profile')->name('profile');
            Route::livewire('harga', 'pages::mitra.prices')->name('prices');
        });
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('verifikasi', 'pages::admin.verifications.index')->name('verifications.index');
    Route::livewire('verifikasi/{technician:id}', 'pages::admin.verifications.show')->name('verifications.show');
    Route::livewire('teknisi', 'pages::admin.technicians.index')->name('technicians.index');
    Route::get('dokumen/{document}', TechnicianDocumentController::class)->name('documents.show');
});

require __DIR__.'/settings.php';
