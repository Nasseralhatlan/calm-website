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
        $phone = (string) env('ADMIN_PHONE', '500000001');
        $email = (string) env('ADMIN_EMAIL', 'admin@calmapp.co');

        User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Calm Admin',
                'email' => $email,
                'role' => UserRole::Admin->value,
            ],
        );
    }
}
