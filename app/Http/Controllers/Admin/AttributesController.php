<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Services\Place\AttributeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttributesController extends Controller
{
    public function __construct(private readonly AttributeService $service) {}

    public function index(): View
    {
        return view('admin.attributes.index', ['attributes' => $this->service->paginate()]);
    }

    public function create(): View
    {
        return view('admin.attributes.create', [
            'attribute' => new Attribute,
            'groups' => AttributeGroup::orderBy('name_en')->get(),
        ]);
    }

    public function store(StoreAttributeRequest $request): RedirectResponse
    {
        $attribute = $this->service->create($request->validated());

        return redirect()
            ->route('admin.attributes.index')
            ->with('status', __('Attribute ":name" created.', ['name' => $attribute->name_en]));
    }

    public function edit(Attribute $attribute): View
    {
        return view('admin.attributes.edit', [
            'attribute' => $attribute,
            'groups' => AttributeGroup::orderBy('name_en')->get(),
        ]);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        $this->service->update($attribute, $request->validated());

        return redirect()
            ->route('admin.attributes.index')
            ->with('status', __('Attribute ":name" updated.', ['name' => $attribute->name_en]));
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $name = $attribute->name_en;
        $this->service->delete($attribute);

        return redirect()
            ->route('admin.attributes.index')
            ->with('status', __('Attribute ":name" deleted.', ['name' => $name]));
    }
}
