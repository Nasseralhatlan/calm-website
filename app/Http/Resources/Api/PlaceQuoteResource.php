<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Availability + pricing quote for the mobile checkout page. Wraps the plain
 * array PlaceAvailabilityService::quote() computes.
 *
 *   bookable           → gate the "Book" button on this single flag.
 *   dates_available /  → granular reasons so the UI can explain *why* a stay
 *   guests_ok            isn't bookable.
 *   breakdown          → per-day rows for the price summary.
 *   pricing            → subtotal (sum of nights) → VAT → total. The guest pays
 *                        `total` (= subtotal + VAT); Calm's commission is taken
 *                        from the host and is NOT shown here. `total_minor` is
 *                        halalas, ready to hand to the payment gateway.
 *
 * Returned by GET /api/places/{place}/quote.
 */
class PlaceQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'place_id' => $this->resource['place_id'],
            'check_in' => $this->resource['check_in'],
            'check_out' => $this->resource['check_out'],
            'days' => $this->resource['days'],
            'guests' => $this->resource['guests'],
            'max_guests' => $this->resource['max_guests'],
            'currency' => $this->resource['currency'],
            'bookable' => $this->resource['bookable'],
            'dates_available' => $this->resource['dates_available'],
            'guests_ok' => $this->resource['guests_ok'],
            'unavailable_dates' => $this->resource['unavailable_dates'],
            'breakdown' => $this->resource['breakdown'],
            'pricing' => [
                'subtotal' => $this->resource['pricing']['subtotal'],
                'vat_percentage' => $this->resource['pricing']['vat_rate'],
                'vat' => $this->resource['pricing']['vat_amount'],
                'total' => $this->resource['pricing']['total'],
                'total_minor' => $this->resource['pricing']['total_minor'],
            ],
        ];
    }
}
