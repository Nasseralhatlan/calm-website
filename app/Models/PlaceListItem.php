<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for the place ↔ place_list relation. Exists so attach()/sync()
 * route through Eloquent's `creating` event — that's what fires HasUuids and
 * mints the row's UUID. Without it the pivot insert violates NOT NULL on `id`.
 */
class PlaceListItem extends Pivot
{
    use HasUuids;

    protected $table = 'place_list_items';

    public $incrementing = false;

    protected $keyType = 'string';
}
