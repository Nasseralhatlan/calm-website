<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Attribute;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceAttribute;
use App\Models\PlacePhoto;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PlaceService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function paginate(?int $perPage = null, ?string $search = null): LengthAwarePaginator
    {
        return Place::query()
            ->with(['host', 'type', 'cityArea.city', 'coverPhoto'])
            ->withCount(['photos', 'attributeValues'])
            ->when($search, fn ($q, string $term) => $q->where(function ($q) use ($term): void {
                // Search is exact-match on the place uuid or LIKE on the host
                // phone. Phone variants the admin might paste are normalized:
                // leading "+", "966" prefix, and inner spaces all stripped.
                $phone = preg_replace('/\D+/', '', $term);
                $phone = preg_replace('/^966/', '', (string) $phone);

                $q->where('id', $term)
                    ->orWhereHas('host', fn ($h) => $h->where('phone', 'like', '%'.$phone.'%'));
            }))
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    /**
     * Everything the admin places index needs: paginated list, status-count
     * cards, and the next-up review target for the "Start review" button.
     * Combines what would otherwise be three service calls so the controller
     * stays at one.
     *
     * @return array{places: LengthAwarePaginator<int, Place>, counts: array<string,int>, nextReview: ?Place}
     */
    public function indexData(?string $search = null, ?int $perPage = null): array
    {
        return [
            'places' => $this->paginate($perPage, $search),
            'counts' => $this->statusCounts(),
            'nextReview' => $this->nextPendingReview(),
        ];
    }

    /**
     * Counts grouped by review_status and status for the admin stats cards.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return [
            'total' => Place::count(),
            'draft' => Place::query()->where('review_status', PlaceReviewStatus::Draft->value)->count(),
            'pending_review' => Place::query()->where('review_status', PlaceReviewStatus::PendingReview->value)->count(),
            'approved' => Place::query()->where('review_status', PlaceReviewStatus::Approved->value)->count(),
            'rejected' => Place::query()->where('review_status', PlaceReviewStatus::Rejected->value)->count(),
            'active' => Place::query()->where('status', PlaceStatus::Active->value)->count(),
            'inactive' => Place::query()->where('status', PlaceStatus::Inactive->value)->count(),
        ];
    }

    /**
     * The next place an admin should review. Used by the "Start review" button
     * — oldest pending row first so older submissions don't starve.
     */
    public function nextPendingReview(?Place $skip = null): ?Place
    {
        return Place::query()
            ->where('review_status', PlaceReviewStatus::PendingReview->value)
            ->when($skip, fn ($q, Place $s) => $q->whereKeyNot($s->id))
            ->oldest('updated_at')
            ->first();
    }

    /**
     * Places that belong to a specific host (the host's own list), paginated so
     * a host with many listings doesn't load them all on one page.
     */
    public function forHost(User $host, ?int $perPage = null): LengthAwarePaginator
    {
        return Place::query()
            ->where('host_user_id', $host->id)
            ->with(['type', 'cityArea.city', 'coverPhoto'])
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
    }

    /**
     * Confirm a host's place — the final submit at the end of the wizard.
     * If a $draftId is provided and matches one of this host's Draft places,
     * we promote that record to PendingReview. Otherwise we create fresh.
     * Attributes + photos are synced in the same transaction so the place
     * row, its facility choices, and its uploaded photo paths land together.
     *
     * @param  array<string, mixed>  $data  Place columns.
     * @param  list<array<string, mixed>>  $attributes  [{attribute_id, value, description}, ...]
     * @param  array<string, mixed>  $photos  ['attribute_paths' => [attrId => [path,...]], 'extra_paths' => [path,...], 'featured' => [marker,...]]
     */
    public function createForHost(
        User $host,
        array $data,
        ?string $draftId = null,
        array $attributes = [],
        array $photos = [],
    ): Place {
        $place = DB::transaction(function () use ($host, $data, $draftId, $attributes, $photos): Place {
            $place = $this->upsertPlace($host, $data, $draftId, PlaceReviewStatus::PendingReview);
            $this->syncAttributes($place, $attributes);
            $this->syncPhotos($place, $photos);

            return $place->refresh();
        });

        // Notify the host their place is now in review (fired after commit).
        $this->notifications->placeSubmitted($place);

        return $place;
    }

    /**
     * Upsert the host's in-progress draft. Called every time the host advances
     * a step so the record exists from step 1 onward. Attributes + photos
     * upsert too — that's what lets us re-hydrate the wizard if the host exits
     * and resumes from the listing.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $attributes
     * @param  array<string, mixed>  $photos
     */
    public function saveDraftForHost(
        User $host,
        array $data,
        ?string $draftId = null,
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
    private function upsertPlace(User $host, array $data, ?string $draftId, PlaceReviewStatus $reviewStatus): Place
    {
        $existing = null;
        if ($draftId !== null) {
            // Allow resuming Drafts AND Rejected places — Rejected rows are
            // re-editable so the host can fix the admin's feedback and
            // resubmit. The submit (createForHost → PendingReview here) also
            // clears the previous rejection_reason for the same reason.
            $existing = Place::query()
                ->where('id', $draftId)
                ->where('host_user_id', $host->id)
                ->whereIn('review_status', [
                    PlaceReviewStatus::Draft->value,
                    PlaceReviewStatus::Rejected->value,
                ])
                ->first();
        }

        $payload = [
            ...$data,
            'host_user_id' => $host->id,
            'status' => PlaceStatus::Inactive->value,
            'review_status' => $reviewStatus->value,
        ];

        // Final submit clears the previous rejection feedback — once the host
        // has fixed it and resubmitted, the old reason no longer applies.
        if ($reviewStatus === PlaceReviewStatus::PendingReview) {
            $payload['rejection_reason'] = null;
        }

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
        // PlaceAttribute uses HasUuids — bulk upsert() bypasses Eloquent's
        // `creating` event, so we have to mint the id ourselves for any new
        // rows (and harmlessly re-supply one for existing rows; the unique
        // (place_id, attribute_id) index drives the conflict path so the id
        // on a hit row is overwritten via the `update` list anyway).
        $stub = new PlaceAttribute;
        $rows = array_map(fn (array $a): array => [
            'id' => $stub->newUniqueId(),
            'place_id' => $place->id,
            'attribute_id' => (string) $a['attribute_id'],
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
     * by their composite "key": `attribute_images.<attrId>.<i>` for per-attribute
     * uploads, `extra_images.<i>` for general photos.
     *
     * Order: `sort_order` increments group-by-group then within (the gallery
     * order — attribute groups arrive in the host's chosen SECTION order, with
     * the general group last). `featured` is the host's ordered list of keys to
     * "show outside" (the place-page showcase, max 10); each photo's
     * `featured_order` is its position in that list (first = cover), or null.
     *
     * Pass `['attribute_paths' => [], 'extra_paths' => []]` (or an empty array)
     * to leave existing photos untouched — used for early draft saves before
     * the host reaches the photos step.
     *
     * @param  array<string, mixed>  $photos
     */
    private function syncPhotos(Place $place, array $photos): void
    {
        if ($photos === [] || (! isset($photos['attribute_paths']) && ! isset($photos['extra_paths']))) {
            return;
        }

        $place->photos()->delete();

        $attributePaths = $photos['attribute_paths'] ?? [];
        $extraPaths = $photos['extra_paths'] ?? [];
        /** @var list<string> $featured Ordered keys shown outside (first = cover). */
        $featured = array_values($photos['featured'] ?? []);

        $featuredOrder = static function (string $key) use ($featured): ?int {
            $pos = array_search($key, $featured, true);

            return $pos === false ? null : (int) $pos;
        };

        $now = now();
        $sortOrder = 0;
        $rows = [];
        // PlacePhoto uses HasUuids — but bulk insert() bypasses Eloquent's
        // `creating` event, so we must mint the primary key ourselves here.
        // Same trick we use in seeders to avoid the NOT NULL id error.
        $stub = new PlacePhoto;

        foreach ($attributePaths as $attributeId => $paths) {
            foreach ($paths as $i => $path) {
                $key = "attribute_images.{$attributeId}.{$i}";
                $rows[] = [
                    'id' => $stub->newUniqueId(),
                    'place_id' => $place->id,
                    'place_attribute_id' => (string) $attributeId,
                    'path' => (string) $path,
                    'sort_order' => $sortOrder++,
                    'featured_order' => $featuredOrder($key),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($extraPaths as $i => $path) {
            $key = "extra_images.{$i}";
            $rows[] = [
                'id' => $stub->newUniqueId(),
                'place_id' => $place->id,
                'place_attribute_id' => null,
                'path' => (string) $path,
                'sort_order' => $sortOrder++,
                'featured_order' => $featuredOrder($key),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            PlacePhoto::insert($rows);
        }
    }

    /**
     * Admin update entrypoint. The optional `lists` key carries the place's
     * curated-list membership (multi-select from the edit form); when present
     * we diff against current membership instead of `sync()`-ing so existing
     * pivot sort_orders survive for the lists the place is staying in. New
     * attachments land at the destination list's tail.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Place $place, array $data): Place
    {
        $lists = $data['lists'] ?? null;
        unset($data['lists']);

        $place->update($data);

        if (is_array($lists)) {
            $this->syncLists($place, $lists);
        }

        return $place->refresh();
    }

    /**
     * Admin edit via the wizard: persist columns (incl. the admin-set status /
     * review_status / rejection_reason), amenities, photos and curated-list
     * membership in one transaction. Unlike the host edit this does NOT force
     * PendingReview — admins keep whatever status they chose on the form.
     *
     * @param  array<string, mixed>  $data  Place columns incl. status/review.
     * @param  list<array<string, mixed>>  $attributes
     * @param  array<string, mixed>  $photos
     * @param  list<string>  $lists
     */
    public function updateByAdmin(Place $place, array $data, array $attributes, array $photos, array $lists): Place
    {
        return DB::transaction(function () use ($place, $data, $attributes, $photos, $lists): Place {
            $place->update($data);

            // An edit carries the full desired state — empty amenities means
            // "remove them all" rather than "leave untouched".
            if ($attributes === []) {
                $place->attributeValues()->delete();
            } else {
                $this->syncAttributes($place, $attributes);
            }

            $this->syncPhotos($place, $photos);
            $this->syncLists($place, $lists);

            return $place->refresh();
        });
    }

    /**
     * @param  list<string>  $listIds
     */
    private function syncLists(Place $place, array $listIds): void
    {
        $listIds = array_values(array_unique($listIds));
        $currentIds = $place->lists()->pluck('place_lists.id')->all();

        $toAdd = array_diff($listIds, $currentIds);
        $toRemove = array_diff($currentIds, $listIds);

        if ($toRemove !== []) {
            $place->lists()->detach($toRemove);
        }

        foreach ($toAdd as $listId) {
            $nextSort = (int) (DB::table('place_list_items')
                ->where('place_list_id', $listId)
                ->max('sort_order') ?? -1) + 1;
            $place->lists()->attach($listId, ['sort_order' => $nextSort]);
        }
    }

    /**
     * Host self-service edit of an existing listing. Persists the changed
     * columns, amenities, and photos, then — because the listing content
     * changed — pushes the place back to PendingReview and offline (Inactive)
     * until an admin re-approves it. Any stale rejection feedback is cleared,
     * since the host has effectively just resubmitted.
     *
     * Unlike the wizard's draft auto-save, an edit always carries the host's
     * full intended state, so empty amenities/photos mean "clear them" — not
     * "leave untouched". Everything lands in one transaction.
     *
     * @param  array<string, mixed>  $data  Validated place-detail columns.
     * @param  list<array<string, mixed>>  $attributes  [{attribute_id, value, description}, ...]
     * @param  array<string, mixed>  $photos  ['attribute_paths' => ..., 'extra_paths' => ..., 'featured' => [...]]
     */
    public function updateDetailsForHost(Place $place, array $data, array $attributes = [], array $photos = []): Place
    {
        $place = DB::transaction(function () use ($place, $data, $attributes, $photos): Place {
            $place->update([
                ...$data,
                'status' => PlaceStatus::Inactive->value,
                'review_status' => PlaceReviewStatus::PendingReview->value,
                'rejection_reason' => null,
            ]);

            // An edit is the full desired state: an empty set means the host
            // removed every amenity, so wipe rather than no-op (syncAttributes
            // early-returns on []). syncPhotos already replaces wholesale.
            if ($attributes === []) {
                $place->attributeValues()->delete();
            } else {
                $this->syncAttributes($place, $attributes);
            }

            $this->syncPhotos($place, $photos);

            return $place->refresh();
        });

        // Editing resubmits for review — notify the host (fired after commit).
        $this->notifications->placeSubmitted($place);

        return $place;
    }

    /**
     * The host's own places for the host app's "My listings" — ALL of them
     * regardless of status (so drafts/pending/rejected show too), newest first,
     * paginated, with the card relations + likes/reviews/bookings counts.
     *
     * @return LengthAwarePaginator<int, Place>
     */
    public function listingsForHost(User $host, ?int $perPage = null): LengthAwarePaginator
    {
        return Place::query()
            ->where('host_user_id', $host->id)
            ->with(['type', 'cityArea.city', 'coverPhoto'])
            ->withCount(['likes', 'publishedReviews', 'bookings'])
            ->withAvg('publishedReviews', 'rate')
            ->latest()
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();
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

    /**
     * Shared eager-load + aggregate set for any list-of-places endpoint that
     * feeds PlaceResource. Loads what the canonical card needs (type, area+city,
     * cover photo) and the aggregates the card displays (likes_count,
     * reviews_count, reviews_avg_rate). When a $viewer is passed, also flags
     * each place as liked/unliked via an exists subquery (the `liked_by_me`
     * column the resource reads).
     *
     * $query is loosely typed because eager-load closures (`with(['x' => fn ($q)`)
     * receive a Relation, not a Builder — both forward method calls to the
     * underlying query.
     */
    public function eagerHomeFields(mixed $query, ?User $viewer = null): mixed
    {
        $query->with(['type', 'cityArea.city', 'coverPhoto', 'photos'])
            ->withCount('likes')
            ->withCount('publishedReviews')
            ->withAvg('publishedReviews', 'rate');

        if ($viewer !== null) {
            $query->selectRaw('places.*, EXISTS(SELECT 1 FROM place_likes WHERE place_likes.place_id = places.id AND place_likes.user_id = ?) AS liked_by_me', [$viewer->id]);
        }

        return $query;
    }

    /**
     * Hydrate a single visible place for the mobile detail screen — all the
     * canonical PlaceResource fields PLUS the relations the detail page
     * specifically needs (attributes with their definitions + groups, the
     * 10 most recent reviews, the host's public profile).
     *
     * Returns null when the place isn't publicly visible (draft, pending,
     * rejected, or inactive) so the controller can 404 cleanly.
     */
    public function findForApi(Place $place, ?User $viewer = null): ?Place
    {
        if (
            $place->status !== PlaceStatus::Active
            || $place->review_status !== PlaceReviewStatus::Approved
        ) {
            return null;
        }

        $place->load([
            'host',
            'type',
            'cityArea.city',
            'photos',
            'coverPhoto',
            'attributeValues.attribute.group',
            'publishedReviews' => fn ($q) => $q->with('guest')->latest()->limit(10),
        ]);

        // Order the place's amenities by the admin-controlled attribute sort so
        // the API matches the add-place wizard and the web place page exactly.
        $place->setRelation(
            'attributeValues',
            $place->attributeValues
                ->sortBy(fn ($pa) => [$pa->attribute?->sort_order ?? 0, $pa->attribute?->name_en ?? ''])
                ->values(),
        );

        $place->loadCount('likes');
        $place->loadCount('publishedReviews');
        $place->loadAvg('publishedReviews', 'rate');

        if ($viewer !== null) {
            $place->setAttribute(
                'liked_by_me',
                $place->likedByUsers()->where('users.id', $viewer->id)->exists(),
            );
        }

        return $place;
    }

    /**
     * Top places by like count, optionally personalized with `is_liked` for
     * the authed viewer. Only Active + Approved places — never surfaces
     * drafts or pending submissions.
     *
     * @return Collection<int, Place>
     */
    public function mostLiked(?User $viewer = null, int $limit = 20): Collection
    {
        $query = Place::query()->visible();
        $this->eagerHomeFields($query, $viewer);

        return $query
            ->orderByDesc('likes_count')
            ->orderByDesc('published_reviews_avg_rate')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The core place search. Always Active+Approved, scoped to a required city,
     * then narrowed by every supplied (optional) filter — each one ANDs onto the
     * query. Amenities match ALL; an optional check-in/check-out keeps only places
     * with no host block and no active booking overlapping that range. Returns
     * the canonical place card (with counts + viewer-aware is_liked), paginated.
     *
     * @param  array<string, mixed>  $f  Validated filters (see SearchPlacesRequest).
     * @return LengthAwarePaginator<int, Place>
     */
    public function search(array $f, ?User $viewer = null, ?int $perPage = null): LengthAwarePaginator
    {
        $query = Place::query()->visible();
        $this->eagerHomeFields($query, $viewer);

        $query
            ->whereHas('cityArea', fn ($q) => $q->where('city_id', $f['city_id']))
            ->when($f['city_area_id'] ?? null, fn ($q, $v) => $q->where('city_area_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")))
            ->when($f['place_type_ids'] ?? null, fn ($q, $v) => $q->whereIn('place_type_id', $v))
            ->when($f['price_min'] ?? null, fn ($q, $v) => $q->where('price', '>=', $v))
            ->when($f['price_max'] ?? null, fn ($q, $v) => $q->where('price', '<=', $v))
            ->when($f['guests'] ?? null, fn ($q, $v) => $q->where('max_guests', '>=', $v))
            // Has ALL of the selected amenities: count of matching pivot rows
            // equals the number requested ((place_id, attribute_id) is unique).
            ->when($f['amenities'] ?? null, fn ($q, $v) => $q->whereHas(
                'attributeValues',
                fn ($a) => $a->whereIn('attribute_id', $v),
                '>=',
                count($v),
            ))
            // Free for the requested stay: no host block and no active-hold
            // booking overlapping [check_in, check_out].
            ->when(! empty($f['check_in']), fn ($q) => $q
                ->whereDoesntHave('blockings', fn ($b) => $b
                    ->where('start_date', '<=', $f['check_out'])
                    ->where('end_date', '>=', $f['check_in']))
                ->whereDoesntHave('bookings', fn ($b) => $b
                    ->activeHold()
                    ->where('start_date', '<=', $f['check_out'])
                    ->where('end_date', '>=', $f['check_in'])));

        match ($f['sort'] ?? 'most_liked') {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query
                ->orderByDesc('likes_count')
                ->orderByDesc('published_reviews_avg_rate')
                ->orderByDesc('created_at'),
        };

        return $query->paginate($perPage ?? config('pagination.per_page'))->withQueryString();
    }

    /**
     * Available filter options ("facets") for a city's search/filters page —
     * computed from the visible places that actually exist there, so the UI only
     * shows filters that will return results. Each option carries a count.
     *
     * @return array{city_id: string, currency: string, price: array{min: int, max: int}, guests: array{min: int, max: int}, areas: list<array<string, mixed>>, place_types: list<array<string, mixed>>, amenities: list<array<string, mixed>>}
     */
    public function filterFacets(string $cityId): array
    {
        $inCity = fn ($q) => $q->visible()->whereHas('cityArea', fn ($c) => $c->where('city_id', $cityId));

        $range = Place::query()
            ->visible()
            ->whereHas('cityArea', fn ($c) => $c->where('city_id', $cityId))
            ->selectRaw('MIN(price) as price_min, MAX(price) as price_max, MIN(max_guests) as guests_min, MAX(max_guests) as guests_max')
            ->first();

        // Areas in the city that have at least one visible place.
        $areas = CityArea::query()
            ->where('city_id', $cityId)
            ->withCount(['places as places_count' => fn ($q) => $q->visible()])
            ->orderBy('name_en')
            ->get()
            ->filter(fn (CityArea $a) => $a->places_count > 0)
            ->map(fn (CityArea $a) => [
                'id' => $a->id,
                'name_en' => $a->name_en,
                'name_ar' => $a->name_ar,
                'places_count' => (int) $a->places_count,
            ])
            ->values()
            ->all();

        // Place types in use in the city.
        $types = PlaceType::query()
            ->withCount(['places as places_count' => $inCity])
            ->orderBy('name_en')
            ->get()
            ->filter(fn (PlaceType $t) => $t->places_count > 0)
            ->map(fn (PlaceType $t) => [
                'id' => $t->id,
                'name_en' => $t->name_en,
                'name_ar' => $t->name_ar,
                'icon' => $t->icon,
                'places_count' => (int) $t->places_count,
            ])
            ->values()
            ->all();

        // Amenities in use in the city, grouped by their attribute group.
        $amenities = Attribute::query()
            ->with('group')
            ->withCount(['placeValues as places_count' => fn ($q) => $q->whereHas('place', $inCity)])
            ->ordered()
            ->get()
            ->filter(fn (Attribute $a) => $a->places_count > 0)
            ->groupBy(fn (Attribute $a) => $a->group_id)
            ->map(fn ($items) => [
                'group' => [
                    'id' => $items->first()->group?->id,
                    'name_en' => $items->first()->group?->name_en,
                    'name_ar' => $items->first()->group?->name_ar,
                ],
                'items' => $items->map(fn (Attribute $a) => [
                    'id' => $a->id,
                    'name_en' => $a->name_en,
                    'name_ar' => $a->name_ar,
                    'icon' => $a->icon,
                    'is_highlighted' => (bool) $a->is_highlighted,
                    'places_count' => (int) $a->places_count,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'city_id' => $cityId,
            'currency' => 'SAR',
            'price' => ['min' => (int) ($range->price_min ?? 0), 'max' => (int) ($range->price_max ?? 0)],
            'guests' => ['min' => (int) ($range->guests_min ?? 0), 'max' => (int) ($range->guests_max ?? 0)],
            'areas' => $areas,
            'place_types' => $types,
            'amenities' => $amenities,
        ];
    }

    /**
     * The viewer's own liked places — the "My favorites" feed. Visible places
     * only (a listing that later went private just drops out), ordered by when
     * they were liked (newest first), paginated. Every row is liked by
     * definition, so `liked_by_me` is set true directly (no exists subquery —
     * which would also clobber the BelongsToMany pivot select).
     *
     * @return LengthAwarePaginator<int, Place>
     */
    public function likedByUser(User $viewer, ?int $perPage = null): LengthAwarePaginator
    {
        $query = $viewer->likedPlaces()->visible();
        $this->eagerHomeFields($query);

        $paginator = $query
            ->orderByPivot('created_at', 'desc')
            ->paginate($perPage ?? config('pagination.per_page'))
            ->withQueryString();

        // Every row is liked by definition → flag it for PlaceResource.
        foreach ($paginator->items() as $place) {
            $place->setAttribute('liked_by_me', true);
        }

        return $paginator;
    }
}
