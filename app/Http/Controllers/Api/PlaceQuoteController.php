<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceQuoteRequest;
use App\Http\Resources\Api\PlaceQuoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Services\Place\PlaceAvailabilityService;
use Illuminate\Http\JsonResponse;

class PlaceQuoteController extends Controller
{
    public function __construct(private readonly PlaceAvailabilityService $service) {}

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

        return ApiResponse::success(
            data: PlaceQuoteResource::make($quote),
            message: 'Quote calculated.',
        );
    }
}
