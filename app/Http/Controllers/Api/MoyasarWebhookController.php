<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoyasarWebhookController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    /**
     * Moyasar server-to-server payment notification. The secret is verified and
     * the booking is settled by re-fetching the invoice from Moyasar (never
     * trusting the webhook body). Always answers 200 once accepted so Moyasar
     * doesn't retry endlessly.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->service->handleWebhook(
            $request->all(),
            $request->header('X-Moyasar-Secret') ?? $request->input('secret_token'),
        );

        return ApiResponse::success(message: 'Webhook received.');
    }
}
