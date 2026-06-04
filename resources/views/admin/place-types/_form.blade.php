@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

<div class="grid grid-cols-1 sm:grid-cols-[80px_1fr_1fr]" style="gap: 16px;">
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الأيقونة' : 'Icon' }}</label>
        <input type="text" name="icon" value="{{ old('icon', $placeType->icon) }}" maxlength="32"
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-center text-[22px] focus:outline-none"
               style="padding: 9px 12px; border-radius: 12px;">
    </div>
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم بالعربية' : 'Name (Arabic)' }}</label>
        <input type="text" name="name_ar" value="{{ old('name_ar', $placeType->name_ar) }}" required dir="rtl"
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;">
    </div>
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم بالإنجليزية' : 'Name (English)' }}</label>
        <input type="text" name="name_en" value="{{ old('name_en', $placeType->name_en) }}" required dir="ltr"
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;">
    </div>
</div>

<div class="flex items-center" style="gap: 12px; margin-top: 24px;">
    <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black"
            style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $submitLabel ?? ($isRtl ? 'حفظ' : 'Save') }}</button>
    <a href="{{ route('admin.place-types.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
</div>
