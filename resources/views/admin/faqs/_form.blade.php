@php
    use App\Enums\FaqAudience;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $currentAudience = old('audience', $faq->audience?->value ?? FaqAudience::Guest->value);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الفئة' : 'Audience' }}</label>
        <select name="audience" required
                class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none"
                style="padding: 11px 14px; border-radius: 12px;">
            @foreach(FaqAudience::cases() as $case)
                <option value="{{ $case->value }}" @selected($currentAudience === $case->value)>
                    {{ $isRtl
                        ? ($case === FaqAudience::Guest ? 'الضيوف' : 'المضيفون')
                        : ucfirst($case->value) }}
                </option>
            @endforeach
        </select>
        @error('audience')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الترتيب' : 'Sort order' }}</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $faq->sort_order ?? 0) }}" min="0" max="9999"
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;" dir="ltr">
        <p class="text-[12px] text-[#717171]" style="margin-top: 6px;">{{ $isRtl ? 'الأصغر يظهر أولاً داخل فئته.' : 'Lower shows first within its audience.' }}</p>
    </div>
</div>

<div style="margin-top: 16px;">
    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'السؤال بالعربية' : 'Question (Arabic)' }}</label>
    <input type="text" name="question_ar" value="{{ old('question_ar', $faq->question_ar) }}" required dir="rtl" maxlength="500"
           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
           style="padding: 11px 14px; border-radius: 12px;">
    @error('question_ar')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
</div>

<div style="margin-top: 16px;">
    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الجواب بالعربية' : 'Answer (Arabic)' }}</label>
    <textarea name="answer_ar" rows="5" required dir="rtl"
              class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
              style="padding: 11px 14px; border-radius: 12px;">{{ old('answer_ar', $faq->answer_ar) }}</textarea>
    @error('answer_ar')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1" style="gap: 16px; margin-top: 16px;">
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'السؤال بالإنجليزية (اختياري)' : 'Question (English, optional)' }}</label>
        <input type="text" name="question_en" value="{{ old('question_en', $faq->question_en) }}" dir="ltr" maxlength="500"
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;">
    </div>
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الجواب بالإنجليزية (اختياري)' : 'Answer (English, optional)' }}</label>
        <textarea name="answer_en" rows="5" dir="ltr"
                  class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                  style="padding: 11px 14px; border-radius: 12px;">{{ old('answer_en', $faq->answer_en) }}</textarea>
    </div>
</div>

<div class="flex items-center" style="gap: 12px; margin-top: 24px;">
    <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black"
            style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $submitLabel ?? ($isRtl ? 'حفظ' : 'Save') }}</button>
    <a href="{{ route('admin.faqs.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
</div>
