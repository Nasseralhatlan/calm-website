<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Place extends Model
{
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
        'rules',
        'status',
        'review_status',
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

    public function coverPhoto(): HasOne
    {
        return $this->hasOne(PlacePhoto::class)->where('is_cover', true);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(PlaceAttribute::class);
    }

    public function blockings(): HasMany
    {
        return $this->hasMany(PlaceBlocking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PlaceReview::class);
    }
}
