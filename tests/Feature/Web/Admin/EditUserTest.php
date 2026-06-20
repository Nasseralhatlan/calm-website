<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Country;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->seed();
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value, 'phone' => '500000001']);
    $this->actingAs($this->admin, 'api');
});

it('shows the edit form with a date picker for birth date', function (): void {
    $user = User::factory()->create(['phone' => '500000002', 'name' => 'Sara']);

    $this->get("/admin/users/{$user->id}/edit")
        ->assertOk()
        ->assertSee('name="birth_date"', escape: false)
        ->assertSee('type="date"', escape: false)
        ->assertSee('name="gender"', escape: false)
        ->assertSee('name="email"', escape: false)
        // Phone is read-only (the login identifier) — no phone input.
        ->assertDontSee('name="phone"', escape: false)
        // Avatar is shown but not editable — no avatar input.
        ->assertDontSee('name="avatar"', escape: false);
});

it('updates all editable fields and syncs age from birth_date', function (): void {
    $user = User::factory()->create(['phone' => '500000003', 'age' => 99]);
    $country = Country::query()->first();

    $this->put("/admin/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'phone' => '512345678', // sent but must be IGNORED — phone isn't admin-editable
        'gender' => 'female',
        'birth_date' => '1995-06-15',
        'country_id' => $country->id,
        'role' => UserRole::User->value,
    ])->assertRedirect('/admin/users')->assertSessionHas('status');

    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->phone)->toBe('500000003') // unchanged — phone not editable from admin
        ->and($user->gender)->toBe('female')
        ->and($user->birth_date->format('Y-m-d'))->toBe('1995-06-15')
        ->and($user->country_id)->toBe($country->id)
        // age is derived from birth_date, not the stale 99.
        ->and($user->age)->toBe(CarbonImmutable::parse('1995-06-15')->age);
});

it('rejects a future birth date and a duplicate email', function (): void {
    $other = User::factory()->create(['phone' => '500000004', 'email' => 'taken@example.com']);
    $user = User::factory()->create(['phone' => '500000005']);

    $this->put("/admin/users/{$user->id}", [
        'birth_date' => now()->addYear()->toDateString(),
        'email' => 'taken@example.com',
        'role' => UserRole::User->value,
    ])->assertSessionHasErrors(['birth_date', 'email']);
});

it('lets a user keep their own email (unique ignores self)', function (): void {
    $user = User::factory()->create(['phone' => '500000006', 'email' => 'mine@example.com']);

    $this->put("/admin/users/{$user->id}", [
        'email' => 'mine@example.com',
        'name' => 'Same Email',
        'role' => UserRole::User->value,
    ])->assertRedirect('/admin/users')->assertSessionDoesntHaveErrors();
});

it('clears birth_date and age when birth date is blanked', function (): void {
    $user = User::factory()->create(['phone' => '500000007', 'birth_date' => '1990-01-01', 'age' => 35]);

    $this->put("/admin/users/{$user->id}", [
        'birth_date' => '',
        'role' => UserRole::User->value,
    ])->assertRedirect('/admin/users');

    $user->refresh();
    expect($user->birth_date)->toBeNull()->and($user->age)->toBeNull();
});
