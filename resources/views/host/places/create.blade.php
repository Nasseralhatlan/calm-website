@extends('layouts.app')

@section('title', 'Calm — Become a host')

@section('body')
@php
    use Illuminate\Support\Facades\Storage;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $dirAttr = $isRtl ? 'rtl' : 'ltr';

    // Sensible default house rules pre-filled for a NEW place — the host can
    // edit, add to, or clear it. (Only seeds new places; existing/draft rules
    // override it on resume/edit.)
    $defaultRulesAr = "• المحافظة على المكان: يرجى الحفاظ على نظافة المكان والمرافق، وتسليمه بحالة جيدة كما تم استلامه.\n• الهدوء والخصوصية: يرجى احترام الجيران والمناطق المحيطة، وتجنب الإزعاج أو رفع الصوت خصوصًا في الأوقات المتأخرة.\n• الحفلات والتجمعات: لا يسمح بإقامة الحفلات أو التجمعات داخل المكان.\n• التدخين: يمنع التدخين داخل المكان.\n• الأثاث والممتلكات: يرجى عدم نقل أو إتلاف الأثاث والمرافق، ويتحمل الضيف مسؤولية أي تلفيات ناتجة عن سوء الاستخدام.\n• أوقات الدخول والخروج: يجب الالتزام بمواعيد تسجيل الدخول والخروج الموضحة في الحجز.";
    $defaultRulesEn = "• Care for the property: Please keep the place and its facilities clean, and hand it over in good condition as you received it.\n• Quiet & privacy: Please respect the neighbors and surroundings, and avoid noise or loud sounds, especially late at night.\n• Parties & gatherings: Parties or gatherings inside the property are not allowed.\n• Smoking: Smoking inside the property is not allowed.\n• Furniture & property: Please do not move or damage the furniture or facilities; the guest is responsible for any damage caused by misuse.\n• Check-in & check-out: Please adhere to the check-in and check-out times shown in the booking.";

    $photoLimitMsg = $isRtl ? 'الحد الأقصى ١٠ صور لكل قسم.' : 'Each section can have at most 10 images.';

    // Reshape for Alpine — only the keys the wizard needs
    $jsonPlaceTypes = $placeTypes->map(fn ($t) => [
        'id' => $t->id,
        'icon' => $t->icon,
        'label' => $isRtl ? $t->name_ar : $t->name_en,
    ])->values();

    $jsonCities = $cities->map(fn ($c) => [
        'id' => $c->id,
        'icon' => $c->avatar ?: '📍',
        'label' => $isRtl ? $c->name_ar : $c->name_en,
        'areas' => $c->areas->map(fn ($a) => [
            'id' => $a->id,
            'label' => $isRtl ? $a->name_ar : $a->name_en,
        ])->values(),
    ])->values();

    $attributeJson = fn ($a) => [
        'id' => $a->id,
        'icon' => $a->icon,
        'label' => $isRtl ? $a->name_ar : $a->name_en,
        'type' => $a->type->value,           // 'boolean' or 'number'
        'photoRule' => $a->photo_rule->value, // 'none' | 'optional' | 'required'
        'isHighlighted' => (bool) $a->is_highlighted,
    ];

    $jsonAttributeGroups = $attributeGroups->map(fn ($g) => [
        'id' => $g->id,
        'label' => $isRtl ? $g->name_ar : $g->name_en,
        'attributes' => $g->attributes->map($attributeJson)->values(),
    ])->values();

    // Highlighted amenities across all groups, in the admin sort order — shown
    // in a dedicated section at the top of the amenities step (they ALSO stay
    // in their group below).
    $jsonHighlightedAttributes = $attributeGroups
        ->flatMap(fn ($g) => $g->attributes)
        ->filter(fn ($a) => $a->is_highlighted)
        ->sortBy([['sort_order', 'asc'], ['name_en', 'asc']])
        ->map($attributeJson)
        ->values();

    $dayLabels = $isRtl
        ? ['sunday' => 'الأحد', 'monday' => 'الإثنين', 'tuesday' => 'الثلاثاء', 'wednesday' => 'الأربعاء', 'thursday' => 'الخميس', 'friday' => 'الجمعة', 'saturday' => 'السبت']
        : ['sunday' => 'Sun', 'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat'];

    // Draft hydrate payload (only populated when `?draft=<id>` resolved a row).
    $jsonDraft = $draft ? [
        'id' => $draft->id,
        'place_type_id' => $draft->place_type_id,
        // Bilingual content — fall back to the legacy single column (assumed the
        // host's primary language) for places saved before this field existed.
        'title_ar' => $draft->title_ar ?? $draft->title,
        'title_en' => $draft->title_en,
        'description_ar' => $draft->description_ar ?? $draft->description,
        'description_en' => $draft->description_en,
        'city_id' => $draft->cityArea?->city_id,
        'city_area_id' => $draft->city_area_id,
        'price' => $draft->price,
        'day_prices' => [
            'sunday' => $draft->price_sunday,
            'monday' => $draft->price_monday,
            'tuesday' => $draft->price_tuesday,
            'wednesday' => $draft->price_wednesday,
            'thursday' => $draft->price_thursday,
            'friday' => $draft->price_friday,
            'saturday' => $draft->price_saturday,
        ],
        'check_in_time' => $draft->check_in_time,
        'check_out_time' => $draft->check_out_time,
        'checkout_next_day' => (bool) $draft->checkout_next_day,
        'max_guests' => $draft->max_guests,
        'rules_ar' => $draft->rules_ar ?? $draft->rules,
        'rules_en' => $draft->rules_en,
        'location_url' => $draft->location_url,
        'review_status' => $draft->review_status?->value,
        'rejection_reason' => $draft->rejection_reason,
        'last_step' => (int) ($draft->last_step ?: 1),
        // Restore the host's facility picks.
        'attributes' => $draft->attributeValues->map(fn ($pa) => [
            'attribute_id' => $pa->attribute_id,
            'value' => $pa->value,
            'description' => $pa->description,
        ])->values(),
        // Restore uploaded photos as { attribute_id|null, path, url, featured_order }.
        // Ordered by sort_order (the relation default) so the wizard rebuilds
        // the within-section and section order; featured_order drives the
        // "shown outside" showcase. The wizard reconstructs its in-memory state.
        'photos' => $draft->photos->map(fn ($p) => [
            'place_attribute_id' => $p->place_attribute_id,
            'path' => $p->path,
            'url' => Storage::disk('s3')->url($p->path),
            'featured_order' => $p->featured_order,
        ])->values(),
    ] : null;

    // Edit-mode config (null in create mode). When present, the wizard renders
    // pre-filled, lets the user jump between steps, and shows a sticky
    // Save / Discard / Cancel bar. Admins also get an extra "Admin settings"
    // step (featured lists + status + review). Passed by the host/admin edit
    // controllers; absent on the public add flow.
    $editConfig = $editConfig ?? null;
    $editing = (bool) ($editConfig['enabled'] ?? false);
    // Step-nav labels for the edit-mode jump bar (admin gets the extra step).
    $wizardStepLabels = $isRtl
        ? ['النوع', 'العنوان', 'المدينة', 'الحي', 'التسعير', 'العناصر', 'الإعداد', 'الصور', 'القواعد']
        : ['Type', 'Title', 'City', 'Area', 'Pricing', 'Amenities', 'Configure', 'Photos', 'Rules'];
    $wizardAdminLabel = $isRtl ? 'المشرف' : 'Admin';
    $jsonEdit = $editConfig ? [
        'enabled' => true,
        'isAdmin' => (bool) ($editConfig['isAdmin'] ?? false),
        'cancelUrl' => $editConfig['cancelUrl'] ?? route('user.places'),
        'lists' => $editConfig['lists'] ?? [],
        'selectedListIds' => $editConfig['selectedListIds'] ?? [],
        'status' => $editConfig['status'] ?? null,
        'reviewStatus' => $editConfig['reviewStatus'] ?? null,
        'rejectionReason' => $editConfig['rejectionReason'] ?? null,
    ] : null;
@endphp

<div class="min-h-screen flex flex-col bg-white" dir="{{ $dirAttr }}">
    {{-- header --}}
    <header class="w-full border-b border-[#ebebeb] sticky top-0 bg-white/90 backdrop-blur z-30">
        <div class="px-6 sm:px-10 lg:px-20 h-20 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 sm:h-10 w-auto" draggable="false">
            </a>
            <div class="flex items-center" style="gap: 4px;">
                <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            style="border-radius: 14px;"
                            class="text-sm font-semibold text-[#222] hover:bg-[#f7f7f7] px-4 py-2.5 transition-colors {{ $locale === 'en' ? 'font-arabic' : '' }}">
                        {{ $locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                </form>
                <a href="{{ route('user.places') }}"
                   style="border-radius: 14px;"
                   class="text-sm font-semibold text-[#717171] hover:text-[#222] hover:bg-[#f7f7f7] px-4 py-2.5 transition-colors {{ $fa }}">
                    {{ $isRtl ? 'إلغاء' : 'Cancel' }}
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1 flex justify-center px-5 sm:px-8 py-10 sm:py-14" dir="{{ $dirAttr }}">
        {{-- Catalog data for the wizard (JSON-in-script-tag pattern from the old wizard,
             safe from HTML attribute escaping with Arabic apostrophes) --}}
        <script id="register-init-data" type="application/json">
            {!! json_encode([
                'placeTypes'      => $jsonPlaceTypes,
                'cities'          => $jsonCities,
                'attributeGroups' => $jsonAttributeGroups,
                'highlightedAttributes' => $jsonHighlightedAttributes,
                'draftEndpoint'   => route('host.places.draft'),
                'draft'           => $jsonDraft,
                'rates'           => $pricingRates,
                'edit'            => $jsonEdit,
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}
        </script>

        <div class="w-full max-w-2xl" x-data="registerWizard()" x-cloak>

            {{-- progress bar --}}
            <div class="mb-12">
                <div class="h-1.5 w-full bg-[#ebebeb] rounded-full overflow-hidden">
                    <div class="h-full bg-[#F88379] rounded-full transition-all duration-500"
                         :style="`width: ${(step / totalSteps) * 100}%`"></div>
                </div>
                <div class="mt-3 text-xs text-[#717171] font-medium {{ $fa }}">
                    <span x-text="step"></span> / <span x-text="totalSteps"></span>
                </div>

                {{-- Edit mode: jump straight to any step/section. --}}
                <div class="mt-4 flex flex-wrap gap-2" x-show="editing" x-cloak>
                    <template x-for="(label, i) in stepLabels()" :key="`stepnav-${i}`">
                        <button type="button" @click="goToStep(i + 1)"
                                class="text-[12px] font-semibold transition-all {{ $fa }}"
                                :class="step === (i + 1) ? 'bg-[#222] text-white' : 'bg-[#f3f4f6] text-[#717171] hover:bg-[#e9eaec]'"
                                style="padding: 6px 12px; border-radius: 999px;"
                                x-text="`${i + 1}. ${label}`"></button>
                    </template>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-8 r-ios-lg bg-[#fef3f2] border border-[#7a2018]/20 p-5 text-sm text-[#7a2018] {{ $fa }}">
                    <div class="font-semibold mb-2">{{ $isRtl ? 'تعذّر الإرسال' : 'Could not submit' }}</div>
                    @foreach ($errors->all() as $err)<div>· {{ $err }}</div>@endforeach
                </div>
            @endif

            {{-- Admin's feedback on a previously-rejected submission. Surfaces
                 at the top of the wizard so the host sees what to fix before
                 they re-submit. Disappears on the next submit because the
                 service clears the rejection_reason when status flips back to
                 PendingReview. --}}
            @if($draft && $draft->review_status?->value === 'rejected' && $draft->rejection_reason)
                <div class="r-ios-lg bg-[#fef2f2] border border-[#fecaca] {{ $fa }}"
                     style="padding: 18px 20px; margin-bottom: 24px;">
                    <div class="flex items-center" style="gap: 10px; margin-bottom: 8px;">
                        <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white"
                              style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: #ef4444;">
                            <span style="width: 6px; height: 6px; border-radius: 999px; background-color: #fecaca;"></span>
                            {{ $isRtl ? 'مرفوض' : 'Rejected' }}
                        </span>
                        <span class="text-[13px] font-bold text-[#7a2018] {{ $fa }}">
                            {{ $isRtl ? 'ملاحظات المراجع' : 'Reviewer feedback' }}
                        </span>
                    </div>
                    <p class="text-[14px] text-[#7a2018] whitespace-pre-line {{ $fa }}">{{ $draft->rejection_reason }}</p>
                    <p class="text-[12px] text-[#a85a4a] {{ $fa }}" style="margin-top: 10px;">
                        {{ $isRtl ? 'صحّح ما طُلب وأعد الإرسال — سيُعاد إرسال طلبك للمراجعة تلقائياً.' : 'Fix what was flagged and resubmit — your place will return to the review queue automatically.' }}
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ $editing ? $editConfig['submitUrl'] : route('host.places.store') }}" @submit="submitting = true" x-ref="form" dir="{{ $dirAttr }}">
                @csrf
                @if($editing) @method('PUT') @endif
                {{-- Carries the draft we've been auto-saving so the server promotes it
                     instead of creating a duplicate row on final submit. --}}
                <input type="hidden" name="draft_id" :value="draftId || ''">

                {{-- Admin-only: attach the listing to this host's phone instead
                     of the admin's own account. Hidden mirror posts on final
                     submit; the same Alpine field is also sent on every draft
                     auto-save (see saveDraft() payload). Non-admins never see
                     the input AND the server-side rule ignores any post anyway. --}}
                @if(auth()->user()?->isAdmin() && ! $editing)
                    <div class="r-ios-lg bg-amber-50 border border-amber-200 {{ $fa }}"
                         style="padding: 18px 20px; margin-bottom: 24px;">
                        <label class="block text-[13px] font-bold text-[#222]" style="margin-bottom: 6px;">
                            {{ $isRtl ? 'إضافة لرقم المضيف' : 'Attach to host phone' }}
                        </label>
                        <input type="tel" name="host_phone" x-model="hostPhone"
                               placeholder="5XXXXXXXX" dir="ltr" maxlength="9"
                               value="{{ old('host_phone') }}"
                               class="w-full bg-white border border-[#ebebeb] focus:border-[#222] text-[15px] tabular-nums focus:outline-none"
                               style="padding: 11px 14px; border-radius: 12px;">
                        <p class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 6px;">
                            {{ $isRtl
                                ? '٩ أرقام تبدأ بـ 5. إن لم يكن المستخدم موجوداً يُنشأ تلقائياً.'
                                : '9-digit national format (5XXXXXXXX). If no user has this phone, a shell account is created automatically.' }}
                        </p>
                    </div>
                @endif

                {{-- ── Step 1: place type ── --}}
                <section x-show="step === 1" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'ما نوع مكانك؟' : "What kind of place is it?" }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر الأقرب — يمكنك إضافة التفاصيل لاحقاً.' : 'Pick the closest match — you can refine later.' }}</p>

                    <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <template x-for="t in placeTypes" :key="t.id">
                            <label class="cursor-pointer r-ios-lg bg-white p-6 transition-all shadow-card shadow-card-hover flex flex-col items-start gap-4 min-h-[150px] border-2"
                                   :class="placeTypeId === t.id ? 'border-[#222]' : 'border-transparent'">
                                <input type="radio" name="place_type_id" :value="t.id" x-model="placeTypeId" class="sr-only">
                                <div class="text-4xl" style="line-height: 1;" x-text="t.icon || '🏠'"></div>
                                <div class="font-semibold text-[#222] {{ $fa }}" x-text="t.label"></div>
                            </label>
                        </template>
                    </div>
                </section>

                {{-- ── Step 2: basics ── --}}
                <section x-show="step === 2" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'المعلومات الأساسية' : 'The basics' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'عنوان قصير ووصف يجذب الضيوف.' : 'A short title and a description guests will love.' }}</p>

                    {{-- Title — Arabic + English (at least one required) --}}
                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'العنوان (عربي)' : 'Title (Arabic)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="title_ar" x-model="titleAr" type="text" maxlength="120" dir="rtl"
                                   placeholder="شاليه فاخر بإطلالة على الجبال"
                                   class="w-full bg-transparent outline-none text-[17px] text-[#222] py-4 px-5 font-arabic">
                        </div>
                    </label>
                    <label class="block mt-4">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'العنوان (إنجليزي)' : 'Title (English)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="title_en" x-model="titleEn" type="text" maxlength="120" dir="ltr"
                                   placeholder="Luxury chalet with mountain view"
                                   class="w-full bg-transparent outline-none text-[17px] text-[#222] py-4 px-5">
                        </div>
                    </label>
                    <p x-show="!titleAr.trim() && !titleEn.trim()" x-cloak class="mt-2 text-sm text-[#dc2626] {{ $fa }}">
                        {{ $isRtl ? 'أدخل العنوان بالعربية أو الإنجليزية على الأقل.' : 'Enter the title in at least Arabic or English.' }}
                    </p>

                    {{-- Description — Arabic + English (optional) --}}
                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'الوصف (عربي)' : 'Description (Arabic)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <textarea name="description_ar" x-model="descriptionAr" maxlength="5000" rows="5" dir="rtl"
                                      placeholder="صف ما يجعل مكانك مميزاً..."
                                      class="w-full bg-transparent outline-none resize-none text-[16px] text-[#222] py-4 px-5 leading-relaxed font-arabic"></textarea>
                        </div>
                        <div class="mt-1.5 text-xs text-[#717171] tabular-nums {{ $isRtl ? 'text-left' : 'text-right' }}">
                            <span x-text="(descriptionAr || '').length"></span> / 5000
                        </div>
                    </label>
                    <label class="block mt-4">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'الوصف (إنجليزي)' : 'Description (English)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <textarea name="description_en" x-model="descriptionEn" maxlength="5000" rows="5" dir="ltr"
                                      placeholder="Tell guests what makes your place special..."
                                      class="w-full bg-transparent outline-none resize-none text-[16px] text-[#222] py-4 px-5 leading-relaxed"></textarea>
                        </div>
                        <div class="mt-1.5 text-xs text-[#717171] tabular-nums {{ $isRtl ? 'text-left' : 'text-right' }}">
                            <span x-text="(descriptionEn || '').length"></span> / 5000
                        </div>
                    </label>

                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'عدد الضيوف الأقصى' : 'Max guests' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden inline-flex items-center" style="width: auto;">
                            <button type="button" @click="maxGuests = Math.max(1, (parseInt(maxGuests) || 1) - 1)"
                                    class="text-[#222] hover:bg-[#f7f7f7] flex items-center justify-center"
                                    style="width: 52px; height: 52px; font-size: 24px; line-height: 1; font-weight: 600;">−</button>
                            <input name="max_guests" x-model.number="maxGuests" type="number" min="1" max="50" inputmode="numeric"
                                   class="bg-transparent outline-none text-[17px] text-[#222] text-center tabular-nums"
                                   style="width: 80px; padding: 14px 0;">
                            <button type="button" @click="maxGuests = Math.min(50, (parseInt(maxGuests) || 0) + 1)"
                                    class="text-[#222] hover:bg-[#f7f7f7] flex items-center justify-center"
                                    style="width: 52px; height: 52px; font-size: 24px; line-height: 1; font-weight: 600;">+</button>
                        </div>
                        <p class="mt-2 text-[12px] text-[#717171] {{ $fa }}">
                            {{ $isRtl ? 'كم شخصاً يمكن أن يقيم في مكانك بشكل مريح؟' : 'How many guests can comfortably stay?' }}
                        </p>
                    </label>
                </section>

                {{-- ── Step 3: city ── --}}
                <section x-show="step === 3" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'في أي مدينة؟' : 'Which city?' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر مدينة وانتقل للتالي لاختيار الحي.' : 'Pick a city, then continue to choose the area.' }}</p>

                    <div class="mt-10 grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <template x-for="c in cities" :key="c.id">
                            <button type="button" @click="selectCity(c.id)"
                                    class="cursor-pointer r-ios-lg p-5 flex flex-col items-start gap-3 min-h-[120px] border-2 transition-all"
                                    :class="cityId === c.id
                                        ? 'border-[#222] bg-[#222] text-white shadow-card'
                                        : 'border-transparent bg-white shadow-card shadow-card-hover text-[#222] hover:border-[#222]'">
                                <div class="text-3xl" style="line-height: 1;" x-text="c.icon"></div>
                                <div class="font-semibold {{ $fa }}" x-text="c.label"></div>
                                <div class="text-xs {{ $fa }}"
                                     :class="cityId === c.id ? 'text-white/70' : 'text-[#717171]'">
                                    <span x-text="c.areas.length"></span>
                                    <span>{{ $isRtl ? 'حياً' : 'areas' }}</span>
                                </div>
                            </button>
                        </template>
                    </div>
                </section>

                {{-- ── Step 4: area (of the chosen city) ── --}}
                <section x-show="step === 4" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'أي حي؟' : 'Which area?' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر الحي داخل المدينة.' : 'Pick the area inside your city.' }}</p>

                    {{-- Selected-city context pill --}}
                    <div x-show="cityId" class="mt-6 inline-flex items-center gap-2 bg-[#fafafa] {{ $fa }}"
                         style="padding: 8px 14px; border-radius: 999px; border: 1px solid #ebebeb;">
                        <span class="text-base" x-text="selectedCity()?.icon"></span>
                        <span class="text-sm font-semibold text-[#222]" x-text="selectedCity()?.label"></span>
                    </div>

                    <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <template x-for="a in (selectedCity()?.areas || [])" :key="a.id">
                            <button type="button" @click="cityAreaId = a.id"
                                    class="cursor-pointer r-ios-lg p-4 text-start text-sm font-semibold transition-all border-2"
                                    :class="cityAreaId === a.id
                                        ? 'border-[#222] bg-[#222] text-white shadow-card'
                                        : 'border-transparent bg-white shadow-card text-[#222] hover:border-[#222]'"
                                    x-text="a.label"></button>
                        </template>
                    </div>

                    <input type="hidden" name="city_area_id" :value="cityAreaId || ''">

                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'رابط الموقع' : 'Location link' }} <span class="text-[#F88379]">*</span></span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="location_url" x-model="locationUrl" type="url" maxlength="2048" dir="ltr"
                                   placeholder="https://maps.google.com/..."
                                   class="w-full bg-transparent outline-none text-[15px] text-[#222] py-4 px-5 {{ $fa }}">
                        </div>
                        <p x-show="locationUrl.trim().length > 0 && !isValidLocationUrl()" x-cloak
                           class="mt-2 text-sm text-[#dc2626] {{ $fa }}">
                            {{ $isRtl ? 'الرجاء إدخال رابط صحيح يبدأ بـ http:// أو https://' : 'Please enter a valid link starting with http:// or https://' }}
                        </p>
                    </label>
                </section>

                {{-- ── Step 5: pricing ── --}}
                <section x-show="step === 5" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'التسعير' : 'Pricing' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'حدد سعراً أساسياً، ويمكنك تخصيص كل يوم.' : 'Set a base price, then optionally adjust per day.' }}</p>

                    <label class="block mt-10">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'السعر الأساسي (ريال / يوم)' : 'Base price (SAR / day)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <input name="price" x-model.number="price" type="number" min="0"
                                   class="w-full bg-transparent outline-none text-[17px] text-[#222] tabular-nums py-4 px-5" dir="ltr">
                        </div>
                    </label>

                    {{-- Live breakdown from the base price (per day): the price the
                         guest sees, Calm's cut, and what the host takes home. --}}
                    <div x-show="_basePrice > 0" x-cloak
                         class="mt-5 r-ios-lg border border-[#ebebeb] bg-[#fafafa] p-5 {{ $fa }}">
                        <div class="flex items-center justify-between text-[15px]">
                            <span class="text-[#717171]">{{ $isRtl ? 'السعر الظاهر للضيف / يوم' : 'Price shown to guest / day' }}</span>
                            <span class="font-bold text-[#222] tabular-nums" dir="ltr"><span x-text="money(_basePrice)"></span> {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                        </div>

                        <div class="flex items-center justify-between text-[15px] mt-3 pt-3 border-t border-[#ebebeb]">
                            <span class="text-[#717171] mt-10">{{ $isRtl ? 'كالم' : 'Calm' }} (<span x-text="commissionRate"></span>%)</span>
                            <span class="font-semibold mt-10 text-[#222] tabular-nums" dir="ltr"><span x-text="money(commissionAmount)"></span> {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                        </div>

                        <div class="flex items-center justify-between text-[15px] mt-2">
                            <span class="font-bold text-[#222]">{{ $isRtl ? 'لك' : 'You' }}</span>
                            <span class="font-bold text-[#10b981] tabular-nums" style="color: #10b981" dir="ltr"><span x-text="money(hostNet)"></span> {{ $isRtl ? 'ر.س' : 'SAR' }}</span>
                        </div>
                    </div>

                    <div class="mt-8">
                        <div class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'السعر لكل يوم (اختياري)' : 'Per-day pricing (optional)' }}</div>
                        <div class="text-xs text-[#717171] mt-1 {{ $fa }}">{{ $isRtl ? 'اتركه فارغاً لاستخدام السعر الأساسي.' : 'Leave empty to use the base price.' }}</div>
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                            @foreach($dayLabels as $day => $label)
                                @php $col = "price_{$day}"; @endphp
                                <div>
                                    <label class="block text-[11px] font-bold text-[#717171] uppercase tracking-wider text-center {{ $fa }}" style="margin-bottom: 4px;">{{ $label }}</label>
                                    <input type="number" name="{{ $col }}" x-model.number="dayPrices['{{ $day }}']" min="0"
                                           :placeholder="_basePrice > 0 ? money(_basePrice) : '0'"
                                           class="w-full bg-white border border-[#dddddd] focus:border-[#222] r-ios text-[14px] text-center tabular-nums py-2.5 px-2 placeholder:text-[#bbb]" dir="ltr">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ── Step 6: pick attributes (everything is a chip) ── --}}
                <section x-show="step === 6" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'مرافق وخصائص المكان' : "What's in your place?" }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'اختر ما يتوفر، وفصّل الكمية ووصف كل واحد في الخطوة التالية.' : "Pick what's available — you'll set counts and descriptions on the next step." }}</p>

                    <div class="mt-10 space-y-8">
                        {{-- Highlights: the most important amenities, surfaced first. They
                             still appear in their group below (selection stays in sync,
                             since both chips key off the same attribute id). --}}
                        <template x-if="highlightedAttributes.length">
                            <div>
                                <h3 class="text-base font-semibold text-[#222] mb-4 flex items-center gap-2 {{ $fa }}">
                                    <span>⭐</span><span>{{ $isRtl ? 'أبرز الخصائص' : 'Highlights' }}</span>
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="a in highlightedAttributes" :key="'hl-' + a.id">
                                        <button type="button"
                                                @click="toggleAttribute(a.id)"
                                                style="border-radius: 999px;"
                                                class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 border-2 text-sm font-semibold transition-all"
                                                :class="hasAttribute(a.id)
                                                    ? 'border-[#222] bg-[#222] text-white shadow-card'
                                                    : 'border-[#dddddd] bg-white text-[#222] hover:border-[#222]'">
                                            <span x-text="a.icon" class="text-base leading-none"></span>
                                            <span x-text="a.label" class="{{ $fa }}"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-for="group in attributeGroups" :key="group.id">
                            <div>
                                <h3 class="text-base font-semibold text-[#222] mb-4 {{ $fa }}" x-text="group.label"></h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="a in group.attributes" :key="a.id">
                                        <button type="button"
                                                @click="toggleAttribute(a.id)"
                                                style="border-radius: 999px;"
                                                class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 border-2 text-sm font-semibold transition-all"
                                                :class="hasAttribute(a.id)
                                                    ? 'border-[#222] bg-[#222] text-white shadow-card'
                                                    : 'border-[#dddddd] bg-white text-[#222] hover:border-[#222]'">
                                            {{-- Selection state is communicated entirely by the dark fill — no leading check mark, just the colour flip. --}}
                                            <span x-text="a.icon" class="text-base leading-none"></span>
                                            <span x-text="a.label" class="{{ $fa }}"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>

                {{-- ── Step 7: configure each chosen attribute (count + description) ── --}}
                <section x-show="step === 7" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'تفاصيل ما اخترته' : 'Tell us about what you picked' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'للعناصر المعدودة حدد الكمية، وأضف وصفاً قصيراً لكل عنصر إذا أحببت.' : 'Set counts for the countable ones and add a short description for any of them.' }}</p>

                    <div class="mt-10 space-y-4">
                        <template x-for="entry in selectedAttributesList()" :key="entry.id">
                            <div class="r-ios-lg bg-white shadow-card p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span x-text="entry.attribute.icon" class="text-2xl leading-none shrink-0"></span>
                                        <div class="font-semibold text-[#222] truncate {{ $fa }}" x-text="entry.attribute.label"></div>
                                    </div>

                                    {{-- Number-type attributes get a stepper here. Booleans don't. --}}
                                    <template x-if="entry.attribute.type === 'number'">
                                        <div class="flex items-center gap-3 shrink-0">
                                            <button type="button" @click="decrementCount(entry.id)" :disabled="entry.count <= 1"
                                                    class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed text-lg leading-none">−</button>
                                            <span class="w-6 text-center text-[18px] font-bold text-[#222] tabular-nums" x-text="entry.count"></span>
                                            <button type="button" @click="incrementCount(entry.id)"
                                                    class="cursor-pointer w-9 h-9 rounded-full border border-[#b0b0b0] text-[#222] hover:border-[#222] flex items-center justify-center text-lg leading-none">+</button>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-3 border border-[#ebebeb] focus-within:border-[#222] transition-all r-ios-lg overflow-hidden bg-[#fafafa]">
                                    <textarea x-model="selectedAttributes[entry.id].description" rows="2" maxlength="500"
                                              placeholder="{{ $isRtl ? 'اكتب وصفاً قصيراً (اختياري)...' : 'Add a short description (optional)...' }}"
                                              class="w-full bg-transparent outline-none resize-none text-[14px] text-[#222] py-3 px-4 leading-relaxed {{ $fa }}"></textarea>
                                </div>
                            </div>
                        </template>

                        {{-- Empty state if no attributes were picked in step 6 --}}
                        <div x-show="Object.keys(selectedAttributes).length === 0"
                             class="r-ios-lg bg-[#fafafa] p-8 text-center text-[#717171] text-sm {{ $fa }}">
                            {{ $isRtl ? 'لم تختر شيئاً في الخطوة السابقة. عُد للخلف وحدد ما يتوفر.' : "You didn't pick anything in the previous step. Go back to choose." }}
                        </div>
                    </div>

                    {{-- Hidden inputs that carry every chosen attribute back to the server --}}
                    <template x-for="entry in selectedAttributesList()" :key="`hid-${entry.id}`">
                        <div>
                            <input type="hidden" :name="`attributes[${entry.id}][attribute_id]`" :value="entry.id">
                            <input type="hidden" :name="`attributes[${entry.id}][value]`" :value="entry.count">
                            <input type="hidden" :name="`attributes[${entry.id}][description]`" :value="entry.description">
                        </div>
                    </template>
                </section>

                {{-- ── Step 8: photos (gallery order + "shown outside" showcase) ── --}}
                <section x-show="step === 8" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'صور المكان' : 'Photos of your place' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'أضف الصور، اسحبها لإعادة الترتيب أو نقلها بين الأقسام (أو استخدم الأسهم)، ثم اختر الصور التي تظهر في صفحة المكان.' : 'Add photos, drag to reorder or move them between sections (or use the arrows), then choose which appear on the place page.' }}</p>

                    {{-- Image rules: at least 5 overall, max 10 per section --}}
                    <div class="mt-4 inline-flex items-center text-[13px] font-semibold {{ $fa }}"
                         :class="totalDoneUploads() >= 5 ? 'text-[#16a34a]' : 'text-[#717171]'"
                         style="gap: 8px; padding: 8px 14px; border-radius: 999px; background-color: #fafafa;">
                        <span x-text="totalDoneUploads() >= 5 ? '✓' : '•'"></span>
                        <span x-text="`${totalDoneUploads()}/5`"></span>
                        <span>{{ $isRtl ? 'صور على الأقل · 10 كحد أقصى لكل قسم' : 'images minimum · 10 max per section' }}</span>
                    </div>
                    <p x-show="photoNotice" x-cloak x-text="photoNotice" class="mt-3 text-[13px] font-semibold text-[#F88379] {{ $fa }}"></p>

                    {{-- A. Per-attribute uploads — reorderable sections, reorderable photos within --}}
                    <div class="mt-10 space-y-4">
                        <template x-for="(entry, secIdx) in orderedPhotoSections()" :key="`up-${entry.id}`">
                            <div data-section-card class="relative r-ios-lg bg-white shadow-card p-5 border-2 transition-all"
                                 :class="(entry.attribute.photoRule === 'required' && uploadCountFor(entry.id, false) === 0 ? 'border-[#F88379]/40' : 'border-transparent')
                                     + (dnd.kind === 'photo' && dnd.overSection === entry.id ? ' ring-2 ring-[#F88379]/50' : (dnd.kind === 'photo' ? ' ring-2 ring-[#F88379]/15' : ''))
                                     + (dnd.kind === 'section' && dnd.id === entry.id ? ' opacity-50' : '')"
                                 @dragover.prevent="dnd.over = null; dnd.overSection = entry.id" @drop.prevent="dropOnSectionArea(entry.id)">
                                {{-- Section drop indicator (Trello-style insertion bar) --}}
                                <div x-show="dnd.kind === 'section' && dnd.overSection === entry.id && dnd.id !== entry.id"
                                     class="absolute -top-2.5 left-4 right-4 h-1.5 rounded-full bg-[#F88379] z-20 pointer-events-none"></div>
                                <div class="flex items-center justify-between mb-3 gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        {{-- Drag handle — reorder this amenity section --}}
                                        <button type="button" draggable="true" @dragstart.stop="beginSectionDrag($event, entry.id)" @dragend="dndEnd()"
                                                class="shrink-0 w-8 h-8 inline-flex items-center justify-center text-xl text-[#bbb] hover:text-[#222] cursor-grab active:cursor-grabbing"
                                                title="{{ $isRtl ? 'اسحب لإعادة ترتيب القسم' : 'Drag to reorder section' }}">⠿</button>
                                        <span x-text="entry.attribute.icon" class="text-2xl leading-none shrink-0"></span>
                                        <div class="font-semibold text-[#222] truncate {{ $fa }}" x-text="entry.attribute.label"></div>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        {{-- Section reorder: move this amenity earlier / later in the gallery --}}
                                        <button type="button" @click="moveSection(entry.id, -1)" :disabled="secIdx === 0"
                                                class="w-9 h-9 inline-flex items-center justify-center text-base font-bold text-[#222] bg-white shadow-card hover:bg-[#f7f7f7] active:scale-95 disabled:opacity-30 disabled:active:scale-100 transition-all"
                                                style="border-radius: 12px;" title="{{ $isRtl ? 'تقديم القسم' : 'Move section up' }}">↑</button>
                                        <button type="button" @click="moveSection(entry.id, 1)" :disabled="secIdx === orderedPhotoSections().length - 1"
                                                class="w-9 h-9 inline-flex items-center justify-center text-base font-bold text-[#222] bg-white shadow-card hover:bg-[#f7f7f7] active:scale-95 disabled:opacity-30 disabled:active:scale-100 transition-all"
                                                style="border-radius: 12px;" title="{{ $isRtl ? 'تأخير القسم' : 'Move section down' }}">↓</button>
                                        <span class="text-xs font-medium ml-1 {{ $fa }}"
                                              :class="entry.attribute.photoRule === 'required' ? 'text-[#F88379]' : 'text-[#717171]'">
                                            <span x-text="entry.attribute.photoRule === 'required'
                                                ? '{{ $isRtl ? 'مطلوب' : 'Required' }}'
                                                : '{{ $isRtl ? 'اختياري' : 'Optional' }}'"></span>
                                        </span>
                                    </div>
                                </div>

                                <label class="block relative cursor-pointer hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                                       style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 24px 16px; text-align: center;">
                                    <div class="text-3xl" style="line-height: 1;">📷</div>
                                    <div class="mt-2 text-sm font-semibold text-[#222]">{{ $isRtl ? 'أضف صوراً' : 'Add photos' }}</div>
                                    <div class="mt-1 text-xs text-[#717171]">{{ $isRtl ? 'يمكنك اختيار أكثر من صورة' : 'You can pick more than one' }}</div>
                                    <input type="file" accept="image/*" multiple
                                           @change="onAttributeFiles($event, entry.id)"
                                           class="absolute inset-0 opacity-0 cursor-pointer">
                                </label>

                                {{-- Upload tiles — reorder within the section --}}
                                <div class="mt-5 grid grid-cols-3 sm:grid-cols-4 gap-3 sm:gap-4" x-show="(attributeUploads[entry.id] || []).length > 0">
                                    <template x-for="(u, uIdx) in attributeUploads[entry.id] || []" :key="`au-${entry.id}-${u.id}`">
                                        <div class="relative group"
                                             :draggable="u.status === 'done' ? 'true' : 'false'"
                                             :style="u.status === 'done' ? 'cursor: grab;' : ''"
                                             @dragstart.stop="beginPhotoDrag($event, entry.id, u.id)" @dragend="dndEnd()"
                                             @dragover.prevent.stop="dnd.over = u.id" @drop.prevent.stop="dropOnPhoto(entry.id, u.id)">
                                            {{-- Drop indicator (Trello-style insertion bar) --}}
                                            <div x-show="dnd.kind === 'photo' && dnd.over === u.id && dnd.id !== u.id"
                                                 class="absolute inset-y-1 ltr:-left-2 rtl:-right-2 w-1.5 rounded-full bg-[#F88379] z-20 pointer-events-none"></div>
                                            <img :src="u.preview || u.url" class="block w-full aspect-square object-cover r-ios pointer-events-none transition-all"
                                                 :class="(u.status === 'uploading' ? 'opacity-40' : '') + (dnd.kind === 'photo' && dnd.id === u.id ? ' opacity-30 outline-2 outline-dashed outline-[#F88379] outline-offset-2' : '')">
                                            {{-- Featured rank badge (#1 = cover) --}}
                                            <span x-show="isFeatured(u.id)"
                                                  class="absolute top-2 ltr:left-2 rtl:right-2 inline-flex items-center gap-1 text-[10px] font-bold bg-[#F88379] text-white px-2 py-0.5 shadow {{ $fa }}"
                                                  style="border-radius: 999px;"
                                                  x-text="featuredRank(u.id) === 1 ? '★ {{ $isRtl ? 'غلاف' : 'COVER' }}' : ('#' + featuredRank(u.id))"></span>
                                            {{-- Status overlay --}}
                                            <div x-show="u.status === 'uploading'" class="absolute inset-0 flex items-center justify-center text-[11px] font-bold text-[#222] {{ $fa }}">
                                                {{ $isRtl ? '...جارٍ الرفع' : 'Uploading...' }}
                                            </div>
                                            <div x-show="u.status === 'failed'"
                                                 class="absolute inset-0 flex items-center justify-center text-[11px] font-bold bg-[#fef3f2]/90 text-[#b91c1c] {{ $fa }}">
                                                {{ $isRtl ? 'فشل الرفع' : 'Upload failed' }}
                                            </div>
                                            {{-- Hover toolbar: reorder ↑↓ + remove --}}
                                            <div class="absolute inset-x-2 bottom-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                                 x-show="u.status === 'done'">
                                                <button type="button" @click="moveUpload(attributeUploads[entry.id], u.id, -1)" :disabled="uIdx === 0"
                                                        class="text-[11px] font-bold bg-white text-[#222] w-7 h-7 inline-flex items-center justify-center shadow disabled:opacity-30 active:scale-95 transition-all"
                                                        style="border-radius: 999px;" title="{{ $isRtl ? 'تقديم' : 'Earlier' }}">↑</button>
                                                <button type="button" @click="moveUpload(attributeUploads[entry.id], u.id, 1)" :disabled="uIdx === (attributeUploads[entry.id].length - 1)"
                                                        class="text-[11px] font-bold bg-white text-[#222] w-7 h-7 inline-flex items-center justify-center shadow disabled:opacity-30 active:scale-95 transition-all"
                                                        style="border-radius: 999px;" title="{{ $isRtl ? 'تأخير' : 'Later' }}">↓</button>
                                                <button type="button" @click="removeAttributeUpload(entry.id, u.id)"
                                                        class="text-[11px] font-bold bg-white text-[#b91c1c] w-7 h-7 inline-flex items-center justify-center shadow ml-auto active:scale-95 transition-all"
                                                        style="border-radius: 999px;">✕</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div x-show="orderedPhotoSections().length === 0"
                             class="r-ios-lg bg-[#fafafa] p-6 text-center text-[#717171] text-sm {{ $fa }}">
                            {{ $isRtl ? 'لا يوجد عنصر مختار يتطلب صوراً — يمكنك المتابعة عبر إضافة صور عامة.' : "None of your picks need attribute-specific photos — use the general photos below if you'd like." }}
                        </div>
                    </div>

                    {{-- B. Extra (general) photos — not tied to any attribute. Also a
                         drop target so photos can be dragged out of any section. --}}
                    <div class="mt-10 transition-all" style="border-radius: 24px;"
                         :class="dnd.kind === 'photo' && dnd.overSection === 'extra' ? 'ring-2 ring-[#F88379]/50' : (dnd.kind === 'photo' ? 'ring-2 ring-[#F88379]/15' : '')"
                         @dragover.prevent="dnd.over = null; dnd.overSection = 'extra'" @drop.prevent="dropOnSectionArea('extra')">
                        <h3 class="text-lg font-bold text-[#222] mb-3 {{ $fa }}">{{ $isRtl ? 'صور إضافية' : 'More photos' }}</h3>
                        <p class="text-[13px] text-[#717171] mb-4 {{ $fa }}">{{ $isRtl ? 'صور للمكان غير مرتبطة بعنصر محدد.' : "Place photos that aren't tied to any specific attribute." }}</p>

                        <label class="block relative cursor-pointer hover:bg-[#f7f7f7] transition-colors {{ $fa }}"
                               style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 24px 16px; text-align: center;">
                            <div class="text-3xl" style="line-height: 1;">🖼️</div>
                            <div class="mt-2 text-sm font-semibold text-[#222]">{{ $isRtl ? 'أضف صوراً عامة' : 'Add general photos' }}</div>
                            <div class="mt-1 text-xs text-[#717171]">{{ $isRtl ? 'يمكنك اختيار أكثر من صورة' : 'You can pick more than one' }}</div>
                            <input type="file" accept="image/*" multiple
                                   @change="onExtraFiles($event)"
                                   class="absolute inset-0 opacity-0 cursor-pointer">
                        </label>

                        <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-3 sm:gap-4" x-show="extraUploads.length > 0">
                            <template x-for="(u, uIdx) in extraUploads" :key="`ex-${u.id}`">
                                <div class="relative group"
                                     :draggable="u.status === 'done' ? 'true' : 'false'"
                                     :style="u.status === 'done' ? 'cursor: grab;' : ''"
                                     @dragstart.stop="beginPhotoDrag($event, 'extra', u.id)" @dragend="dndEnd()"
                                     @dragover.prevent.stop="dnd.over = u.id" @drop.prevent.stop="dropOnPhoto('extra', u.id)">
                                    <div x-show="dnd.kind === 'photo' && dnd.over === u.id && dnd.id !== u.id"
                                         class="absolute inset-y-1 ltr:-left-2 rtl:-right-2 w-1.5 rounded-full bg-[#F88379] z-20 pointer-events-none"></div>
                                    <img :src="u.preview || u.url" class="block w-full aspect-square object-cover r-ios pointer-events-none transition-all"
                                         :class="(u.status === 'uploading' ? 'opacity-40' : '') + (dnd.kind === 'photo' && dnd.id === u.id ? ' opacity-30 outline-2 outline-dashed outline-[#F88379] outline-offset-2' : '')">
                                    <span x-show="isFeatured(u.id)"
                                          class="absolute top-2 ltr:left-2 rtl:right-2 inline-flex items-center gap-1 text-[10px] font-bold bg-[#F88379] text-white px-2 py-0.5 shadow {{ $fa }}"
                                          style="border-radius: 999px;"
                                          x-text="featuredRank(u.id) === 1 ? '★ {{ $isRtl ? 'غلاف' : 'COVER' }}' : ('#' + featuredRank(u.id))"></span>
                                    <div x-show="u.status === 'uploading'" class="absolute inset-0 flex items-center justify-center text-[11px] font-bold text-[#222] {{ $fa }}">
                                        {{ $isRtl ? '...جارٍ الرفع' : 'Uploading...' }}
                                    </div>
                                    <div x-show="u.status === 'failed'"
                                         class="absolute inset-0 flex items-center justify-center text-[11px] font-bold bg-[#fef3f2]/90 text-[#b91c1c] {{ $fa }}">
                                        {{ $isRtl ? 'فشل الرفع' : 'Upload failed' }}
                                    </div>
                                    <div class="absolute inset-x-2 bottom-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                         x-show="u.status === 'done'">
                                        <button type="button" @click="moveUpload(extraUploads, u.id, -1)" :disabled="uIdx === 0"
                                                class="text-[11px] font-bold bg-white text-[#222] w-7 h-7 inline-flex items-center justify-center shadow disabled:opacity-30 active:scale-95 transition-all"
                                                style="border-radius: 999px;" title="{{ $isRtl ? 'تقديم' : 'Earlier' }}">↑</button>
                                        <button type="button" @click="moveUpload(extraUploads, u.id, 1)" :disabled="uIdx === (extraUploads.length - 1)"
                                                class="text-[11px] font-bold bg-white text-[#222] w-7 h-7 inline-flex items-center justify-center shadow disabled:opacity-30 active:scale-95 transition-all"
                                                style="border-radius: 999px;" title="{{ $isRtl ? 'تأخير' : 'Later' }}">↓</button>
                                        <button type="button" @click="removeExtraUpload(u.id)"
                                                class="text-[11px] font-bold bg-white text-[#b91c1c] w-7 h-7 inline-flex items-center justify-center shadow ml-auto active:scale-95 transition-all"
                                                style="border-radius: 999px;">✕</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- C. "Shown outside" showcase — pick up to 10 for the place page; first = cover --}}
                    <div class="mt-12" x-show="totalDoneUploads() > 0">
                        <h3 class="text-lg font-bold text-[#222] mb-1 {{ $fa }}">{{ $isRtl ? 'الصور الظاهرة في صفحة المكان' : 'Photos shown on the place page' }}</h3>
                        <p class="text-[13px] text-[#717171] mb-4 {{ $fa }}">
                            {{ $isRtl ? 'اختر حتى ١٠ صور لعرضها في صفحة المكان. الأولى هي صورة الغلاف. إن لم تختر شيئاً سنعرض أول صورة.' : 'Pick up to 10 photos for the place page. The first is the cover. If you pick none, the first photo is used.' }}
                            <span class="font-semibold text-[#222]" x-text="`(${featured.length}/${featuredMax})`"></span>
                        </p>

                        {{-- Tap to add/remove from the showcase --}}
                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <template x-for="p in allDonePhotos()" :key="`feat-pick-${p.id}`">
                                <button type="button" @click="toggleFeatured(p.id)"
                                        class="relative block w-full aspect-square r-ios overflow-hidden border-2 transition-all"
                                        :class="isFeatured(p.id) ? 'border-[#F88379]' : (featured.length >= featuredMax ? 'border-transparent opacity-40' : 'border-transparent opacity-70 hover:opacity-100')">
                                    <img :src="p.preview || p.url" class="block w-full h-full object-cover">
                                    <span x-show="isFeatured(p.id)"
                                          class="absolute top-1 ltr:left-1 rtl:right-1 w-6 h-6 inline-flex items-center justify-center text-[11px] font-bold bg-[#F88379] text-white {{ $fa }}"
                                          style="border-radius: 999px;"
                                          x-text="featuredRank(p.id) === 1 ? '★' : featuredRank(p.id)"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Ordered showcase strip — reorder / drop --}}
                        <div x-show="featured.length > 0" class="mt-6 space-y-3">
                            <template x-for="(fid, fIdx) in featured" :key="`fo-${fid}`">
                                <div class="relative flex items-center gap-3 r-ios-lg bg-white shadow-card p-3 transition-all"
                                     draggable="true"
                                     @dragstart.stop="beginFeaturedDrag($event, fid)" @dragend="dndEnd()"
                                     @dragover.prevent.stop="dnd.overFeatured = fid" @drop.prevent.stop="dropOnFeatured(fid)"
                                     :class="dnd.kind === 'featured' && dnd.id === fid ? 'opacity-50 outline-2 outline-dashed outline-[#F88379]' : ''"
                                     style="cursor: grab;">
                                    {{-- Drop indicator line --}}
                                    <div x-show="dnd.kind === 'featured' && dnd.overFeatured === fid && dnd.id !== fid"
                                         class="absolute -top-2 left-3 right-3 h-1.5 rounded-full bg-[#F88379] z-20 pointer-events-none"></div>
                                    <span class="text-xl text-[#bbb] shrink-0 select-none" title="{{ $isRtl ? 'اسحب لإعادة الترتيب' : 'Drag to reorder' }}">⠿</span>
                                    <span class="w-7 text-center text-sm font-bold shrink-0"
                                          :class="fIdx === 0 ? 'text-[#F88379]' : 'text-[#717171]'"
                                          x-text="fIdx === 0 ? '★' : (fIdx + 1)"></span>
                                    <img :src="photoById(fid)?.preview || photoById(fid)?.url" class="w-16 h-16 object-cover r-ios shrink-0 pointer-events-none">
                                    <span class="text-sm font-medium text-[#717171] truncate flex-1 {{ $fa }}"
                                          x-text="fIdx === 0 ? '{{ $isRtl ? 'صورة الغلاف' : 'Cover photo' }}' : '{{ $isRtl ? 'صورة معروضة' : 'Shown' }}'"></span>
                                    <button type="button" @click="moveFeatured(fid, -1)" :disabled="fIdx === 0"
                                            class="w-9 h-9 inline-flex items-center justify-center text-base font-bold text-[#222] bg-white shadow-card hover:bg-[#f7f7f7] active:scale-95 disabled:opacity-30 disabled:active:scale-100 transition-all"
                                            style="border-radius: 12px;" title="{{ $isRtl ? 'تقديم' : 'Earlier' }}">↑</button>
                                    <button type="button" @click="moveFeatured(fid, 1)" :disabled="fIdx === featured.length - 1"
                                            class="w-9 h-9 inline-flex items-center justify-center text-base font-bold text-[#222] bg-white shadow-card hover:bg-[#f7f7f7] active:scale-95 disabled:opacity-30 disabled:active:scale-100 transition-all"
                                            style="border-radius: 12px;" title="{{ $isRtl ? 'تأخير' : 'Later' }}">↓</button>
                                    <button type="button" @click="toggleFeatured(fid)"
                                            class="w-9 h-9 inline-flex items-center justify-center text-base font-bold text-[#b91c1c] bg-white shadow-card hover:bg-[#fef3f2] active:scale-95 transition-all"
                                            style="border-radius: 12px;">✕</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Hidden inputs — form-submit payload. Attribute groups POST in
                         the host's section order so sort_order follows the gallery. --}}
                    <template x-for="entry in orderedPhotoSections()" :key="`aphid-${entry.id}`">
                        <div>
                            <template x-for="(u, uIdx) in (attributeUploads[entry.id] || []).filter(x => x.status === 'done')"
                                      :key="`aphidv-${entry.id}-${u.id}`">
                                <input type="hidden" :name="`attribute_image_paths[${entry.id}][${uIdx}]`" :value="u.path">
                            </template>
                        </div>
                    </template>
                    <template x-for="(u, uIdx) in extraUploads.filter(x => x.status === 'done')" :key="`exhid-${u.id}`">
                        <input type="hidden" :name="`extra_image_paths[${uIdx}]`" :value="u.path">
                    </template>
                    <template x-for="(marker, fIdx) in featuredMarkers()" :key="`feathid-${fIdx}`">
                        <input type="hidden" :name="`featured[${fIdx}]`" :value="marker">
                    </template>
                </section>

                {{-- ── Step 9: check-in/out + rules + submit ── --}}
                <section x-show="step === 9" x-transition.opacity>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'تفاصيل الإقامة' : 'House rules & timing' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'الوقت والقواعد التي تحدد تجربة الضيوف.' : 'Set the timing and the rules guests should follow.' }}</p>

                    @php
                        // 24 hourly options — value is the 24h wire format we store
                        // (HH:00), label is the 12h presentation the host picks from.
                        $hours = [];
                        for ($h = 0; $h < 24; $h++) {
                            $value = sprintf('%02d:00', $h);
                            $period = $h < 12 ? 'AM' : 'PM';
                            $hour12 = $h === 0 ? 12 : ($h > 12 ? $h - 12 : $h);
                            $hours[$value] = sprintf('%d:00 %s', $hour12, $period);
                        }
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-10">
                        <label>
                            <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'وقت الوصول' : 'Check-in' }}</span>
                            <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <select name="check_in_time" x-model="checkInTime" dir="ltr"
                                        class="w-full bg-transparent outline-none text-[16px] tabular-nums text-[#222] py-4 px-5 cursor-pointer">
                                    @foreach($hours as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <span class="block mt-2 text-[12px] text-[#717171] {{ $fa }}">{{ $isRtl ? 'الوصول دائماً في أول يوم من الحجز.' : "Check-in is always on the booking's first day." }}</span>
                        </label>
                        <label>
                            <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'وقت المغادرة' : 'Check-out' }}</span>
                            <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <select name="check_out_time" x-model="checkOutTime" dir="ltr"
                                        class="w-full bg-transparent outline-none text-[16px] tabular-nums text-[#222] py-4 px-5 cursor-pointer">
                                    @foreach($hours as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="flex items-start mt-3 cursor-pointer" style="gap: 10px;">
                                {{-- Hidden 0 + checkbox 1 so the form always posts a real boolean
                                     (an unchecked checkbox sends nothing). --}}
                                <input type="hidden" name="checkout_next_day" value="0">
                                <input type="checkbox" name="checkout_next_day" value="1" x-model="checkoutNextDay" class="mt-0.5 w-4 h-4 accent-[#F88379] shrink-0">
                                <span class="text-[12px] text-[#717171] {{ $fa }}">{{ $isRtl ? 'المغادرة في صباح اليوم التالي لنهاية الحجز (إقامة ليلية). ألغِ التحديد إذا كانت المغادرة في نفس يوم نهاية الحجز.' : 'Checkout is the morning after the booking ends (overnight stay). Uncheck if checkout is the same day the booking ends.' }}</span>
                            </label>
                        </label>
                    </div>

                    <label class="block mt-8">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'قواعد المكان — عربي (اختياري)' : 'House rules — Arabic (optional)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg">
                            <textarea name="rules_ar" x-model="rulesAr" rows="6" maxlength="5000" dir="rtl"
                                      placeholder="مثلاً: ممنوع التدخين، الحفلات بإذن مسبق..."
                                      class="w-full bg-transparent outline-none resize-y text-[15px] text-[#222] py-4 px-5 leading-relaxed font-arabic"
                                      style="min-height: 140px; max-height: 70vh;"></textarea>
                        </div>
                    </label>
                    <label class="block mt-4">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'قواعد المكان — إنجليزي (اختياري)' : 'House rules — English (optional)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg">
                            <textarea name="rules_en" x-model="rulesEn" rows="6" maxlength="5000" dir="ltr"
                                      placeholder="e.g. No smoking, no parties without prior approval..."
                                      class="w-full bg-transparent outline-none resize-y text-[15px] text-[#222] py-4 px-5 leading-relaxed"
                                      style="min-height: 140px; max-height: 70vh;"></textarea>
                        </div>
                    </label>
                </section>

                {{-- ── Admin settings step (admins only, edit mode) ── --}}
                <section x-show="editing && isAdmin && step === totalSteps" x-transition.opacity x-cloak>
                    <h2 class="text-3xl sm:text-[34px] font-bold tracking-tight text-[#222] {{ $fa }}">{{ $isRtl ? 'إعدادات المشرف' : 'Admin settings' }}</h2>
                    <p class="mt-2 text-[#717171] text-base {{ $fa }}">{{ $isRtl ? 'القوائم المميزة وحالة الإعلان — تظهر للمشرفين فقط.' : 'Featured lists and listing status — visible to admins only.' }}</p>

                    {{-- Featured-list membership --}}
                    <div class="mt-10">
                        <h3 class="text-lg font-bold text-[#222] mb-3 {{ $fa }}">{{ $isRtl ? 'القوائم المميزة' : 'Featured lists' }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="l in adminLists" :key="`adminlist-${l.id}`">
                                <label class="flex items-center gap-3 r-ios-lg bg-white shadow-card p-4 cursor-pointer border-2 transition-all"
                                       :class="selectedLists.includes(l.id) ? 'border-[#222]' : 'border-transparent'">
                                    <input type="checkbox" :value="l.id" :checked="selectedLists.includes(l.id)" @change="toggleList(l.id)" class="sr-only">
                                    <span class="text-sm font-semibold text-[#222] {{ $fa }}" x-text="l.label"></span>
                                </label>
                            </template>
                        </div>
                        <div x-show="adminLists.length === 0" class="r-ios-lg bg-[#fafafa] p-5 text-center text-[#717171] text-sm {{ $fa }}">
                            {{ $isRtl ? 'لا توجد قوائم مميزة بعد.' : 'No featured lists yet.' }}
                        </div>
                        <template x-for="id in selectedLists" :key="`listhid-${id}`">
                            <input type="hidden" name="lists[]" :value="id">
                        </template>
                    </div>

                    {{-- Status + review controls (admin keeps full control) --}}
                    <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label>
                            <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'حالة الإعلان' : 'Active status' }}</span>
                            <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <select name="status" x-model="statusValue" class="w-full bg-transparent outline-none text-[15px] text-[#222] py-4 px-5 cursor-pointer {{ $fa }}">
                                    <option value="active">{{ $isRtl ? 'مفعّل' : 'Active' }}</option>
                                    <option value="inactive">{{ $isRtl ? 'موقوف' : 'Inactive' }}</option>
                                </select>
                            </div>
                        </label>
                        <label>
                            <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'حالة المراجعة' : 'Review status' }}</span>
                            <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                                <select name="review_status" x-model="reviewValue" class="w-full bg-transparent outline-none text-[15px] text-[#222] py-4 px-5 cursor-pointer {{ $fa }}">
                                    <option value="draft">{{ $isRtl ? 'مسودة' : 'Draft' }}</option>
                                    <option value="pending_review">{{ $isRtl ? 'قيد المراجعة' : 'Pending review' }}</option>
                                    <option value="approved">{{ $isRtl ? 'موافق عليه' : 'Approved' }}</option>
                                    <option value="rejected">{{ $isRtl ? 'مرفوض' : 'Rejected' }}</option>
                                </select>
                            </div>
                        </label>
                    </div>
                    <label class="block mt-4">
                        <span class="text-sm font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'سبب الرفض (اختياري)' : 'Rejection reason (optional)' }}</span>
                        <div class="mt-3 border border-[#dddddd] focus-within:border-[#222] transition-all bg-white shadow-card r-ios-lg overflow-hidden">
                            <textarea name="rejection_reason" x-model="rejectionReason" rows="3" maxlength="2000"
                                      class="w-full bg-transparent outline-none resize-none text-[15px] text-[#222] py-4 px-5 leading-relaxed {{ $fa }}"></textarea>
                        </div>
                    </label>
                </section>

                {{-- ── Nav buttons (create flow) ── --}}
                <div class="mt-12 flex items-center justify-between gap-4" x-show="!editing">
                    <button type="button" @click="back" x-show="step > 1"
                            class="px-6 py-3 text-[#717171] hover:text-[#222] font-semibold transition-colors {{ $fa }}">
                        {{ $isRtl ? 'السابق →' : '← Back' }}
                    </button>
                    <div x-show="step === 1"></div>

                    <button type="button" @click="next" x-show="step < totalSteps" :disabled="!canAdvance() || draftSaving"
                            class="inline-flex items-center justify-center font-bold text-white bg-[#222] hover:bg-black disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.98] transition-all {{ $fa }}"
                            style="padding: 14px 28px; border-radius: 16px;">
                        {{-- Slot opens (width + margin + opacity) when saveDraft is in flight,
                             sliding the SVG spinner in next to the Continue label rather than replacing it. --}}
                        <span class="calm-spinner-slot" :class="{ 'is-active': draftSaving }" aria-hidden="true">
                            <svg class="calm-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                                <path d="M22 12a10 10 0 0 1-10 10"/>
                            </svg>
                        </span>
                        <span>{{ $isRtl ? 'التالي' : 'Continue' }}</span>
                    </button>

                    <button type="submit" x-show="step === totalSteps" :disabled="submitting || !canAdvance()"
                            class="font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.98] transition-all {{ $fa }}"
                            style="padding: 14px 28px; border-radius: 16px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                        <span x-show="!submitting">{{ $isRtl ? 'إنشاء المكان' : 'Create place' }}</span>
                        <span x-show="submitting" x-cloak>{{ $isRtl ? 'جاري الإنشاء...' : 'Creating...' }}</span>
                    </button>
                </div>

                {{-- ── Sticky action bar (edit flow) ── --}}
                <div x-show="editing" x-cloak
                     class="sticky bottom-0 z-20 mt-12 -mx-5 sm:-mx-8 px-5 sm:px-8 py-4 bg-white/95 backdrop-blur border-t border-[#ebebeb] flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="back" :disabled="step === 1"
                                class="inline-flex items-center font-semibold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] disabled:opacity-30 transition-all {{ $fa }}"
                                style="padding: 11px 18px; border-radius: 14px;">{{ $isRtl ? 'السابق →' : '← Back' }}</button>
                        <button type="button" @click="editNext" :disabled="step >= totalSteps"
                                class="inline-flex items-center font-semibold text-[#222] bg-[#f3f4f6] hover:bg-[#e9eaec] disabled:opacity-30 transition-all {{ $fa }}"
                                style="padding: 11px 18px; border-radius: 14px;">{{ $isRtl ? '← التالي' : 'Next →' }}</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="discard()"
                                class="inline-flex items-center font-semibold text-[#b91c1c] hover:bg-[#fef2f2] transition-all {{ $fa }}"
                                style="padding: 11px 16px; border-radius: 14px;">{{ $isRtl ? 'تجاهل التغييرات' : 'Discard' }}</button>
                        <a :href="editCancelUrl"
                           class="inline-flex items-center font-semibold text-[#717171] hover:text-[#222] border border-[#ebebeb] transition-all {{ $fa }}"
                           style="padding: 11px 18px; border-radius: 14px;">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
                        <button type="submit" :disabled="submitting"
                                class="inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#f56b60] disabled:bg-[#dddddd] disabled:cursor-not-allowed active:scale-[0.98] transition-all {{ $fa }}"
                                style="padding: 11px 24px; border-radius: 14px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                            <span x-show="!submitting">{{ $isRtl ? 'حفظ' : 'Save' }}</span>
                            <span x-show="submitting" x-cloak>{{ $isRtl ? 'جاري الحفظ...' : 'Saving...' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function registerWizard() {
    const init = JSON.parse(document.getElementById('register-init-data').textContent);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return {
        step: 1,
        totalSteps: 9,
        submitting: false,
        draftSaving: false,
        draftError: '',

        // Edit mode (null = fresh "add" flow). Pre-fills, enables step jumping
        // + sticky Save/Discard/Cancel, and (for admins) an extra settings step.
        editing: !!(init.edit && init.edit.enabled),
        isAdmin: !!(init.edit && init.edit.isAdmin),
        editCancelUrl: init.edit?.cancelUrl || '',
        adminLists: init.edit?.lists || [],
        selectedLists: [...(init.edit?.selectedListIds || [])],
        statusValue: init.edit?.status || 'inactive',
        reviewValue: init.edit?.reviewStatus || 'draft',
        rejectionReason: init.edit?.rejectionReason || '',

        // Catalog data from the backend
        placeTypes: init.placeTypes,
        cities: init.cities,
        attributeGroups: init.attributeGroups,
        highlightedAttributes: init.highlightedAttributes || [],
        draftEndpoint: init.draftEndpoint,

        // Form state
        draftId: null,                  // server-assigned once step 1 advances
        // Admin-only: phone the listing should attach to. Stays '' for hosts
        // (the field isn't rendered) so saveDraft() sends an empty string the
        // server ignores. Form submit also posts this via the visible input.
        hostPhone: '',
        placeTypeId: null,
        titleAr: '',
        titleEn: '',
        descriptionAr: '',
        descriptionEn: '',
        cityId: null,                   // intermediate — picked first
        cityAreaId: null,               // sent to server
        price: 0,
        // Per-day overrides. Empty means "use the base price" — the input shows
        // the base as a placeholder so every day visibly reflects it. A typed
        // value overrides just that day. (Stored 0/null loads back as empty.)
        dayPrices: {
            sunday: '', monday: '', tuesday: '', wednesday: '', thursday: '', friday: '', saturday: '',
        },

        // Commission percentage from settings — drives the pricing preview.
        commissionRate: Number(init.rates?.commission ?? 15),

        // Pricing preview (per day), from the base price.
        get _basePrice() { return Number(this.price) || 0; },
        get commissionAmount() { return this._basePrice * this.commissionRate / 100; },   // Calm's cut
        get hostNet() { return this._basePrice - this.commissionAmount; },                 // host take-home
        money(n) { return (Math.round(n * 100) / 100).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        checkInTime: '15:00',
        checkOutTime: '12:00',
        checkoutNextDay: true,
        // Default to 1 — UI starts at the minimum so the +/− stepper has
        // something sensible to mutate. Required at final submit.
        maxGuests: 1,
        // Pre-filled default rules (both languages) for a new place; overridden
        // by saved rules on draft-resume / edit (see init.draft below).
        rulesAr: @js($defaultRulesAr),
        rulesEn: @js($defaultRulesEn),
        // Map link (Google Maps, etc.) the host pastes; shown to guests only
        // after their booking is confirmed.
        locationUrl: '',
        // attribute_id → { count, description }
        selectedAttributes: {},

        // Upload pipeline state (master's pattern). Each entry is
        // { id, status: 'uploading'|'done'|'failed', preview, path, url, name, error? }
        attributeUploads: {},   // attribute_id → list
        extraUploads: [],       // general photos, no attribute
        photoSectionMax: 10,    // hard cap of images per section (incl. "other")
        photoMinTotal: 5,       // a place must have at least this many overall
        photoNotice: '',        // transient "limit reached" message
        featured: [],           // ordered upload ids "shown outside" (first = cover)
        featuredMax: 10,        // cap on the showcase set
        sectionOrder: [],       // attribute_ids in host-chosen gallery section order
        uploadCounter: 0,       // monotonic id source for upload rows

        init() {
            // Resume a draft if the controller handed us one via `?draft=<id>`.
            if (init.draft) {
                this.placeTypeId = init.draft.place_type_id;
                this.titleAr       = init.draft.title_ar || '';
                this.titleEn       = init.draft.title_en || '';
                this.descriptionAr = init.draft.description_ar || '';
                this.descriptionEn = init.draft.description_en || '';
                this.cityId      = init.draft.city_id || null;
                this.cityAreaId  = init.draft.city_area_id || null;
                // Per-day prices fall back to the base price server-side when left
                // at 0. Load real overrides; show 0/null as empty so the base
                // placeholder takes over and nothing reads as "priced at zero".
                this.price       = init.draft.price || 0;
                for (const [day, val] of Object.entries(init.draft.day_prices || {})) {
                    this.dayPrices[day] = Number(val) > 0 ? Number(val) : '';
                }
                this.checkInTime  = init.draft.check_in_time || '15:00';
                this.checkOutTime = init.draft.check_out_time || '12:00';
                this.checkoutNextDay = init.draft.checkout_next_day ?? true;
                this.maxGuests    = init.draft.max_guests || 1;
                this.rulesAr      = init.draft.rules_ar || '';
                this.rulesEn      = init.draft.rules_en || '';
                this.locationUrl  = init.draft.location_url || '';
                this.draftId      = init.draft.id;

                // Restore selected attributes — keyed by attribute_id, value = count, plus description.
                (init.draft.attributes || []).forEach((a) => {
                    this.selectedAttributes[a.attribute_id] = {
                        count: Number(a.value) || 1,
                        description: a.description || '',
                    };
                });

                // Restore uploaded photos into the same in-memory shape the upload
                // pipeline builds. `status: 'done'` so they render as fully-uploaded
                // and persist back through the next saveDraft() round-trip.
                // Photos arrive ordered by sort_order, so pushing in sequence
                // restores both the within-section order and (via first-seen)
                // the gallery section order.
                const featuredRows = [];
                (init.draft.photos || []).forEach((p) => {
                    const id = ++this.uploadCounter;
                    const entry = { id, status: 'done', preview: p.url, path: p.path, url: p.url, name: '' };
                    if (p.place_attribute_id != null) {
                        if (!this.attributeUploads[p.place_attribute_id]) this.attributeUploads[p.place_attribute_id] = [];
                        this.attributeUploads[p.place_attribute_id].push(entry);
                        if (!this.sectionOrder.includes(p.place_attribute_id)) this.sectionOrder.push(p.place_attribute_id);
                    } else {
                        this.extraUploads.push(entry);
                    }
                    if (p.featured_order !== null && p.featured_order !== undefined) {
                        featuredRows.push({ id, order: p.featured_order });
                    }
                });
                // Rebuild the showcase order from the stored featured_order.
                this.featured = featuredRows.sort((a, b) => a.order - b.order).map((r) => r.id);

                // Resume at the right step. Priority order:
                //   1. ?step=N on the URL (set by saveDraft via history.replaceState,
                //      and by the language switch's back()-with-Referer round-trip)
                //   2. The draft row's persisted `last_step` column (set every
                //      time saveDraft runs server-side), used when the host
                //      clicks "Continue" from /my-places without a step in the URL
                //   3. Step 1 as a final fallback
                const urlStep = parseInt(new URLSearchParams(window.location.search).get('step') || '0', 10);
                const persistedStep = parseInt(init.draft.last_step || '0', 10);
                const resumeStep = urlStep || persistedStep || 1;
                if (resumeStep >= 1 && resumeStep <= this.totalSteps) {
                    this.step = resumeStep;
                }
            }

            // Edit mode: admins get an extra "Admin settings" step; always
            // start at step 1 (the host is reviewing, not resuming a draft).
            if (this.editing) {
                this.totalSteps = this.isAdmin ? 10 : 9;
                this.step = 1;
            }
        },

        // ── Navigation
        async next() {
            if (this.step >= this.totalSteps || !this.canAdvance()) return;
            if (!this.editing) await this.saveDraft();   // create flow auto-saves; edit doesn't
            this.step++;
            this.syncUrl();
        },
        back() {
            if (this.step > 1) {
                this.step--;
                this.syncUrl();
            }
        },
        // ── Edit-mode navigation (free jumping + sticky bar)
        stepLabels() {
            const labels = @json($wizardStepLabels);
            if (this.isAdmin) labels.push(@json($wizardAdminLabel));
            return labels.slice(0, this.totalSteps);
        },
        goToStep(n) {
            if (!this.editing) return;
            this.step = Math.min(Math.max(1, n), this.totalSteps);
        },
        editNext() { this.goToStep(this.step + 1); },
        discard() {
            const msg = @json($isRtl ? 'تجاهل كل التغييرات غير المحفوظة؟' : 'Discard all unsaved changes?');
            if (window.confirm(msg)) window.location.reload();
        },
        toggleList(id) {
            const i = this.selectedLists.indexOf(id);
            if (i >= 0) this.selectedLists.splice(i, 1);
            else this.selectedLists.push(id);
        },
        /**
         * Reflect (draftId, step) in the address bar via history.replaceState
         * so a page reload — e.g. the language switch which POSTs and then
         * `back()`s — lands on the same draft + same step instead of starting
         * the wizard over from step 1. Only mutates the URL; no nav.
         */
        syncUrl() {
            if (!this.draftId) return;
            const url = new URL(window.location.href);
            url.searchParams.set('draft', String(this.draftId));
            url.searchParams.set('step',  String(this.step));
            window.history.replaceState({}, '', url.toString());
        },

        /**
         * Per-step rules. Returns true if the host is allowed to advance from
         * the current step. Step 8 is the final one — its rule gates `submit`.
         */
        canAdvance() {
            switch (this.step) {
                case 1: return !!this.placeTypeId;
                case 2: return this.titleAr.trim().length > 0 || this.titleEn.trim().length > 0;
                case 3: return !!this.cityId;          // city pick
                case 4: return !!this.cityAreaId && this.isValidLocationUrl(); // area pick + valid location link
                case 5: return Number(this.price) > 0; // pricing
                case 6: return Object.keys(this.selectedAttributes).length > 0;        // attribute pick
                case 7: return this.selectedAttributesList().every((e) => e.count >= 1); // configure
                case 8: return this.totalDoneUploads() >= this.photoMinTotal           // ≥5 images overall
                    && this.photoNeedingAttributes()
                        .filter((e) => e.attribute.photoRule === 'required')
                        .every((e) => this.uploadCountFor(e.id, true) > 0);            // photos

                case 9: return this.checkInTime.trim().length > 0 && this.checkOutTime.trim().length > 0;
                default: return true;
            }
        },

        /** A pasted map link must be a valid http(s) URL — matches the server 'url' rule. */
        isValidLocationUrl() {
            const v = (this.locationUrl || '').trim();
            if (v.length === 0) return false;
            try {
                const u = new URL(v);
                return u.protocol === 'http:' || u.protocol === 'https:';
            } catch (e) {
                return false;
            }
        },

        // ── City (step 3)
        selectCity(id) {
            // Re-picking the same city keeps the area choice; switching cities clears it.
            if (this.cityId !== id) {
                this.cityAreaId = null;
            }
            this.cityId = id;
        },
        selectedCity() {
            return this.cities.find((c) => c.id === this.cityId) || null;
        },

        // ── Attributes (selection — step 6)
        hasAttribute(id) {
            // Direct key access goes through Alpine's reactive proxy `get` trap,
            // so the UI updates on toggleAttribute(). `hasOwnProperty.call` did
            // NOT trigger reactivity → the chip's selected style stuck stale.
            return this.selectedAttributes[id] !== undefined;
        },
        toggleAttribute(id) {
            if (this.hasAttribute(id)) {
                delete this.selectedAttributes[id];
                delete this.photoPreviews[id];
            } else {
                this.selectedAttributes[id] = { count: 1, description: '' };
            }
        },

        // ── Attributes (configure — step 7)
        attributeById(id) {
            for (const g of this.attributeGroups) {
                const a = g.attributes.find((x) => x.id === id);
                if (a) return a;
            }
            return null;
        },
        selectedAttributesList() {
            return Object.keys(this.selectedAttributes).map((rawId) => {
                // rawId is the UUID string key from selectedAttributes; keep it as a string.
                const id = rawId;
                const entry = this.selectedAttributes[rawId];
                return {
                    id,
                    attribute: this.attributeById(id),
                    count: entry.count,
                    description: entry.description,
                };
            }).filter((e) => e.attribute);
        },
        incrementCount(id) {
            if (this.selectedAttributes[id]) {
                this.selectedAttributes[id].count = (this.selectedAttributes[id].count || 1) + 1;
            }
        },
        decrementCount(id) {
            if (this.selectedAttributes[id] && this.selectedAttributes[id].count > 1) {
                this.selectedAttributes[id].count -= 1;
            }
        },

        // ── Photos (step 8) — presign-then-PUT pipeline mirroring master/hosts.
        photoNeedingAttributes() {
            return this.selectedAttributesList().filter(
                (e) => e.attribute.photoRule === 'required' || e.attribute.photoRule === 'optional'
            );
        },
        uploadCountFor(attributeId, doneOnly = true) {
            const list = this.attributeUploads[attributeId] || [];
            return doneOnly ? list.filter((u) => u.status === 'done').length : list.length;
        },
        totalDoneUploads() {
            let n = this.extraUploads.filter((u) => u.status === 'done').length;
            Object.keys(this.attributeUploads).forEach((k) => {
                n += (this.attributeUploads[k] || []).filter((u) => u.status === 'done').length;
            });
            return n;
        },
        /**
         * The composite key the server expects for a single upload:
         *   - `attribute_images.<attrId>.<i>` for an attribute upload
         *   - `extra_images.<i>` for a general upload
         * `i` is the position within that group's `.filter(status==='done')`
         * list — the exact shape the hidden inputs POST, so featured markers
         * line up with the stored paths regardless of section order.
         */
        markerFor(uploadId) {
            const attrIds = Object.keys(this.attributeUploads);
            for (const aid of attrIds) {
                const done = (this.attributeUploads[aid] || []).filter((u) => u.status === 'done');
                const idx = done.findIndex((u) => u.id === uploadId);
                if (idx >= 0) return `attribute_images.${aid}.${idx}`;
            }
            const extras = this.extraUploads.filter((u) => u.status === 'done');
            const idx = extras.findIndex((u) => u.id === uploadId);
            if (idx >= 0) return `extra_images.${idx}`;
            return '';
        },
        // Find a done upload row by id across attribute + extra lists.
        photoById(uploadId) {
            for (const aid of Object.keys(this.attributeUploads)) {
                const row = (this.attributeUploads[aid] || []).find((u) => u.id === uploadId);
                if (row) return row;
            }
            return this.extraUploads.find((u) => u.id === uploadId) || null;
        },
        // Every done photo, flat, in gallery order (sections → within → extras last).
        allDonePhotos() {
            const out = [];
            this.orderedPhotoSections().forEach((entry) => {
                (this.attributeUploads[entry.id] || [])
                    .filter((u) => u.status === 'done')
                    .forEach((u) => out.push(u));
            });
            this.extraUploads.filter((u) => u.status === 'done').forEach((u) => out.push(u));
            return out;
        },
        // ── Featured ("shown outside") showcase
        isFeatured(id) { return this.featured.includes(id); },
        featuredRank(id) { return this.featured.indexOf(id) + 1; }, // 1-based; 0 = not featured
        toggleFeatured(id) {
            const i = this.featured.indexOf(id);
            if (i >= 0) { this.featured.splice(i, 1); return; }
            if (this.featured.length >= this.featuredMax) return;
            this.featured.push(id);
        },
        moveFeatured(id, dir) {
            const i = this.featured.indexOf(id);
            const j = i + dir;
            if (i < 0 || j < 0 || j >= this.featured.length) return;
            [this.featured[i], this.featured[j]] = [this.featured[j], this.featured[i]];
        },
        /**
         * Featured markers in showcase order. If the host picked none, default
         * to the first gallery photo so the place page always has a cover.
         */
        featuredMarkers() {
            let ids = this.featured.filter((id) => this.markerFor(id) !== '');
            if (ids.length === 0) {
                const first = this.allDonePhotos()[0];
                if (first) ids = [first.id];
            }
            return ids.map((id) => this.markerFor(id)).filter((m) => m !== '');
        },
        // ── Gallery section ordering
        syncSectionOrder() {
            const needing = this.photoNeedingAttributes();
            needing.forEach((e) => { if (!this.sectionOrder.includes(e.id)) this.sectionOrder.push(e.id); });
            this.sectionOrder = this.sectionOrder.filter((id) => needing.some((e) => e.id === id));
        },
        orderedPhotoSections() {
            this.syncSectionOrder();
            const order = this.sectionOrder;
            return [...this.photoNeedingAttributes()].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
        },
        moveSection(id, dir) {
            this.syncSectionOrder();
            const i = this.sectionOrder.indexOf(id);
            const j = i + dir;
            if (i < 0 || j < 0 || j >= this.sectionOrder.length) return;
            [this.sectionOrder[i], this.sectionOrder[j]] = [this.sectionOrder[j], this.sectionOrder[i]];
        },
        // Reorder a photo within its own list (an attribute list or the extras).
        moveUpload(list, id, dir) {
            const i = list.findIndex((u) => u.id === id);
            const j = i + dir;
            if (i < 0 || j < 0 || j >= list.length) return;
            [list[i], list[j]] = [list[j], list[i]];
        },

        // ── Drag & drop (desktop). Touch users use the ↑↓ buttons. A single
        // `dnd` record tracks what's in flight; two dispatchers route a drop
        // by kind so sections, photos (within + across sections) and the
        // featured strip all share the same gesture.
        dnd: { kind: null, listKey: null, id: null, over: null, overSection: null, overFeatured: null },
        // The real backing array for a photo list key ('extra' or an attrId).
        // Creates the attribute array on demand so cross-section drops land in
        // the live (reactive) array, not a throwaway copy.
        listByKey(key) {
            if (key === 'extra') return this.extraUploads;
            if (!this.attributeUploads[key]) this.attributeUploads[key] = [];
            return this.attributeUploads[key];
        },
        sectionDragStart(id) { this.dnd = { kind: 'section', listKey: null, id, over: null, overSection: null, overFeatured: null }; },
        photoDragStart(listKey, id) { this.dnd = { kind: 'photo', listKey, id, over: null, overSection: null, overFeatured: null }; },
        featuredDragStart(id) { this.dnd = { kind: 'featured', listKey: null, id, over: null, overSection: null, overFeatured: null }; },
        dndEnd() { this.dnd = { kind: null, listKey: null, id: null, over: null, overSection: null, overFeatured: null }; },
        // Give the cursor a clean "picked-up" preview (the photo / card itself,
        // centred under the pointer) and a move cursor — the Trello feel.
        _setDragImage(ev, el) {
            if (!ev.dataTransfer || !el) return;
            ev.dataTransfer.effectAllowed = 'move';
            const r = el.getBoundingClientRect();
            try { ev.dataTransfer.setDragImage(el, r.width / 2, r.height / 2); } catch (e) {}
        },
        beginPhotoDrag(ev, listKey, id) {
            this.photoDragStart(listKey, id);
            this._setDragImage(ev, ev.currentTarget.querySelector('img'));
        },
        beginSectionDrag(ev, id) {
            this.sectionDragStart(id);
            this._setDragImage(ev, ev.currentTarget.closest('[data-section-card]'));
        },
        beginFeaturedDrag(ev, id) {
            this.featuredDragStart(id);
            this._setDragImage(ev, ev.currentTarget);
        },
        _reorder(list, fromId, toId) {
            const from = list.indexOf(fromId);
            const to = list.indexOf(toId);
            if (from < 0 || to < 0 || from === to) return;
            const [m] = list.splice(from, 1);
            list.splice(to, 0, m);
        },
        _movePhoto(srcKey, id, destKey, destId) {
            if (id === destId) return;
            const src = this.listByKey(srcKey);
            const i = src.findIndex((u) => u.id === id);
            if (i < 0) return;
            const [item] = src.splice(i, 1);
            const dest = this.listByKey(destKey);
            let idx = dest.length;
            if (destId != null) {
                const t = dest.findIndex((u) => u.id === destId);
                if (t >= 0) idx = t;
            }
            dest.splice(idx, 0, item);
        },
        // Drop onto a specific photo tile (insert before it / reorder).
        dropOnPhoto(destKey, destId) {
            if (this.dnd.kind === 'photo') this._movePhoto(this.dnd.listKey, this.dnd.id, destKey, destId);
            else if (this.dnd.kind === 'section') { this.syncSectionOrder(); this._reorder(this.sectionOrder, this.dnd.id, destKey); }
            this.dndEnd();
        },
        // Drop onto a section's open area (append to the end of that list).
        dropOnSectionArea(destKey) {
            if (this.dnd.kind === 'section') { this.syncSectionOrder(); this._reorder(this.sectionOrder, this.dnd.id, destKey); }
            else if (this.dnd.kind === 'photo') this._movePhoto(this.dnd.listKey, this.dnd.id, destKey, null);
            this.dndEnd();
        },
        // Drop onto a featured-strip row (reorder the showcase).
        dropOnFeatured(targetId) {
            if (this.dnd.kind === 'featured') this._reorder(this.featured, this.dnd.id, targetId);
            this.dndEnd();
        },
        removeAttributeUpload(attributeId, uploadId) {
            this.attributeUploads[attributeId] = (this.attributeUploads[attributeId] || [])
                .filter((u) => u.id !== uploadId);
            this.featured = this.featured.filter((id) => id !== uploadId);
        },
        removeExtraUpload(uploadId) {
            this.extraUploads = this.extraUploads.filter((u) => u.id !== uploadId);
            this.featured = this.featured.filter((id) => id !== uploadId);
        },
        _readPreview(file) {
            return new Promise((resolve) => {
                const r = new FileReader();
                r.onload = (e) => resolve(e.target.result);
                r.readAsDataURL(file);
            });
        },
        /**
         * Shrink a photo in the browser BEFORE the presigned upload so we store
         * one web-optimized WebP per photo: resize to a 2048px max edge + WebP
         * @ 0.82, capped at ~300 KB (browser-image-compression lowers quality
         * further only if a photo exceeds the cap). Sharp on real devices, much
         * smaller than the multi-MB originals. iPhone HEIC/HEIF is converted to
         * JPEG first via heic2any (lazy-loaded — it bundles libheif WASM).
         * Anything that can't be processed falls back to the original so uploads
         * never break. Files already < 150 KB pass through untouched.
         */
        async _compress(file) {
            const isHeic = /image\/heic|image\/heif/.test(file.type) || /\.(heic|heif)$/i.test(file.name);
            if (!file.type.startsWith('image/') && !isHeic) return file;
            // Keep animation/vector intact — re-encoding would wreck them.
            if (file.type === 'image/gif' || file.type === 'image/svg+xml') return file;

            let work = file;
            if (isHeic) {
                try {
                    const heic2any = await window.loadHeic2any();
                    const out = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.92 });
                    const blob = Array.isArray(out) ? out[0] : out;
                    work = new File([blob], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
                } catch (e) {
                    console.warn('[upload] HEIC convert failed, trying original', e);
                }
            } else if (file.size < 150 * 1024) {
                return file; // already small (< 150 KB), non-HEIC — nothing to gain
            }

            if (!window.imageCompression) return work;
            try {
                const out = await window.imageCompression(work, {
                    maxSizeMB: 0.3,           // ~300 KB cap — web-optimized, keeps photos sharp
                    maxWidthOrHeight: 2048,   // plenty for full-screen on real devices
                    initialQuality: 0.82,     // crisp WebP; the lib only drops further if over the cap
                    fileType: 'image/webp',
                    useWebWorker: true,       // off the main thread → UI stays responsive
                });
                const webp = new File([out], work.name.replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp' });
                return webp.size < work.size ? webp : work; // never end up bigger
            } catch (e) {
                console.warn('[upload] compression failed, using', work === file ? 'original' : 'converted', e);
                return work; // a converted HEIC is at least a displayable JPEG
            }
        },
        /**
         * Step 1: compress the photo (above), then POST {filename, mime} to
         * /host-register/presign → ticket with put_url.
         * Step 2: PUT the raw bytes straight to S3 (DO Spaces) — PHP doesn't see them.
         * Returns { path, url } on success.
         */
        async _uploadOne(file) {
            file = await this._compress(file);
            const presignRes = await fetch('{{ route('host.places.presign') }}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    filename: file.name,
                    mime: file.type || 'application/octet-stream',
                }),
            });
            if (!presignRes.ok) {
                let msg = `Could not get upload URL (${presignRes.status})`;
                try {
                    const err = await presignRes.json();
                    if (err.message) msg = err.message;
                } catch (_) {}
                throw new Error(msg);
            }
            const ticket = await presignRes.json();

            const putRes = await fetch(ticket.put_url, {
                method: 'PUT',
                headers: {
                    'Content-Type': ticket.mime,
                    'x-amz-acl': 'public-read',
                },
                body: file,
            });
            if (!putRes.ok) throw new Error(`Upload to storage failed (${putRes.status})`);

            return { path: ticket.path, url: ticket.public_url };
        },
        async onAttributeFiles(event, attributeId) {
            let files = Array.from(event.target.files || []);
            event.target.value = '';
            if (!files.length) return;
            if (!this.attributeUploads[attributeId]) this.attributeUploads[attributeId] = [];

            files = this._capSectionFiles(files, this.attributeUploads[attributeId].length);
            if (!files.length) return;

            for (const file of files) {
                const preview = await this._readPreview(file);
                const id = ++this.uploadCounter;
                this.attributeUploads[attributeId].push({
                    id, name: file.name, status: 'uploading', preview, path: null, url: null,
                });
                this._uploadOne(file).then((r) => {
                    const row = (this.attributeUploads[attributeId] || []).find((u) => u.id === id);
                    if (row) { row.path = r.path; row.url = r.url; row.status = 'done'; }
                }).catch((err) => {
                    const row = (this.attributeUploads[attributeId] || []).find((u) => u.id === id);
                    if (row) { row.status = 'failed'; row.error = err.message || 'Upload failed'; }
                });
            }
        },
        async onExtraFiles(event) {
            let files = Array.from(event.target.files || []);
            event.target.value = '';
            if (!files.length) return;

            files = this._capSectionFiles(files, this.extraUploads.length);
            if (!files.length) return;

            for (const file of files) {
                const preview = await this._readPreview(file);
                const id = ++this.uploadCounter;
                this.extraUploads.push({
                    id, name: file.name, status: 'uploading', preview, path: null, url: null,
                });
                this._uploadOne(file).then((r) => {
                    const row = this.extraUploads.find((u) => u.id === id);
                    if (row) { row.path = r.path; row.url = r.url; row.status = 'done'; }
                }).catch((err) => {
                    const row = this.extraUploads.find((u) => u.id === id);
                    if (row) { row.status = 'failed'; row.error = err.message || 'Upload failed'; }
                });
            }
        },

        /** Trim a file list to the section's remaining slots (max 10). */
        _capSectionFiles(files, currentCount) {
            const allowed = this.photoSectionMax - currentCount;
            if (allowed <= 0) { this.flashPhotoNotice(); return []; }
            if (files.length > allowed) { this.flashPhotoNotice(); return files.slice(0, allowed); }
            return files;
        },
        flashPhotoNotice() {
            this.photoNotice = @js($photoLimitMsg);
            clearTimeout(this._photoNoticeTimer);
            this._photoNoticeTimer = setTimeout(() => { this.photoNotice = ''; }, 4000);
        },
        /** Count of successfully-uploaded photos across all sections + "other". */
        totalDoneUploads() {
            let n = this.extraUploads.filter((u) => u.status === 'done').length;
            for (const k in this.attributeUploads) {
                n += (this.attributeUploads[k] || []).filter((u) => u.status === 'done').length;
            }
            return n;
        },

        // ── Draft auto-save
        /**
         * Persist current wizard state to the server as a Draft place. Called
         * after every `next()`. Silent on failure (just logs) — the wizard
         * keeps working from in-memory state and the final submit still works.
         */
        async saveDraft() {
            if (!this.placeTypeId) return;   // nothing to save yet (haven't passed step 1)
            this.draftSaving = true;
            this.draftError = '';

            // Place column subset.
            const payload = {
                draft_id: this.draftId,
                // Empty string when the admin hasn't typed it yet (or for non-
                // admins where the field isn't rendered) — the server ignores
                // an empty value and falls back to the current user.
                host_phone: (this.hostPhone || '').trim(),
                place_type_id: this.placeTypeId,
                title_ar: this.titleAr || null,
                title_en: this.titleEn || null,
                description_ar: this.descriptionAr || null,
                description_en: this.descriptionEn || null,
                city_area_id: this.cityAreaId || null,
                price: Number(this.price) || 0,
                check_in_time: this.checkInTime || '15:00',
                check_out_time: this.checkOutTime || '12:00',
                checkout_next_day: this.checkoutNextDay,
                max_guests: Number(this.maxGuests) || null,
                rules_ar: this.rulesAr || null,
                rules_en: this.rulesEn || null,
                location_url: this.locationUrl || null,
                last_step: this.step,
                price_sunday:    this.dayPrices.sunday    || 0,
                price_monday:    this.dayPrices.monday    || 0,
                price_tuesday:   this.dayPrices.tuesday   || 0,
                price_wednesday: this.dayPrices.wednesday || 0,
                price_thursday:  this.dayPrices.thursday  || 0,
                price_friday:    this.dayPrices.friday    || 0,
                price_saturday:  this.dayPrices.saturday  || 0,
            };

            // Attribute picks — only sent once the host has chosen anything,
            // so early-step drafts don't blow away a previous round's picks.
            const attrIds = Object.keys(this.selectedAttributes);
            if (attrIds.length > 0) {
                payload.attributes = attrIds.map((id) => ({
                    attribute_id: String(id),
                    value: String(this.selectedAttributes[id].count),
                    description: this.selectedAttributes[id].description || null,
                }));
            }

            // Photo paths — keyed by attribute_id, plus extras + cover marker.
            // Same guard: only send the photo payload once any upload is done so
            // pre-step-8 draft saves don't wipe what was already on the server.
            if (this.totalDoneUploads() > 0) {
                const attrPaths = {};
                Object.keys(this.attributeUploads).forEach((aid) => {
                    const done = (this.attributeUploads[aid] || []).filter((u) => u.status === 'done');
                    if (done.length > 0) attrPaths[aid] = done.map((u) => u.path);
                });
                const extras = this.extraUploads.filter((u) => u.status === 'done').map((u) => u.path);

                payload.attribute_image_paths = attrPaths;
                payload.extra_image_paths = extras;
                payload.featured = this.featuredMarkers();
            }

            try {
                const res = await fetch(this.draftEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                if (!res.ok) throw new Error('Draft save failed (HTTP ' + res.status + ')');
                const data = await res.json();
                if (data && data.id) this.draftId = data.id;
            } catch (e) {
                console.warn('[wizard] draft save failed:', e);
                this.draftError = e.message || 'Draft save failed';
            } finally {
                this.draftSaving = false;
            }
        },
    };
}
</script>
@endsection
