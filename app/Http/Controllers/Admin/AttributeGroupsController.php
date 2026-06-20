<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeGroupRequest;
use App\Http\Requests\Admin\UpdateAttributeGroupRequest;
use App\Models\AttributeGroup;
use App\Services\Place\AttributeGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Groups are created/edited/deleted inline (JSON) from the merged attributes
 * page — there's no standalone group screen anymore.
 */
class AttributeGroupsController extends Controller
{
    public function __construct(private readonly AttributeGroupService $service) {}

    public function store(StoreAttributeGroupRequest $request): RedirectResponse|JsonResponse
    {
        $group = $this->service->create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['group' => $this->serialize($group)], Response::HTTP_CREATED);
        }

        return redirect()->route('admin.attributes.index')
            ->with('status', __('Group ":name" created.', ['name' => $group->name_en]));
    }

    public function update(UpdateAttributeGroupRequest $request, AttributeGroup $attributeGroup): RedirectResponse|JsonResponse
    {
        $group = $this->service->update($attributeGroup, $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['group' => $this->serialize($group)]);
        }

        return redirect()->route('admin.attributes.index')
            ->with('status', __('Group ":name" updated.', ['name' => $group->name_en]));
    }

    public function destroy(Request $request, AttributeGroup $attributeGroup): RedirectResponse|JsonResponse
    {
        $name = $attributeGroup->name_en;
        $this->service->delete($attributeGroup);

        if ($request->wantsJson()) {
            return response()->json(status: Response::HTTP_NO_CONTENT);
        }

        return redirect()->route('admin.attributes.index')
            ->with('status', __('Group ":name" deleted.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AttributeGroup $group): array
    {
        return [
            'id' => $group->id,
            'name_ar' => $group->name_ar,
            'name_en' => $group->name_en,
            'sort_order' => (int) $group->sort_order,
            'attributes' => [],
        ];
    }
}
