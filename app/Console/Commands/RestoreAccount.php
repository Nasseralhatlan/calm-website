<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\User\AccountDeletionService;
use Illuminate\Console\Command;

/**
 * Support tool: bring back a soft-deleted account by its original phone (or id).
 * Looked up via deleted_phone, since the live phone was nulled on deletion.
 */
class RestoreAccount extends Command
{
    protected $signature = 'accounts:restore {identifier : original phone (e.g. 512345678) or user id}';

    protected $description = 'Restore a soft-deleted user account by original phone or id';

    public function handle(AccountDeletionService $service): int
    {
        $identifier = (string) $this->argument('identifier');

        $user = User::onlyTrashed()
            ->where('deleted_phone', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if ($user === null) {
            $this->error("No deleted account found for '{$identifier}'.");

            return self::FAILURE;
        }

        $service->restore($user);
        $user->refresh();

        if ($user->phone === null && $user->deleted_phone !== null) {
            $this->warn("Restored, but the phone {$user->deleted_phone} is now taken by another account — restored without it (manual merge needed).");
        } else {
            $this->info("Restored account {$user->id} ({$user->phone}).");
        }

        return self::SUCCESS;
    }
}
