<?php

declare(strict_types=1);

use App\Models\Setting;

it('renders the public support page with contact details from settings', function (): void {
    Setting::query()->create(['key' => 'support_email', 'value' => 'help@calmapp.co']);
    Setting::query()->create(['key' => 'support_phone', 'value' => '+966500000000']);

    $this->get('/support')
        ->assertOk()
        ->assertSee('help@calmapp.co')
        ->assertSee('+966500000000')
        ->assertSee('mailto:help@calmapp.co', escape: false)
        ->assertSee('tel:+966500000000', escape: false);
});

it('renders gracefully when support settings are unset', function (): void {
    $this->get('/support')->assertOk();
});

it('is publicly accessible (no auth required)', function (): void {
    $this->get('/support')->assertOk()->assertDontSee('login', escape: false);
});
