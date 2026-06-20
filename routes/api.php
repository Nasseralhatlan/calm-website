<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\CitiesController;
use App\Http\Controllers\Api\CountriesController;
// use App\Http\Controllers\Api\DeviceTokenController; // notifications temporarily disabled
use App\Http\Controllers\Api\HostController;
use App\Http\Controllers\Api\MoyasarWebhookController;
// use App\Http\Controllers\Api\NotificationController; // notifications temporarily disabled
use App\Http\Controllers\Api\PlaceAvailabilityController;
use App\Http\Controllers\Api\PlaceLikesController;
use App\Http\Controllers\Api\PlaceListsController;
use App\Http\Controllers\Api\PlaceQuoteController;
use App\Http\Controllers\Api\PlacesController;
use App\Http\Controllers\Api\PlaceTypesController;
use App\Http\Controllers\Api\ReviewsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Public / unauthenticated ────────────────────────────────────────────────
Route::middleware('throttle:public')->group(function (): void {
    // Service-layer enforces per-identifier cooldown + per-OTP attempt cap,
    // so only the surrounding `throttle:public` (30/min per IP) applies here.
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);

    // Home-screen feed — all read-only, no auth required. Likes-aware fields
    // (is_liked) stay false for anonymous viewers; they'll flip once the
    // client retries the same endpoint with a Bearer token after login.
    Route::get('/countries', [CountriesController::class, 'index']);
    Route::get('/cities', [CitiesController::class, 'index']);
    Route::get('/place-types', [PlaceTypesController::class, 'index']);
    Route::get('/place-lists', [PlaceListsController::class, 'index']);
    // Public app settings — a hardcoded whitelist (currently support phone +
    // email). Clients can't request arbitrary settings.
    Route::get('/settings', SettingsController::class);
    Route::get('/places/most-liked', [PlacesController::class, 'mostLiked']);
    // Core search. Required ?city_id=, plus optional filters (type, price, guests,
    // amenities, dates, sort). Registered before /places/{place} so "search"
    // isn't swallowed by the {place} catch.
    Route::get('/places/search', [PlacesController::class, 'search']);
    // Available filter options for a city's filters page (?city_id=). Before
    // /places/{place} so "filters" isn't swallowed by the {place} catch.
    Route::get('/places/filters', [PlacesController::class, 'filters']);
    // Single-place detail for the mobile detail screen. Auth-aware: a Bearer
    // token flips `is_liked` correctly; anonymous viewers see it as false.
    Route::get('/places/{place}', [PlacesController::class, 'show']);
    // Blocked dates for the place's booking calendar. Optional ?from=&to=
    // (Y-m-d) window the result; defaults to [today, +12 months].
    Route::get('/places/{place}/unavailable-dates', PlaceAvailabilityController::class);
    // Availability + pricing quote for the checkout page. Required ?check_in=
    // &check_out= (Y-m-d, inclusive), optional ?guests=. Source of truth for
    // price + bookability before the guest commits.
    Route::get('/places/{place}/quote', PlaceQuoteController::class);

    // Moyasar server-to-server payment notification. No auth (Moyasar calls it);
    // the handler verifies the shared secret and re-checks the invoice via the
    // API before settling the booking.
    Route::post('/payments/moyasar/webhook', MoyasarWebhookController::class)
        ->name('payments.moyasar.webhook');
});

// ─── Authenticated (any role) ────────────────────────────────────────────────
Route::middleware(['auth:api', 'throttle:authenticated'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::get('/user', [UserController::class, 'me']);
    // JSON field updates (name, gender, …). For a profile-picture upload use
    // the POST alias below — PHP only parses multipart bodies on POST.
    Route::patch('/user', [UserController::class, 'update']);
    Route::post('/user', [UserController::class, 'update']);

    // ── Notifications API — TEMPORARILY DISABLED (code kept; uncomment to re-enable). ──
    // // Expo push-token registration (call on login + token refresh; remove on logout).
    // Route::post('/devices', [DeviceTokenController::class, 'register']);
    // Route::delete('/devices', [DeviceTokenController::class, 'unregister']);
    //
    // // In-app notification feed.
    // Route::get('/notifications', [NotificationController::class, 'index']);
    // Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    // Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    // Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);

    // Heart-icon toggles. POST = like, DELETE = unlike; both idempotent.
    Route::post('/places/{place}/like', [PlaceLikesController::class, 'store']);
    Route::delete('/places/{place}/like', [PlaceLikesController::class, 'destroy']);
    // The viewer's own liked places ("My favorites"), paginated. Top-level path
    // so it never collides with the public /places/{place} catch.
    Route::get('/favorites', [PlacesController::class, 'favorites']);

    // Host app: bookings on the host's places, their own listings, earnings, reviews.
    Route::get('/host/bookings', [HostController::class, 'bookings']);
    Route::get('/host/listings', [HostController::class, 'listings']);
    Route::get('/host/earnings', [HostController::class, 'earnings']);
    Route::get('/host/reviews', [HostController::class, 'reviews']);

    // Bookings. POST holds the dates + opens a Moyasar invoice; the status
    // endpoint re-verifies and confirms once paid.
    Route::post('/places/{place}/bookings', [BookingsController::class, 'store']);
    // The guest's own bookings list ("My bookings"), paginated, newest first.
    Route::get('/bookings', [BookingsController::class, 'index']);
    // Still-payable holds for a "finish your payment" card on the home screen.
    // Registered before /bookings/{booking}/* so "pending" isn't a {booking}.
    Route::get('/bookings/pending', [BookingsController::class, 'pending']);
    Route::get('/bookings/{booking}/payment-status', [BookingsController::class, 'paymentStatus']);
    // Called by the app when the guest backs out of the hosted payment page.
    Route::post('/bookings/{booking}/cancel', [BookingsController::class, 'cancel']);

    // Guest reviews: post one for a completed booking's place; delete own.
    Route::post('/bookings/{booking}/reviews', [ReviewsController::class, 'store']);
    Route::delete('/reviews/{review}', [ReviewsController::class, 'destroy']);
});

// ─── Admin-only ──────────────────────────────────────────────────────────────
Route::middleware(['auth:api', 'admin', 'throttle:authenticated'])
    ->prefix('admin')
    ->group(function (): void {
        // future admin endpoints land here
    });
