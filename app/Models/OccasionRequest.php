<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OccasionRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One occasion planning lead. `details` holds whatever the wizard collected
 * beyond the occasion type — see the migration for why it's a blob.
 */
class OccasionRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'occasion_type',
        'details',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'status' => OccasionRequestStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** One field out of `details`, or null when the wizard didn't collect it. */
    public function detail(string $key): mixed
    {
        return $this->details[$key] ?? null;
    }
}
