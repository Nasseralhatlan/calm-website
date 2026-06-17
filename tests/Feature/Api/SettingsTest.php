<?php

declare(strict_types=1);

use App\Models\Setting;

beforeEach(function (): void {
    $this->seed();
});

it('returns the whitelisted public settings publicly', function (): void {
    $this->getJson('/api/settings')
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.support_phone', '+966500000000')
        ->assertJsonPath('data.support_email', 'support@calmapp.co')
        ->assertJsonStructure(['status', 'message', 'data' => ['support_phone', 'support_email']]);
});

it('reflects admin-updated settings', function (): void {
    Setting::query()->updateOrCreate(['key' => 'support_email'], ['value' => 'help@calm.test']);

    $this->getJson('/api/settings')
        ->assertOk()
        ->assertJsonPath('data.support_email', 'help@calm.test');
});

it('returns null for a whitelisted setting that is not set', function (): void {
    Setting::query()->where('key', 'support_phone')->delete();

    $this->getJson('/api/settings')
        ->assertOk()
        ->assertJsonPath('data.support_phone', null)
        ->assertJsonPath('data.support_email', 'support@calmapp.co');
});

it('exposes only whitelisted settings — never admin-only ones', function (): void {
    $raw = $this->getJson('/api/settings')->assertOk()->getContent();

    expect($raw)->not->toContain('commission_percentage')
        ->and($raw)->not->toContain('vat_percentage');
});
