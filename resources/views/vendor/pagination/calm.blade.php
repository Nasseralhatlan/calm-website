@php
    $isRtl = app()->getLocale() === 'ar';
    // Shared pill styles so every cell matches the app's squircle aesthetic.
    $cell = 'inline-flex items-center justify-center text-sm font-semibold select-none transition-colors';
    $cellStyle = 'min-width: 40px; height: 40px; padding: 0 12px; border-radius: 12px;';

    // The pager is kept LTR in both locales (page numbers are numeric, so a
    // left→right run reads naturally either way). This guarantees the arrow's
    // visual direction matches where it takes you: ‹ always = previous/back,
    // › always = next/forward — no reversed "points right but goes back" glyphs.
    $prevArrow = '&lsaquo;'; // ‹ — previous / back
    $nextArrow = '&rsaquo;'; // › — next / forward
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ $isRtl ? 'التنقل بين الصفحات' : 'Pagination' }}"
         class="flex items-center justify-between flex-wrap" style="gap: 12px;">

        {{-- Result summary --}}
        <p class="text-sm text-[#717171] {{ $isRtl ? 'font-arabic' : '' }}" style="margin: 0;">
            @if ($isRtl)
                عرض <span class="font-semibold text-[#222]">{{ $paginator->firstItem() }}</span>
                إلى <span class="font-semibold text-[#222]">{{ $paginator->lastItem() }}</span>
                من <span class="font-semibold text-[#222]">{{ $paginator->total() }}</span> نتيجة
            @else
                Showing <span class="font-semibold text-[#222]">{{ $paginator->firstItem() }}</span>
                to <span class="font-semibold text-[#222]">{{ $paginator->lastItem() }}</span>
                of <span class="font-semibold text-[#222]">{{ $paginator->total() }}</span> results
            @endif
        </p>

        {{-- Page links — forced LTR in both locales so prev (‹) sits left and
             next (›) right, arrows matching their action regardless of page dir. --}}
        <div class="flex items-center" dir="ltr" style="gap: 6px;">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $cell }} text-[#bbb] bg-white border border-[#ebebeb] cursor-default"
                      style="{{ $cellStyle }}" aria-disabled="true">
                    {!! $prevArrow !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="{{ $cell }} text-[#222] bg-white border border-[#ebebeb] hover:bg-[#fafafa]"
                   style="{{ $cellStyle }}" aria-label="{{ $isRtl ? 'السابق' : 'Previous' }}">
                    {!! $prevArrow !!}
                </a>
            @endif

            {{-- Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $cell }} text-[#717171] bg-transparent cursor-default"
                          style="{{ $cellStyle }}">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $cell }} text-white bg-[#222] border border-[#222]"
                                  style="{{ $cellStyle }}" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}"
                               class="{{ $cell }} text-[#222] bg-white border border-[#ebebeb] hover:bg-[#fafafa]"
                               style="{{ $cellStyle }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="{{ $cell }} text-[#222] bg-white border border-[#ebebeb] hover:bg-[#fafafa]"
                   style="{{ $cellStyle }}" aria-label="{{ $isRtl ? 'التالي' : 'Next' }}">
                    {!! $nextArrow !!}
                </a>
            @else
                <span class="{{ $cell }} text-[#bbb] bg-white border border-[#ebebeb] cursor-default"
                      style="{{ $cellStyle }}" aria-disabled="true">
                    {!! $nextArrow !!}
                </span>
            @endif
        </div>
    </nav>
@endif
