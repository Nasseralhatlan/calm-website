<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for users ↔ places (likes). Exists so attach()/sync() route
 * through Eloquent's `creating` event — that's what fires HasUuids and mints
 * the row's UUID. Same trick as PlaceListItem.
 */
class PlaceLike extends Pivot
{
    use HasUuids;

    protected $table = 'place_likes';

    public $incrementing = false;

    protected $keyType = 'string';
}
