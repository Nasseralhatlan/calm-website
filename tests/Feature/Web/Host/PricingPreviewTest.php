<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
});

it('renders the pricing breakdown with the commission rate from settings', function (): void {
    Setting::query()->updateOrCreate(['key' => 'commission_percentage'], ['value' => '12']);

    $host = User::factory()->create(['phone' => '560000001']);

    $this->actingAs($host, 'api')
        ->get('/host-register')
        ->assertOk()
        ->assertSee('السعر الظاهر للضيف / يوم')  // the per-day breakdown row
        ->assertSee('"commission":12', false);   // rate handed to the wizard via init JSON
});
