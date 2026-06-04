<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Place;
use App\Models\PlaceAttribute;
use App\Models\PlacePhoto;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PlaceService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Place::query()
            ->with(['host', 'type', 'cityArea.city'])
            ->withCount(['photos', 'attributeValues'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Places that belong to a specific host (the host's own list).
     *
     * @return Collection<int, Place>
     */
    public function forHost(User $host): Collection
    {
        return Place::query()
            ->where('host_user_id', $host->id)
            ->with(['type', 'cityArea.city', 'coverPhoto'])
            ->latest()
            ->get();
    }

    /**
     * Confirm a host's place — the final submit at the end of the wizard.
     * If a $draftId is provided and matches one of this host's Draft places,
     * we promote that record to PendingReview. Otherwise we create fresh.
     * Attributes + photos are synced in the same transaction so the place
     * row, its facility choices, and its uploaded photo paths land together.
     *
     * @param  array<string, mixed>      $data        Place columns.
     * @param  list<array<string, mixed>> $attributes  [{attribute_id, value, description}, ...]
     * @param  array<string, mixed>      $photos      ['attribute_paths' => [attrId => [path,...]], 'extra_paths' => [path,...], 'cover_key' => string|null]
     */
    public function createForHost(
        User $host,
        array $data,
        ?int $draftId = null,
        array $attributes = [],
        array $photos = [],
    ): Place {
        return DB::transaction(function () use ($host, $data, $draftId, $attributes, $photos): Place {
            $place = $this->upsertPlace($host, $data, $draftId, PlaceReviewStatus::PendingReview);
            $this->syncAttributes($place, $attributes);
            $this->syncPhotos($place, $photos);

            return $place->refresh();
        });
    }

    /**
     * Upsert the host's in-progress draft. Called every time the host advances
     * a step so the record exists from step 1 onward. Attributes + photos
     * upsert too — that's what lets us re-hydrate the wizard if the host exits
     * and resumes from the listing.
     *
     * @param  array<string, mixed>      $data
     * @param  list<array<string, mixed>> $attributes
     * @param  array<string, mixed>      $photos
     */
    public function saveDraftForHost(
        User $host,
        array $data,
        ?int $draftId = null,
        array $attributes = [],
        array $photos = [],
    ): Place {
        return DB::transaction(function () use ($host, $data, $draftId, $attributes, $photos): Place {
            $place = $this->upsertPlace($host, $data, $draftId, PlaceReviewStatus::Draft);
            $this->syncAttributes($place, $attributes);
            $this->syncPhotos($place, $photos);

            return $place->refresh();
        });
    }

    /**
     * Find-or-create the place row, scoped to the given host. When $draftId
     * matches one of this host's Draft places we update it; otherwise we
     * create a fresh row. Existing rows in non-Draft state are ignored.
     */
    private function upsertPlace(User $host, array $data, ?int $draftId, PlaceReviewStatus $reviewStatus): Place
    {
        $existing = null;
        if ($draftId !== null) {
            $existing = Place::query()
                ->where('id', $draftId)
                ->where('host_user_id', $host->id)
                ->where('review_status', PlaceReviewStatus::Draft->value)
                ->first();
        }

        $payload = [
            ...$data,
            'host_user_id' => $host->id,
            'status' => PlaceStatus::Inactive->value,
            'review_status' => $reviewStatus->value,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->refresh();
        }

        return Place::query()->create($payload);
    }

    /**
     * Sync the place's attribute choices: drop ones the host removed, upsert
     * the rest. Empty input is a no-op — pass `[]` to leave the row's
     * attributes untouched (useful for intermediate draft saves where the
     * host hasn't touched step 6/7 yet).
     *
     * @param  list<array<string, mixed>>  $attributes
     */
    private function syncAttributes(Place $place, array $attributes): void
    {
        if ($attributes === []) {
            return;
        }

        $keepIds = array_column($attributes, 'attribute_id');
        $place->attributeValues()
            ->whereNotIn('attribute_id', $keepIds)
            ->delete();

        $now = now();
        $rows = array_map(fn (array $a): array => [
            'place_id' => $place->id,
            'attribute_id' => (int) $a['attribute_id'],
            'value' => isset($a['value']) ? (string) $a['value'] : null,
            'description' => $a['description'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes);

        PlaceAttribute::upsert(
            $rows,
            ['place_id', 'attribute_id'],
            ['value', 'description', 'updated_at'],
        );
    }

    /**
     * Replace the place's photos with the given paths. Photos are referenced
     * by their composite "key" in the form: `attribute_images.<attrId>.<i>`
     * for per-attribute uploads, `extra_images.<i>` for general place photos.
     * The host's chosen `cover_key` flips `is_cover` on the matching row.
     *
     * Pass `['attribute_paths' => [], 'extra_paths' => []]` (or an empty array)
     * to leave existing photos untouched — used for early draft saves before
     * the host reaches step 8.
     *
     * @param  array<string, mixed>  $photos
     */
    private function syncPhotos(Place $place, array $photos): void
    {
        if ($photos === [] || (!isset($photos['attribute_paths']) && !isset($photos['extra_paths']))) {
            return;
        }

        $place->photos()->delete();

        $attributePaths = $photos['attribute_paths'] ?? [];
        $extraPaths     = $photos['extra_paths']     ?? [];
        $coverKey       = $photos['cover_key']       ?? null;

        $now       = now();
        $sortOrder = 0;
        $rows      = [];

        foreach ($attributePaths as $attributeId => $paths) {
            foreach ($paths as $i => $path) {
                $key = "attribute_images.{$attributeId}.{$i}";
                $rows[] = [
                    'place_id' => $place->id,
                    'place_attribute_id' => (int) $attributeId,
                    'path' => (string) $path,
                    'is_cover' => $key === $coverKey,
                    'sort_order' => $sortOrder++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($extraPaths as $i => $path) {
            $key = "extra_images.{$i}";
            $rows[] = [
                'place_id' => $place->id,
                'place_attribute_id' => null,
                'path' => (string) $path,
                'is_cover' => $key === $coverKey,
                'sort_order' => $sortOrder++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            PlacePhoto::insert($rows);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Place $place, array $data): Place
    {
        $place->update($data);

        return $place->refresh();
    }

    public function setReviewStatus(Place $place, PlaceReviewStatus $status): Place
    {
        $place->update(['review_status' => $status->value]);

        return $place->refresh();
    }

    public function delete(Place $place): void
    {
        $place->delete();
    }
}
