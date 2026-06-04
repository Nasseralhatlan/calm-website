<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AttributeService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Attribute::query()
            ->with('group')
            ->orderBy('name_en')
            ->paginate($perPage);
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

        return $data;
    }
}
