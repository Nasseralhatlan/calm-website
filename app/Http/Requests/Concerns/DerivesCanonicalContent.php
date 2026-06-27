<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Bilingual place content: the form posts `title_ar/title_en`,
 * `description_ar/_en`, `rules_ar/_en`. The single legacy columns
 * (`title`/`description`/`rules`) stay populated as the canonical value
 * (`*_ar ?: *_en`) so search, snapshots and non-localized screens keep working.
 */
trait DerivesCanonicalContent
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withCanonicalContent(array $data): array
    {
        foreach (['title', 'description', 'rules'] as $field) {
            $ar = $data["{$field}_ar"] ?? null;
            $en = $data["{$field}_en"] ?? null;

            if ($ar !== null || $en !== null) {
                $data[$field] = (is_string($ar) && $ar !== '') ? $ar
                    : ((is_string($en) && $en !== '') ? $en : null);
            }
        }

        return $data;
    }
}
