<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An external iCal URL (Airbnb / Gathern / Google Calendar) the host pasted
 * into one of their places. The hourly sync fetches it and mirrors its events
 * into place_blockings rows (source = 'ical') keyed by the event UID, so the
 * external platform's busy dates block availability here too.
 */
class PlaceCalendarFeed extends Model
{
    use HasUuids;

    protected $fillable = [
        'place_id',
        'name',
        'url',
        'last_synced_at',
        'last_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    /** The blockings this feed currently mirrors (deleted when the feed goes). */
    public function blockings(): HasMany
    {
        return $this->hasMany(PlaceBlocking::class, 'calendar_feed_id');
    }
}
