<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AttributePhotoRule;
use App\Enums\AttributeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderAttributesRequest;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Services\Place\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AttributesController extends Controller
{
    public function __construct(private readonly AttributeService $service) {}

    /**
     * The merged management page: every group with its attributes, drag-sorted,
     * with inline create/edit/delete/highlight (Alpine + JSON endpoints below).
     */
    public function index(): View
    {
        $groups = $this->service->grouped();

        return view('admin.attributes.index', [
            'init' => [
                'groups' => $groups->map(fn ($g): array => [
                    'id' => $g->id,
                    'name_ar' => $g->name_ar,
                    'name_en' => $g->name_en,
                    'is_standalone' => (bool) $g->is_standalone,
                    'attributes' => $g->attributes->map(fn (Attribute $a): array => $this->serialize($a))->values(),
                ])->values(),
                'typeOptions' => array_map(fn (AttributeType $c): string => $c->value, AttributeType::cases()),
                'photoRuleOptions' => array_map(fn (AttributePhotoRule $c): string => $c->value, AttributePhotoRule::cases()),
            ],
        ]);
    }

    public function reorder(ReorderAttributesRequest $request): RedirectResponse|JsonResponse
    {
        $this->service->applyOrder($request->orderedGroups());

        if ($request->wantsJson()) {
            return response()->json(status: Response::HTTP_NO_CONTENT);
        }

        return redirect()->route('admin.attributes.index')->with('status', __('Order saved.'));
    }

    /** Instant "mark important" toggle from a chip's star. */
    public function toggleHighlight(Attribute $attribute): JsonResponse
    {
        return response()->json(['is_highlighted' => $this->service->toggleHighlight($attribute)]);
    }

    public function store(StoreAttributeRequest $request): RedirectResponse|JsonResponse
    {
        $attribute = $this->service->create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['attribute' => $this->serialize($attribute)], Response::HTTP_CREATED);
        }

        return redirect()->route('admin.attributes.index')
            ->with('status', __('Attribute ":name" created.', ['name' => $attribute->name_en]));
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): RedirectResponse|JsonResponse
    {
        $attribute = $this->service->update($attribute, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['attribute' => $this->serialize($attribute)]);
        }

        return redirect()->route('admin.attributes.index')
            ->with('status', __('Attribute ":name" updated.', ['name' => $attribute->name_en]));
    }

    public function destroy(Request $request, Attribute $attribute): RedirectResponse|JsonResponse
    {
        $name = $attribute->name_en;
        $this->service->delete($attribute);

        if ($request->wantsJson()) {
            return response()->json(status: Response::HTTP_NO_CONTENT);
        }

        return redirect()->route('admin.attributes.index')
            ->with('status', __('Attribute ":name" deleted.', ['name' => $name]));
    }

    /**
     * Shape an attribute for the merged page's Alpine state (and the JSON
     * create/update responses, so the client can drop it straight into a chip).
     *
     * @return array<string, mixed>
     */
    private function serialize(Attribute $attribute): array
    {
        return [
            'id' => $attribute->id,
            'group_id' => $attribute->group_id,
            'name_ar' => $attribute->name_ar,
            'name_en' => $attribute->name_en,
            'question_ar' => $attribute->question_ar,
            'question_en' => $attribute->question_en,
            'icon' => $attribute->icon,
            'type' => $attribute->type->value,
            'photo_rule' => $attribute->photo_rule->value,
            'options' => $attribute->options ?? [],
            'is_highlighted' => (bool) $attribute->is_highlighted,
            'sort_order' => (int) $attribute->sort_order,
        ];
    }
}
