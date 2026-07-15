<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\AttributePhotoRule;
use App\Models\Attribute;
use Illuminate\Validation\Validator;

/**
 * Server-side enforcement of per-amenity photo rules — the client UI hides
 * upload boxes for `none`-rule amenities and gates `required` ones, but the
 * SERVER is the source of truth:
 *
 *  - a selected `required`-rule amenity without a photo fails validation;
 *  - photos submitted under a `none`-rule amenity never count toward the
 *    5-image minimum (and are dropped at persist time by
 *    PlaceService::syncPhotos) — clients can't make users upload photos
 *    the amenity doesn't want.
 */
trait ValidatesAmenityPhotoRules
{
    private const MIN_PHOTOS = 5;

    protected function enforceAmenityPhotoRules(Validator $validator): void
    {
        $imagePaths = collect($this->input('attribute_image_paths', []));
        $selectedIds = collect($this->input('attributes', []))
            ->pluck('attribute_id')
            ->filter()
            ->map(fn ($id): string => (string) $id);

        // One lookup for every attribute referenced anywhere in the payload.
        $rules = Attribute::query()
            ->whereIn('id', $selectedIds->merge($imagePaths->keys())->unique()->values())
            ->pluck('photo_rule', 'id');

        // Required-rule amenities the host selected must carry ≥1 photo.
        foreach ($selectedIds as $attributeId) {
            $rule = $rules[$attributeId] ?? null;
            $count = collect($imagePaths->get($attributeId, []))->filter()->count();

            if ($rule === AttributePhotoRule::Required && $count === 0) {
                $validator->errors()->add(
                    "attribute_image_paths.{$attributeId}",
                    __('This amenity requires at least one photo.'),
                );
            }
        }

        // The 5-image minimum counts only photos that will actually persist:
        // none-rule sections are stripped, so they can't satisfy the minimum.
        $countable = $imagePaths
            ->reject(fn ($paths, $attributeId): bool => ($rules[(string) $attributeId] ?? null) === AttributePhotoRule::None)
            ->flatten()
            ->filter()
            ->count()
            + collect($this->input('extra_image_paths', []))->filter()->count();

        if ($countable < self::MIN_PHOTOS) {
            $validator->errors()->add('images', __('A place must have at least :min images.', ['min' => self::MIN_PHOTOS]));
        }
    }
}
