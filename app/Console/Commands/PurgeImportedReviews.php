<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlaceReview;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes everything ImportedReviewsSeeder created. Imported reviews are
 * exactly the rows with NO guest account and NO booking (organic reviews
 * always carry both), so the purge is surgical. Also sweeps any leftover
 * SEED-REV mock reviewer users + their reviews from the earlier import
 * design (no-op when none exist). Place ratings recompute automatically —
 * aggregates are query-time.
 */
class PurgeImportedReviews extends Command
{
    protected $signature = 'reviews:purge-imported {--dry-run : Only report what would be deleted}';

    protected $description = 'Delete all imported (accountless) reviews and any legacy mock reviewer users';

    public function handle(): int
    {
        $imported = PlaceReview::query()
            ->whereNull('guest_user_id')
            ->whereNull('booking_id');

        // Legacy: the first import design attached reviews to SEED-REV users.
        $legacyUserIds = User::query()->where('phone', 'like', 'SEED-REV-%')->pluck('id');
        $legacyReviews = PlaceReview::query()->whereIn('guest_user_id', $legacyUserIds);

        $reviewCount = $imported->count() + $legacyReviews->count();

        if ($this->option('dry-run')) {
            $this->info("Would delete {$reviewCount} review(s) and {$legacyUserIds->count()} mock reviewer(s).");

            return self::SUCCESS;
        }

        // Reviews before users — the user FK is nullOnDelete, so deleting
        // users first would strand their reviews as (fake) accountless rows.
        $imported->forceDelete();
        $legacyReviews->forceDelete();
        User::query()->whereIn('id', $legacyUserIds)->forceDelete();

        $this->info("Deleted {$reviewCount} review(s) and {$legacyUserIds->count()} mock reviewer(s).");

        return self::SUCCESS;
    }
}
