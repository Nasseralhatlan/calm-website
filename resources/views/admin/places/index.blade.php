@extends('layouts.admin')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';

    // Same vivid pill palette as host/places/index — saturated bg, lighter dot, white text.
    $reviewPill = fn (PlaceReviewStatus $s): array => match ($s) {
        PlaceReviewStatus::Draft         => ['bg' => '#9ca3af', 'dot' => '#e5e7eb'],
        PlaceReviewStatus::PendingReview => ['bg' => '#f59e0b', 'dot' => '#fde68a'],
        PlaceReviewStatus::Approved      => ['bg' => '#10b981', 'dot' => '#a7f3d0'],
        PlaceReviewStatus::Rejected      => ['bg' => '#ef4444', 'dot' => '#fecaca'],
    };
    $statusPill = fn (PlaceStatus $s): array => $s === PlaceStatus::Active
        ? ['bg' => '#10b981', 'dot' => '#a7f3d0']
        : ['bg' => '#9ca3af', 'dot' => '#e5e7eb'];
@endphp

@section('title', $isRtl ? 'الأماكن' : 'Places')
@section('heading', $isRtl ? 'الأماكن' : 'Places')

@section('main')
    <p class="text-[14px] text-[#717171]" style="margin-bottom: 20px;">{{ $places->total() }} {{ $isRtl ? 'مكان' : 'places' }}</p>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'العنوان' : 'Title' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المضيف' : 'Host' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'النوع' : 'Type' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المدينة' : 'City' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الحالة' : 'Status' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'حالة المراجعة' : 'Review' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($places as $place)
                    @php $sp = $statusPill($place->status); $rp = $reviewPill($place->review_status); @endphp
                    <tr class="border-t border-[#ebebeb]">
                        <td style="padding: 14px 20px;" class="font-medium">{{ $place->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}</td>
                        <td style="padding: 14px 20px;" class="text-[#717171]" dir="ltr">{{ $place->host?->phone ? '+966 '.$place->host->phone : ($place->host?->email ?? '—') }}</td>
                        <td style="padding: 14px 20px;" class="text-[#717171]">{{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}</td>
                        <td style="padding: 14px 20px;" class="text-[#717171]">{{ $isRtl ? $place->cityArea?->city?->name_ar : $place->cityArea?->city?->name_en }}</td>
                        <td style="padding: 14px 20px;">
                            <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white"
                                  style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $sp['bg'] }};">
                                <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $sp['dot'] }};"></span>
                                {{ $place->status->value }}
                            </span>
                        </td>
                        <td style="padding: 14px 20px;">
                            <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white"
                                  style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $rp['bg'] }};">
                                <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $rp['dot'] }};"></span>
                                {{ str_replace('_', ' ', $place->review_status->value) }}
                            </span>
                        </td>
                        <td style="padding: 14px 20px;" class="text-end whitespace-nowrap">
                            <a href="{{ route('admin.places.edit', $place) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.places.destroy', $place) }}" class="inline" style="margin-inline-start: 14px;" onsubmit="return confirm('{{ $isRtl ? 'حذف هذا المكان؟' : 'Delete this place?' }}');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding: 32px 20px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد أماكن بعد.' : 'No places yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($places->hasPages())<div style="margin-top: 20px;">{{ $places->links() }}</div>@endif
@endsection
