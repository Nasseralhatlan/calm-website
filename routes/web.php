<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AttributeGroupsController;
use App\Http\Controllers\Admin\AttributesController;
use App\Http\Controllers\Admin\BookingsController;
use App\Http\Controllers\Admin\CitiesController;
use App\Http\Controllers\Admin\CityAreasController;
use App\Http\Controllers\Admin\CountriesController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqsController;
// use App\Http\Controllers\Admin\NotificationsController; // notifications temporarily disabled
use App\Http\Controllers\Admin\FinanceDocumentPdfController;
use App\Http\Controllers\Admin\PlaceListsController;
use App\Http\Controllers\Admin\PlaceReviewController;
use App\Http\Controllers\Admin\PlacesController;
use App\Http\Controllers\Admin\PlaceTypesController;
use App\Http\Controllers\Admin\ReviewsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\Host\CalendarSyncController as HostCalendarSyncController;
use App\Http\Controllers\Host\PlaceAvailabilityController as HostAvailabilityController;
use App\Http\Controllers\Host\PlacesController as HostPlacesController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Support\Facades\Route;

// ─── Public ──────────────────────────────────────────────────────────────────
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::post('/locale/{locale}', [LandingController::class, 'switchLocale'])->name('locale.switch');
Route::get('/places/{place}', [PlaceController::class, 'show'])->name('places.show');

// Moyasar redirects the guest's payment WebView here. The mobile app matches
// these URLs to know payment finished (after) or was abandoned (back), then
// drives confirmation/cancellation via the booking APIs.
Route::get('/calm-after-payment', [PaymentReturnController::class, 'afterPayment'])->name('payment.after');
Route::get('/calm-back-payment', [PaymentReturnController::class, 'back'])->name('payment.back');

// Static content pages (legal + about), Arabic-only. Public — also opened in
// the mobile app's WebView.
Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/terms', [PageController::class, 'terms'])->name('pages.terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('pages.privacy');
Route::get('/cancellation-policy', [PageController::class, 'cancellation'])->name('pages.cancellation');
Route::get('/community-standards', [PageController::class, 'community'])->name('pages.community');
// Public support page — contact details rendered from admin settings.
Route::get('/support', [PageController::class, 'support'])->name('pages.support');
// Public FAQ page — admin-curated Q&A, guests + hosts as two sections on one page.
Route::get('/faq', [PageController::class, 'faq'])->name('pages.faq');

// Per-place iCal export — polled anonymously by Airbnb/Gathern/Google after
// the host pastes the URL there. The secret {token} is the whole credential;
// the controller hash_equals-checks it and 404s on mismatch.
Route::get('/ical/places/{place}/{token}.ics', CalendarFeedController::class)
    ->name('calendar.export');

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

    // Host self-service detail edit — resubmits the listing for review on save.
    Route::get('/my-places/{place}/edit', [HostPlacesController::class, 'edit'])->name('host.places.edit');
    Route::put('/my-places/{place}', [HostPlacesController::class, 'update'])->name('host.places.update');
    Route::delete('/my-places/{place}', [HostPlacesController::class, 'destroy'])->name('host.places.destroy');

    // Availability manager — host blocks/unblocks dates for their own place.
    Route::get('/my-places/{place}/availability', [HostAvailabilityController::class, 'show'])
        ->name('host.places.availability');
    Route::post('/my-places/{place}/blockings', [HostAvailabilityController::class, 'store'])
        ->name('host.places.blockings.store');
    Route::delete('/my-places/{place}/blockings/{blocking}', [HostAvailabilityController::class, 'destroy'])
        ->scopeBindings()
        ->name('host.places.blockings.destroy');

    // Calendar sync (Airbnb/Gathern-style iCal): imported feeds + export link,
    // managed from the "Calendar sync" card on the Availability page.
    Route::post('/my-places/{place}/calendar-feeds', [HostCalendarSyncController::class, 'store'])
        ->name('host.places.calendar-feeds.store');
    // {calendarFeed} (not {feed}) so the scoped binding resolves through the
    // Place::calendarFeeds() relation.
    Route::delete('/my-places/{place}/calendar-feeds/{calendarFeed}', [HostCalendarSyncController::class, 'destroy'])
        ->scopeBindings()
        ->name('host.places.calendar-feeds.destroy');
    Route::post('/my-places/{place}/calendar-feeds/sync', [HostCalendarSyncController::class, 'syncNow'])
        ->name('host.places.calendar-feeds.sync');
    Route::post('/my-places/{place}/calendar-token/rotate', [HostCalendarSyncController::class, 'rotateToken'])
        ->name('host.places.calendar-token.rotate');

    // Regular-user dashboard tabs (placeholders until each feature ships)
    Route::get('/bookings', [UserDashboardController::class, 'bookings'])->name('user.bookings');
    Route::get('/my-bookings', [UserDashboardController::class, 'myBookings'])->name('user.my-bookings');
    // Booking detail — guest or host of that booking only.
    Route::get('/bookings/{booking}', [UserDashboardController::class, 'showBooking'])->name('user.bookings.show');
    Route::get('/financials', [UserDashboardController::class, 'financials'])->name('user.financials');
    Route::get('/favorites', [UserDashboardController::class, 'favorites'])->name('user.favorites');
    // Support inside the dashboard chrome (sidebar). Public /support redirects here when logged in.
    Route::get('/account/support', [PageController::class, 'userSupport'])->name('user.support');
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
        // Attribute groups: store/update/destroy only — driven inline (JSON)
        // from the merged attributes page (no standalone group screen).
        // The literal `standalone` toggle registers before the resource so it
        // isn't captured as an {attributeGroup} route parameter.
        Route::post('attribute-groups/{attributeGroup}/standalone', [AttributeGroupsController::class, 'toggleStandalone'])->name('attribute-groups.standalone');
        Route::resource('attribute-groups', AttributeGroupsController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['attribute-groups' => 'attributeGroup']);
        // Inline actions for the merged page — drag-sort save + the star toggle.
        // Registered before the resource so the literal paths aren't captured as
        // an {attribute} route parameter.
        Route::post('attributes/reorder', [AttributesController::class, 'reorder'])->name('attributes.reorder');
        Route::post('attributes/{attribute}/highlight', [AttributesController::class, 'toggleHighlight'])->name('attributes.highlight');
        // The merged page replaces the old index + create/edit form pages.
        Route::resource('attributes', AttributesController::class)->except(['show', 'create', 'edit']);

        // Place review workflow — three actions per place + skip.
        Route::get('/places/{place}/review', [PlaceReviewController::class, 'show'])->name('places.review');
        Route::post('/places/{place}/review/approve', [PlaceReviewController::class, 'approve'])->name('places.review.approve');
        Route::post('/places/{place}/review/reject', [PlaceReviewController::class, 'reject'])->name('places.review.reject');
        Route::post('/places/{place}/review/skip', [PlaceReviewController::class, 'skip'])->name('places.review.skip');

        // Guest review moderation (under_review / published / blocked).
        Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/status', [ReviewsController::class, 'updateStatus'])->name('reviews.status');

        // All bookings — search (guest/host phone, place/booking id), view, cancel.
        Route::get('/bookings', [BookingsController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [BookingsController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [BookingsController::class, 'cancel'])->name('bookings.cancel');

        // Payouts are automatic (Moyasar). Human actions, from the booking's
        // page: retry a bank-rejected transfer, or record a hand-made bank
        // transfer while automatic payouts aren't available.
        Route::post('/bookings/{booking}/payout/retry', [BookingsController::class, 'retryPayout'])->name('bookings.payout.retry');
        Route::post('/bookings/{booking}/payout/mark-paid', [BookingsController::class, 'markPayoutPaid'])->name('bookings.payout.mark-paid');
        // Fresh expiring Qoyod PDF for a tax document (admin support view).
        Route::get('/finance-documents/{document}/pdf', FinanceDocumentPdfController::class)->name('finance-documents.pdf');

        // Curated landing-page lists ("Featured chalets", "Editor's picks", etc.)
        // Adding places to a list happens from the place's edit page; this
        // surface only manages list metadata + the remove-from-list button.
        Route::resource('place-lists', PlaceListsController::class)
            ->parameters(['place-lists' => 'placeList'])
            ->except(['show']);
        Route::delete('/place-lists/{placeList}/places/{place}', [PlaceListsController::class, 'detach'])
            ->name('place-lists.detach');

        // Places + settings
        Route::resource('places', PlacesController::class)->except(['create', 'store']);
        Route::resource('settings', SettingsController::class)->except(['show', 'create']);

        // FAQs — admin-curated Q&A shown on the public /faq page.
        Route::resource('faqs', FaqsController::class)->except(['show']);

        // Users — list + edit. Self-registration handles creation; deletion is
        // intentionally not exposed here.
        Route::resource('users', UsersController::class)->only(['index', 'edit', 'update']);

        // Notifications — TEMPORARILY DISABLED (code kept; uncomment to re-enable).
        // Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        // Route::post('/notifications', [NotificationsController::class, 'store'])->name('notifications.store');
    });
