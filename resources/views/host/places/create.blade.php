@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'كن مضيفاً' : 'Become a host')
@section('heading', $isRtl ? 'أضف مكانك' : 'Add your place')

@section('main')
    <div class="bg-white max-w-3xl"
         style="padding: 28px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <p class="text-[14px] text-[#717171] {{ $fa }}" style="margin-bottom: 24px;">
            {{ $isRtl ? 'املأ هذه المعلومات الأساسية لإضافة مكانك. سيتم مراجعته قبل النشر.' : 'Fill in the basics to register your place. We\'ll review it before it goes live.' }}
        </p>

        <form method="POST" action="{{ route('host.places.store') }}">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'العنوان' : 'Title' }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'السعر الأساسي (ريال)' : 'Base price (SAR)' }}</label>
                    <input type="number" name="price" value="{{ old('price', 0) }}" required min="0"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'النوع' : 'Type' }}</label>
                    <select name="place_type_id" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        <option value="">{{ $isRtl ? 'اختر نوعاً' : 'Select a type' }}</option>
                        @foreach($placeTypes as $pt)
                            <option value="{{ $pt->id }}" @selected(old('place_type_id') == $pt->id)>{{ $pt->icon }} {{ $isRtl ? $pt->name_ar : $pt->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الموقع' : 'Location' }}</label>
                    <select name="city_area_id" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        <option value="">{{ $isRtl ? 'اختر الموقع' : 'Select an area' }}</option>
                        @foreach($cityAreas as $ca)
                            <option value="{{ $ca->id }}" @selected(old('city_area_id') == $ca->id)>{{ $isRtl ? $ca->name_ar : $ca->name_en }} — {{ $isRtl ? $ca->city?->name_ar : $ca->city?->name_en }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'وصف المكان' : 'Description' }}</label>
                <textarea name="description" rows="4"
                          class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                          style="padding: 11px 14px; border-radius: 12px;">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'وقت الوصول' : 'Check-in time' }}</label>
                    <input type="text" name="check_in_time" value="{{ old('check_in_time', '15:00') }}" required dir="ltr"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'وقت المغادرة' : 'Check-out time' }}</label>
                    <input type="text" name="check_out_time" value="{{ old('check_out_time', '12:00') }}" required dir="ltr"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
            </div>

            <div class="flex items-center" style="gap: 12px; margin-top: 28px;">
                <button type="submit" class="font-semibold text-white bg-[#F88379] hover:bg-[#f56b60]"
                        style="padding: 12px 24px; border-radius: 14px; font-size: 15px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">{{ $isRtl ? 'إنشاء المكان' : 'Create place' }}</button>
                <a href="{{ route('profile') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
            </div>
        </form>
    </div>
@endsection
