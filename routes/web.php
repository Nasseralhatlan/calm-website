<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AttributeGroupsController;
use App\Http\Controllers\Admin\AttributesController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\CityAreasController;
use App\Http\Controllers\Admin\CountriesController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlaceReviewController;
use App\Http\Controllers\Admin\PlaceTypesController;
use App\Http\Controllers\Admin\PlacesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Host\PlacesController as HostPlacesController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Support\Facades\Route;

// ─── Public ──────────────────────────────────────────────────────────────────
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::post('/locale/{locale}', [LandingController::class, 'switchLocale'])->name('locale.switch');
Route::get('/places/{place}', [PlaceController::class, 'show'])->name('places.show');

// ─── Auth (web, OTP → JWT cookie) ────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'requestOtp'])->name('login.request');
    Route::get('/login/verify', [LoginController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/verify', [LoginController::class, 'verifyOtp'])->name('login.verify.submit');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth:api')
    ->name('logout');

// ─── Authenticated user surface (any role) ───────────────────────────────────
Route::middleware('auth:api')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Host registration + the host's own places list
    Route::get('/host-register', [HostPlacesController::class, 'create'])->name('host.places.create');
    Route::post('/host-register', [HostPlacesController::class, 'store'])->name('host.places.store');
    Route::post('/host-register/draft', [HostPlacesController::class, 'saveDraft'])->name('host.places.draft');
    Route::post('/host-register/presign', [HostPlacesController::class, 'presignUpload'])->name('host.places.presign');
    Route::get('/my-places', [HostPlacesController::class, 'index'])->name('user.places');

    // Regular-user dashboard tabs (placeholders until each feature ships)
    Route::get('/bookings',     [UserDashboardController::class, 'bookings'])    ->name('user.bookings');
    Route::get('/my-bookings',  [UserDashboardController::class, 'myBookings'])  ->name('user.my-bookings');
    Route::get('/financials',   [UserDashboardController::class, 'financials'])  ->name('user.financials');
    Route::get('/favorites',    [UserDashboardController::class, 'favorites'])   ->name('user.favorites');
    Route::get('/support',      [UserDashboardController::class, 'support'])     ->name('user.support');
});

// ─── Admin (auth + role) ─────────────────────────────────────────────────────
Route::middleware(['auth:api', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Geography
        Route::resource('countries', CountriesController::class)->except(['show']);
        Route::resource('cities', CitiesController::class)->except(['show']);
        Route::resource('city-areas', CityAreasController::class)
            ->except(['show'])
            ->parameters(['city-areas' => 'cityArea']);

        // Places taxonomy
        Route::resource('place-types', PlaceTypesController::class)
            ->except(['show'])
            ->parameters(['place-types' => 'placeType']);
        Route::resource('attribute-groups', AttributeGroupsController::class)
            ->except(['show'])
            ->parameters(['attribute-groups' => 'attributeGroup']);
        Route::resource('attributes', AttributesController::class)->except(['show']);

        // Place review workflow — three actions per place + skip.
        Route::get('/places/{place}/review', [PlaceReviewController::class, 'show'])->name('places.review');
        Route::post('/places/{place}/review/approve', [PlaceReviewController::class, 'approve'])->name('places.review.approve');
        Route::post('/places/{place}/review/reject', [PlaceReviewController::class, 'reject'])->name('places.review.reject');
        Route::post('/places/{place}/review/skip', [PlaceReviewController::class, 'skip'])->name('places.review.skip');

        // Places + settings
        Route::resource('places', PlacesController::class)->except(['create', 'store']);
        Route::resource('settings', SettingsController::class)->except(['show', 'create']);

        // Users — list + edit. Self-registration handles creation; deletion is
        // intentionally not exposed here.
        Route::resource('users', UsersController::class)->only(['index', 'edit', 'update']);
    });
