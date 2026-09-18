@extends('layouts.app')

{{-- «طلب مناسبة» — the occasions wizard, web twin of the app's /occasions.
     Four steps → confirmation. Public to fill; submitting opens the login
     modal, which reloads this page — so the answers are parked in
     sessionStorage and the submit resumes itself on the way back.
     Options come from config/occasions.php (the app mirrors the same keys). --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $L = $isRtl ? 'ar' : 'en';

    $types = config('occasions.types');
    $needGroups = config('occasions.need_groups');
    $needs = config('occasions.needs');
    $presets = config('occasions.guest_presets');
    $venues = config('occasions.venue_statuses');

    // Chips grouped for rendering, in config order. preserveKeys is load-bearing:
    // without it groupBy reindexes to 0,1,2… and the chips submit numbers
    // instead of the option keys.
    $needsByGroup = collect($needs)->groupBy('group', preserveKeys: true);

    // ⚠ A string :style binding REPLACES the static style attribute — it does not
    // merge. So every selectable control keeps its full geometry in these bases
    // and both ternary branches re-state it. Dropping this is what flattens the
    // cards into borderless rectangles.
    $cardBase = 'aspect-ratio: 1 / 0.92; border-radius: 18px; gap: 10px; padding: 10px; transition: border-color 0.15s, background-color 0.15s;';
    $chipBase = 'gap: 7px; padding: 11px 16px; border-radius: 999px; font-size: 13.5px; color: #000; transition: border-color 0.15s, background-color 0.15s;';
    $presetBase = 'padding: 11px 22px; border-radius: 999px; font-size: 14px; color: #000; transition: border-color 0.15s, background-color 0.15s;';
    $venueBase = 'padding: 13px 20px; border-radius: 999px; font-size: 14px; color: #000; transition: border-color 0.15s, background-color 0.15s;';
    $on = 'border: 1.5px solid #000; background-color: #F7F7F7; font-weight: 700;';
    $off = 'border: 1.5px solid #EDEDED; background-color: #fff; font-weight: 500;';
@endphp

@section('title', ($isRtl ? 'طلب مناسبة' : 'Occasion request').' — Calm')

@section('body')
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="min-h-screen {{ $fa }}" style="background-color: #fff;"
     x-data="calmOccasions(@js([
        'authed' => auth('api')->check(),
        'isRtl' => $isRtl,
        'homeUrl' => route('landing'),
     ]))">

    {{-- ══ Wizard ══ --}}
    <div x-show="!done">
        {{-- Header: title centred, close/back at the visual start of the row --}}
        <header class="sticky top-0 z-30" style="background-color: #fff;">
            <div class="relative mx-auto w-full flex items-center justify-center" style="max-width: 560px; padding: 16px 20px 12px;">
                <span class="font-bold text-black {{ $fa }}" style="font-size: 17px;">{{ $isRtl ? 'طلب مناسبة' : 'Occasion request' }}</span>
                <button type="button" @click="back()" x-bind:aria-label="step === 1 ? '{{ $isRtl ? 'إغلاق' : 'Close' }}' : '{{ $isRtl ? 'رجوع' : 'Back' }}'"
                        class="calm-press absolute flex items-center justify-center text-black"
                        style="left: 20px; width: 40px; height: 40px;">
                    <span x-show="step === 1" style="font-size: 19px;">✕</span>
                    {{-- Back points the way the page reads: ‹ in LTR, › in RTL --}}
                    <svg x-show="step > 1" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                         style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                        <path d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            {{-- Step indicator: 4 equal segments, filled up to the current step --}}
            <div class="mx-auto w-full flex" style="max-width: 560px; gap: 8px; padding: 0 20px 14px;">
                @for($i = 1; $i <= 4; $i++)
                    <span class="calm-round" style="flex: 1; height: 3px; border-radius: 999px; background-color: #EDEDED; transition: background-color 0.25s;"
                          :style="step >= {{ $i }} ? 'flex: 1; height: 3px; border-radius: 999px; transition: background-color 0.25s; background-color: #F88379;' : 'flex: 1; height: 3px; border-radius: 999px; transition: background-color 0.25s; background-color: #EDEDED;'"></span>
                @endfor
            </div>
        </header>

        <main class="mx-auto w-full" style="max-width: 560px; padding: 8px 20px 140px;">

            {{-- ── Step 1 — occasion type ── --}}
            <section x-show="step === 1">
                <p class="{{ $fa }}" style="font-size: 14px; line-height: 1.7; color: #AAAAAA;">
                    {{ $isRtl
                        ? 'أخبرنا أكثر عن مناسبتك، وسيتواصل معك مختصو الفعاليات لدينا بأفكار ومقترحات. نصمّم وننفّذ المناسبات بجميع أحجامها، من الصغيرة إلى الكبيرة، وبأسعار تنافسية جداً.'
                        : 'Tell us more about your occasion and our event specialists will come back to you with ideas and suggestions. We design and execute occasions of every size, from small to large, at very competitive prices.' }}
                </p>

                <h1 class="font-bold text-black {{ $fa }}" style="font-size: 23px; line-height: 1.3; margin-top: 26px;">
                    {{ $isRtl ? 'ما نوع مناسبتك؟' : 'What are we celebrating?' }}
                </h1>

                <div class="grid grid-cols-3" style="gap: 12px; margin-top: 18px;">
                    @foreach($types as $key => $t)
                        <button type="button" @click="pickType(@js($key))"
                                class="calm-press flex flex-col items-center justify-center"
                                style="{{ $cardBase }} {{ $off }}"
                                :style="type === @js($key) ? '{{ $cardBase }} {{ $on }}' : '{{ $cardBase }} {{ $off }}'">
                            <span style="font-size: 27px; line-height: 1;">{{ $t['emoji'] }}</span>
                            <span class="text-black text-center {{ $fa }}" style="font-size: 12.5px; line-height: 1.25; font-weight: 500;"
                                  :style="type === @js($key) ? 'font-size: 12.5px; line-height: 1.25; font-weight: 700;' : 'font-size: 12.5px; line-height: 1.25; font-weight: 500;'">{{ $t[$L] }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- «أخرى» reveals free text --}}
                <div x-show="type === 'other'" x-cloak style="margin-top: 16px;">
                    <input type="text" x-model="typeOther" maxlength="80" x-ref="typeOther"
                           placeholder="{{ $isRtl ? 'ما هي مناسبتك؟' : 'What is the occasion?' }}"
                           class="w-full text-black focus:outline-none {{ $fa }}"
                           style="font-size: 15px; padding: 16px 18px; border-radius: 16px; background-color: #F7F7F7; border: 1.5px solid #EDEDED;">
                </div>
            </section>

            {{-- ── Step 2 — what they have in mind ── --}}
            <section x-show="step === 2" x-cloak>
                <h1 class="font-bold text-black {{ $fa }}" style="font-size: 23px; line-height: 1.3;">
                    {{ $isRtl ? 'أي أفكار تحب نجهّزها لمناسبتك؟' : 'What would you love for your occasion?' }}
                </h1>
                <p class="{{ $fa }}" style="font-size: 14px; line-height: 1.6; margin-top: 8px; color: #AAAAAA;">
                    {{ $isRtl
                        ? 'اختر ما يعجبك، ونقدر نرتّب أي شيء تحتاجه.'
                        : 'Pick whatever you like. We can arrange anything you need.' }}
                </p>

                @foreach($needGroups as $gKey => $g)
                    <h2 class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-top: 24px;">{{ $g[$L] }}</h2>
                    <div class="flex flex-wrap" style="gap: 10px; margin-top: 12px;">
                        @foreach($needsByGroup[$gKey] ?? [] as $key => $n)
                            <button type="button" @click="toggleNeed(@js($key))"
                                    class="calm-press calm-round inline-flex items-center {{ $fa }}"
                                    style="{{ $chipBase }} {{ $off }}"
                                    :style="needs.includes(@js($key)) ? '{{ $chipBase }} {{ $on }}' : '{{ $chipBase }} {{ $off }}'">
                                <span style="font-size: 15px;">{{ $n['emoji'] }}</span>{{ $n[$L] }}
                            </button>
                        @endforeach
                    </div>
                @endforeach

                <div x-show="needs.includes('other')" x-cloak style="margin-top: 16px;">
                    <textarea x-model="needsOther" maxlength="300" rows="3"
                              placeholder="{{ $isRtl ? 'ما الذي تحتاجه؟' : 'What else do you need?' }}"
                              class="w-full text-black focus:outline-none {{ $fa }}"
                              style="font-size: 15px; padding: 16px 18px; border-radius: 16px; background-color: #F7F7F7; border: 1.5px solid #EDEDED; resize: none;"></textarea>
                </div>
            </section>

            {{-- ── Step 3 — guest count ── --}}
            <section x-show="step === 3" x-cloak>
                <h1 class="font-bold text-black {{ $fa }}" style="font-size: 23px; line-height: 1.3;">
                    {{ $isRtl ? 'كم عدد الضيوف المتوقع؟' : 'How many guests are you expecting?' }}
                </h1>

                <input type="text" inputmode="numeric" x-model="guests" x-ref="guests"
                       @input="guests = guests.replace(/[^0-9]/g, '').slice(0, 5)"
                       placeholder="0"
                       class="w-full text-center font-bold text-black focus:outline-none tabular-nums"
                       style="font-size: 26px; padding: 22px 18px; border-radius: 18px; background-color: #FAFAFA; border: 1.5px solid #EDEDED; margin-top: 20px;">

                <div class="flex flex-wrap justify-center" style="gap: 10px; margin-top: 18px;">
                    @foreach($presets as $n)
                        <button type="button" @click="guests = '{{ $n }}'"
                                class="calm-press calm-round tabular-nums"
                                style="{{ $presetBase }} {{ $off }}"
                                :style="guests === '{{ $n }}' ? '{{ $presetBase }} {{ $on }}' : '{{ $presetBase }} {{ $off }}'">{{ $n }}</button>
                    @endforeach
                </div>
            </section>

            {{-- ── Step 4 — venue + notes ── --}}
            <section x-show="step === 4" x-cloak>
                <h1 class="font-bold text-black {{ $fa }}" style="font-size: 23px; line-height: 1.3;">
                    {{ $isRtl ? 'هل لديك مكان لمناسبتك؟' : 'Do you have a place for your occasion?' }}
                </h1>

                <div class="flex flex-wrap" style="gap: 10px; margin-top: 18px;">
                    @foreach($venues as $key => $v)
                        <button type="button" @click="venueStatus = venueStatus === @js($key) ? null : @js($key)"
                                class="calm-press calm-round {{ $fa }}"
                                style="{{ $venueBase }} {{ $off }}"
                                :style="venueStatus === @js($key) ? '{{ $venueBase }} {{ $on }}' : '{{ $venueBase }} {{ $off }}'">{{ $v[$L] }}</button>
                    @endforeach
                </div>

                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-top: 28px;">
                    {{ $isRtl ? 'ملاحظات إضافية (اختياري)' : 'Extra notes (optional)' }}
                </h2>
                <textarea x-model="notes" maxlength="1000" rows="5"
                          placeholder="{{ $isRtl ? 'أي تفاصيل أو طلبات خاصة…' : 'Any details or special requests…' }}"
                          class="w-full text-black focus:outline-none {{ $fa }}"
                          style="font-size: 15px; padding: 16px 18px; border-radius: 18px; background-color: #FAFAFA; border: 1.5px solid #EDEDED; resize: none; margin-top: 12px;"></textarea>

                <p x-show="error" x-cloak class="{{ $fa }}" style="font-size: 13.5px; color: #e11d48; margin-top: 14px;" x-text="error"></p>
            </section>
        </main>

        {{-- Bottom CTA — black when the step is valid, grey when it isn't --}}
        <div class="fixed bottom-0 inset-x-0 z-30" style="background-color: #fff; border-top: 1px solid #F2F2F2;">
            <div class="mx-auto w-full" style="max-width: 560px; padding: 14px 20px calc(18px + env(safe-area-inset-bottom, 0px));">
                @php $ctaBase = 'padding: 17px; border-radius: 16px; font-size: 15.5px; gap: 10px; transition: background-color 0.2s;'; @endphp
                <button type="button" @click="next()" :disabled="!stepValid() || submitting"
                        class="calm-press w-full flex items-center justify-center font-bold text-white {{ $fa }}"
                        style="{{ $ctaBase }} background-color: #B6B6B6;"
                        :style="(stepValid() && !submitting) ? '{{ $ctaBase }} background-color: #000;' : '{{ $ctaBase }} background-color: #B6B6B6; cursor: not-allowed;'">
                    <svg x-show="submitting" x-cloak class="calm-spinner" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                        <path d="M22 12a10 10 0 0 1-10 10"/>
                    </svg>
                    <span x-text="step === 4 ? @js($isRtl ? 'إرسال الطلب' : 'Send request') : @js($isRtl ? 'التالي' : 'Next')"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══ Confirmation ══ --}}
    <div x-show="done" x-cloak class="min-h-screen flex flex-col">
        <div class="flex-1 flex flex-col items-center justify-center text-center" style="padding: 40px 32px;">
            <span style="font-size: 54px; line-height: 1;">🎉</span>
            <h1 class="font-bold text-black {{ $fa }}" style="font-size: 25px; line-height: 1.3; margin-top: 26px;">
                {{ $isRtl ? 'شكراً! وصلتنا تفاصيلك' : 'Thank you! We\'ve got your details' }}
            </h1>
            <p class="{{ $fa }}" style="font-size: 15px; line-height: 1.75; margin-top: 14px; max-width: 380px; color: #AAAAAA;">
                {{ $isRtl
                    ? 'سيتواصل معك فريق الفعاليات خلال دقائق بأفكار ولنبدأ التخطيط لمناسبتك معاً. يمكنك متابعة طلبك في صفحة الحجوزات.'
                    : 'Our events team will reach out within minutes with ideas, and we\'ll start planning your occasion together. You can follow your request in the Bookings tab.' }}
            </p>
        </div>
        <div style="padding: 14px 20px calc(18px + env(safe-area-inset-bottom, 0px));">
            <div class="mx-auto w-full" style="max-width: 560px;">
                <a href="{{ route('landing') }}"
                   class="calm-press w-full flex items-center justify-center font-bold text-white {{ $fa }}"
                   style="padding: 17px; border-radius: 16px; font-size: 15.5px; background-color: #000;">
                    {{ $isRtl ? 'تم' : 'Done' }}
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function calmOccasions(cfg) {
        // Answers are parked here when a signed-out guest hits "Send request",
        // because the login modal reloads the page.
        const STASH = 'calm:occasion:pending';

        return {
            step: 1,
            type: null,
            typeOther: '',
            needs: [],
            needsOther: '',
            guests: '',
            venueStatus: null,
            notes: '',
            submitting: false,
            done: false,
            error: '',

            init() {
                window.calmTrackOnce?.('occasion', 'start');

                // Coming back from the login modal — restore and finish the job.
                const stashed = sessionStorage.getItem(STASH);
                if (stashed && cfg.authed) {
                    sessionStorage.removeItem(STASH);
                    try {
                        Object.assign(this, JSON.parse(stashed));
                        this.submitting = false;
                        this.$nextTick(() => this.submit());
                    } catch (e) { /* malformed stash — just start fresh */ }
                }
            },

            pickType(key) {
                this.type = key;
                window.calmTrack?.('occasion', 'type', { type: key });
                if (key === 'other') this.$nextTick(() => this.$refs.typeOther?.focus());
            },

            toggleNeed(key) {
                const i = this.needs.indexOf(key);
                if (i === -1) this.needs.push(key);
                else this.needs.splice(i, 1);
            },

            /** Steps 2 and 4 are optional, so they're always passable. */
            stepValid() {
                if (this.step === 1) return this.type !== null && (this.type !== 'other' || this.typeOther.trim() !== '');
                if (this.step === 3) return parseInt(this.guests || '0', 10) >= 1;
                return true;
            },

            back() {
                if (this.step === 1) { window.location.href = cfg.homeUrl; return; }
                this.step--;
            },

            next() {
                if (!this.stepValid() || this.submitting) return;

                if (this.step < 4) {
                    // Report each answer as the guest leaves the step that
                    // captured it — that's the point they committed to it.
                    if (this.step === 2) window.calmTrack?.('occasion', 'needs', { needs: [...this.needs] });
                    if (this.step === 3) window.calmTrack?.('occasion', 'guests', { range: window.calmGuestsRange?.(this.guests) });

                    this.step++;
                    // Steps that open on a text field focus it, except notes —
                    // that one sits under a choice the guest makes first.
                    if (this.step === 3) this.$nextTick(() => this.$refs.guests?.focus());
                    return;
                }

                this.submit();
            },

            payload() {
                return {
                    occasion_type: this.type,
                    occasion_type_other: this.type === 'other' ? this.typeOther.trim() : null,
                    // Spread: Alpine's reactive proxy serialises as {"0": …}.
                    needs: [...this.needs],
                    needs_other: this.needs.includes('other') && this.needsOther.trim() !== '' ? this.needsOther.trim() : null,
                    guests: parseInt(this.guests || '0', 10),
                    venue_status: this.venueStatus,
                    notes: this.notes.trim() !== '' ? this.notes.trim() : null,
                };
            },

            async submit() {
                if (this.submitting) return;

                // Signed out: park the answers, sign in, resume on reload.
                if (!cfg.authed) {
                    sessionStorage.setItem(STASH, JSON.stringify({
                        step: this.step, type: this.type, typeOther: this.typeOther,
                        needs: [...this.needs], needsOther: this.needsOther,
                        guests: this.guests, venueStatus: this.venueStatus, notes: this.notes,
                    }));
                    // `reason` swaps the modal's welcome copy for "One last step —
                    // sign in to submit your occasion request and track it."
                    window.dispatchEvent(new CustomEvent('calm-open-login', { detail: { reason: 'occasion' } }));
                    return;
                }

                this.submitting = true;
                this.error = '';

                try {
                    const res = await fetch('/api/occasion-requests', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(this.payload()),
                    });

                    if (!res.ok) {
                        const body = await res.json().catch(() => ({}));
                        const first = body?.data?.errors ? Object.values(body.data.errors)[0]?.[0] : null;
                        this.error = first || (cfg.isRtl
                            ? 'تعذّر إرسال الطلب، حاول مرة أخرى.'
                            : 'We could not send your request. Please try again.');
                        this.submitting = false;
                        return;
                    }

                    // has_notes, never the notes themselves — free text is the
                    // one field a guest could put a phone number in.
                    window.calmTrack?.('occasion', 'submit', {
                        occasion_type: this.type,
                        needs: [...this.needs],
                        guests: parseInt(this.guests || '0', 10),
                        guests_range: window.calmGuestsRange?.(this.guests),
                        venue_status: this.venueStatus,
                        has_notes: this.notes.trim() !== '',
                    });

                    this.done = true;
                    window.scrollTo({ top: 0 });
                } catch (e) {
                    this.error = cfg.isRtl
                        ? 'تحقّق من اتصالك بالإنترنت وحاول مرة أخرى.'
                        : 'Check your connection and try again.';
                }

                this.submitting = false;
            },
        };
    }
</script>
@endsection
