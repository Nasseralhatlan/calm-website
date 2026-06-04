@extends('layouts.user')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $reviewColor = fn (PlaceReviewStatus $s) => match ($s) {
        PlaceReviewStatus::Draft => 'background-color: #f3f4f6; color: #6b7280;',
        PlaceReviewStatus::PendingReview => 'background-color: #fef3c7; color: #92400e;',
        PlaceReviewStatus::Approved => 'background-color: #d1fae5; color: #15803d;',
        PlaceReviewStatus::Rejected => 'background-color: #fee2e2; color: #b91c1c;',
    };
    $statusColor = fn (PlaceStatus $s) => $s === PlaceStatus::Active
        ? 'background-color: #d1fae5; color: #15803d;'
        : 'background-color: #f3f4f6; color: #6b7280;';
@endphp

@section('title', $isRtl ? 'أماكني' : 'My places')
@section('heading', $isRtl ? 'أماكني' : 'My places')

@section('header-action')
    <a href="{{ route('host.places.create') }}"
       class="inline-flex items-center font-semibold text-white bg-[#F88379] hover:bg-[#f56b60] {{ $fa }}"
       style="padding: 10px 18px; gap: 8px; border-radius: 14px; font-size: 14px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
        <span>+</span><span>{{ $isRtl ? 'إضافة مكان جديد' : 'Add a new place' }}</span>
    </a>
@endsection

@section('main')
    @if($places->isEmpty())
        @include('user._empty_state', [
            'icon' => '🏡',
            'title' => $isRtl ? 'لم تضف أي مكان بعد' : "You haven't added any places yet",
            'subtitle' => $isRtl ? 'أضف مكانك الأول وابدأ باستقبال الحجوزات.' : 'Add your first place to start accepting bookings.',
        ])
    @else
        <p class="text-[14px] text-[#717171]" style="margin-bottom: 20px;">
            {{ $places->count() }} {{ $isRtl ? 'مكان' : 'places' }}
        </p>

        <div class="bg-white overflow-hidden"
             style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            <table class="w-full">
                <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                    <tr>
                        <th class="text-center" style="padding: 14px 12px; width: 56px;">{{ $isRtl ? 'النوع' : 'Type' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'العنوان' : 'Title' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المدينة' : 'City' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'السعر' : 'Price' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الحالة' : 'Status' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المراجعة' : 'Review' }}</th>
                    </tr>
                </thead>
                <tbody class="text-[14px]">
                    @foreach($places as $place)
                        <tr class="border-t border-[#ebebeb]">
                            <td class="text-center" style="padding: 14px 12px; font-size: 22px; line-height: 1;">{{ $place->type?->icon ?: '🏠' }}</td>
                            <td style="padding: 14px 20px;" class="font-medium">{{ $place->title }}</td>
                            <td style="padding: 14px 20px;" class="text-[#717171]">{{ $isRtl ? $place->cityArea?->city?->name_ar : $place->cityArea?->city?->name_en }}</td>
                            <td style="padding: 14px 20px;" class="font-semibold tabular-nums" dir="ltr">{{ number_format($place->price) }} SAR</td>
                            <td style="padding: 14px 20px;">
                                <span class="text-[11px] font-bold uppercase tracking-wider" style="padding: 4px 10px; border-radius: 999px; {{ $statusColor($place->status) }}">{{ $place->status->value }}</span>
                            </td>
                            <td style="padding: 14px 20px;">
                                <span class="text-[11px] font-bold uppercase tracking-wider" style="padding: 4px 10px; border-radius: 999px; {{ $reviewColor($place->review_status) }}">{{ $place->review_status->value }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
