@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $sections = [
        'guest' => ['label' => $isRtl ? 'أسئلة الضيوف' : 'Guest FAQs', 'items' => $faqs['guest']],
        'host' => ['label' => $isRtl ? 'أسئلة المضيفين' : 'Host FAQs', 'items' => $faqs['host']],
    ];
@endphp

@section('title', $isRtl ? 'الأسئلة الشائعة' : 'FAQs')
@section('heading', $isRtl ? 'الأسئلة الشائعة' : 'FAQs')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171] {{ $isRtl ? 'font-arabic' : '' }}">
            {{ $isRtl ? 'تظهر هذه الأسئلة في صفحة /faq العامة، كل فئة في تبويب مستقل.' : 'Shown on the public /faq page — one tab per audience.' }}
        </p>
        <a href="{{ route('admin.faqs.create') }}"
           class="font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 20px; border-radius: 12px; font-size: 14px;">
            {{ $isRtl ? '+ سؤال جديد' : '+ New FAQ' }}
        </a>
    </div>

    @foreach($sections as $key => $section)
        <div class="bg-white" style="border-radius: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px;">
            <div class="flex items-center" style="gap: 10px; margin-bottom: 14px;">
                <h2 class="text-[16px] font-bold text-[#222] {{ $isRtl ? 'font-arabic' : '' }}">{{ $section['label'] }}</h2>
                <span class="text-[12px] text-[#717171] tabular-nums">· {{ $section['items']->count() }}</span>
                <a href="{{ route('pages.faq') }}#{{ $key }}" target="_blank" rel="noopener"
                   class="text-[12px] text-[#717171] hover:text-[#222]" style="margin-inline-start: auto;">
                    {{ $isRtl ? 'معاينة ↗' : 'Preview ↗' }}
                </a>
            </div>

            @forelse($section['items'] as $faq)
                <div class="flex items-start border-t border-[#f0f0f0]" style="gap: 12px; padding: 12px 2px;">
                    <span class="text-[12px] text-[#bbb] tabular-nums" style="min-width: 28px; padding-top: 2px;">{{ $faq->sort_order }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-[14px] font-semibold text-[#222] {{ $isRtl ? 'font-arabic' : '' }}">{{ $faq->question_ar }}</p>
                        <p class="text-[13px] text-[#717171] {{ $isRtl ? 'font-arabic' : '' }}" style="margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $faq->answer_ar }}</p>
                    </div>
                    <div class="flex items-center shrink-0" style="gap: 14px; padding-top: 2px;">
                        <a href="{{ route('admin.faqs.edit', $faq) }}" class="text-[13px] font-semibold text-[#717171] hover:text-[#222] {{ $isRtl ? 'font-arabic' : '' }}">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" class="inline"
                              onsubmit="return confirm('{{ $isRtl ? 'حذف هذا السؤال؟' : 'Delete this FAQ?' }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[13px] font-semibold text-[#dc2626] hover:underline {{ $isRtl ? 'font-arabic' : '' }}">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-[13px] text-[#999] {{ $isRtl ? 'font-arabic' : '' }}" style="padding: 8px 2px;">{{ $isRtl ? 'لا توجد أسئلة بعد.' : 'No FAQs yet.' }}</p>
            @endforelse
        </div>
    @endforeach
@endsection
