<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class HostImage extends Model
{
    protected $fillable = ['host_id', 'host_facility_id', 'path', 'sort', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    protected $appends = ['url'];

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(HostFacility::class, 'host_facility_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->path);
    }
}
