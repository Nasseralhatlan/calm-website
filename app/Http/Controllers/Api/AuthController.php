<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OtpType;
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
        $otp = $this->auth->requestOtp(OtpType::Phone, $request->phone());

        return ApiResponse::success(
            data: [
                'phone' => $request->phone(),
                // When this OTP code stops being accepted. Frontend can drive
                // a countdown timer + the "resend" button enable state from
                // this. ISO 8601 in UTC.
                'expires_at' => $otp->expires_at->toIso8601String(),
            ],
            message: 'OTP dispatched.',
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $session = $this->auth->verifyOtpAndLogin(
            OtpType::Phone,
            $request->phone(),
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
                // Absolute timestamp the token stops working — frontend can
                // schedule a refresh (e.g. at 80% of TTL) without doing
                // now() + expires_in arithmetic on the client clock.
                'expires_at' => now()->addSeconds($session->ttlSeconds)->toIso8601String(),
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
                'expires_at' => now()->addSeconds($session->ttlSeconds)->toIso8601String(),
            ],
            message: 'Token refreshed.',
        )->withCookie($session->cookie);
    }
}
