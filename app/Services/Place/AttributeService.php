<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class AttributeService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return Attribute::query()
            ->with('group')
            ->ordered()
            ->paginate($perPage ?? config('pagination.per_page'));
    }

    /**
     * All groups (in order) with their attributes (in order) — powers the
     * drag-and-drop sort page.
     *
     * @return Collection<int, AttributeGroup>
     */
    public function grouped(): Collection
    {
        return AttributeGroup::query()
            // placeValues count powers the delete confirm's blast-radius warning.
            ->with(['attributes' => fn ($q) => $q->ordered()->withCount('placeValues')])
            ->ordered()
            ->get();
    }

    /**
     * Persist a new drag-and-drop order. Groups are numbered by their position
     * in $groups; attributes get a single running index across all groups (in
     * group order) so the flat attribute order matches the grouped order.
     *
     * @param  list<array{id: string, attributes: list<string>}>  $groups
     */
    public function applyOrder(array $groups): void
    {
        DB::transaction(function () use ($groups): void {
            $attributeCursor = 0;

            foreach ($groups as $groupIndex => $group) {
                AttributeGroup::query()->whereKey($group['id'])->update(['sort_order' => $groupIndex]);

                foreach ($group['attributes'] ?? [] as $attributeId) {
                    Attribute::query()
                        ->whereKey($attributeId)
                        ->where('group_id', $group['id'])
                        ->update(['sort_order' => $attributeCursor++]);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Attribute
    {
        return Attribute::query()->create($this->normalize($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Attribute $attribute, array $data): Attribute
    {
        $attribute->update($this->normalize($data));

        return $attribute->refresh();
    }

    public function delete(Attribute $attribute): void
    {
        $attribute->delete();
    }

    /** Flip the "highlighted" flag (the merged page's instant star toggle). */
    public function toggleHighlight(Attribute $attribute): bool
    {
        $attribute->update(['is_highlighted' => ! $attribute->is_highlighted]);

        return $attribute->is_highlighted;
    }

    /**
     * Drop the `options` array unless the type actually uses it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $type = AttributeType::tryFrom((string) ($data['type'] ?? ''));

        if (! $type?->hasOptions()) {
            $data['options'] = null;
        }

        // A blank "Sort" field arrives as null — keep the column's 0 default.
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }

        return $data;
    }
}
