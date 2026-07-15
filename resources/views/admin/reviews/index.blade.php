@extends('layouts.admin')

@php
    use App\Enums\ReviewStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $pill = fn (?ReviewStatus $s): array => match ($s) {
        ReviewStatus::Published   => ['bg' => '#10b981', 'dot' => '#a7f3d0'],
        ReviewStatus::Blocked     => ['bg' => '#ef4444', 'dot' => '#fecaca'],
        default                   => ['bg' => '#f59e0b', 'dot' => '#fde68a'], // under_review
    };
    $statusLabel = fn (?ReviewStatus $s): string => $isRtl ? match ($s) {
        ReviewStatus::Published => 'منشور',
        ReviewStatus::Blocked   => 'محظور',
        default                 => 'قيد المراجعة',
    } : str_replace('_', ' ', $s?->value ?? 'under_review');

    $filters = [
        ['key' => null,            'label_ar' => 'الكل',          'label_en' => 'All'],
        ['key' => 'under_review',  'label_ar' => 'قيد المراجعة',   'label_en' => 'Under review'],
        ['key' => 'published',     'label_ar' => 'منشور',         'label_en' => 'Published'],
        ['key' => 'blocked',       'label_ar' => 'محظور',         'label_en' => 'Blocked'],
    ];
@endphp

@section('title', $isRtl ? 'التقييمات' : 'Reviews')
@section('heading', $isRtl ? 'التقييمات' : 'Reviews')

@section('main')
    {{-- Status filter + search --}}
    <div class="flex items-center justify-between flex-wrap" style="margin-bottom: 16px; gap: 12px;">
        <div class="flex items-center flex-wrap" style="gap: 8px;">
            @foreach($filters as $f)
                <a href="{{ route('admin.reviews.index', array_filter(['status' => $f['key'], 'q' => $search])) }}"
                   class="text-[13px] font-semibold {{ $fa }}"
                   style="padding: 7px 14px; border-radius: 999px; {{ ($status === $f['key']) ? 'background-color:#222;color:#fff;' : 'background-color:#fff;color:#717171;border:1px solid #ebebeb;' }}">
                    {{ $isRtl ? $f['label_ar'] : $f['label_en'] }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('admin.reviews.index') }}" class="flex items-center bg-white" style="border-radius: 14px; padding: 4px;">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="text" name="q" value="{{ $search }}" placeholder="{{ $isRtl ? 'ابحث باسم المكان' : 'Search place title' }}"
                   class="bg-transparent text-[14px] text-[#222] focus:outline-none {{ $fa }}" style="padding: 8px 14px;">
            <button type="submit" class="font-semibold text-white bg-[#222]" style="padding: 8px 16px; border-radius: 10px; font-size: 13px;">{{ $isRtl ? 'بحث' : 'Search' }}</button>
        </form>
    </div>

    <p class="text-[14px] text-[#717171]" style="margin-bottom: 12px;">{{ $reviews->total() }} {{ $isRtl ? 'تقييم' : 'reviews' }}</p>

    @if($reviews->isEmpty())
        <div class="bg-white text-center text-[#717171]" style="padding: 48px 20px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            {{ $isRtl ? 'لا توجد تقييمات.' : 'No reviews.' }}
        </div>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                @php $p = $pill($review->status); @endphp
                <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                    <div class="flex items-start justify-between flex-wrap" style="gap: 12px;">
                        <div style="flex: 1; min-width: 240px;">
                            <div class="flex items-center" style="gap: 10px;">
                                <span class="text-[15px] font-bold text-[#222] {{ $fa }}">{{ $review->place?->title ?: ($isRtl ? '— مكان محذوف —' : '— deleted place —') }}</span>
                                <span class="text-[14px] tabular-nums text-[#f59e0b]">{{ str_repeat('★', (int) $review->rate) }}{{ str_repeat('☆', 5 - (int) $review->rate) }}</span>
                            </div>
                            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">
                                {{ $review->guest?->name ?: ($review->reviewer_name ?: ($isRtl ? 'ضيف' : 'Guest')) }} · {{ $review->created_at?->diffForHumans() }}
                            </div>
                            @if($review->comment)
                                <p class="text-[14px] text-[#222] {{ $fa }}" style="margin-top: 10px; white-space: pre-line;">{{ $review->comment }}</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                              style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $p['bg'] }};">
                            <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $p['dot'] }};"></span>
                            {{ $statusLabel($review->status) }}
                        </span>
                    </div>

                    {{-- Status actions --}}
                    <div class="flex items-center border-t border-[#ebebeb]" style="margin-top: 14px; padding-top: 12px; gap: 10px;">
                        @foreach([
                            ['st' => ReviewStatus::Published, 'ar' => 'نشر', 'en' => 'Publish', 'c' => '#10b981'],
                            ['st' => ReviewStatus::Blocked, 'ar' => 'حظر', 'en' => 'Block', 'c' => '#ef4444'],
                            ['st' => ReviewStatus::UnderReview, 'ar' => 'قيد المراجعة', 'en' => 'Under review', 'c' => '#717171'],
                        ] as $action)
                            @if($review->status !== $action['st'])
                                <form method="POST" action="{{ route('admin.reviews.status', $review) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $action['st']->value }}">
                                    <button type="submit" class="text-[13px] font-semibold {{ $fa }}" style="color: {{ $action['c'] }};">{{ $isRtl ? $action['ar'] : $action['en'] }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if($reviews->hasPages())<div style="margin-top: 24px;">{{ $reviews->links() }}</div>@endif
    @endif
@endsection
