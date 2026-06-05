@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'تعديل مفتاح' : 'Edit setting')
@section('heading', $isRtl ? 'تعديل مفتاح' : 'Edit setting')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.settings.update', $setting) }}">
            @csrf
            @method('PUT')

            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'المفتاح' : 'Key' }}</label>
            <input type="text" name="key" value="{{ old('key', $setting->key) }}" required dir="ltr"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] font-mono focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px; margin-bottom: 16px;">

            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'القيمة' : 'Value' }}</label>
            <textarea name="value" rows="6"
                      class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                      style="padding: 11px 14px; border-radius: 12px;">{{ old('value', $setting->value) }}</textarea>

            <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
                <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black"
                        style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $isRtl ? 'تحديث' : 'Update' }}</button>
                <a href="{{ route('admin.settings.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
            </div>
        </form>
    </div>
@endsection
