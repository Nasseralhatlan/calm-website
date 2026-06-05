<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeGroupRequest;
use App\Http\Requests\Admin\UpdateAttributeGroupRequest;
use App\Models\AttributeGroup;
use App\Services\Place\AttributeGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttributeGroupsController extends Controller
{
    public function __construct(private readonly AttributeGroupService $service) {}

    public function index(): View
    {
        return view('admin.attribute-groups.index', ['attributeGroups' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.attribute-groups.create', ['attributeGroup' => new AttributeGroup]);
    }

    public function store(StoreAttributeGroupRequest $request): RedirectResponse
    {
        $group = $this->service->create($request->validated());

        return redirect()
            ->route('admin.attribute-groups.index')
            ->with('status', __('Group ":name" created.', ['name' => $group->name_en]));
    }

    public function edit(AttributeGroup $attributeGroup): View
    {
        return view('admin.attribute-groups.edit', compact('attributeGroup'));
    }

    public function update(UpdateAttributeGroupRequest $request, AttributeGroup $attributeGroup): RedirectResponse
    {
        $this->service->update($attributeGroup, $request->validated());

        return redirect()
            ->route('admin.attribute-groups.index')
            ->with('status', __('Group ":name" updated.', ['name' => $attributeGroup->name_en]));
    }

    public function destroy(AttributeGroup $attributeGroup): RedirectResponse
    {
        $name = $attributeGroup->name_en;
        $this->service->delete($attributeGroup);

        return redirect()
            ->route('admin.attribute-groups.index')
            ->with('status', __('Group ":name" deleted.', ['name' => $name]));
    }
}
