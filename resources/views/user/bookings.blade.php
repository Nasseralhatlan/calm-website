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
        <p class="text-[14px] text-[#717171]" style="margin-bottom: 20px;">
            {{ $bookings->count() }} {{ $isRtl ? 'حجز' : 'bookings' }}
        </p>

        <div class="bg-white overflow-hidden" style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            <table class="w-full">
                <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                    <tr>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المكان' : 'Place' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الضيف' : 'Guest' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'التواريخ' : 'Dates' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الضيوف' : 'Guests' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الإجمالي' : 'Total' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الحالة' : 'Status' }}</th>
                        <th class="text-end" style="padding: 14px 20px;"></th>
                    </tr>
                </thead>
                <tbody class="text-[14px]">
                    @foreach($bookings as $booking)
                        @php
                            $p = $pill($booking->booking_status);
                            $place = $booking->place;
                            $guest = $booking->guest;
                        @endphp
                        <tr class="border-t border-[#ebebeb] hover:bg-[#fafafa] transition-colors">
                            <td class="text-start" style="padding: 14px 20px;">
                                <span class="inline-flex items-center" style="gap: 12px;">
                                    @if($place?->coverPhoto?->url)
                                        <img src="{{ $place->coverPhoto->url }}" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 12px;">
                                    @else
                                        <span style="font-size: 22px;">{{ $place?->type?->icon ?: '🏠' }}</span>
                                    @endif
                                    <span class="font-medium text-[#222]">{{ $place?->title ?: '—' }}</span>
                                </span>
                            </td>
                            <td class="text-start text-[#717171]" style="padding: 14px 20px;">
                                {{ $guest?->name ?: ($guest?->phone ? '+966 '.$guest->phone : '—') }}
                            </td>
                            <td class="text-start text-[#222]" style="padding: 14px 20px;" dir="ltr">
                                {{ $booking->start_date?->isoFormat('D MMM') }} → {{ $booking->end_date?->isoFormat('D MMM YYYY') }}
                            </td>
                            <td class="text-start text-[#717171]" style="padding: 14px 20px;">{{ $booking->guests }}</td>
                            <td class="text-start font-semibold tabular-nums" style="padding: 14px 20px;" dir="ltr">
                                {{ number_format($booking->total / 100, 2) }} {{ $isRtl ? 'ر.س' : 'SAR' }}
                            </td>
                            <td class="text-start" style="padding: 14px 20px;">
                                <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                                      style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $p['bg'] }};">
                                    <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $p['dot'] }};"></span>
                                    {{ $label($booking->booking_status) }}
                                </span>
                            </td>
                            <td class="text-end" style="padding: 14px 20px;">
                                <a href="{{ route('user.bookings.show', $booking) }}"
                                   class="inline-flex items-center gap-1 text-[13px] font-bold text-[#F88379] hover:text-[#f56b60] {{ $fa }}">
                                    {{ $isRtl ? 'التفاصيل' : 'Details' }} {{ $isRtl ? '←' : '→' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
