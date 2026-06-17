<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PlacePhoto extends Model
{
    use HasUuids;

    protected $fillable = [
        'place_id',
        'place_attribute_id',
        'path',
        'sort_order',
        'featured_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'featured_order' => 'integer',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'place_attribute_id');
    }

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }

        // Photos are uploaded straight to S3 via presigned PUT URLs (wizard
        // step 8), so paths live on the `s3` disk regardless of what
        // `filesystems.default` happens to be. Reading from the default disk
        // pointed at `public` was returning broken `/storage/...` URLs.
        return (string) Storage::disk('s3')->url($this->path);
    }
}
