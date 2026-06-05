<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\OtpAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly OtpAuthService $auth) {}

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $this->auth->requestOtp($request->otpType(), $request->identifier());

        return ApiResponse::success(
            data: ['type' => $request->otpType()->value, 'identifier' => $request->identifier()],
            message: 'OTP dispatched.',
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $session = $this->auth->verifyOtpAndLogin(
            $request->otpType(),
            $request->identifier(),
            $request->code(),
        );

        if (! $session) {
            return ApiResponse::error('Invalid or expired OTP.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ApiResponse::success(
            data: [
                'token' => $session->token,
                'token_type' => 'bearer',
                'expires_in' => $session->ttlSeconds,
                'user' => UserResource::make($session->user)->resolve($request),
            ],
            message: 'Verified.',
        )->withCookie($session->cookie);
    }

    public function logout(): JsonResponse
    {
        $forgetCookie = $this->auth->logout();

        return ApiResponse::success(message: 'Logged out.')->withCookie($forgetCookie);
    }

    public function refresh(Request $request): JsonResponse
    {
        $session = $this->auth->refreshSessionFor($request->user());

        return ApiResponse::success(
            data: [
                'token' => $session->token,
                'token_type' => 'bearer',
                'expires_in' => $session->ttlSeconds,
            ],
            message: 'Token refreshed.',
        )->withCookie($session->cookie);
    }
}
