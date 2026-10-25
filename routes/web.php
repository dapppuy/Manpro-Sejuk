<?php

use App\Http\Controllers\Admin\TechnicianDocumentController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderMediaController;
use App\Http\Controllers\WorkProofPhotoController;
use App\Http\Middleware\EnsureTechnicianIsApproved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('cari', 'pages::search')->name('search');
Route::livewire('teknisi/{technician}', 'pages::technicians.show')->name('technicians.show');

Route::post('webhooks/midtrans', MidtransWebhookController::class)->name('webhooks.midtrans');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', fn (Request $request): RedirectResponse => redirect($request->user()->homeRoute()))->name('dashboard');
    Route::get('pesanan/media/{media}', OrderMediaController::class)->name('orders.media');
    Route::get('pesanan/bukti/{proof}/{side}', WorkProofPhotoController::class)->name('orders.proofs.photo');
});

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::livewire('pesan/{technician}', 'pages::requests.create')->name('requests.create');
    Route::livewire('pesanan', 'pages::orders.index')->name('orders.index');
    Route::livewire('pesanan/{order}', 'pages::orders.show')->name('orders.show');
    Route::livewire('pesanan/{order}/ulasan', 'pages::reviews.create')->name('reviews.create');
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
            Route::livewire('pesanan', 'pages::mitra.orders.index')->name('orders.index');
            Route::livewire('pesanan/{order}', 'pages::mitra.orders.show')->name('orders.show');
            Route::livewire('pesanan/{order}/estimasi', 'pages::mitra.orders.estimate')->name('orders.estimate');
            Route::livewire('pesanan/{order}/biaya-final', 'pages::mitra.orders.final-cost')->name('orders.final-cost');
            Route::livewire('pesanan/{order}/bukti', 'pages::mitra.orders.proof')->name('orders.proof');
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
