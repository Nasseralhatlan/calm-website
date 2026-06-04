<?php

declare(strict_types=1);

use App\Models\User;

it('updates the user via PATCH /profile and redirects back with a flash status', function (): void {
    $user = User::factory()->create(['phone' => '512345678', 'name' => null]);
    $this->actingAs($user, 'api');

    $this->patch('/profile', [
        'name' => 'Nasser',
        'gender' => 'male',
        'age' => 30,
    ])->assertRedirect()->assertSessionHas('status');

    $fresh = User::find($user->id);
    expect($fresh->name)->toBe('Nasser');
    expect($fresh->gender)->toBe('male');
    expect($fresh->age)->toBe(30);
});

it('flashes validation errors back on bad PATCH /profile', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $this->actingAs($user, 'api');

    $this->patch('/profile', ['email' => 'not-an-email'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');
});

it('redirects unauthenticated PATCH /profile to /login', function (): void {
    $this->patch('/profile', ['name' => 'Test'])->assertRedirect('/login');
});
