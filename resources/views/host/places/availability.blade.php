@extends('layouts.user')

@php
    use Carbon\CarbonImmutable;

    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $today = CarbonImmutable::now()->startOfDay();

    // Expand the upcoming blockings into a flat set of Y-m-d strings so the
    // calendar can highlight every unavailable day (not just the range ends).
    $blockedDays = [];
    foreach ($blockings as $b) {
        $cursor = CarbonImmutable::parse($b->start_date->toDateString());
        $end = CarbonImmutable::parse($b->end_date->toDateString());
        if ($cursor->lessThan($today)) {
            $cursor = $today;
        }
        while ($cursor->lessThanOrEqualTo($end)) {
            $blockedDays[$cursor->toDateString()] = true;
            $cursor = $cursor->addDay();
        }
    }
    $blockedDays = array_keys($blockedDays);

    $monthNames = $isRtl
        ? ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    $weekdays = $isRtl
        ? ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت']
        : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    $cfg = [
        'today' => $today->toDateString(),
        'blocked' => $blockedDays,
        'year' => (int) $today->year,
        'month' => (int) $today->month - 1, // JS months are 0-based
        'monthNames' => $monthNames,
        'weekdays' => $weekdays,
        'isRtl' => $isRtl,
    ];

    $city = $place->cityArea?->city;
@endphp

@section('title', $isRtl ? 'إدارة التواريخ' : 'Manage availability')
@section('heading', $isRtl ? 'إدارة التواريخ' : 'Manage availability')

@section('header-action')
    <a href="{{ route('user.places') }}"
       class="inline-flex items-center font-semibold text-[#222] bg-white hover:bg-[#f7f7f7] border border-[#ebebeb] {{ $fa }}"
       style="padding: 10px 18px; gap: 8px; border-radius: 14px; font-size: 14px;">
        <span>{{ $isRtl ? '→' : '←' }}</span><span>{{ $isRtl ? 'أماكني' : 'My places' }}</span>
    </a>
@endsection

@section('main')
    {{-- Which place we're editing --}}
    <div class="inline-flex items-center bg-white {{ $fa }}"
         style="margin-bottom: 24px; padding: 12px 18px; gap: 12px; border-radius: 18px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <span style="font-size: 24px; line-height: 1;">{{ $place->type?->icon ?: '🏠' }}</span>
        <span>
            <span class="block font-bold text-[#222] text-[15px]">{{ $place->title }}</span>
            <span class="block text-[12px] text-[#717171]">{{ $city?->avatar ?: '📍' }} {{ $isRtl ? $city?->name_ar : $city?->name_en }}</span>
        </span>
    </div>

    <div x-data="availabilityCalendar(@js($cfg))"
         class="grid grid-cols-1 lg:grid-cols-[1fr_320px]" style="gap: 24px; align-items: start;">

        {{-- ── Calendar ─────────────────────────────────────────────────── --}}
        <div class="bg-white" style="border-radius: 28px; padding: 24px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            {{-- Month nav. Flexbox already mirrors the button positions in RTL
                 (prev → right, next → left), so we only need each chevron to
                 point outward. SVGs are used instead of ‹/› text glyphs because
                 those angle-quote characters get bidi-mirrored inside an RTL
                 document, which flipped them the wrong way. --}}
            @php
                $chevLeft = '<path d="M15 6l-6 6 6 6"/>';
                $chevRight = '<path d="M9 6l6 6-6 6"/>';
                // prev sits on the start edge: left in LTR, right in RTL.
                $prevChev = $isRtl ? $chevRight : $chevLeft;
                $nextChev = $isRtl ? $chevLeft : $chevRight;
            @endphp
            <div class="flex items-center justify-between" style="margin-bottom: 18px;">
                <button type="button" @click="prevMonth()" aria-label="{{ $isRtl ? 'الشهر السابق' : 'Previous month' }}"
                        class="inline-flex items-center justify-center text-[#222] hover:bg-[#f3f4f6]"
                        style="width: 38px; height: 38px; border-radius: 12px; border: 1px solid #ebebeb;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $prevChev !!}</svg>
                </button>
                <span class="font-bold text-[#222] text-[16px]" x-text="monthLabel"></span>
                <button type="button" @click="nextMonth()" aria-label="{{ $isRtl ? 'الشهر التالي' : 'Next month' }}"
                        class="inline-flex items-center justify-center text-[#222] hover:bg-[#f3f4f6]"
                        style="width: 38px; height: 38px; border-radius: 12px; border: 1px solid #ebebeb;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $nextChev !!}</svg>
                </button>
            </div>

            {{-- Weekday header --}}
            <div class="grid grid-cols-7 text-center text-[12px] font-semibold text-[#717171] {{ $fa }}" style="margin-bottom: 8px;">
                <template x-for="(wd, i) in weekdays" :key="i">
                    <div x-text="wd" style="padding: 6px 0;"></div>
                </template>
            </div>

            {{-- Day grid --}}
            <div class="grid grid-cols-7" style="gap: 6px;">
                <template x-for="(cell, i) in cells" :key="i">
                    <div>
                        {{-- empty leading slot --}}
                        <template x-if="!cell">
                            <div></div>
                        </template>
                        <template x-if="cell">
                            <button type="button"
                                    @click="pick(cell)"
                                    :disabled="cell.isPast || cell.isBlocked"
                                    class="w-full flex items-center justify-center text-[14px] font-medium tabular-nums transition-colors"
                                    style="aspect-ratio: 1 / 1; border-radius: 12px;"
                                    :style="cellStyle(cell)"
                                    :title="cell.isBlocked ? '{{ $isRtl ? 'محجوب' : 'Blocked' }}' : ''">
                                <span x-text="cell.day"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 18px; gap: 16px;">
                <span class="inline-flex items-center" style="gap: 6px;">
                    <span style="width: 14px; height: 14px; border-radius: 5px; background: #F88379;"></span>{{ $isRtl ? 'محدد' : 'Selected' }}
                </span>
                <span class="inline-flex items-center" style="gap: 6px;">
                    <span style="width: 14px; height: 14px; border-radius: 5px; background: #fde2e0; border: 1px solid #f5b5af;"></span>{{ $isRtl ? 'محجوب' : 'Blocked' }}
                </span>
                <span class="inline-flex items-center" style="gap: 6px;">
                    <span style="width: 14px; height: 14px; border-radius: 5px; background: #fff; border: 1px solid #ebebeb;"></span>{{ $isRtl ? 'متاح' : 'Available' }}
                </span>
            </div>
        </div>

        {{-- ── Block form (selection summary) ───────────────────────────── --}}
        <div class="bg-white" style="border-radius: 28px; padding: 24px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            <h2 class="font-bold text-[#222] text-[16px] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'حجب التواريخ' : 'Block dates' }}</h2>
            <p class="text-[13px] text-[#717171] {{ $fa }}" style="margin-bottom: 18px;">
                {{ $isRtl ? 'اختر تاريخ البداية ثم النهاية من التقويم.' : 'Pick a start then an end date on the calendar.' }}
            </p>

            {{-- Empty state --}}
            <div x-show="!hasSelection" class="text-[13px] text-[#999] {{ $fa }}"
                 style="padding: 16px; border-radius: 14px; background: #fafafa; border: 1px dashed #e5e7eb;">
                {{ $isRtl ? 'لم يتم تحديد أي تواريخ بعد.' : 'No dates selected yet.' }}
            </div>

            {{-- Selection + submit --}}
            <form x-show="hasSelection" x-cloak method="POST"
                  action="{{ route('host.places.blockings.store', $place) }}">
                @csrf
                <input type="hidden" name="start_date" :value="selStart">
                <input type="hidden" name="end_date" :value="effEnd">

                <div class="flex items-center justify-between text-[14px]" style="margin-bottom: 14px;">
                    <span class="font-semibold text-[#222]" dir="ltr" x-text="rangeLabel"></span>
                    <span class="text-[12px] text-[#717171] {{ $fa }}">
                        <span x-text="nights"></span> {{ $isRtl ? 'ليلة' : 'night(s)' }}
                    </span>
                </div>

                <label class="block text-[12px] font-semibold text-[#717171] {{ $fa }}" style="margin-bottom: 6px;">
                    {{ $isRtl ? 'السبب (اختياري)' : 'Reason (optional)' }}
                </label>
                <input type="text" name="reason" maxlength="255"
                       placeholder="{{ $isRtl ? 'مثال: استخدام شخصي، صيانة' : 'e.g. Personal use, maintenance' }}"
                       class="w-full text-[14px] {{ $fa }}"
                       style="padding: 11px 14px; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 16px;">

                <div class="flex items-center" style="gap: 10px;">
                    <button type="submit"
                            class="flex-1 inline-flex items-center justify-center font-semibold text-white bg-[#F88379] hover:bg-[#f56b60] {{ $fa }}"
                            style="padding: 11px 16px; border-radius: 14px; font-size: 14px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                        {{ $isRtl ? 'حجب هذه التواريخ' : 'Block these dates' }}
                    </button>
                    <button type="button" @click="clear()" data-no-loading
                            class="inline-flex items-center justify-center font-semibold text-[#717171] hover:bg-[#f3f4f6] {{ $fa }}"
                            style="padding: 11px 14px; border-radius: 14px; font-size: 14px; border: 1px solid #ebebeb;">
                        {{ $isRtl ? 'إلغاء' : 'Clear' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Existing blocked ranges ──────────────────────────────────────── --}}
    <div style="margin-top: 28px;">
        <h2 class="font-bold text-[#222] text-[16px] {{ $fa }}" style="margin-bottom: 14px;">
            {{ $isRtl ? 'التواريخ المحجوبة' : 'Blocked dates' }}
        </h2>

        @if($blockings->isEmpty())
            <div class="bg-white text-[14px] text-[#717171] {{ $fa }}"
                 style="border-radius: 20px; padding: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                {{ $isRtl ? 'لا توجد تواريخ محجوبة قادمة. مكانك متاح بالكامل.' : 'No upcoming blocked dates — your place is fully available.' }}
            </div>
        @else
            <div class="bg-white overflow-hidden" style="border-radius: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                @foreach($blockings as $b)
                    @php
                        $start = $b->start_date;
                        $end = $b->end_date;
                        $sameDay = $start->isSameDay($end);
                        $nights = $start->diffInDays($end) + 1;
                        $imported = ($b->source ?? 'manual') === 'ical';
                    @endphp
                    <div class="flex items-center justify-between border-t first:border-t-0 border-[#ebebeb]" style="padding: 14px 20px;">
                        <div class="flex items-center" style="gap: 14px;">
                            <span class="inline-flex items-center justify-center"
                                  style="width: 40px; height: 40px; border-radius: 12px; background: {{ $imported ? '#eff6ff' : '#fef2f2' }}; font-size: 18px;">{{ $imported ? '🔗' : '🚫' }}</span>
                            <div class="{{ $fa }}">
                                <div class="font-semibold text-[#222] text-[14px]" dir="ltr">
                                    @if($sameDay)
                                        {{ $start->isoFormat('ddd, D MMM YYYY') }}
                                    @else
                                        {{ $start->isoFormat('ddd, D MMM YYYY') }} → {{ $end->isoFormat('ddd, D MMM YYYY') }}
                                    @endif
                                </div>
                                <div class="text-[12px] text-[#717171]">
                                    {{ $nights }} {{ $isRtl ? 'ليلة' : 'night(s)' }}@if($imported && $b->feed) · {{ $isRtl ? 'عبر' : 'via' }} {{ $b->feed->name }}@elseif($b->reason) · {{ $b->reason }}@endif
                                </div>
                            </div>
                        </div>
                        @if($imported)
                            {{-- Managed by its feed — unblocking here would just resurrect next sync. --}}
                            <span class="inline-flex items-center font-semibold text-[#2563eb] {{ $fa }}"
                                  style="padding: 8px 14px; border-radius: 12px; font-size: 13px; background: #eff6ff;">
                                {{ $isRtl ? 'مزامنة خارجية' : 'Synced' }}
                            </span>
                        @else
                            <form method="POST" action="{{ route('host.places.blockings.destroy', [$place, $b]) }}"
                                  onsubmit="return confirm('{{ $isRtl ? 'إلغاء حجب هذه التواريخ؟' : 'Unblock these dates?' }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center font-semibold text-[#dc2626] hover:bg-[#fef2f2] {{ $fa }}"
                                        style="padding: 8px 14px; border-radius: 12px; font-size: 13px; border: 1px solid #fecaca;">
                                    {{ $isRtl ? 'إلغاء الحجب' : 'Unblock' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Calendar sync (iCal — Airbnb / Gathern / Google) ─────────────── --}}
    <div style="margin-top: 28px;">
        <h2 class="font-bold text-[#222] text-[16px] {{ $fa }}" style="margin-bottom: 14px;">
            {{ $isRtl ? 'مزامنة التقويم' : 'Calendar sync' }}
        </h2>

        <div class="bg-white" style="border-radius: 20px; padding: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            {{-- Export: Calm → other platforms --}}
            <div class="{{ $fa }}">
                <div class="font-semibold text-[#222] text-[14px]">{{ $isRtl ? 'تصدير تقويم كالم' : 'Export your Calm calendar' }}</div>
                <p class="text-[13px] text-[#717171]" style="margin-top: 4px;">
                    {{ $isRtl
                        ? 'الصق هذا الرابط في Airbnb أو Gathern أو تقويم Google لتُحجب تواريخ كالم المحجوزة هناك تلقائياً.'
                        : 'Paste this link into Airbnb, Gathern or Google Calendar so your Calm bookings block those dates there automatically.' }}
                </p>
                <div x-data="copyField()" class="flex items-center" style="gap: 10px; margin-top: 12px;">
                    <input type="text" readonly x-ref="copySource" value="{{ $exportUrl }}" onclick="this.select()" dir="ltr"
                           class="flex-1 text-[13px] text-[#717171] bg-[#fafafa]"
                           style="padding: 10px 14px; border-radius: 12px; border: 1px solid #e5e7eb; min-width: 0;">
                    <button type="button" data-no-loading @click="copy()"
                            class="inline-flex items-center justify-center font-semibold text-white bg-[#222] hover:bg-[#000] {{ $fa }}"
                            style="padding: 10px 16px; border-radius: 12px; font-size: 13px; white-space: nowrap;">
                        <span x-show="!copied">{{ $isRtl ? 'نسخ' : 'Copy' }}</span>
                        <span x-show="copied" x-cloak>{{ $isRtl ? 'تم النسخ ✓' : 'Copied ✓' }}</span>
                    </button>
                    <form method="POST" action="{{ route('host.places.calendar-token.rotate', $place) }}"
                          onsubmit="return confirm('{{ $isRtl ? 'سيتوقف الرابط القديم فوراً وستحتاج لتحديثه في المنصات الأخرى. متابعة؟' : 'The old link stops working instantly and must be re-pasted on the other platforms. Continue?' }}');">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center font-semibold text-[#717171] hover:bg-[#f3f4f6] {{ $fa }}"
                                style="padding: 10px 14px; border-radius: 12px; font-size: 13px; border: 1px solid #ebebeb; white-space: nowrap;">
                            {{ $isRtl ? 'تجديد الرابط' : 'Regenerate link' }}
                        </button>
                    </form>
                </div>
            </div>

            <div style="height: 1px; background: #ebebeb; margin: 20px 0;"></div>

            {{-- Import: other platforms → Calm --}}
            <div class="{{ $fa }}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-[#222] text-[14px]">{{ $isRtl ? 'استيراد تقويمات خارجية' : 'Import external calendars' }}</div>
                        <p class="text-[13px] text-[#717171]" style="margin-top: 4px;">
                            {{ $isRtl
                                ? 'الصق رابط iCal من المنصة الأخرى لتُحجب حجوزاتها هنا. تتم المزامنة كل ساعة تلقائياً.'
                                : "Paste the other platform's iCal link so its bookings block dates here. Feeds re-sync automatically every hour." }}
                        </p>
                    </div>
                    @if($feeds->isNotEmpty())
                        <form method="POST" action="{{ route('host.places.calendar-feeds.sync', $place) }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center font-semibold text-[#222] hover:bg-[#f3f4f6] {{ $fa }}"
                                    style="padding: 9px 14px; border-radius: 12px; font-size: 13px; border: 1px solid #ebebeb; white-space: nowrap;">
                                ⟳ {{ $isRtl ? 'مزامنة الآن' : 'Sync now' }}
                            </button>
                        </form>
                    @endif
                </div>

                @if($feeds->isNotEmpty())
                    <div style="margin-top: 14px; border: 1px solid #ebebeb; border-radius: 14px; overflow: hidden;">
                        @foreach($feeds as $feed)
                            <div class="flex items-center justify-between border-t first:border-t-0 border-[#ebebeb]" style="padding: 12px 16px; gap: 12px;">
                                <div style="min-width: 0;">
                                    <div class="flex items-center" style="gap: 8px;">
                                        <span class="font-semibold text-[#222] text-[14px]">{{ $feed->name }}</span>
                                        @if($feed->last_status === 'ok')
                                            <span class="text-[11px] font-bold text-[#059669]" style="padding: 2px 8px; border-radius: 999px; background: #ecfdf5;">{{ $isRtl ? 'متزامن' : 'OK' }}</span>
                                        @elseif($feed->last_status === 'error')
                                            <span class="text-[11px] font-bold text-[#dc2626]" style="padding: 2px 8px; border-radius: 999px; background: #fef2f2;" title="{{ $feed->last_error }}">{{ $isRtl ? 'خطأ' : 'Error' }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[12px] text-[#717171] truncate" dir="ltr" style="max-width: 460px;">
                                        {{ $feed->url }}
                                        @if($feed->last_synced_at) · {{ $isRtl ? 'آخر مزامنة' : 'synced' }} {{ $feed->last_synced_at->diffForHumans() }}@endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('host.places.calendar-feeds.destroy', [$place, $feed]) }}"
                                      onsubmit="return confirm('{{ $isRtl ? 'إزالة هذا التقويم؟ ستتحرر كل التواريخ المحجوبة عبره.' : 'Remove this calendar? Every date it blocks becomes available again.' }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center font-semibold text-[#dc2626] hover:bg-[#fef2f2] {{ $fa }}"
                                            style="padding: 7px 12px; border-radius: 10px; font-size: 12px; border: 1px solid #fecaca; white-space: nowrap;">
                                        {{ $isRtl ? 'إزالة' : 'Remove' }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('host.places.calendar-feeds.store', $place) }}"
                      class="flex flex-wrap items-end" style="gap: 10px; margin-top: 14px;">
                    @csrf
                    <div style="flex: 0 1 180px;">
                        <label class="block text-[12px] font-semibold text-[#717171]" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم' : 'Name' }}</label>
                        <input type="text" name="name" required maxlength="100" value="{{ old('name') }}"
                               placeholder="{{ $isRtl ? 'مثال: Airbnb' : 'e.g. Airbnb' }}"
                               class="w-full text-[14px] {{ $fa }}"
                               style="padding: 10px 14px; border-radius: 12px; border: 1px solid #e5e7eb;">
                    </div>
                    <div style="flex: 1 1 280px;">
                        <label class="block text-[12px] font-semibold text-[#717171]" style="margin-bottom: 6px;">{{ $isRtl ? 'رابط iCal' : 'iCal link' }}</label>
                        <input type="url" name="url" required maxlength="2048" value="{{ old('url') }}" dir="ltr"
                               placeholder="https://…/calendar.ics"
                               class="w-full text-[14px]"
                               style="padding: 10px 14px; border-radius: 12px; border: 1px solid #e5e7eb;">
                    </div>
                    <button type="submit"
                            class="inline-flex items-center justify-center font-semibold text-white bg-[#F88379] hover:bg-[#f56b60] {{ $fa }}"
                            style="padding: 11px 18px; border-radius: 12px; font-size: 14px; white-space: nowrap; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                        {{ $isRtl ? 'إضافة' : 'Connect' }}
                    </button>
                </form>
                @error('url')
                    <p class="text-[12px] text-[#dc2626] {{ $fa }}" style="margin-top: 8px;">{{ $message }}</p>
                @enderror
                @error('name')
                    <p class="text-[12px] text-[#dc2626] {{ $fa }}" style="margin-top: 8px;">{{ $message }}</p>
                @enderror
                @error('blocking')
                    <p class="text-[12px] text-[#dc2626] {{ $fa }}" style="margin-top: 8px;">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <style>[x-cloak] { display: none !important; }</style>

    <script>
        document.addEventListener('alpine:init', () => {
            // Copy-to-clipboard for the export URL. navigator.clipboard only
            // exists in secure contexts (HTTPS / localhost) — on plain-HTTP
            // staging or LAN IPs it's undefined and the click would silently
            // throw. Fall back to the legacy select + execCommand path, which
            // works everywhere the page renders.
            Alpine.data('copyField', () => ({
                copied: false,

                copy() {
                    const el = this.$refs.copySource;
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(el.value)
                            .then(() => this.flash())
                            .catch(() => this.fallbackCopy(el));
                    } else {
                        this.fallbackCopy(el);
                    }
                },

                fallbackCopy(el) {
                    el.focus();
                    el.select();
                    el.setSelectionRange(0, el.value.length); // iOS Safari needs the explicit range
                    try {
                        if (document.execCommand('copy')) this.flash();
                    } catch (e) {
                        // Copy genuinely unavailable — the text stays selected
                        // so the user can Cmd/Ctrl+C manually.
                    }
                },

                flash() {
                    this.copied = true;
                    clearTimeout(this._copyTimer);
                    this._copyTimer = setTimeout(() => { this.copied = false; }, 2000);
                },
            }));

            Alpine.data('availabilityCalendar', (cfg) => ({
                today: cfg.today,
                blocked: cfg.blocked,
                isRtl: cfg.isRtl,
                monthNames: cfg.monthNames,
                weekdays: cfg.weekdays,
                year: cfg.year,
                month: cfg.month, // 0-based
                selStart: null,
                selEnd: null,

                pad(n) { return n < 10 ? '0' + n : String(n); },
                fmt(y, m, d) { return y + '-' + this.pad(m + 1) + '-' + this.pad(d); },

                get monthLabel() { return this.monthNames[this.month] + ' ' + this.year; },

                prevMonth() {
                    if (this.month === 0) { this.month = 11; this.year--; } else { this.month--; }
                },
                nextMonth() {
                    if (this.month === 11) { this.month = 0; this.year++; } else { this.month++; }
                },

                get cells() {
                    const startDow = new Date(this.year, this.month, 1).getDay(); // 0=Sun
                    const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
                    const out = [];
                    for (let i = 0; i < startDow; i++) out.push(null);
                    for (let d = 1; d <= daysInMonth; d++) {
                        const ds = this.fmt(this.year, this.month, d);
                        out.push({
                            day: d,
                            date: ds,
                            isPast: ds < this.today,
                            isBlocked: this.blocked.includes(ds),
                            isToday: ds === this.today,
                        });
                    }
                    return out;
                },

                get effEnd() { return this.selEnd || this.selStart; },
                get hasSelection() { return !!this.selStart; },

                get nights() {
                    if (!this.selStart) return 0;
                    const a = new Date(this.selStart + 'T00:00:00');
                    const b = new Date(this.effEnd + 'T00:00:00');
                    return Math.round((b - a) / 86400000) + 1;
                },

                get rangeLabel() {
                    if (!this.selStart) return '';
                    const f = (s) => {
                        const dt = new Date(s + 'T00:00:00');
                        return dt.toLocaleDateString(this.isRtl ? 'ar' : 'en', { day: 'numeric', month: 'short', year: 'numeric' });
                    };
                    return this.selEnd && this.selEnd !== this.selStart
                        ? f(this.selStart) + ' → ' + f(this.selEnd)
                        : f(this.selStart);
                },

                isSelected(ds) {
                    if (!this.selStart) return false;
                    if (!this.selEnd) return ds === this.selStart;
                    return ds >= this.selStart && ds <= this.selEnd;
                },

                pick(cell) {
                    if (!cell || cell.isPast || cell.isBlocked) return;
                    if (!this.selStart || this.selEnd) {
                        this.selStart = cell.date;
                        this.selEnd = null;
                    } else if (cell.date < this.selStart) {
                        this.selStart = cell.date;
                    } else {
                        this.selEnd = cell.date;
                    }
                },

                clear() { this.selStart = null; this.selEnd = null; },

                cellStyle(cell) {
                    if (cell.isBlocked) {
                        return 'background:#fde2e0;color:#c0362c;border:1px solid #f5b5af;cursor:not-allowed;';
                    }
                    if (cell.isPast) {
                        return 'background:#fff;color:#d1d5db;cursor:not-allowed;';
                    }
                    if (this.isSelected(cell.date)) {
                        return 'background:#F88379;color:#fff;border:1px solid #F88379;';
                    }
                    const border = cell.isToday ? '1px solid #F88379' : '1px solid #ebebeb';
                    return 'background:#fff;color:#222;border:' + border + ';cursor:pointer;';
                },
            }));
        });
    </script>
@endsection
