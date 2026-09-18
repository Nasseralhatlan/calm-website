{{-- Global in-place login modal — SPA-style: opens over ANY page (no
     navigation), runs the same OTP flow as the app through
     /api/auth/otp/request + /api/auth/otp/verify (verify sets the JWT
     cookie), then reloads the page (or follows `next`) so server-rendered
     auth state catches up. Bottom sheet on mobile, centered fading modal on
     desktop. Open it from anywhere with:

       $dispatch('calm-open-login')              → reload current page after login
       $dispatch('calm-open-login', { next })    → redirect to `next` after login
       $dispatch('calm-open-login', { reason })  → swap the welcome copy for one
                                                   that names the job being
                                                   interrupted (see REASONS)

     Included by layouts.app for guests; /login stays as the deep-link
     fallback. --}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp
<div x-data="calmLoginModal()"
     x-on:calm-open-login.window="open($event.detail || {})"
     x-show="openState" x-cloak
     class="calm-login-backdrop fixed inset-0 z-50 flex flex-col justify-end sm:justify-center sm:items-center sm:px-6"
     role="dialog" aria-modal="true">
    <div class="absolute inset-0" @click="close()"></div>

    <div class="calm-login-sheet bg-white w-full relative" :style="kb ? 'margin-bottom: ' + kb + 'px;' : ''">
        <div style="padding: 26px 24px calc(40px + env(safe-area-inset-bottom, 0px));">

            {{-- Logo + close/back --}}
            <div class="flex items-center justify-center" style="position: relative; margin-bottom: 30px;">
                <img src="/assets/logo/logo.png" alt="Calm" style="height: 32px; width: auto;" draggable="false">
                <button type="button" @click="step === 'otp' ? step = 'phone' : close()"
                        :aria-label="step === 'otp' ? '{{ $isRtl ? 'رجوع' : 'Back' }}' : '{{ $isRtl ? 'إغلاق' : 'Close' }}'"
                        class="calm-press calm-round flex items-center justify-center bg-white text-black"
                        style="position: absolute; inset-inline-start: 0; width: 44px; height: 44px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.1);">
                    <span x-show="step === 'phone'" style="font-size: 16px;">✕</span>
                    <svg x-show="step === 'otp'" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                         style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                        <path d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            {{-- ── Step 1: phone ── --}}
            <div x-show="step === 'phone'">
                {{-- Copy follows the reason the modal was opened for (see REASONS). --}}
                <h2 class="text-center font-bold text-black {{ $fa }}" style="font-size: 24px; line-height: 1.3;"
                    x-text="copy.title">
                    {{ $isRtl ? 'حياك الله فى كالم' : 'Welcome to Calm' }}
                </h2>
                <p class="text-center {{ $fa }}" style="font-size: 14px; line-height: 1.6; margin-top: 8px; color: #AAAAAA;"
                   x-text="copy.subtitle">
                    {{ $isRtl ? 'سجّل دخولك لاكتشاف أجمل الوجهات، وإدارة حجوزاتك بكل سهولة' : 'Sign in to discover the best destinations and manage your bookings with ease' }}
                </p>

                <div class="flex items-stretch" style="gap: 12px; margin-top: 32px;">
                    <div class="shrink-0 flex items-center" dir="ltr" style="background-color: #FAFAFA; border-radius: 18px; padding: 0 6px;">
                        <select x-model="countryId"
                                class="bg-transparent text-[15px] font-bold text-black tabular-nums focus:outline-none cursor-pointer"
                                style="appearance: none; -webkit-appearance: none; -moz-appearance: none; padding: 15px 10px;"
                                aria-label="Country dial code">
                            <template x-for="c in countries" :key="c.id">
                                <option :value="c.id" x-text="(c.avatar ? c.avatar + '  ' : '') + c.dial_code"></option>
                            </template>
                        </select>
                    </div>
                    <input type="tel" x-model="phone" inputmode="numeric" maxlength="9" placeholder="5xxxxxxxx"
                           autocomplete="tel-national" dir="ltr" @keydown.enter.prevent="requestOtp()"
                           @focus="setTimeout(() => $el.scrollIntoView({ block: 'center', behavior: 'smooth' }), 300)"
                           class="calm-login-input flex-1 bg-white text-[17px] font-bold text-black focus:outline-none tabular-nums"
                           style="padding: 15px 18px; min-width: 0; border: 1.5px solid #E9E9E9; border-radius: 18px; letter-spacing: 0.5px; text-align: end; transition: border-color 0.15s;">
                </div>

                <p x-show="error" x-cloak class="text-[13px] text-[#DC2626] {{ $fa }}" style="margin-top: 10px;" x-text="error"></p>

                <button type="button" @click="requestOtp()" :disabled="!validPhone() || busy"
                        class="calm-press w-full font-bold text-white inline-flex items-center justify-center {{ $fa }}"
                        :style="'margin-top: 40px; padding: 16px; border-radius: 18px; font-size: 15px; gap: 8px; transition: background-color 0.2s; background-color: ' + (validPhone() && !busy ? '#000' : '#BDBDBD') + ';'">
                    <svg x-show="busy" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.2-8.56">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                    <span>{{ $isRtl ? 'التالى' : 'Next' }}</span>
                </button>

                <p class="text-center text-[12px] {{ $fa }}" style="margin-top: 14px; line-height: 1.6; color: #AAAAAA;">
                    {{ $isRtl ? 'بالمتابعة، أنت توافق على شروط الاستخدام وسياسة الخصوصية الخاصة بكالم.' : 'By continuing, you agree to Calm\'s Terms and Privacy Policy.' }}
                </p>
            </div>

            {{-- ── Step 2: OTP ── --}}
            <div x-show="step === 'otp'" x-cloak>
                <h2 class="text-center font-bold text-black {{ $fa }}" style="font-size: 24px; line-height: 1.3;">
                    {{ $isRtl ? 'تحقق من رقم الجوال' : 'Verify your phone' }}
                </h2>
                <p class="text-center {{ $fa }}" style="font-size: 14px; line-height: 1.6; margin-top: 8px; color: #AAAAAA;">
                    {{ $isRtl ? 'تم إرسال رمز التحقق إلى رقم جوالك، أدخله للمتابعة بأمان' : 'We sent a verification code to your phone — enter it to continue' }}
                </p>

                <input type="text" x-model="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                       dir="ltr" placeholder="－ － － － － －" @keydown.enter.prevent="verifyOtp()"
                       @focus="setTimeout(() => $el.scrollIntoView({ block: 'center', behavior: 'smooth' }), 300)"
                       class="calm-login-input w-full bg-white text-[22px] text-center font-bold text-black tabular-nums focus:outline-none"
                       style="margin-top: 30px; padding: 16px 14px; border: 1.5px solid #000; border-radius: 18px; letter-spacing: 0.35em;">

                <p x-show="error" x-cloak class="text-[13px] text-[#DC2626] text-center {{ $fa }}" style="margin-top: 10px;" x-text="error"></p>

                <div class="flex items-center justify-center {{ $fa }}" style="margin-top: 34px; gap: 8px;">
                    <template x-if="resendIn > 0">
                        <span class="flex items-center" style="gap: 8px;">
                            <span class="font-bold text-black tabular-nums" dir="ltr" style="font-size: 15px;" x-text="fmtCountdown()"></span>
                            <span style="font-size: 13px; color: #AAAAAA;">{{ $isRtl ? 'الوقت المتبقي' : 'Time remaining' }}</span>
                        </span>
                    </template>
                    <template x-if="resendIn === 0">
                        <button type="button" @click="requestOtp()" class="font-bold text-black underline {{ $fa }}" style="font-size: 13px;">
                            {{ $isRtl ? 'إعادة الإرسال' : 'Resend code' }}
                        </button>
                    </template>
                </div>

                <button type="button" @click="verifyOtp()" :disabled="!/^\d{6}$/.test(otp) || busy"
                        class="calm-press w-full font-bold text-white inline-flex items-center justify-center {{ $fa }}"
                        :style="'margin-top: 16px; padding: 16px; border-radius: 18px; font-size: 15px; gap: 8px; transition: background-color 0.2s; background-color: ' + (/^\d{6}$/.test(otp) && !busy ? '#000' : '#BDBDBD') + ';'">
                    <svg x-show="busy" x-cloak width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.2-8.56">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.9s" repeatCount="indefinite"/>
                        </path>
                    </svg>
                    <span>{{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    if (!window.calmLoginModal) {
        window.calmLoginModal = function () {
            const AR = @js($isRtl);

            return {
                openState: false,
                step: 'phone',
                next: null,
                // Why we're asking. Generic by default; a flow that interrupts
                // something the guest already started should say so instead of
                // the cold-open welcome. Add cases as flows need them.
                reason: null,
                REASONS: {
                    occasion: {
                        title: AR ? 'خطوة أخيرة' : 'One last step',
                        subtitle: AR
                            ? 'سجّل دخولك لإرسال طلب مناسبتك ومتابعته.'
                            : 'Sign in to submit your occasion request and track it.',
                    },
                },
                get copy() {
                    return this.REASONS[this.reason] || {
                        title: AR ? 'حياك الله فى كالم' : 'Welcome to Calm',
                        subtitle: AR
                            ? 'سجّل دخولك لاكتشاف أجمل الوجهات، وإدارة حجوزاتك بكل سهولة'
                            : 'Sign in to discover the best destinations and manage your bookings with ease',
                    };
                },
                countries: [],
                countryId: null,
                phone: '',
                otp: '',
                error: '',
                busy: false,
                resendIn: 0,
                _timer: null,

                async open(detail) {
                    this.next = detail.next || null;
                    this.reason = detail.reason || null;
                    // No identifier on any login:* event — these are the screens
                    // where PII is most tempting and least necessary.
                    window.calmTrack?.('login', 'open', { reason: this.reason || 'generic' });
                    this.step = 'phone';
                    this.error = '';
                    this.openState = true;
                    document.body.style.overflow = 'hidden';
                    this.watchKeyboard();
                    if (!this.countries.length) {
                        try {
                            const res = await fetch('/api/countries', { headers: { Accept: 'application/json' } });
                            const list = ((await res.json()).data || []);
                            // Saudi first, like the app.
                            this.countries = list.sort((a, b) => (a.country_code === 'SA' ? -1 : 0) - (b.country_code === 'SA' ? -1 : 0));
                            this.countryId = this.countries[0]?.id || null;
                        } catch (e) { /* dial list is presentational; phone OTP is KSA */ }
                    }
                },
                close() {
                    this.openState = false;
                    document.body.style.overflow = '';
                    this.stopTimer();
                    this.unwatchKeyboard();
                },

                // ── Keyboard clearance (iOS doesn't resize the layout
                //    viewport, so lift the sheet by the keyboard's height) ──
                kb: 0,
                _vv: null,
                watchKeyboard() {
                    if (!window.visualViewport) return;
                    this._vv = () => {
                        const vv = window.visualViewport;
                        this.kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
                    };
                    window.visualViewport.addEventListener('resize', this._vv);
                    window.visualViewport.addEventListener('scroll', this._vv);
                    this._vv();
                },
                unwatchKeyboard() {
                    if (this._vv && window.visualViewport) {
                        window.visualViewport.removeEventListener('resize', this._vv);
                        window.visualViewport.removeEventListener('scroll', this._vv);
                    }
                    this._vv = null;
                    this.kb = 0;
                },

                normPhone() {
                    return this.phone.replace(/\D/g, '').replace(/^00966|^966/, '').replace(/^0/, '');
                },
                validPhone() {
                    return /^5\d{8}$/.test(this.normPhone());
                },

                async requestOtp() {
                    if (!this.validPhone() || this.busy) return;
                    this.busy = true; this.error = '';
                    try {
                        const res = await fetch('/api/auth/otp/request', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                            body: JSON.stringify({ phone: this.normPhone() }),
                        });
                        const json = await res.json();
                        if (!res.ok) {
                            this.error = json.data?.errors?.phone?.[0] || json.message || (AR ? 'حدث خطأ' : 'Something went wrong');
                            return;
                        }
                        window.calmTrack?.('login', 'otp_sent');
                        this.step = 'otp';
                        this.otp = '';
                        this.startTimer(180);
                    } catch (e) {
                        this.error = AR ? 'تعذر الاتصال — أعد المحاولة.' : 'Connection failed — try again.';
                    } finally {
                        this.busy = false;
                    }
                },

                async verifyOtp() {
                    if (!/^\d{6}$/.test(this.otp) || this.busy) return;
                    this.busy = true; this.error = '';
                    window.calmTrack?.('login', 'otp_submit');
                    try {
                        const res = await fetch('/api/auth/otp/verify', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                            body: JSON.stringify({ phone: this.normPhone(), otp: this.otp }),
                        });
                        if (!res.ok) {
                            this.error = AR ? 'رمز غير صحيح أو منتهي.' : 'Wrong or expired code.';
                            return;
                        }
                        // Bind the anonymous journey to the person BEFORE the
                        // reload, so the events already sent this session join up.
                        // The reload re-identifies from the meta tag anyway; this
                        // just closes the gap if a caller skips the reload.
                        try {
                            const body = await res.clone().json();
                            window.calmIdentify?.(body?.data?.user?.id);
                        } catch (e) { /* identity also resolves on reload */ }

                        // The JWT cookie is set by the response — refresh so the
                        // server-rendered auth state (hearts, tabs, حسابى) updates.
                        if (this.next) { window.location.href = this.next; } else { window.location.reload(); }
                    } catch (e) {
                        this.error = AR ? 'تعذر الاتصال — أعد المحاولة.' : 'Connection failed — try again.';
                    } finally {
                        this.busy = false;
                    }
                },

                startTimer(seconds) {
                    this.stopTimer();
                    this.resendIn = seconds;
                    this._timer = setInterval(() => {
                        this.resendIn = Math.max(0, this.resendIn - 1);
                        if (this.resendIn === 0) this.stopTimer();
                    }, 1000);
                },
                stopTimer() {
                    if (this._timer) { clearInterval(this._timer); this._timer = null; }
                },
                fmtCountdown() {
                    const m = Math.floor(this.resendIn / 60), s = this.resendIn % 60;
                    return `${m}:${String(s).padStart(2, '0')}`;
                },
            };
        };
    }
</script>
<style>
    .calm-login-input:focus { border-color: #000 !important; }
    .calm-login-backdrop { background-color: rgba(25, 25, 25, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); overflow-y: auto; }
    .calm-login-sheet {
        border-radius: 28px 28px 0 0;
        box-shadow: 0 -4px 16px rgba(0,0,0,0.12);
        animation: calm-login-up 0.4s cubic-bezier(0.22, 0.9, 0.36, 1);
        transition: margin-bottom 0.2s ease;
    }
    @media (min-width: 640px) {
        .calm-login-sheet {
            max-width: 480px;
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            animation: calm-login-in 0.3s ease-out;
        }
    }
    @keyframes calm-login-up { from { transform: translateY(100%); } to { transform: none; } }
    @keyframes calm-login-in { from { opacity: 0; transform: scale(0.96) translateY(12px); } to { opacity: 1; transform: none; } }
</style>
