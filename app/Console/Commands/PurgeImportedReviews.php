<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlaceReview;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes everything ImportedReviewsSeeder created: the reviews AND their
 * mock reviewer users (identified by the reserved SEED-REV phone block).
 * Place ratings recompute automatically — aggregates are query-time.
 */
class PurgeImportedReviews extends Command
{
    protected $signature = 'reviews:purge-imported {--dry-run : Only report what would be deleted}';

    protected $description = 'Delete all imported off-platform reviews and their mock reviewer users';

    public function handle(): int
    {
        $userIds = User::query()->where('phone', 'like', 'SEED-REV-%')->pluck('id');
        $reviewCount = PlaceReview::query()->whereIn('guest_user_id', $userIds)->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$reviewCount} review(s) and {$userIds->count()} mock reviewer(s).");

            return self::SUCCESS;
        }

        // Reviews first (the user FK is nullOnDelete — deleting users alone
        // would strand anonymous reviews still counting toward ratings).
        PlaceReview::query()->whereIn('guest_user_id', $userIds)->forceDelete();
        User::query()->whereIn('id', $userIds)->forceDelete();

        $this->info("Deleted {$reviewCount} review(s) and {$userIds->count()} mock reviewer(s).");

        return self::SUCCESS;
    }
}
