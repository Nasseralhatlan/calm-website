<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        // The sort page submits the whole arrangement as a single JSON string
        // in `payload` — decode it into a `groups` array for validation.
        $decoded = json_decode((string) $this->input('payload'), true);

        if (is_array($decoded)) {
            $this->merge(['groups' => $decoded]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'groups' => ['required', 'array'],
            'groups.*.id' => ['required', 'uuid', 'exists:attribute_groups,id'],
            'groups.*.attributes' => ['array'],
            'groups.*.attributes.*' => ['uuid', 'exists:attributes,id'],
        ];
    }

    /**
     * @return list<array{id: string, attributes: list<string>}>
     */
    public function orderedGroups(): array
    {
        return array_map(
            fn (array $group): array => [
                'id' => $group['id'],
                'attributes' => $group['attributes'] ?? [],
            ],
            $this->validated('groups'),
        );
    }
}
