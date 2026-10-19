<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromoCodeRequest;
use App\Http\Requests\Admin\UpdatePromoCodeRequest;
use App\Models\PromoCode;
use App\Services\Promo\PromoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PromoCodesController extends Controller
{
    public function __construct(private readonly PromoCodeService $service) {}

    public function index(): View
    {
        return view('admin.promo-codes.index', [
            'codes' => $this->service->adminList(),
            'commissionRate' => $this->service->currentCommissionRate(),
        ]);
    }

    public function create(): View
    {
        return view('admin.promo-codes.create', [
            'promo' => new PromoCode(['is_active' => true, 'max_uses_per_user' => 1]),
            'commissionRate' => $this->service->currentCommissionRate(),
        ]);
    }

    public function store(StorePromoCodeRequest $request): RedirectResponse
    {
        $promo = $this->service->create($request->validated());

        return $this->savedRedirect($promo, __('Promo code created.'));
    }

    public function edit(PromoCode $promoCode): View
    {
        return view('admin.promo-codes.edit', [
            'promo' => $promoCode,
            'commissionRate' => $this->service->currentCommissionRate(),
        ]);
    }

    public function update(UpdatePromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $promo = $this->service->update($promoCode, $request->validated());

        return $this->savedRedirect($promo, __('Promo code updated.'));
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        $deleted = $this->service->delete($promoCode);

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('status', $deleted
                ? __('Promo code deleted.')
                : __('Promo code has bookings, so it was deactivated instead of deleted.'));
    }

    /** Saved — and if the code eats past Calm's commission, say so again. */
    private function savedRedirect(PromoCode $promo, string $status): RedirectResponse
    {
        $check = $promo->commissionCheck($this->service->currentCommissionRate());

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('status', $status)
            ->with('promo_warning', $check['exceeds'] ? $promo->code : null);
    }
}
