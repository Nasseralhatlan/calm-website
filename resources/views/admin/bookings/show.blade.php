@extends('layouts.admin')

@php
    use App\Enums\BookingStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $canCancel = $booking->booking_status === BookingStatus::Confirmed;
@endphp

@section('title', $isRtl ? 'تفاصيل الحجز' : 'Booking details')
@section('heading', ($isRtl ? 'حجز ' : 'Booking ').$booking->reference)

@section('main')
    <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center text-[14px] text-[#717171] hover:text-[#222] {{ $fa }}" style="margin-bottom: 16px; gap: 6px;">
        <span class="rtl:scale-x-[-1] inline-block">←</span>
        {{ $isRtl ? 'كل الحجوزات' : 'All bookings' }}
    </a>

    <div style="max-width: 860px; display: flex; flex-direction: column; gap: 16px;">
        @include('partials._booking_detail', ['booking' => $booking, 'audience' => 'admin'])

        {{-- ── Cancel actions (admin only) ── --}}
        <div style="background:#fff;border-radius:24px;padding:24px;box-shadow:0px 8px 24px 0px rgba(0,0,0,0.05);">
            <h2 class="text-[15px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'إلغاء الحجز' : 'Cancel booking' }}</h2>
            @if($canCancel)
                <p class="text-[13px] text-[#999] {{ $fa }}" style="margin-bottom: 16px;">
                    {{ $isRtl ? 'سيتم إشعار الضيف والمضيف. لا يتم استرداد المبلغ تلقائياً.' : 'The guest and host will be notified. No automatic refund.' }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 10px;">
                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}"
                          onsubmit="return confirm('{{ $isRtl ? 'إلغاء بناءً على طلب المضيف؟' : 'Cancel this booking on behalf of the host?' }}')">
                        @csrf
                        <input type="hidden" name="actor" value="host">
                        <button type="submit" class="w-full font-semibold text-[#b91c1c] bg-[#fef2f2] hover:bg-[#fee2e2] transition-colors {{ $fa }}"
                                style="padding: 12px; border-radius: 14px; font-size: 14px;">
                            {{ $isRtl ? 'إلغاء (طلب المضيف)' : 'Cancel (host request)' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}"
                          onsubmit="return confirm('{{ $isRtl ? 'إلغاء بناءً على طلب الضيف / الإدارة؟' : 'Cancel this booking on behalf of the guest?' }}')">
                        @csrf
                        <input type="hidden" name="actor" value="admin">
                        <button type="submit" class="w-full font-bold text-white bg-[#ef4444] hover:bg-[#dc2626] transition-colors {{ $fa }}"
                                style="padding: 12px; border-radius: 14px; font-size: 14px;">
                            {{ $isRtl ? 'إلغاء (طلب الضيف)' : 'Cancel (guest request)' }}
                        </button>
                    </form>
                </div>
            @else
                <p class="text-[13px] text-[#717171] {{ $fa }}">
                    {{ $isRtl ? 'لا يمكن الإلغاء إلا للحجوزات المؤكدة.' : 'Only confirmed bookings can be cancelled.' }}
                </p>
            @endif
        </div>
    </div>
@endsection
