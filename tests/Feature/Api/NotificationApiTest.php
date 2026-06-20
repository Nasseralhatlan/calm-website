<?php

declare(strict_types=1);

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserNotification;

// The notification + device API routes are temporarily disabled (commented out
// in routes/api.php). Skip these endpoint tests until they're re-enabled — the
// underlying service/feature stays covered by NotificationServiceTest.
beforeEach(fn () => test()->markTestSkipped('Notification API routes temporarily disabled.'));

function bearer(User $user): array
{
    return ['Authorization' => 'Bearer '.auth('api')->login($user)];
}

function makeNotification(User $user, array $attrs = []): UserNotification
{
    return UserNotification::query()->create(array_merge([
        'user_id' => $user->id,
        'type' => 'broadcast',
        'title_ar' => 'مرحبا',
        'title_en' => 'Hello',
        'body_ar' => 'نص',
        'body_en' => 'Body',
        'data' => ['k' => 'v'],
    ], $attrs));
}

it('registers an Expo device token (upsert) and updates locale', function (): void {
    $user = User::factory()->create(['phone' => '514000001', 'locale' => 'ar']);

    $this->withHeaders(bearer($user))
        ->postJson('/api/devices', ['token' => 'ExponentPushToken[x]', 'platform' => 'ios', 'locale' => 'en'])
        ->assertOk();

    // Re-registering the same token must not duplicate.
    $this->withHeaders(bearer($user))
        ->postJson('/api/devices', ['token' => 'ExponentPushToken[x]', 'platform' => 'android'])
        ->assertOk();

    expect(DeviceToken::query()->where('token', 'ExponentPushToken[x]')->count())->toBe(1)
        ->and(DeviceToken::query()->where('token', 'ExponentPushToken[x]')->value('platform'))->toBe('android')
        ->and($user->refresh()->locale)->toBe('en');
});

it('unregisters a device token', function (): void {
    $user = User::factory()->create(['phone' => '514000002']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[y]']);

    $this->withHeaders(bearer($user))
        ->deleteJson('/api/devices', ['token' => 'ExponentPushToken[y]'])
        ->assertOk();

    expect(DeviceToken::query()->where('token', 'ExponentPushToken[y]')->exists())->toBeFalse();
});

it('lists the feed localized + paginated and returns unread count', function (): void {
    $user = User::factory()->create(['phone' => '514000003', 'locale' => 'en']);
    makeNotification($user);
    makeNotification($user, ['read_at' => now()]);

    $this->withHeaders(bearer($user))
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2)
        ->assertJsonPath('data.items.0.title', 'Hello')   // english locale
        ->assertJsonStructure(['data' => ['items' => [['id', 'type', 'title', 'body', 'data', 'is_read', 'created_at']], 'pagination']]);

    $this->withHeaders(bearer($user))
        ->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('marks one + all notifications read', function (): void {
    $user = User::factory()->create(['phone' => '514000004']);
    $a = makeNotification($user);
    $b = makeNotification($user);

    $this->withHeaders(bearer($user))->postJson("/api/notifications/{$a->id}/read")->assertOk();
    expect($a->refresh()->read_at)->not->toBeNull()
        ->and($b->refresh()->read_at)->toBeNull();

    $this->withHeaders(bearer($user))->postJson('/api/notifications/read-all')->assertOk();
    expect($user->userNotifications()->unread()->count())->toBe(0);
});

it("404s when marking someone else's notification", function (): void {
    $user = User::factory()->create(['phone' => '514000005']);
    $other = User::factory()->create(['phone' => '514000006']);
    $notification = makeNotification($other);

    $this->withHeaders(bearer($user))
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

it('requires auth for the feed', function (): void {
    $this->getJson('/api/notifications')->assertStatus(401);
    $this->postJson('/api/devices', ['token' => 'x'])->assertStatus(401);
});
