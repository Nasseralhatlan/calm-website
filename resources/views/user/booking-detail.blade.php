@extends('layouts.user')

@php
    use App\Enums\BookingStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $pill = fn (BookingStatus $s): array => match ($s) {
        BookingStatus::PendingPayment => ['bg' => '#f59e0b', 'dot' => '#fde68a'],
        BookingStatus::Confirmed => ['bg' => '#10b981', 'dot' => '#a7f3d0'],
        BookingStatus::Completed => ['bg' => '#3b82f6', 'dot' => '#bfdbfe'],
        BookingStatus::Expired => ['bg' => '#9ca3af', 'dot' => '#e5e7eb'],
        default => ['bg' => '#ef4444', 'dot' => '#fecaca'],
    };
    $label = fn (BookingStatus $s): string => $isRtl ? match ($s) {
        BookingStatus::PendingPayment => 'بانتظار الدفع',
        BookingStatus::Confirmed => 'مؤكد',
        BookingStatus::Completed => 'مكتمل',
        BookingStatus::Expired => 'منتهي',
        BookingStatus::CanceledByHost => 'ملغى (المضيف)',
        BookingStatus::CanceledByGuest => 'ملغى (الضيف)',
    } : str_replace('_', ' ', $s->value);

    $sar = fn (int $minor): string => number_format($minor / 100, 2);

    $place = $booking->place;
    $city = $place?->cityArea?->city;
    $p = $pill($booking->booking_status);
    $payout = $booking->booking_amount - $booking->commission_amount;
    $backRoute = $isHost ? 'user.bookings' : 'user.my-bookings';

    // small shared styles
    $card = 'background:#fff;border-radius:24px;padding:22px;box-shadow:0px 10px 30px 0px rgba(0,0,0,0.05);';
    $rowCss = 'display:flex;align-items:center;justify-content:space-between;padding:9px 0;';
@endphp

@section('title', $isRtl ? 'تفاصيل الحجز' : 'Booking details')
@section('heading', $place?->title ?: ($isRtl ? 'تفاصيل الحجز' : 'Booking details'))

@section('header-action')
    <a href="{{ route($backRoute) }}"
       class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] border border-[#ebebeb] {{ $fa }}"
       style="padding: 10px 18px; gap: 8px; border-radius: 14px; font-size: 14px;">
        <span>{{ $isRtl ? '→' : '←' }}</span>
        <span>{{ $isHost ? ($isRtl ? 'حجوزات أماكني' : 'Bookings') : ($isRtl ? 'حجوزاتي' : 'My bookings') }}</span>
    </a>
@endsection

@section('main')
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white {{ $fa }}"
              style="padding: 5px 14px 5px 10px; border-radius: 999px; gap: 6px; background-color: {{ $p['bg'] }};">
            <span style="width: 7px; height: 7px; border-radius: 999px; background-color: {{ $p['dot'] }};"></span>
            {{ $label($booking->booking_status) }}
        </span>
        <span class="inline-flex items-center text-[12px] font-semibold text-[#222] bg-[#f7f7f7]"
              style="padding: 5px 12px; border-radius: 999px; gap: 6px;">
            <span class="text-[#999] {{ $fa }}">{{ $isRtl ? 'رقم الحجز' : 'Booking ref' }}</span>
            <span dir="ltr" class="tabular-nums">{{ $booking->reference }}</span>
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2" style="gap: 18px; align-items: start; max-width: 920px;">

        {{-- ── Place ─────────────────────────────────────────────── --}}
        <div style="{{ $card }}">
            <div class="inline-flex items-center {{ $fa }}" style="gap: 14px; margin-bottom: 6px;">
                @if($place?->coverPhoto?->url)
                    <img src="{{ $place->coverPhoto->url }}" alt="" style="width: 64px; height: 64px; object-fit: cover; border-radius: 16px;">
                @else
                    <span style="font-size: 30px;">{{ $place?->type?->icon ?: '🏠' }}</span>
                @endif
                <span>
                    <span class="block font-bold text-[#222] text-[16px]">{{ $place?->title ?: '—' }}</span>
                    <span class="block text-[13px] text-[#717171]">
                        {{ $isRtl ? $place?->type?->name_ar : $place?->type?->name_en }}
                        @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
                    </span>
                </span>
            </div>
            @if($place)
                <a href="{{ route('places.show', $place) }}" target="_blank" rel="noopener"
                   class="text-[13px] font-semibold text-[#F88379] hover:text-[#f56b60] {{ $fa }}">
                    {{ $isRtl ? 'عرض المكان ↗' : 'View place ↗' }}
                </a>
            @endif
        </div>

        {{-- ── Stay ──────────────────────────────────────────────── --}}
        <div style="{{ $card }}">
            <h2 class="font-bold text-[#222] text-[15px] {{ $fa }}" style="margin-bottom: 10px;">{{ $isRtl ? 'تفاصيل الإقامة' : 'Stay' }}</h2>
            <div class="text-[14px] {{ $fa }}">
                <div style="{{ $rowCss }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'التواريخ' : 'Dates' }}</span>
                    <span class="font-semibold text-[#222]" dir="ltr">{{ $booking->start_date?->isoFormat('D MMM') }} → {{ $booking->end_date?->isoFormat('D MMM YYYY') }}</span>
                </div>
                <div style="{{ $rowCss }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'عدد الأيام' : 'Days' }}</span>
                    <span class="font-semibold text-[#222]">{{ $booking->quantity }}</span>
                </div>
                <div style="{{ $rowCss }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'الوصول / المغادرة' : 'Check-in / out' }}</span>
                    <span class="font-semibold text-[#222] tabular-nums" dir="ltr">{{ $booking->check_in_time ?? '—' }} / {{ $booking->check_out_time ?? '—' }}</span>
                </div>
                <div style="{{ $rowCss }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'الضيوف' : 'Guests' }}</span>
                    <span class="font-semibold text-[#222]">{{ $booking->guests }}</span>
                </div>
                @if($booking->rules)
                    <div style="padding-top: 10px; border-top: 1px solid #f0f0f0; margin-top: 6px;">
                        <span class="block text-[#717171]" style="margin-bottom: 4px;">{{ $isRtl ? 'القواعد' : 'House rules' }}</span>
                        <span class="text-[#222]" style="white-space: pre-line;">{{ $booking->rules }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Pricing ───────────────────────────────────────────── --}}
        <div style="{{ $card }}">
            <h2 class="font-bold text-[#222] text-[15px] {{ $fa }}" style="margin-bottom: 10px;">
                {{ $isHost ? ($isRtl ? 'الأرباح' : 'Earnings') : ($isRtl ? 'الدفع' : 'Payment') }}
            </h2>
            <div class="text-[14px] {{ $fa }}">
                <div style="{{ $rowCss }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'قيمة الحجز' : 'Booking amount' }}</span>
                    <span class="font-semibold text-[#222] tabular-nums" dir="ltr">{{ $sar($booking->booking_amount) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                </div>

                @if($isHost)
                    <div style="{{ $rowCss }}">
                        <span class="text-[#717171]">{{ $isRtl ? 'عمولة كالم' : 'Calm commission' }} ({{ rtrim(rtrim(number_format($booking->commission_rate, 2), '0'), '.') }}%)</span>
                        <span class="font-semibold text-[#ef4444] tabular-nums" dir="ltr">− {{ $sar($booking->commission_amount) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                    </div>
                    <div style="{{ $rowCss }} border-top:1px solid #f0f0f0;margin-top:4px;">
                        <span class="font-bold text-[#222]">{{ $isRtl ? 'صافي أرباحك' : 'Your payout' }}</span>
                        <span class="font-bold text-[#10b981] tabular-nums" dir="ltr">{{ $sar($payout) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                    </div>
                    <div style="{{ $rowCss }}">
                        <span class="text-[#717171]">{{ $isRtl ? 'حالة التحويل' : 'Payout status' }}</span>
                        <span class="font-semibold text-[#222]">{{ $booking->payout_status === 'paid' ? ($isRtl ? 'تم التحويل' : 'Paid') : ($isRtl ? 'بانتظار التحويل' : 'Not paid') }}</span>
                    </div>
                    <div style="{{ $rowCss }}">
                        <span class="text-[#717171]">{{ $isRtl ? 'الضريبة المحصلة' : 'VAT collected' }} ({{ rtrim(rtrim(number_format($booking->vat_rate, 2), '0'), '.') }}%)</span>
                        <span class="text-[#717171] tabular-nums" dir="ltr">{{ $sar($booking->vat_amount) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                    </div>
                @else
                    <div style="{{ $rowCss }}">
                        <span class="text-[#717171]">{{ $isRtl ? 'ضريبة القيمة المضافة' : 'VAT' }} ({{ rtrim(rtrim(number_format($booking->vat_rate, 2), '0'), '.') }}%)</span>
                        <span class="font-semibold text-[#222] tabular-nums" dir="ltr">{{ $sar($booking->vat_amount) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                    </div>
                    <div style="{{ $rowCss }} border-top:1px solid #f0f0f0;margin-top:4px;">
                        <span class="font-bold text-[#222]">{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                        <span class="font-bold text-[#222] tabular-nums" dir="ltr">{{ $sar($booking->total) }} {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                    </div>
                @endif

                <div style="{{ $rowCss }} border-top:1px solid #f0f0f0;margin-top:4px;">
                    <span class="text-[#717171]">{{ $isRtl ? 'طريقة الدفع' : 'Payment method' }}</span>
                    <span class="font-semibold text-[#222]" dir="ltr">{{ $booking->payment_method ?? '—' }}</span>
                </div>
            </div>
        </div>

        {{-- ── Counterparty ──────────────────────────────────────── --}}
        <div style="{{ $card }}">
            <h2 class="font-bold text-[#222] text-[15px] {{ $fa }}" style="margin-bottom: 10px;">
                {{ $isHost ? ($isRtl ? 'الضيف' : 'Guest') : ($isRtl ? 'المضيف' : 'Host') }}
            </h2>
            @php $party = $isHost ? $booking->guest : $booking->host; @endphp
            <div class="text-[14px] {{ $fa }}">
                <div style="{{ $rowCss }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'الاسم' : 'Name' }}</span>
                    <span class="font-semibold text-[#222]">{{ $party?->name ?: '—' }}</span>
                </div>
                @if($isHost)
                    {{-- Host needs to be able to contact the guest. --}}
                    <div style="{{ $rowCss }}">
                        <span class="text-[#717171]">{{ $isRtl ? 'الجوال' : 'Phone' }}</span>
                        <span class="font-semibold text-[#222]" dir="ltr">{{ $party?->phone ? '+966 '.$party->phone : '—' }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="text-[12px] text-[#999] {{ $fa }}" style="margin-top: 14px; max-width: 920px;" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
        {{ $isRtl ? 'تاريخ الحجز:' : 'Booked:' }} {{ $booking->created_at?->isoFormat('D MMM YYYY, h:mm a') }}
        @if($booking->confirmed_at)
            · {{ $isRtl ? 'تأكيد الدفع:' : 'Confirmed:' }} {{ $booking->confirmed_at?->isoFormat('D MMM YYYY, h:mm a') }}
        @endif
    </div>
@endsection
