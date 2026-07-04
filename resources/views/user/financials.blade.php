@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $sr = fn (int $minor) => number_format($minor / 100, 2);
    $pct = fn (float $r) => rtrim(rtrim(number_format($r, 2), '0'), '.');
@endphp

@section('title', $isRtl ? 'المالية' : 'Financials')
@section('heading', $isRtl ? 'المالية' : 'Financials')

@section('main')
    <div style="max-width: 760px; display: flex; flex-direction: column; gap: 16px;">

        {{-- ── Earnings summary ── --}}
        <div class="grid grid-cols-1 sm:grid-cols-3" style="gap: 14px;">
            <div class="bg-white" style="padding: 20px; border-radius: 20px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
                <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'إجمالي الأرباح' : 'Total earned' }}</div>
                <div class="font-bold text-[#222] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($earnings['total_minor']) }}</div>
                <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">{{ $earnings['bookings_count'] }} {{ $isRtl ? 'حجز · صافي بعد عمولة كالم' : 'booking(s) · net of Calm commission' }}</div>
            </div>
            <div class="bg-white" style="padding: 20px; border-radius: 20px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
                <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'تم تحويله لك' : 'Paid out' }}</div>
                <div class="font-bold text-[#10b981] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($earnings['paid_minor']) }}</div>
            </div>
            <div class="bg-white" style="padding: 20px; border-radius: 20px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
                <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'قيد التحويل' : 'Pending payout' }}</div>
                <div class="font-bold text-[#f59e0b] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($earnings['not_paid_minor']) }}</div>
                <div class="text-[12px] text-[#999] {{ $fa }}" style="margin-top: 2px;">{{ $isRtl ? 'يُحوَّل بعد انتهاء الإقامة' : 'Transferred after the stay ends' }}</div>
            </div>
        </div>

        {{-- Where the host's payouts are sent. --}}
        @include('partials._bank_account_form', ['user' => $user])

        {{-- ── Per-booking earnings ── --}}
        @if($bookings->isEmpty())
            @include('user._empty_state', [
                'icon' => '💳',
                'title' => $isRtl ? 'لا توجد حركات مالية بعد' : 'No transactions yet',
                'subtitle' => $isRtl ? 'ستظهر هنا أرباحك ومدفوعاتك عند وصول أول حجز.' : 'Your earnings and payouts will appear here once your first booking lands.',
            ])
        @else
            <div>
                <h2 class="font-bold text-[#222] text-[16px] {{ $fa }}" style="margin-bottom: 12px;">
                    {{ $isRtl ? 'الحركات' : 'Transactions' }}
                </h2>
                <div class="bg-white overflow-hidden" style="border-radius: 20px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
                    @foreach($bookings as $b)
                        @php $paid = $b->payout_status === 'paid'; @endphp
                        <a href="{{ route('user.bookings.show', $b) }}"
                           class="flex items-center justify-between border-t first:border-t-0 border-[#f0f0f0] hover:bg-[#fafafa] transition-colors"
                           style="padding: 14px 18px; gap: 12px;">
                            <span class="flex items-center min-w-0" style="gap: 12px;">
                                <span class="shrink-0 overflow-hidden bg-[#f3f4f6] flex items-center justify-center" style="width: 44px; height: 44px; border-radius: 12px;">
                                    @if($b->place?->coverPhoto?->url)
                                        <img src="{{ $b->place->coverPhoto->url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                    @else
                                        <span style="font-size: 20px;">{{ $b->place?->type?->icon ?: '🏠' }}</span>
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-[#222] text-[14px] truncate {{ $fa }}">{{ $b->place?->title ?? '—' }}</span>
                                    <span class="block text-[12px] text-[#717171]" dir="ltr">
                                        {{ $b->reference }} · {{ $b->start_date?->isoFormat('D MMM') }} → {{ $b->checkoutAt()?->isoFormat('D MMM YYYY') }}
                                    </span>
                                </span>
                            </span>
                            <span class="shrink-0 text-end">
                                <span class="block font-bold text-[#222] text-[15px] tabular-nums" dir="ltr">SR {{ $sr($b->hostNetMinor()) }}</span>
                                {{-- Where the number comes from: gross minus Calm's cut (commission + its VAT). --}}
                                <span class="block text-[11px] text-[#bbb] tabular-nums" dir="ltr">SR {{ $sr($b->host_gross_amount) }} − SR {{ $sr((int) $b->host_gross_amount - $b->hostNetMinor()) }} {{ $isRtl ? 'عمولة' : 'fee' }}</span>
                                @if($paid)
                                    <span class="inline-flex items-center text-[11px] font-bold text-[#059669]" style="gap: 4px; background: #ecfdf5; padding: 2px 9px; border-radius: 999px;">
                                        ✓ {{ $isRtl ? 'مدفوع' : 'Paid' }}@if($b->paid_out_at) · {{ $b->paid_out_at->isoFormat('D MMM') }}@endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center text-[11px] font-bold text-[#b45309]" style="gap: 4px; background: #fffbeb; padding: 2px 9px; border-radius: 999px;">
                                        {{ $isRtl ? 'قيد التحويل' : 'Pending' }}
                                    </span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
                @if($bookings->hasPages())
                    <div style="margin-top: 18px;">{{ $bookings->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
