<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Place\SettingService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * Public app settings for the mobile client. Returns a fixed whitelist of
     * fields (currently support_phone, support_email) — the keys are hardcoded
     * in the service, so clients can never request arbitrary settings.
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->settings->publicSettings(),
            message: 'Settings fetched.',
        );
    }
}
