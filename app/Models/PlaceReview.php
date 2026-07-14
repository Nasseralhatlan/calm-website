<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlaceReview extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'place_id',
        'guest_user_id',
        'reviewer_name',
        'booking_id',
        'rate',
        'comment',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'status' => ReviewStatus::class,
            'deleted_at' => 'datetime',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Published reviews — the only ones shown publicly + counted in the rating. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Published->value);
    }
}
