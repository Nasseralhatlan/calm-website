<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostFacility extends Model
{
    protected $fillable = ['host_id', 'key', 'count'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HostImage::class);
    }
}
