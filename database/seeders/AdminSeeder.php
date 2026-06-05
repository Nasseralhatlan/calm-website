<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Nasser's number — stored in the 9-digit national format the app
        // expects (no leading 0, no +966). Override via ADMIN_PHONE in .env
        // if you're running this on a different account.
        $phone = (string) env('ADMIN_PHONE', '501203845');
        $email = (string) env('ADMIN_EMAIL', 'nasser@calmapp.co');

        User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Nassser Alhatlan',
                'email' => $email,
                'role' => UserRole::Admin->value,
            ],
        );
    }
}
