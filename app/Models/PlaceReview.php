<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceReview extends Model
{
    use HasUuids;

    protected $fillable = [
        'place_id',
        'booking_id',
        'rate',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'integer',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
