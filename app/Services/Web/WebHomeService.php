<?php

declare(strict_types=1);

namespace App\Services\Web;

use App\Models\User;
use App\Services\Geo\CityService;
use App\Services\Place\PlaceListService;
use App\Services\Place\PlaceService;
use App\Services\Place\PlaceTypeService;

final class WebHomeService
{
    public function __construct(
        private readonly PlaceService $places,
        private readonly PlaceListService $lists,
        private readonly PlaceTypeService $types,
        private readonly CityService $cities,
    ) {}

    /**
     * Everything the browse home renders in one call: the quick place-type
     * boxes, the curated lists (active, visible places only, likes-aware for
     * the viewer), and the city → area catalog behind the search modal.
     *
     * @return array<string, mixed>
     */
    public function data(?User $viewer): array
    {
        return [
            'placeTypes' => $this->types->activeForApi(),
            'lists' => $this->lists->activeForApi($this->places, $viewer),
            'cities' => $this->cities->activeForApi(),
        ];
    }
}
