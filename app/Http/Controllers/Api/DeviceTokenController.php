<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expo push-token registration. The app calls register on login and whenever
 * Expo hands it a fresh token; unregister on logout.
 */
class DeviceTokenController extends Controller
{
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        // Upsert by token so the same device re-registering just re-points to
        // the current user (and is claimed away from a previous account).
        DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'last_used_at' => now(),
            ],
        );

        // The app may pass its UI language so notifications match it.
        if (! empty($data['locale']) && $data['locale'] !== $user->locale) {
            $user->update(['locale' => $data['locale']]);
        }

        return ApiResponse::success(message: 'Device registered.');
    }

    public function unregister(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'max:255']]);

        $request->user()->deviceTokens()->where('token', $request->string('token'))->delete();

        return ApiResponse::success(message: 'Device unregistered.');
    }
}
