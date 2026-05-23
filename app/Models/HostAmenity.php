<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostAmenity extends Model
{
    protected $fillable = ['host_id', 'key'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
