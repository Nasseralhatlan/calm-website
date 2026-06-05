<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Country;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->actingAs($this->admin, 'api');
});

// ─── access control ──────────────────────────────────────────────────────────

it('non-admin cannot list countries', function (): void {
    $user = User::factory()->create(['role' => UserRole::User->value]);
    $this->actingAs($user, 'api')->get('/admin/countries')->assertRedirect('/profile');
});

// ─── index ───────────────────────────────────────────────────────────────────

it('lists countries', function (): void {
    Country::query()->create(['country_code' => 'AE', 'name_ar' => 'الإمارات', 'name_en' => 'UAE']);

    // The redesigned table renders one column per row: an emoji + the
    // locale-appropriate name, plus a separate ISO code column. On the
    // default locale (ar) the Arabic name is visible alongside the code.
    $this->get('/admin/countries')
        ->assertOk()
        ->assertSee('AE')
        ->assertSee('الإمارات', escape: false);
});

// ─── create + store ──────────────────────────────────────────────────────────

it('shows the create country form', function (): void {
    $this->get('/admin/countries/create')->assertOk();
});

it('stores a new country', function (): void {
    $this->post('/admin/countries', [
        'country_code' => 'KW',
        'name_ar' => 'الكويت',
        'name_en' => 'Kuwait',
    ])->assertRedirect('/admin/countries')->assertSessionHas('status');

    expect(Country::where('country_code', 'KW')->exists())->toBeTrue();
});

it('rejects a country with a duplicate code', function (): void {
    Country::query()->create(['country_code' => 'KW', 'name_ar' => 'الكويت', 'name_en' => 'Kuwait']);

    $this->post('/admin/countries', [
        'country_code' => 'KW',
        'name_ar' => 'كويت',
        'name_en' => 'Kuwait Dup',
    ])->assertSessionHasErrors('country_code');

    expect(Country::where('country_code', 'KW')->count())->toBe(1);
});

it('requires all fields on store', function (): void {
    $this->post('/admin/countries', [])
        ->assertSessionHasErrors(['country_code', 'name_ar', 'name_en']);
});

// ─── edit + update ───────────────────────────────────────────────────────────

it('shows the edit form', function (): void {
    $country = Country::query()->create(['country_code' => 'BH', 'name_ar' => 'البحرين', 'name_en' => 'Bahrain']);

    $this->get('/admin/countries/'.$country->id.'/edit')->assertOk()->assertSee('Bahrain');
});

it('updates a country', function (): void {
    $country = Country::query()->create(['country_code' => 'OM', 'name_ar' => 'عمان', 'name_en' => 'Oman']);

    $this->put('/admin/countries/'.$country->id, [
        'country_code' => 'OM',
        'name_ar' => 'سلطنة عمان',
        'name_en' => 'Sultanate of Oman',
    ])->assertRedirect('/admin/countries');

    expect($country->fresh()->name_en)->toBe('Sultanate of Oman');
});

it('allows keeping the same country code on update', function (): void {
    $country = Country::query()->create(['country_code' => 'QA', 'name_ar' => 'قطر', 'name_en' => 'Qatar']);

    $this->put('/admin/countries/'.$country->id, [
        'country_code' => 'QA',
        'name_ar' => 'دولة قطر',
        'name_en' => 'Qatar',
    ])->assertRedirect('/admin/countries')->assertSessionDoesntHaveErrors();
});

// ─── destroy ─────────────────────────────────────────────────────────────────

it('deletes a country', function (): void {
    $country = Country::query()->create(['country_code' => 'JO', 'name_ar' => 'الأردن', 'name_en' => 'Jordan']);

    $this->delete('/admin/countries/'.$country->id)
        ->assertRedirect('/admin/countries')
        ->assertSessionHas('status');

    expect(Country::find($country->id))->toBeNull();
});
