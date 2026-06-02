<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::post('/locale/{locale}', [LandingController::class, 'switchLocale'])->name('locale.switch');

// hidden host registration (no auth, no link from anywhere)
Route::get('/host-register', [HostController::class, 'create'])->name('hosts.create');
Route::post('/host-register', [HostController::class, 'store'])->name('hosts.store');
Route::post('/host-register/upload-image', [HostController::class, 'uploadImage'])->name('hosts.upload-image');
Route::post('/host-register/presign-upload', [HostController::class, 'presignUpload'])->name('hosts.presign-upload');

// public, shareable property page
Route::get('/p/{slug}', [HostController::class, 'show'])->name('property.show');

// hidden admin dashboard, password in URL
Route::get('/admin/{password}', [AdminController::class, 'index'])->name('admin.index');
