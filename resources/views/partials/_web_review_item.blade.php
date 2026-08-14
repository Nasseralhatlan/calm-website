{{-- App-style review (place page strip + reviews modal): NO card box — the
     parent draws the separators. Square avatar with white border + shadow at
     the start edge, name beside it, date under the name, black stars, then
     the comment. Expects: $review, optional $clamp (+ page scope: $isRtl, $fa).
     $clamp = the horizontal strip variant: comment clamped + «عرض المزيد»
     opening the reviews modal (needs the page's Alpine root in scope). --}}
@php
    $reviewerName = \Illuminate\Support\Str::of((string) ($review->guest?->name ?? $review->reviewer_name))->trim()->explode(' ')->first() ?: ($isRtl ? 'ضيف' : 'Guest');
    $reviewAvatar = $review->guest?->avatar_url ?? null;
    $clamp = $clamp ?? false;
@endphp
<div class="{{ $fa }}">
    <div class="flex items-center" style="gap: 12px;">
        <span class="shrink-0 overflow-hidden flex items-center justify-center font-bold text-black"
              style="width: 48px; height: 48px; border-radius: 14px; corner-shape: squircle; -webkit-corner-shape: squircle; border: 2px solid #ffffff; box-shadow: 0 2px 12px rgba(0,0,0,0.18); background-color: #F5F5F5; font-size: 18px;">
            @if($reviewAvatar)
                <img src="{{ $reviewAvatar }}" alt="" class="w-full h-full object-cover" loading="lazy">
            @else
                {{ mb_substr($reviewerName, 0, 1) }}
            @endif
        </span>
        <div class="min-w-0">
            <div class="font-bold text-black truncate" style="font-size: 14px;">{{ $reviewerName }}</div>
            {{-- The app shows the month in Latin («August 2026») even in Arabic. --}}
            <div dir="ltr" class="{{ $isRtl ? 'text-right' : 'text-left' }}" style="font-size: 12px; color: #AAAAAA; margin-top: 3px;">{{ $review->created_at?->format('F Y') }}</div>
        </div>
    </div>
    <div style="margin-top: 10px;">
        <span dir="ltr" style="font-size: 13px; letter-spacing: 2px;">@for($st = 1; $st <= 5; $st++)<span style="color: {{ $st <= (int) $review->rate ? '#1A1A1A' : '#E3E3E3' }};">★</span>@endfor</span>
    </div>
    @if($review->comment)
        <p class="text-black" style="font-size: 13.5px; line-height: 1.8; margin-top: 10px;{{ $clamp ? ' display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;' : '' }}">{{ $review->comment }}</p>
        @if($clamp)
            <button type="button" @click="openSheet('reviews')"
                    class="calm-press font-bold text-black underline" style="font-size: 13px; margin-top: 8px;">
                {{ $isRtl ? 'عرض المزيد' : 'Show more' }}
            </button>
        @endif
    @endif
</div>
