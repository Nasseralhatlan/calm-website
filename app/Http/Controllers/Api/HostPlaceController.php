<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PlaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePlaceStatusRequest;
use App\Http\Requests\Host\SaveDraftRequest;
use App\Http\Requests\Host\StorePlaceRequest;
use App\Http\Requests\Host\UpdatePlaceDetailsRequest;
use App\Http\Resources\Api\HostPlaceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Place;
use App\Models\User;
use App\Services\Place\PlaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile host place management — the full create/edit wizard over the API.
 * Mirrors the web wizard (Host\PlacesController) exactly: same FormRequests,
 * same PlaceService calls, so validation and lifecycle rules can never drift
 * between platforms. The admin acting-on-behalf path (host_phone) stays
 * web-only — here the host is always the authenticated user. Owner-only on
 * every {place} route, like HostBlockingController.
 */
class HostPlaceController extends Controller
{
    public function __construct(private readonly PlaceService $service) {}

    /** The full editable place (drafts/pending/rejected included) for resume/edit. */
    public function show(Request $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $place = $this->service->editableForHost($place);

        return ApiResponse::success(
            data: (new HostPlaceResource($place))->resolve($request),
            message: 'Host place fetched.',
        );
    }

    /**
     * Wizard auto-save. Called every time the host advances a step; upserts
     * the in-progress draft and returns its id so the client keeps patching
     * the same record (first call sends draft_id=null).
     */
    public function draft(SaveDraftRequest $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $draft = $this->service->saveDraftForHost(
            $host,
            $request->placeData(),
            $request->string('draft_id')->toString() ?: null,
            $request->attributesData(),
            $request->photosData(),
        );

        return ApiResponse::success(
            data: [
                'id' => $draft->id,
                'review_status' => $draft->review_status->value,
            ],
            message: 'Draft saved.',
        );
    }

    /** Final submit at the end of the wizard — the place goes to PendingReview. */
    public function store(StorePlaceRequest $request): JsonResponse
    {
        /** @var User $host */
        $host = $request->user();

        $place = $this->service->createForHost(
            $host,
            $request->placeData(),
            $request->string('draft_id')->toString() ?: null,
            $request->attributesData(),
            $request->photosData(),
        );

        return ApiResponse::success(
            data: (new HostPlaceResource($place))->resolve($request),
            message: 'Place submitted for review.',
            status: Response::HTTP_CREATED,
        );
    }

    /** Edit an existing listing — the service resubmits it for review. */
    public function update(UpdatePlaceDetailsRequest $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $place = $this->service->updateDetailsForHost(
            $place,
            $request->placeData(),
            $request->attributesData(),
            $request->photosData(),
        );

        return ApiResponse::success(
            data: (new HostPlaceResource($place))->resolve($request),
            message: 'Place updated — resubmitted for review.',
        );
    }

    /**
     * Pause / unpause a listing. Not part of the edit flow on purpose: a
     * status toggle is not a content change, so it must never push the place
     * back into the review queue. Activation only works on approved places
     * (the service enforces it); pausing always works.
     */
    public function updateStatus(UpdatePlaceStatusRequest $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $place = $this->service->setStatusForHost(
            $place,
            PlaceStatus::from((string) $request->validated('status')),
        );

        return ApiResponse::success(
            data: [
                'id' => $place->id,
                'status' => $place->status->value,
                'review_status' => $place->review_status->value,
            ],
            message: $place->status === PlaceStatus::Active ? 'Place activated.' : 'Place paused.',
        );
    }

    /** Archive a listing — soft delete, so bookings and history survive. */
    public function destroy(Request $request, Place $place): JsonResponse
    {
        $this->authorizeOwner($request, $place);

        $this->service->delete($place);

        return ApiResponse::success(message: 'Place deleted.');
    }

    /** Only the place's own host may read or modify it. */
    private function authorizeOwner(Request $request, Place $place): void
    {
        abort_unless($place->host_user_id === $request->user()?->id, Response::HTTP_FORBIDDEN);
    }
}
