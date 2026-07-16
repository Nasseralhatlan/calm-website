@extends('layouts.legal')

@php
    use App\Enums\FaqAudience;
    $isRtl = app()->getLocale() === 'ar';
    $tabs = [
        FaqAudience::Guest->value => $isRtl ? 'للضيوف' : 'For guests',
        FaqAudience::Host->value => $isRtl ? 'للمضيفين' : 'For hosts',
    ];
@endphp

@section('title', $isRtl ? 'الأسئلة الشائعة' : 'FAQ')

@section('content')
    <p>
        {{ $isRtl
            ? 'أكثر الأسئلة التي تصلنا، مع إجاباتها. إن لم تجد سؤالك هنا فتواصل معنا عبر صفحة الدعم.'
            : "The questions we hear most, answered. Can't find yours? Reach us via the support page." }}
    </p>

    {{-- Audience tabs — server-side (?audience=), no JS so it renders anywhere. --}}
    <div class="flex" style="gap: 8px; margin: 18px 0 22px;">
        @foreach($tabs as $value => $label)
            <a href="{{ route('pages.faq', ['audience' => $value]) }}"
               class="text-[14px] font-semibold {{ $audience->value === $value ? 'text-white bg-[#222]' : 'text-[#555] bg-white border border-[#e5e5e5] hover:border-[#222]' }}"
               style="padding: 9px 22px; border-radius: 999px; text-decoration: none;">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @forelse($faqs as $faq)
        <details class="bg-white border border-[#ececec]" style="border-radius: 16px; margin-bottom: 10px; overflow: hidden;">
            <summary class="cursor-pointer font-semibold text-[#222]" style="padding: 15px 18px; font-size: 15px; list-style: none;">
                {{ $isRtl ? $faq->question_ar : ($faq->question_en ?: $faq->question_ar) }}
            </summary>
            <div style="padding: 0 18px 15px; white-space: pre-line;">{{ $isRtl ? $faq->answer_ar : ($faq->answer_en ?: $faq->answer_ar) }}</div>
        </details>
    @empty
        <p style="color: #999;">{{ $isRtl ? 'لا توجد أسئلة في هذا القسم بعد.' : 'No questions in this section yet.' }}</p>
    @endforelse
@endsection
