<?php

declare(strict_types=1);

use App\Models\User;

it('updates the authenticated user via PATCH /api/user', function (): void {
    $user = User::factory()->create([
        'phone' => '512345678',
        'name' => null,
        'gender' => null,
    ]);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/user', [
            'name' => 'Nasser',
            'gender' => 'male',
            'age' => 30,
        ])
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.name', 'Nasser')
        ->assertJsonPath('data.gender', 'male')
        ->assertJsonPath('data.age', 30);

    $fresh = User::find($user->id);
    expect($fresh->name)->toBe('Nasser');
    expect($fresh->gender)->toBe('male');
    expect($fresh->age)->toBe(30);
});

it('rejects an invalid email on PATCH /api/user', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/user', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['email']]]);
});

it('ignores role attempts on PATCH /api/user', function (): void {
    $user = User::factory()->create(['phone' => '512345678', 'role' => 'user']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/user', ['role' => 'admin', 'name' => 'Test'])
        ->assertOk();

    expect(User::find($user->id)->role->value)->toBe('user');
});

it('rejects unauthenticated PATCH /api/user', function (): void {
    $this->patchJson('/api/user', ['name' => 'Test'])->assertStatus(401);
});
