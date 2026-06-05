@extends('layouts.admin')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    use Illuminate\Support\Facades\Storage;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', $isRtl ? 'تعديل المكان' : 'Edit place')
@section('heading', $place->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —'))

@section('main')
    {{-- Header: links + host context --}}
    <div class="flex items-center justify-between flex-wrap" style="margin-bottom: 18px; gap: 10px;">
        <div class="text-[12px] text-[#717171] {{ $fa }}">
            {{ $isRtl ? 'المعرّف:' : 'ID:' }}
            <code class="text-[11px] text-[#bababa]" dir="ltr">{{ $place->id }}</code>
            ·
            {{ $isRtl ? 'المضيف:' : 'Host:' }}
            <code class="text-[12px] text-[#222]" dir="ltr">
                {{ $place->host?->phone ? '+966 '.$place->host->phone : ($place->host?->email ?? '—') }}
            </code>
        </div>
        <div class="flex items-center" style="gap: 10px;">
            <a href="{{ route('places.show', $place) }}" target="_blank" rel="noopener"
               class="text-[13px] font-semibold text-[#222] hover:underline {{ $fa }}">
                {{ $isRtl ? 'معاينة كزائر ↗' : 'Preview as guest ↗' }}
            </a>
            @if($place->review_status === PlaceReviewStatus::PendingReview)
                <a href="{{ route('admin.places.review', $place) }}"
                   class="inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#f56b60] {{ $fa }}"
                   style="padding: 8px 14px; gap: 6px; border-radius: 12px; font-size: 13px;">
                    {{ $isRtl ? 'مراجعة' : 'Review' }} →
                </a>
            @endif
        </div>
    </div>

    {{-- Read-only context: photos + attributes the host configured --}}
    @if($place->photos->count() > 0 || $place->attributeValues->count() > 0)
        <div class="bg-white max-w-3xl" style="padding: 20px; margin-bottom: 16px; border-radius: 24px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.04);">
            <h3 class="text-[14px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 12px;">
                {{ $isRtl ? 'تفاصيل المكان (للعرض فقط)' : 'Place details (read-only)' }}
            </h3>

            @if($place->photos->count() > 0)
                <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-2" style="margin-bottom: 14px;">
                    @foreach($place->photos as $p)
                        @php $url = str_starts_with($p->path, 'http') ? $p->path : Storage::disk('s3')->url($p->path); @endphp
                        <div class="relative">
                            <img src="{{ $url }}" alt="" class="block w-full aspect-square object-cover" style="border-radius: 10px;">
                            @if($p->is_cover)
                                <span class="absolute top-1 inline-flex items-center text-[9px] font-bold bg-[#F88379] text-white"
                                      style="padding: 1px 5px; border-radius: 999px; {{ $isRtl ? 'right: 3px;' : 'left: 3px;' }}">★</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if($place->attributeValues->count() > 0)
                <div class="flex flex-wrap" style="gap: 6px;">
                    @foreach($place->attributeValues as $pa)
                        @php $a = $pa->attribute; @endphp
                        @continue(! $a)
                        <span class="inline-flex items-center bg-[#f7f7f7] text-[#222] text-[12px] {{ $fa }}"
                              style="padding: 4px 10px; border-radius: 999px; gap: 5px;">
                            <span>{{ $a->icon }}</span>
                            <span>{{ $isRtl ? $a->name_ar : $a->name_en }}</span>
                            @if($pa->value && is_numeric($pa->value) && (int) $pa->value > 1)
                                <span class="text-[#717171]" dir="ltr">× {{ $pa->value }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

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

            @php
                $hours = [];
                for ($h = 0; $h < 24; $h++) {
                    $value = sprintf('%02d:00', $h);
                    $period = $h < 12 ? 'AM' : 'PM';
                    $hour12 = $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h);
                    $hours[$value] = sprintf('%d:00 %s', $hour12, $period);
                }
                $currentCheckIn  = old('check_in_time',  $place->check_in_time);
                $currentCheckOut = old('check_out_time', $place->check_out_time);
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'وقت الوصول' : 'Check-in time' }}</label>
                    <select name="check_in_time" required dir="ltr"
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none cursor-pointer"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach($hours as $val => $label)
                            <option value="{{ $val }}" @selected($currentCheckIn === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'وقت المغادرة' : 'Check-out time' }}</label>
                    <select name="check_out_time" required dir="ltr"
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none cursor-pointer"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach($hours as $val => $label)
                            <option value="{{ $val }}" @selected($currentCheckOut === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
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

            <div style="margin-top: 16px;">
                <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
                    {{ $isRtl ? 'سبب الرفض (يظهر للمضيف)' : 'Rejection reason (visible to host)' }}
                </label>
                <textarea name="rejection_reason" rows="3" maxlength="2000"
                          placeholder="{{ $isRtl ? 'اتركه فارغاً إذا لم يكن المكان مرفوضاً.' : 'Leave empty if the place is not rejected.' }}"
                          class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                          style="padding: 11px 14px; border-radius: 12px;">{{ old('rejection_reason', $place->rejection_reason) }}</textarea>
            </div>

            <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
                <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black"
                        style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $isRtl ? 'تحديث' : 'Update' }}</button>
                <a href="{{ route('admin.places.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
            </div>
        </form>
    </div>
@endsection
