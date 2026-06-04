@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
            {{ $isRtl ? 'رمز الدولة (ISO)' : 'Country code (ISO)' }}
        </label>
        <input type="text"
               name="country_code"
               value="{{ old('country_code', $country->country_code) }}"
               required
               maxlength="8"
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] font-mono uppercase focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;">
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
            {{ $isRtl ? 'الاسم بالعربية' : 'Name (Arabic)' }}
        </label>
        <input type="text"
               name="name_ar"
               value="{{ old('name_ar', $country->name_ar) }}"
               required
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;"
               dir="rtl">
    </div>
    <div>
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
            {{ $isRtl ? 'الاسم بالإنجليزية' : 'Name (English)' }}
        </label>
        <input type="text"
               name="name_en"
               value="{{ old('name_en', $country->name_en) }}"
               required
               class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none"
               style="padding: 11px 14px; border-radius: 12px;"
               dir="ltr">
    </div>
</div>

<div class="flex items-center" style="gap: 12px; margin-top: 24px;">
    <button type="submit"
            class="font-semibold text-white bg-[#222] hover:bg-black"
            style="padding: 11px 22px; border-radius: 12px; corner-shape: squircle; font-size: 14px;">
        {{ $submitLabel ?? ($isRtl ? 'حفظ' : 'Save') }}
    </button>
    <a href="{{ route('admin.countries.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
</div>
