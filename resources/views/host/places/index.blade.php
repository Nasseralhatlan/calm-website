@extends('layouts.user')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    // Saturated pill palette — solid colored background, lighter "live" dot,
    // white text. Catches the eye at a glance instead of melting into the row.
    $reviewPill = fn (PlaceReviewStatus $s): array => match ($s) {
        PlaceReviewStatus::Draft         => ['bg' => '#9ca3af', 'dot' => '#e5e7eb'],
        PlaceReviewStatus::PendingReview => ['bg' => '#f59e0b', 'dot' => '#fde68a'],
        PlaceReviewStatus::Approved      => ['bg' => '#10b981', 'dot' => '#a7f3d0'],
        PlaceReviewStatus::Rejected      => ['bg' => '#ef4444', 'dot' => '#fecaca'],
    };
    $statusPill = fn (PlaceStatus $s): array => $s === PlaceStatus::Active
        ? ['bg' => '#10b981', 'dot' => '#a7f3d0']
        : ['bg' => '#9ca3af', 'dot' => '#e5e7eb'];

    $reviewLabel = fn (PlaceReviewStatus $s): string => $isRtl ? match ($s) {
        PlaceReviewStatus::Draft         => 'مسودة',
        PlaceReviewStatus::PendingReview => 'قيد المراجعة',
        PlaceReviewStatus::Approved      => 'موافق عليه',
        PlaceReviewStatus::Rejected      => 'مرفوض',
    } : str_replace('_', ' ', $s->value);

    $statusLabel = fn (PlaceStatus $s): string => $isRtl
        ? ($s === PlaceStatus::Active ? 'مفعّل' : 'موقوف')
        : $s->value;
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
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المكان' : 'Place' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المدينة' : 'City' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'السعر' : 'Price' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الحالة' : 'Status' }}</th>
                        <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المراجعة' : 'Review' }}</th>
                        <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراء' : 'Action' }}</th>
                    </tr>
                </thead>
                <tbody class="text-[14px]">
                    @foreach($places as $place)
                        @php
                            $rp = $reviewPill($place->review_status);
                            $sp = $statusPill($place->status);
                            $isDraft = $place->review_status === PlaceReviewStatus::Draft;
                            $city = $place->cityArea?->city;
                        @endphp
                        <tr class="border-t border-[#ebebeb] {{ $isDraft ? 'bg-[#fffbeb]/40 hover:bg-[#fffbeb]' : 'hover:bg-[#fafafa]' }} transition-colors">
                            <td class="text-start" style="padding: 14px 20px;">
                                <span class="inline-flex items-center" style="gap: 12px;">
                                    <span style="font-size: 22px; line-height: 1;">{{ $place->type?->icon ?: '🏠' }}</span>
                                    <span class="font-medium text-[#222]">{{ $place->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}</span>
                                </span>
                            </td>
                            <td class="text-start text-[#717171]" style="padding: 14px 20px;">
                                <span class="inline-flex items-center" style="gap: 8px;">
                                    <span>{{ $city?->avatar ?: '📍' }}</span>
                                    <span>{{ $isRtl ? $city?->name_ar : $city?->name_en }}</span>
                                </span>
                            </td>
                            <td class="text-start font-semibold tabular-nums" style="padding: 14px 20px;" dir="ltr">{{ number_format($place->price) }} SAR</td>
                            <td class="text-start" style="padding: 14px 20px;">
                                <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                                      style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $sp['bg'] }};">
                                    <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $sp['dot'] }};"></span>
                                    {{ $statusLabel($place->status) }}
                                </span>
                            </td>
                            <td class="text-start" style="padding: 14px 20px;">
                                <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                                      style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $rp['bg'] }};">
                                    <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $rp['dot'] }};"></span>
                                    {{ $reviewLabel($place->review_status) }}
                                </span>
                            </td>
                            <td class="text-end" style="padding: 14px 20px;">
                                @php $isRejected = $place->review_status === PlaceReviewStatus::Rejected; @endphp
                                <span class="inline-flex items-center justify-end flex-wrap" style="gap: 6px 14px;">
                                    {{-- View as a guest (owner sees a status banner on top). --}}
                                    <a href="{{ route('places.show', $place) }}"
                                       class="inline-flex items-center gap-1 text-[13px] font-semibold text-[#717171] hover:text-[#222] {{ $fa }}">
                                        {{ $isRtl ? '👁 عرض' : '👁 View' }}
                                    </a>
                                    {{-- Edit: drafts continue building in the wizard; everything
                                         else opens the pre-filled editor (saving resubmits). --}}
                                    @if($isDraft)
                                        <a href="{{ route('host.places.create', ['draft' => $place->id]) }}"
                                           class="inline-flex items-center gap-1 text-[13px] font-bold text-[#F88379] hover:text-[#f56b60] {{ $fa }}">
                                            {{ $isRtl ? '↩ متابعة' : 'Continue ↪' }}
                                        </a>
                                    @else
                                        <a href="{{ route('host.places.edit', $place) }}"
                                           class="inline-flex items-center gap-1 text-[13px] font-bold {{ $isRejected ? 'text-[#ef4444] hover:text-[#dc2626]' : 'text-[#222] hover:text-[#000]' }} {{ $fa }}"
                                           @if($isRejected) title="{{ $place->rejection_reason }}" @endif>
                                            {{ $isRtl ? '✎ تعديل' : '✎ Edit' }}
                                        </a>
                                    @endif
                                    @if($place->review_status === PlaceReviewStatus::Approved)
                                        <a href="{{ route('host.places.availability', $place) }}"
                                           class="inline-flex items-center gap-1 text-[13px] font-bold text-[#F88379] hover:text-[#f56b60] {{ $fa }}">
                                            {{ $isRtl ? '📅 التواريخ' : '📅 Dates' }}
                                        </a>
                                    @endif
                                    {{-- Delete archives the place (soft delete, reversible). --}}
                                    <form method="POST" action="{{ route('host.places.destroy', $place) }}" class="inline m-0"
                                          onsubmit="return confirm('{{ $isRtl ? 'حذف هذا المكان؟ يمكن استعادته لاحقاً.' : 'Delete this place? It can be restored later.' }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 text-[13px] font-bold text-[#dc2626] hover:text-[#b91c1c] {{ $fa }}">
                                            {{ $isRtl ? '🗑 حذف' : '🗑 Delete' }}
                                        </button>
                                    </form>
                                </span>
                            </td>
                        </tr>
                        @if($isRejected && $place->rejection_reason)
                            {{-- Inline reviewer feedback on the rejected row so the host
                                 sees what to fix without leaving the listing. --}}
                            <tr class="bg-[#fef2f2]/60">
                                <td colspan="6" style="padding: 12px 20px 16px 20px;">
                                    <div class="text-[12px] {{ $fa }}">
                                        <span class="font-bold text-[#7a2018]">{{ $isRtl ? 'ملاحظات المراجع:' : 'Reviewer feedback:' }}</span>
                                        <span class="text-[#7a2018]">{{ $place->rejection_reason }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
