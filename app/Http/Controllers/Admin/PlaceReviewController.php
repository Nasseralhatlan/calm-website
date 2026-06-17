<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\Place\PlaceReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin review surface — three buttons (Approve / Reject / Skip) on a
 * customer-style preview of the place. After each action we hand the admin
 * the NEXT pending row so they can flow through the queue.
 */
class PlaceReviewController extends Controller
{
    public function __construct(private readonly PlaceReviewService $reviews) {}

    /** Show the review page for a specific place. */
    public function show(Place $place): View
    {
        $place->load([
            'host',
            'type',
            'cityArea.city',
            'photos',
            'attributeValues.attribute.group',
        ]);

        return view('admin.places.review', [
            'place' => $place,
            'preview' => true,
            'next' => $this->reviews->nextAfter($place),
        ]);
    }

    public function approve(Place $place): RedirectResponse
    {
        $next = $this->reviews->approve($place);

        return $this->redirectToNext($next, __('Place ":title" approved.', ['title' => $place->title ?? '—']));
    }

    public function reject(Request $request, Place $place): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $next = $this->reviews->reject($place, $validated['rejection_reason']);

        return $this->redirectToNext($next, __('Place ":title" rejected — host notified.', ['title' => $place->title ?? '—']));
    }

    public function skip(Place $place): RedirectResponse
    {
        $next = $this->reviews->skipAfter($place);

        return $this->redirectToNext($next, __('Skipped.'));
    }

    /**
     * Send the admin to the next pending review, or back to the places list
     * when the queue is empty.
     */
    private function redirectToNext(?Place $next, string $status): RedirectResponse
    {
        if ($next) {
            return redirect()->route('admin.places.review', $next)->with('status', $status);
        }

        return redirect()->route('admin.places.index')->with('status', __(':status Queue is now empty.', ['status' => $status]));
    }
}
