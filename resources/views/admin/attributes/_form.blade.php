@php
    use App\Enums\AttributeType;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $currentType = old('type', $attribute->type?->value ?? AttributeType::Text->value);
    $optionsText = old('options_text', $attribute->options ? implode("\n", $attribute->options) : '');
@endphp

<div x-data="{ type: '{{ $currentType }}' }">

    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'المجموعة' : 'Group' }}</label>
            <select name="group_id" required
                    class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                    style="padding: 11px 14px; border-radius: 12px;">
                <option value="">{{ $isRtl ? 'اختر مجموعة' : 'Select a group' }}</option>
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" @selected(old('group_id', $attribute->group_id) == $g->id)>{{ $isRtl ? $g->name_ar : $g->name_en }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'النوع' : 'Type' }}</label>
            <select name="type" x-model="type" required
                    class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                    style="padding: 11px 14px; border-radius: 12px;">
                @foreach(AttributeType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($currentType === $case->value)>{{ $case->value }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم بالعربية' : 'Name (Arabic)' }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $attribute->name_ar) }}" required dir="rtl"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px;">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم بالإنجليزية' : 'Name (English)' }}</label>
            <input type="text" name="name_en" value="{{ old('name_en', $attribute->name_en) }}" required dir="ltr"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px;">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'سؤال بالعربية' : 'Question (Arabic)' }}</label>
            <input type="text" name="question_ar" value="{{ old('question_ar', $attribute->question_ar) }}" dir="rtl"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px;">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'سؤال بالإنجليزية' : 'Question (English)' }}</label>
            <input type="text" name="question_en" value="{{ old('question_en', $attribute->question_en) }}" dir="ltr"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px;">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'أيقونة (إيموجي أو اسم)' : 'Icon (emoji or name)' }}</label>
            <input type="text" name="icon" value="{{ old('icon', $attribute->icon) }}"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px;">
        </div>
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الصورة' : 'Photo' }}</label>
            @php
                $currentPhotoRule = old('photo_rule', $attribute->photo_rule?->value ?? \App\Enums\AttributePhotoRule::None->value);
                $photoRuleLabels = $isRtl
                    ? [\App\Enums\AttributePhotoRule::None->value => 'بدون صورة', \App\Enums\AttributePhotoRule::Optional->value => 'صورة اختيارية', \App\Enums\AttributePhotoRule::Required->value => 'صورة مطلوبة']
                    : [\App\Enums\AttributePhotoRule::None->value => 'No photo', \App\Enums\AttributePhotoRule::Optional->value => 'Photo optional', \App\Enums\AttributePhotoRule::Required->value => 'Photo required'];
            @endphp
            <select name="photo_rule" required
                    class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                    style="padding: 11px 14px; border-radius: 12px;">
                @foreach(\App\Enums\AttributePhotoRule::cases() as $case)
                    <option value="{{ $case->value }}" @selected($currentPhotoRule === $case->value)>{{ $photoRuleLabels[$case->value] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
        <div>
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الترتيب' : 'Sort' }}</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $attribute->sort_order ?? 0) }}" min="0" max="9999"
                   class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                   style="padding: 11px 14px; border-radius: 12px;" dir="ltr">
            <p class="text-[12px] text-[#717171]" style="margin-top: 6px;">{{ $isRtl ? 'الأصغر يظهر أولاً في نموذج الإضافة وصفحة المكان.' : 'Lower shows first in the add form and place page.' }}</p>
        </div>
        <div x-data="{ highlighted: {{ old('is_highlighted', $attribute->is_highlighted) ? 'true' : 'false' }} }">
            <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'مميّزة' : 'Highlight' }}</label>
            <input type="hidden" name="is_highlighted" :value="highlighted ? 1 : 0">
            <button type="button" @click="highlighted = !highlighted" role="switch" :aria-checked="highlighted"
                    class="flex items-center w-full bg-[#fafafa] border border-[#ebebeb] hover:border-[#222] transition-colors cursor-pointer text-start"
                    style="padding: 11px 14px; border-radius: 12px; gap: 12px;">
                {{-- iOS-style toggle, RTL-safe via logical inset --}}
                <span class="relative shrink-0 transition-colors" style="width: 46px; height: 28px; border-radius: 999px;"
                      :style="highlighted ? 'background-color:#F88379' : 'background-color:#dddddd'">
                    <span class="absolute bg-white transition-all" style="width: 22px; height: 22px; top: 3px; border-radius: 999px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"
                          :style="highlighted ? 'inset-inline-start: 21px' : 'inset-inline-start: 3px'"></span>
                </span>
                <span class="text-[14px] text-[#222]">{{ $isRtl ? 'إبراز هذه الخاصية في قسم منفصل' : 'Feature this in a separate Highlights section' }}</span>
            </button>
        </div>
    </div>

    {{-- Options textarea — only shown for select / multi_select --}}
    <div x-show="['select','multi_select'].includes(type)" x-cloak style="margin-top: 16px;">
        <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">{{ $isRtl ? 'الخيارات (سطر لكل خيار)' : 'Options (one per line)' }}</label>
        <textarea name="options_text" rows="6"
                  class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none font-mono"
                  style="padding: 11px 14px; border-radius: 12px;">{{ $optionsText }}</textarea>
    </div>

    <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
        <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black"
                style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $submitLabel ?? ($isRtl ? 'حفظ' : 'Save') }}</button>
        <a href="{{ route('admin.attributes.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
    </div>
</div>
