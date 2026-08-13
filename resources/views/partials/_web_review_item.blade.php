{{-- App-style review box (place page + reviews modal): transparent card with a
     light border; square avatar with white border + shadow at the start edge,
     name beside it, date under the name; stars; then the comment separated by
     a light hairline. Expects: $review (+ page scope: $isRtl, $fa). --}}
@php
    $reviewerName = \Illuminate\Support\Str::of((string) ($review->guest?->name ?? $review->reviewer_name))->trim()->explode(' ')->first() ?: ($isRtl ? 'ضيف' : 'Guest');
    $reviewAvatar = $review->guest?->avatar_url ?? null;
@endphp
<div class="{{ $fa }}" style="border: 1px solid #F1F1F1; border-radius: 20px; corner-shape: squircle; -webkit-corner-shape: squircle; padding: 16px;">
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
            <div style="font-size: 12px; color: #AAAAAA; margin-top: 3px;">{{ $review->created_at?->translatedFormat($isRtl ? 'F Y' : 'M Y') }}</div>
        </div>
    </div>
    <div style="margin-top: 12px;">
        <span dir="ltr" style="font-size: 12px; letter-spacing: 2px;">@for($st = 1; $st <= 5; $st++)<span style="color: {{ $st <= (int) $review->rate ? '#F5B60F' : '#E3E3E3' }};">★</span>@endfor</span>
    </div>
    @if($review->comment)
        <p class="text-black" style="font-size: 13.5px; line-height: 1.8; margin-top: 12px; padding-top: 12px; border-top: 1px solid #F1F1F1;">{{ $review->comment }}</p>
    @endif
</div>
