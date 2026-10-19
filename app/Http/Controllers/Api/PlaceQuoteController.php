<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceQuoteRequest;
use App\Http\Resources\Api\PlaceQuoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Services\Place\PlaceAvailabilityService;
use App\Services\Promo\PromoCodeService;
use Illuminate\Http\JsonResponse;

class PlaceQuoteController extends Controller
{
    public function __construct(
        private readonly PlaceAvailabilityService $service,
        private readonly PromoCodeService $promos,
    ) {}

    /**
     * Availability + pricing quote for a stay — the source of truth the mobile
     * checkout page reads before the guest books. Public + 404s on any place
     * that isn't Active + Approved, matching the other read endpoints.
     */
    public function __invoke(PlaceQuoteRequest $request, Place $place): JsonResponse
    {
        $guests = $request->validated('guests');

        $quote = $this->service->quote(
            $place,
            $request->validated('check_in'),
            $request->validated('check_out'),
            $guests !== null ? (int) $guests : null,
        );

        if ($quote === null) {
            return ApiResponse::error('Place not found.', 404);
        }

        // Optional promo: re-price with the discount when it applies, or echo
        // the refusal reason so the checkout can explain it. Public endpoint —
        // the user is resolved only if a token came along (per-guest limits).
        $code = trim((string) $request->validated('promo_code', ''));
        $quote['promo'] = null;
        if ($code !== '') {
            $result = $this->promos->resolve($code, $request->user('api'), $quote['pricing']['subtotal_minor']);
            if ($result['ok']) {
                $quote['pricing'] = $this->service->pricingFor($quote['pricing']['subtotal_minor'], $result['discount_minor']);
                $quote['promo'] = ['code' => $result['promo']->code, 'applied' => true, 'reason' => null];
            } else {
                $quote['promo'] = [
                    'code' => mb_strtoupper($code),
                    'applied' => false,
                    'reason' => $result['reason'],
                    'message' => PromoCodeService::refusalMessage($result['reason']),
                    'min_stay' => isset($result['min_stay_minor']) ? $result['min_stay_minor'] / 100 : null,
                ];
            }
        }

        return ApiResponse::success(
            data: PlaceQuoteResource::make($quote),
            message: 'Quote calculated.',
        );
    }
}
