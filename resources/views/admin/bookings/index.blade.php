@extends('layouts.admin')

@php
    use App\Enums\BookingStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $fmtTime = fn (?string $t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '—';

    // Filter chips (key => label). 'all' clears the status filter.
    $filters = [
        'all'             => $isRtl ? 'الكل' : 'All',
        'pending_payment' => $isRtl ? 'بانتظار الدفع' : 'Pending',
        'confirmed'       => $isRtl ? 'مؤكد' : 'Confirmed',
        'completed'       => $isRtl ? 'مكتمل' : 'Completed',
        'cancelled'       => $isRtl ? 'ملغاة' : 'Cancelled',
        'expired'         => $isRtl ? 'منتهي الصلاحية' : 'Expired',
    ];
    $chipTint = [
        'all' => '#222', 'pending_payment' => '#f59e0b', 'confirmed' => '#10b981',
        'completed' => '#3b82f6', 'cancelled' => '#ef4444', 'expired' => '#9ca3af',
    ];
@endphp

@section('title', $isRtl ? 'الحجوزات' : 'Bookings')
@section('heading', $isRtl ? 'الحجوزات' : 'Bookings')

@section('main')
    {{-- ── Search ── --}}
    <form method="GET" action="{{ route('admin.bookings.index') }}" class="flex items-center bg-white {{ $fa }}"
          style="margin-bottom: 14px; border-radius: 14px; padding: 4px; max-width: 560px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.04);">
        @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <span class="flex items-center justify-center text-[#9ca3af]" style="padding: 0 6px 0 12px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </span>
        <input type="text" name="q" value="{{ $search }}"
               placeholder="{{ $isRtl ? 'ابحث برقم الحجز أو المكان أو جوال الضيف/المضيف' : 'Search booking ref, place ID, or guest/host phone' }}"
               class="flex-1 bg-transparent text-[14px] text-[#222] focus:outline-none {{ $fa }}"
               style="padding: 8px 6px;" dir="auto">
        <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black {{ $fa }}"
                style="padding: 8px 16px; border-radius: 10px; font-size: 13px;">{{ $isRtl ? 'بحث' : 'Search' }}</button>
        @if($search)
            <a href="{{ route('admin.bookings.index', array_filter(['status' => $status])) }}" class="text-[13px] text-[#717171] hover:text-[#222] {{ $fa }}" style="padding: 0 12px;">{{ $isRtl ? '✕' : '✕' }}</a>
        @endif
    </form>

    {{-- ── Status filter chips ── --}}
    <div class="flex items-center flex-wrap" style="gap: 8px; margin-bottom: 18px;">
        @foreach($filters as $key => $label)
            @php
                $active = ($key === 'all' && ! $status) || $status === $key;
                $params = array_filter(['q' => $search, 'status' => $key === 'all' ? null : $key]);
                $tint = $chipTint[$key];
            @endphp
            <a href="{{ route('admin.bookings.index', $params) }}"
               class="inline-flex items-center transition-all {{ $fa }}"
               style="gap: 7px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
                      {{ $active ? "background-color: {$tint}; color: #fff;" : 'background-color: #fff; color: #717171; box-shadow: 0px 4px 12px 0px rgba(0,0,0,0.04);' }}">
                @if($key !== 'all')
                    <span style="width: 7px; height: 7px; border-radius: 999px; background-color: {{ $active ? '#fff' : $tint }};"></span>
                @endif
                <span>{{ $label }}</span>
                <span class="tabular-nums {{ $active ? 'text-white/80' : 'text-[#bbb]' }}" style="font-size: 12px;">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach

        {{-- Failed automatic payouts — the only payout state needing a human.
             Shown only when at least one exists, so it acts as an alert. --}}
        @if(($counts['payout_failed'] ?? 0) > 0 || $payoutFailed)
            <a href="{{ route('admin.bookings.index', array_filter(['q' => $search, 'payout_failed' => $payoutFailed ? null : 1])) }}"
               class="inline-flex items-center transition-all {{ $fa }}"
               style="gap: 7px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
                      {{ $payoutFailed ? 'background-color: #b91c1c; color: #fff;' : 'background-color: #fef2f2; color: #b91c1c; box-shadow: 0px 4px 12px 0px rgba(0,0,0,0.04);' }}">
                <span>⚠ {{ $isRtl ? 'تحويلات فاشلة' : 'Failed payouts' }}</span>
                <span class="tabular-nums {{ $payoutFailed ? 'text-white/80' : '' }}" style="font-size: 12px;">{{ $counts['payout_failed'] ?? 0 }}</span>
            </a>
        @endif
    </div>

    @if($bookings->isEmpty())
        <div class="bg-white text-center text-[#717171] {{ $fa }}" style="padding: 56px 20px; border-radius: 24px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.04);">
            <div style="font-size: 32px; margin-bottom: 8px;">📅</div>
            {{ $isRtl ? 'لا توجد حجوزات مطابقة.' : 'No matching bookings.' }}
        </div>
    @else
        <div class="flex flex-col" style="gap: 14px;">
            @foreach($bookings as $b)
                @php
                    $st = $b->booking_status;
                    $checkoutDate = $b->checkoutAt();
                @endphp
                <a href="{{ route('admin.bookings.show', $b) }}"
                   class="block bg-white hover:shadow-lg transition-all"
                   style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">

                    {{-- Header: thumbnail · place + reference · status · total --}}
                    <div class="flex items-center" style="gap: 14px;">
                        <span class="shrink-0 overflow-hidden bg-[#f3f4f6] flex items-center justify-center" style="width: 64px; height: 64px; border-radius: 16px;">
                            @if($b->place?->coverPhoto?->url)
                                <img src="{{ $b->place->coverPhoto->url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <span style="font-size: 26px;">{{ $b->place?->type?->icon ?: '🏠' }}</span>
                            @endif
                        </span>

                        <span class="flex-1 min-w-0">
                            <span class="block font-bold text-[#222] text-[16px] truncate {{ $fa }}">{{ $b->place?->title ?? '—' }}</span>
                            <span class="inline-flex items-center font-bold tabular-nums" style="margin-top: 6px; background-color: #fff4f3; color: #F88379; padding: 3px 10px; border-radius: 8px; font-size: 12px; letter-spacing: 0.5px;" dir="ltr">{{ $b->reference }}</span>
                        </span>

                        <span class="shrink-0 flex flex-col items-end" style="gap: 6px;">
                            <span class="inline-flex items-center text-white font-semibold {{ $fa }}"
                                  style="gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 11px; background-color: {{ $st->pill() }};">
                                {{ $st->label($isRtl) }}
                            </span>
                            <span class="font-bold text-[#222] text-[15px] tabular-nums" dir="ltr">SR {{ number_format($b->total_amount / 100, 2) }}</span>
                        </span>
                    </div>

                    {{-- Details: guest · host · check-in · check-out --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4" style="gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'الضيف' : 'Guest' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] truncate {{ $fa }}">{{ $b->guest?->name ?: '—' }}</span>
                            <span class="block text-[13px] text-[#717171]">@if($b->guest?->phone)<span dir="ltr">+966 {{ $b->guest->phone }}</span>@else—@endif</span>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'المضيف' : 'Host' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] truncate {{ $fa }}">{{ $b->host?->name ?: '—' }}</span>
                            <span class="block text-[13px] text-[#717171]">@if($b->host?->phone)<span dir="ltr">+966 {{ $b->host->phone }}</span>@else—@endif</span>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'الدخول' : 'Check-in' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] {{ $fa }}">{{ $b->start_date?->isoFormat('ddd D MMM') ?: '—' }}</span>
                            <span class="block text-[13px] text-[#717171] tabular-nums"><span dir="ltr">{{ $fmtTime($b->check_in_time) }}</span></span>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'الخروج' : 'Check-out' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] {{ $fa }}">
                                {{ optional($checkoutDate)->isoFormat('ddd D MMM') ?: '—' }}
                                @if($b->checkout_next_day)<span class="text-[11px] font-semibold text-[#F88379]">{{ $isRtl ? '· التالي' : '· next day' }}</span>@endif
                            </span>
                            <span class="block text-[13px] text-[#717171] tabular-nums"><span dir="ltr">{{ $fmtTime($b->check_out_time) }}</span></span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        @if($bookings->hasPages())
            <div style="margin-top: 24px;">{{ $bookings->links() }}</div>
        @endif
    @endif
@endsection
