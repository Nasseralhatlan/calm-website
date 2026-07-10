<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceBlocking extends Model
{
    use HasUuids;

    protected $fillable = [
        'place_id',
        'start_date',
        'end_date',
        'reason',
        'source',
        'calendar_feed_id',
        'external_uid',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** The external feed this block mirrors (null for host-made manual blocks). */
    public function feed(): BelongsTo
    {
        return $this->belongsTo(PlaceCalendarFeed::class, 'calendar_feed_id');
    }

    /** Mirrored from an external calendar (managed by sync, not the host). */
    public function isImported(): bool
    {
        return $this->source === 'ical';
    }
}
