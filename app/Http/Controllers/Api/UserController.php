<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return UserResource::make($user)->response()->setStatusCode(Response::HTTP_OK);
    }

    public function update(UpdateProfileRequest $request, UserService $userService): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $updated = $userService->update($user, $request->validated());

        return ApiResponse::success(
            data: UserResource::make($updated)->resolve($request),
            message: 'Profile updated.',
        );
    }
}
