<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReviewStatusRequest;
use App\Models\PlaceReview;
use App\Services\Place\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewsController extends Controller
{
    public function __construct(private readonly ReviewService $service) {}

    public function index(Request $request): View
    {
        $status = $request->query('status') ?: null;
        $search = trim((string) $request->query('q', '')) ?: null;

        return view('admin.reviews.index', [
            'reviews' => $this->service->paginateForAdmin($status, $search),
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function updateStatus(UpdateReviewStatusRequest $request, PlaceReview $review): RedirectResponse
    {
        $this->service->setStatus($review, ReviewStatus::from($request->validated('status')));

        return redirect()->back()->with('status', __('Review status updated.'));
    }
}
