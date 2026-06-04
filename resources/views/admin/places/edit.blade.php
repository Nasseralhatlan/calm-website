@extends('layouts.admin')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'تعديل المكان' : 'Edit place')
@section('heading', $place->title)

@section('main')
    <div class="bg-white max-w-3xl" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.places.update', $place) }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'العنوان' : 'Title' }}</label>
                    <input type="text" name="title" value="{{ old('title', $place->title) }}" required
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'السعر الأساسي' : 'Base price' }}</label>
                    <input type="number" name="price" value="{{ old('price', $place->price) }}" required min="0"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
            </div>

            {{-- ── Per-day prices ── --}}
            @php
                $dayLabels = $isRtl
                    ? ['sunday' => 'الأحد', 'monday' => 'الإثنين', 'tuesday' => 'الثلاثاء', 'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت']
                    : ['sunday' => 'Sun', 'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat'];
            @endphp
            <div style="margin-top: 16px;">
                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'السعر لكل يوم' : 'Per-day pricing' }}</label>
                <p class="text-[12px] text-[#717171]" style="margin-bottom: 10px;">{{ $isRtl ? 'اترك القيمة 0 لاستخدام السعر الأساسي لذلك اليوم.' : 'Leave at 0 to fall back to the base price for that day.' }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7" style="gap: 8px;">
                    @foreach(\App\Models\Place::PRICE_COLUMNS as $day => $column)
                        <div>
                            <label for="{{ $column }}" class="block text-[11px] font-bold text-[#717171] uppercase tracking-wider text-center" style="margin-bottom: 4px;">{{ $dayLabels[$day] }}</label>
                            <input type="number" name="{{ $column }}" id="{{ $column }}" value="{{ old($column, $place->{$column}) }}" min="0"
                                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[14px] text-center tabular-nums focus:outline-none"
                                   style="padding: 9px 10px; border-radius: 10px;">
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="margin-top: 16px;">
                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الوصف' : 'Description' }}</label>
                <textarea name="description" rows="4"
                          class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                          style="padding: 11px 14px; border-radius: 12px;">{{ old('description', $place->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'النوع' : 'Type' }}</label>
                    <select name="place_type_id" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach($placeTypes as $pt)
                            <option value="{{ $pt->id }}" @selected(old('place_type_id', $place->place_type_id) == $pt->id)>{{ $isRtl ? $pt->name_ar : $pt->name_en }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الحي' : 'Area' }}</label>
                    <select name="city_area_id" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach($cityAreas as $ca)
                            <option value="{{ $ca->id }}" @selected(old('city_area_id', $place->city_area_id) == $ca->id)>
                                {{ $isRtl ? $ca->name_ar : $ca->name_en }} — {{ $isRtl ? $ca->city?->name_ar : $ca->city?->name_en }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'وقت الوصول' : 'Check-in time' }}</label>
                    <input type="text" name="check_in_time" value="{{ old('check_in_time', $place->check_in_time) }}" required dir="ltr" placeholder="15:00"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'وقت المغادرة' : 'Check-out time' }}</label>
                    <input type="text" name="check_out_time" value="{{ old('check_out_time', $place->check_out_time) }}" required dir="ltr" placeholder="12:00"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
            </div>

            <div style="margin-top: 16px;">
                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'القواعد' : 'House rules' }}</label>
                <textarea name="rules" rows="3"
                          class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                          style="padding: 11px 14px; border-radius: 12px;">{{ old('rules', $place->rules) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الحالة' : 'Status' }}</label>
                    <select name="status" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach(PlaceStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('status', $place->status?->value) === $s->value)>{{ $s->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'حالة المراجعة' : 'Review status' }}</label>
                    <select name="review_status" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach(PlaceReviewStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('review_status', $place->review_status?->value) === $s->value)>{{ $s->value }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
                <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black"
                        style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $isRtl ? 'تحديث' : 'Update' }}</button>
                <a href="{{ route('admin.places.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
            </div>
        </form>
    </div>
@endsection
