<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Unified API envelope: { status, message, data }.
 *
 * Every API response — success or error — flows through here so the frontend
 * can rely on one shape regardless of which controller or middleware produced it.
 */
final class ApiResponse
{
    public static function make(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'status' => $status,
            'message' => $message,
            'data' => self::normalize($data),
        ], $status);
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return self::make($data, $message, $status);
    }

    public static function error(string $message, int $status, mixed $data = null): JsonResponse
    {
        return self::make($data, $message, $status);
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data === null) {
            return null;
        }

        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->resolve(request());
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        return $data;
    }
}
