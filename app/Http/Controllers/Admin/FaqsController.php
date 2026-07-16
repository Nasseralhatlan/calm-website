<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Http\Requests\Admin\UpdateFaqRequest;
use App\Models\Faq;
use App\Services\Content\FaqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FaqsController extends Controller
{
    public function __construct(private readonly FaqService $service) {}

    public function index(): View
    {
        return view('admin.faqs.index', ['faqs' => $this->service->grouped()]);
    }

    public function create(): View
    {
        return view('admin.faqs.create', ['faq' => new Faq]);
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', __('FAQ created.'));
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.edit', ['faq' => $faq]);
    }

    public function update(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->service->update($faq, $request->validated());

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', __('FAQ updated.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->service->delete($faq);

        return redirect()
            ->route('admin.faqs.index')
            ->with('status', __('FAQ deleted.'));
    }
}
