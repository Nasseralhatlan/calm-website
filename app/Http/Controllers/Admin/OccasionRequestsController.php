<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OccasionRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOccasionRequestStatusRequest;
use App\Models\OccasionRequest;
use App\Services\Occasion\OccasionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OccasionRequestsController extends Controller
{
    public function __construct(private readonly OccasionRequestService $service) {}

    /** The events team's queue — every lead, filterable by pipeline stage. */
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: null;
        $search = $request->string('q')->toString() ?: null;

        return view('admin.occasion-requests.index', [
            'requests' => $this->service->forAdminPaginated($status, $search),
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function updateStatus(UpdateOccasionRequestStatusRequest $request, OccasionRequest $occasionRequest): RedirectResponse
    {
        $this->service->updateStatus(
            $occasionRequest,
            OccasionRequestStatus::from($request->validated('status')),
        );

        return redirect()
            ->back()
            ->with('status', __('Occasion request updated.'));
    }
}
