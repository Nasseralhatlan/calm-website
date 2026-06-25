<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\User\AccountDeletionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Optional, off by default. When `account.retain_days` is set, permanently
 * scrubs PII on accounts soft-deleted longer ago than the window. Skipped
 * entirely when no retention window is configured (data kept forever).
 */
class PurgeDeletedAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AccountDeletionService $service): void
    {
        $retainDays = config('account.retain_days');

        if ($retainDays === null) {
            return; // retention disabled — keep deleted accounts indefinitely
        }

        $cutoff = now()->subDays((int) $retainDays);

        User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereNotNull('deleted_phone')      // not yet purged (purge nulls this)
            ->chunkById(200, function ($users) use ($service): void {
                foreach ($users as $user) {
                    $service->purge($user);
                }
            });
    }
}
