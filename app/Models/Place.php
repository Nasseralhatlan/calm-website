<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'description',
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
        'max_guests',
        'rules',
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
            'reviewed_at' => 'datetime',
        ];
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlaceReview::class);
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
