<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSettingRequest;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\Place\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingService $service) {}

    public function index(): View
    {
        return view('admin.settings.index', ['settings' => $this->service->paginate()]);
    }

    public function store(StoreSettingRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('admin.settings.index')->with('status', __('Setting created.'));
    }

    public function edit(Setting $setting): View
    {
        return view('admin.settings.edit', compact('setting'));
    }

    public function update(UpdateSettingRequest $request, Setting $setting): RedirectResponse
    {
        $this->service->update($setting, $request->validated());

        return redirect()->route('admin.settings.index')->with('status', __('Setting updated.'));
    }

    public function destroy(Setting $setting): RedirectResponse
    {
        $this->service->delete($setting);

        return redirect()->route('admin.settings.index')->with('status', __('Setting deleted.'));
    }
}
