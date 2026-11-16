<?php

use App\Http\Controllers\Admin\TechnicianDocumentController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderMediaController;
use App\Http\Controllers\RunScheduleController;
use App\Http\Controllers\UnitPhotoController;
use App\Http\Controllers\WorkProofPhotoController;
use App\Http\Middleware\EnsureTechnicianIsApproved;
use App\Http\Middleware\RedirectNonCustomers;
use App\Livewire\Customer\SmartAcCheck;
use Illuminate\Support\Facades\Route;

Route::middleware([RedirectNonCustomers::class])->group(function () {
    Route::livewire('/', 'pages::home')->name('home');
    Route::livewire('cari', 'pages::search')->name('search');
    Route::livewire('teknisi/{technician}', 'pages::technicians.show')->name('technicians.show');
});

Route::post('webhooks/midtrans', MidtransWebhookController::class)->name('webhooks.midtrans');

Route::livewire('u/{qrToken}', 'pages::units.qr')->name('unit.qr.show');
Route::get('cron/{token}', RunScheduleController::class)->middleware('throttle:10,1')->name('cron.run');

Route::livewire('syarat-ketentuan', 'pages::content-page')->defaults('slug', 'syarat-ketentuan')->name('pages.terms');
Route::livewire('kebijakan-privasi', 'pages::content-page')->defaults('slug', 'kebijakan-privasi')->name('pages.privacy');
Route::livewire('perjanjian-kemitraan', 'pages::content-page')->defaults('slug', 'perjanjian-kemitraan')->name('pages.partnership');
Route::livewire('bantuan', 'pages::content-page')->defaults('slug', 'bantuan')->name('pages.help');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
    Route::get('pesanan/media/{media}', OrderMediaController::class)->name('orders.media');
    Route::get('pesanan/bukti/{proof}/{side}', WorkProofPhotoController::class)->name('orders.proofs.photo');
    Route::get('unit-ac/{unit}/foto/{photo}', UnitPhotoController::class)->name('units.photo');
});

Route::middleware(['auth', RedirectNonCustomers::class, 'verified', 'role:customer'])->group(function () {
    Route::livewire('pesan/{technician}', 'pages::requests.create')->name('requests.create');
    Route::get('cek-ac-pintar', SmartAcCheck::class)->name('customer.smart-check');
    Route::livewire('akun', 'pages::account')->name('account');
    Route::livewire('unit-ac', 'pages::units.index')->name('units.index');
    Route::livewire('unit-ac/{unit}', 'pages::units.show')->name('units.show');
    Route::livewire('pesanan', 'pages::orders.index')->name('orders.index');
    Route::livewire('pesanan/{order}', 'pages::orders.show')->name('orders.show');
    Route::livewire('pesanan/{order}/ulasan', 'pages::reviews.create')->name('reviews.create');
});

Route::prefix('mitra')->name('mitra.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::view('masuk', 'pages::auth.mitra-login')->name('login');
        Route::livewire('daftar', 'pages::mitra.register')->name('register');
    });

    Route::middleware(['auth', 'verified', 'role:technician'])->group(function () {
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
            Route::livewire('jadwal', 'pages::mitra.schedule')->name('schedule');
            Route::livewire('galeri', 'pages::mitra.gallery')->name('gallery');
            Route::livewire('pendapatan', 'pages::mitra.earnings')->name('earnings');
        });
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
    Route::livewire('verifikasi', 'pages::admin.verifications.index')->name('verifications.index');
    Route::livewire('verifikasi/{technician:id}', 'pages::admin.verifications.show')->name('verifications.show');
    Route::livewire('teknisi', 'pages::admin.technicians.index')->name('technicians.index');
    Route::livewire('pesanan', 'pages::admin.orders.index')->name('orders.index');
    Route::livewire('pesanan/{order}', 'pages::admin.orders.show')->name('orders.show');
    Route::livewire('dana', 'pages::admin.funds')->name('funds');
    Route::livewire('ulasan', 'pages::admin.reviews')->name('reviews');
    Route::livewire('akun', 'pages::admin.users.index')->name('users.index');
    Route::livewire('akun/{user}', 'pages::admin.users.show')->name('users.show');
    Route::livewire('data-master', 'pages::admin.master')->name('master');
    Route::livewire('konten', 'pages::admin.content')->name('content');
    Route::livewire('log-audit', 'pages::admin.audit')->name('audit');
    Route::get('dokumen/{document}', TechnicianDocumentController::class)->name('documents.show');
});

require __DIR__.'/settings.php';
