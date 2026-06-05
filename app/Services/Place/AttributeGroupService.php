<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\AttributeGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AttributeGroupService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return AttributeGroup::query()
            ->withCount('attributes')
            ->orderBy('name_en')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AttributeGroup
    {
        return AttributeGroup::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AttributeGroup $group, array $data): AttributeGroup
    {
        $group->update($data);

        return $group->refresh();
    }

    public function delete(AttributeGroup $group): void
    {
        $group->delete();
    }
}
