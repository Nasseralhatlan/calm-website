<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaceUnavailableDatesRequest;
use App\Http\Resources\Api\PlaceAvailabilityResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Services\Place\PlaceAvailabilityService;
use Illuminate\Http\JsonResponse;

class PlaceAvailabilityController extends Controller
{
    public function __construct(private readonly PlaceAvailabilityService $service) {}

    /**
     * Unavailable (blocked) dates for a place's calendar. Public + 404s on any
     * place that isn't Active + Approved, so drafts and rejected listings never
     * leak their schedule via direct URL — same contract as the detail endpoint.
     */
    public function __invoke(PlaceUnavailableDatesRequest $request, Place $place): JsonResponse
    {
        $availability = $this->service->unavailableDates(
            $place,
            $request->validated('from'),
            $request->validated('to'),
        );

        if ($availability === null) {
            return ApiResponse::error('Place not found.', 404);
        }

        return ApiResponse::success(
            data: PlaceAvailabilityResource::make($availability),
            message: 'Unavailable dates fetched.',
        );
    }
}
