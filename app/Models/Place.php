<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Place extends Model
{
    use HasUuids;
    use SoftDeletes;

    /**
     * Canonical day → column-name map. Useful when iterating per-day prices
     * in views, services, or pricing-calculation logic.
     *
     * @var array<string, string>
     */
    public const PRICE_COLUMNS = [
        'sunday' => 'price_sunday',
        'monday' => 'price_monday',
        'tuesday' => 'price_tuesday',
        'wednesday' => 'price_wednesday',
        'thursday' => 'price_thursday',
        'friday' => 'price_friday',
        'saturday' => 'price_saturday',
    ];

    protected $fillable = [
        'host_user_id',
        'place_type_id',
        'city_area_id',
        'title',
        'title_ar',
        'title_en',
        'description',
        'description_ar',
        'description_en',
        'price',
        'price_sunday',
        'price_monday',
        'price_tuesday',
        'price_wednesday',
        'price_thursday',
        'price_friday',
        'price_saturday',
        'check_in_time',
        'check_out_time',
        'checkout_next_day',
        'max_guests',
        'rules',
        'rules_ar',
        'rules_en',
        'location_url',
        'latitude',
        'longitude',
        'status',
        'review_status',
        'rejection_reason',
        'reviewed_at',
        'last_step',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlaceStatus::class,
            'review_status' => PlaceReviewStatus::class,
            'price' => 'integer',
            'price_sunday' => 'integer',
            'price_monday' => 'integer',
            'price_tuesday' => 'integer',
            'price_wednesday' => 'integer',
            'price_thursday' => 'integer',
            'price_friday' => 'integer',
            'price_saturday' => 'integer',
            'max_guests' => 'integer',
            'checkout_next_day' => 'boolean',
            'reviewed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * The pin coordinates PUBLIC payloads may carry: rounded to 2 decimals
     * (≈ ±1 km) so guests see the area, not the door. The exact pin is
     * host-only until a booking is confirmed — same sensitivity rule as
     * location_url. Null until the host sets a pin.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function approxCoords(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return [
            'latitude' => round((float) $this->latitude, 2),
            'longitude' => round((float) $this->longitude, 2),
        ];
    }

    /**
     * Listing content in the current app locale, falling back to the other
     * language and finally to the canonical column. Used by user-facing web
     * views; the API returns both *_ar/*_en for the client to localize.
     */
    public function getLocalizedTitleAttribute(): ?string
    {
        return $this->pickLocale($this->title_ar, $this->title_en, $this->title);
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return $this->pickLocale($this->description_ar, $this->description_en, $this->description);
    }

    public function getLocalizedRulesAttribute(): ?string
    {
        return $this->pickLocale($this->rules_ar, $this->rules_en, $this->rules);
    }

    private function pickLocale(?string $ar, ?string $en, ?string $canonical): ?string
    {
        return app()->getLocale() === 'ar'
            ? ($ar ?: $en ?: $canonical)
            : ($en ?: $ar ?: $canonical);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PlaceType::class, 'place_type_id');
    }

    public function cityArea(): BelongsTo
    {
        return $this->belongsTo(CityArea::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PlacePhoto::class)->orderBy('sort_order');
    }

    /** The cover = the first photo in the "shown outside" showcase. */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(PlacePhoto::class)
            ->whereNotNull('featured_order')
            ->orderBy('featured_order');
    }

    /** Photos the host chose to show outside (place page), ordered; first = cover. */
    public function featuredPhotos(): HasMany
    {
        return $this->hasMany(PlacePhoto::class)
            ->whereNotNull('featured_order')
            ->orderBy('featured_order');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(PlaceAttribute::class);
    }

    public function blockings(): HasMany
    {
        return $this->hasMany(PlaceBlocking::class);
    }

    /** External iCal feeds (Airbnb/Gathern/…) imported into this place. */
    public function calendarFeeds(): HasMany
    {
        return $this->hasMany(PlaceCalendarFeed::class);
    }

    /**
     * The secret for this place's public iCal export URL. Minted lazily on
     * first use (places that never sync never carry one) and deliberately NOT
     * in $fillable — only this method (and rotation) may set it, so it can
     * never arrive via mass assignment from a request payload.
     */
    public function ensureCalendarToken(): string
    {
        if ($this->calendar_token === null) {
            $this->forceFill(['calendar_token' => Str::lower(Str::random(40))])->save();
        }

        return (string) $this->calendar_token;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlaceReview::class);
    }

    /** Only published reviews — what's shown publicly + counted in the rating. */
    public function publishedReviews(): HasMany
    {
        return $this->hasMany(PlaceReview::class)->where('status', ReviewStatus::Published->value);
    }

    /**
     * Admin-curated lists this place belongs to. The pivot row carries the
     * place's position inside each list so the landing-page section renders
     * cards in admin-chosen order.
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(PlaceList::class, 'place_list_items')
            ->using(PlaceListItem::class)
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }

    /** Users who liked this place. */
    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'place_likes')
            ->using(PlaceLike::class)
            ->withTimestamps();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PlaceLike::class);
    }

    /** Approved + active places — the only ones the public API surfaces. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('status', PlaceStatus::Active->value)
            ->where('review_status', PlaceReviewStatus::Approved->value);
    }

    /** Whether this place is publicly visible (the row-level twin of scopeVisible). */
    public function isVisible(): bool
    {
        return $this->status === PlaceStatus::Active
            && $this->review_status === PlaceReviewStatus::Approved;
    }
}
