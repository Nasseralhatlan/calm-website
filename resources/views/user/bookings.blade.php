@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $fmtTime = fn (?string $t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '—';
@endphp

@section('title', $isRtl ? 'حجوزات أماكني' : 'Bookings')
@section('heading', $isRtl ? 'حجوزات أماكني' : 'Bookings on your places')

@section('main')
    @if($bookings->isEmpty())
        @include('user._empty_state', [
            'icon' => '📅',
            'title' => $isRtl ? 'لا توجد حجوزات بعد' : 'No bookings yet',
            'subtitle' => $isRtl ? 'ستظهر هنا حجوزات الضيوف على أماكنك.' : 'Guest bookings on your places will appear here.',
        ])
    @else
        <p class="text-[14px] text-[#717171] {{ $fa }}" style="margin-bottom: 18px;">
            {{ $bookings->total() }} {{ $isRtl ? 'حجز' : 'bookings' }}
        </p>

        <div class="flex flex-col" style="gap: 14px;">
            @foreach($bookings as $booking)
                @php
                    $st = $booking->booking_status;
                    $checkoutDate = $booking->checkoutAt();
                @endphp
                <a href="{{ route('user.bookings.show', $booking) }}"
                   class="block bg-white hover:shadow-lg transition-all"
                   style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">

                    {{-- Header: thumbnail · place + reference · status · total --}}
                    <div class="flex items-center" style="gap: 14px;">
                        <span class="shrink-0 overflow-hidden bg-[#f3f4f6] flex items-center justify-center" style="width: 64px; height: 64px; border-radius: 16px;">
                            @if($booking->place?->coverPhoto?->url)
                                <img src="{{ $booking->place->coverPhoto->url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <span style="font-size: 26px;">{{ $booking->place?->type?->icon ?: '🏠' }}</span>
                            @endif
                        </span>

                        <span class="flex-1 min-w-0">
                            <span class="block font-bold text-[#222] text-[16px] truncate {{ $fa }}">{{ $booking->place?->title ?? '—' }}</span>
                            <span class="inline-flex items-center font-bold tabular-nums" style="margin-top: 6px; background-color: #fff4f3; color: #F88379; padding: 3px 10px; border-radius: 8px; font-size: 12px; letter-spacing: 0.5px;" dir="ltr">{{ $booking->reference }}</span>
                        </span>

                        <span class="shrink-0 flex flex-col items-end" style="gap: 6px;">
                            <span class="inline-flex items-center text-white font-semibold {{ $fa }}"
                                  style="gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 11px; background-color: {{ $st->pill() }};">
                                {{ $st->label($isRtl) }}
                            </span>
                            <span class="font-bold text-[#222] text-[15px] tabular-nums" dir="ltr">SR {{ number_format($booking->total / 100, 2) }}</span>
                        </span>
                    </div>

                    {{-- Details: guest · guests · check-in · check-out --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4" style="gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'الضيف' : 'Guest' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] truncate {{ $fa }}">{{ $booking->guest?->name ?: '—' }}</span>
                            <span class="block text-[13px] text-[#717171]">@if($booking->guest?->phone)<span dir="ltr">+966 {{ $booking->guest->phone }}</span>@else—@endif</span>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'عدد الضيوف' : 'Guests' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] {{ $fa }}">{{ $booking->guests }}</span>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'الدخول' : 'Check-in' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] {{ $fa }}">{{ $booking->start_date?->isoFormat('ddd D MMM') ?: '—' }}</span>
                            <span class="block text-[13px] text-[#717171] tabular-nums"><span dir="ltr">{{ $fmtTime($booking->check_in_time) }}</span></span>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'الخروج' : 'Check-out' }}</span>
                            <span class="block text-[14px] font-medium text-[#222] {{ $fa }}">
                                {{ optional($checkoutDate)->isoFormat('ddd D MMM') ?: '—' }}
                                @if($booking->checkout_next_day)<span class="text-[11px] font-semibold text-[#F88379]">{{ $isRtl ? '· التالي' : '· next day' }}</span>@endif
                            </span>
                            <span class="block text-[13px] text-[#717171] tabular-nums"><span dir="ltr">{{ $fmtTime($booking->check_out_time) }}</span></span>
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
