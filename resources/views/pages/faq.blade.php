@extends('layouts.legal')

@php
    $isRtl = app()->getLocale() === 'ar';
    $sections = [
        'guest' => ['title' => $isRtl ? 'أسئلة الضيوف' : 'For guests', 'items' => $faqs['guest']],
        'host' => ['title' => $isRtl ? 'أسئلة المضيفين' : 'For hosts', 'items' => $faqs['host']],
    ];
@endphp

@section('title', $isRtl ? 'الأسئلة الشائعة' : 'FAQ')

@section('content')
    <p>
        {{ $isRtl
            ? 'أكثر الأسئلة التي تصلنا، مع إجاباتها. إن لم تجد سؤالك هنا فتواصل معنا عبر صفحة الدعم.'
            : "The questions we hear most, answered. Can't find yours? Reach us via the support page." }}
    </p>

    @foreach($sections as $key => $section)
        <h2 id="{{ $key }}">{{ $section['title'] }}</h2>

        @forelse($section['items'] as $faq)
            <details class="bg-white border border-[#ececec]" style="border-radius: 16px; margin-bottom: 10px; overflow: hidden;">
                <summary class="cursor-pointer font-semibold text-[#222]" style="padding: 15px 18px; font-size: 15px; list-style: none;">
                    {{ $isRtl ? $faq->question_ar : ($faq->question_en ?: $faq->question_ar) }}
                </summary>
                <div style="padding: 0 18px 15px; white-space: pre-line;">{{ $isRtl ? $faq->answer_ar : ($faq->answer_en ?: $faq->answer_ar) }}</div>
            </details>
        @empty
            <p style="color: #999;">{{ $isRtl ? 'لا توجد أسئلة في هذا القسم بعد.' : 'No questions in this section yet.' }}</p>
        @endforelse
    @endforeach
@endsection
