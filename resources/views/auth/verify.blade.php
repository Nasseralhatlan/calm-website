@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', ($isRtl ? 'التحقق' : 'Verify') . ' · Calm')

@section('body')
{{-- App-parity login modal — step 2 of 2 (OTP). --}}
<div class="calm-auth-backdrop min-h-screen flex flex-col justify-end sm:justify-center sm:items-center sm:px-6">
    <div class="calm-auth-sheet bg-white w-full">
        <div style="padding: 26px 24px calc(40px + env(safe-area-inset-bottom, 0px));">

            {{-- Logo + back --}}
            <div class="flex items-center justify-center" style="position: relative; margin-bottom: 34px;">
                <img src="/assets/logo/logo.png" alt="Calm" style="height: 34px; width: auto;" draggable="false">
                <a href="{{ route('login', array_filter(['next' => $next ?? null])) }}" aria-label="{{ $isRtl ? 'رجوع' : 'Back' }}"
                   class="calm-press calm-round flex items-center justify-center bg-white text-black"
                   style="position: absolute; inset-inline-start: 0; width: 44px; height: 44px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.1);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
                         style="{{ $isRtl ? '' : 'transform: scaleX(-1);' }}">
                        <path d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>

            <h1 class="text-center font-bold text-black {{ $fa }}" style="font-size: 26px; line-height: 1.3;">
                {{ $isRtl ? 'تحقق من رقم الجوال' : 'Verify your phone' }}
            </h1>
            <p class="text-center {{ $fa }}" style="font-size: 15px; line-height: 1.6; margin-top: 10px; color: #AAAAAA;">
                {{ $isRtl ? 'تم إرسال رمز التحقق إلى رقم جوالك، أدخله للمتابعة بأمان' : 'We sent a verification code to your phone — enter it to continue' }}
            </p>

            @if(session('status'))
                <div class="text-[13px] text-[#15803d] bg-[#f0fdf4] {{ $fa }}"
                     style="padding: 12px 14px; border-radius: 14px; margin-top: 18px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.verify.submit') }}" novalidate x-data="{ c: '' }">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                @if(! empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

                <input type="text"
                       name="otp"
                       x-model="c"
                       inputmode="numeric"
                       pattern="\d{6}"
                       maxlength="6"
                       autocomplete="one-time-code"
                       required
                       autofocus
                       dir="ltr"
                       placeholder="－ － － － － －"
                       class="calm-input w-full bg-white text-[24px] text-center font-bold text-black tabular-nums focus:outline-none"
                       style="margin-top: 38px; padding: 18px 14px; border: 1.5px solid #000; border-radius: 18px; letter-spacing: 0.35em;">

                @error('otp')
                    <p class="text-[13px] text-[#dc2626] text-center {{ $fa }}" style="margin-top: 10px;">{{ $message }}</p>
                @enderror

                {{-- Countdown («الوقت المتبقي 2:54») --}}
                @if(! empty($expiresAtMs))
                    <div x-data="otpCountdown({{ (int) $expiresAtMs }})" x-init="start"
                         class="flex items-center justify-center {{ $fa }}" style="margin-top: 70px; gap: 8px;">
                        <template x-if="remainingMs > 0">
                            <span class="flex items-center" style="gap: 8px;">
                                <span class="font-bold text-black tabular-nums" dir="ltr" style="font-size: 16px;" x-text="format"></span>
                                <span style="font-size: 14px; color: #AAAAAA;">{{ $isRtl ? 'الوقت المتبقي' : 'Time remaining' }}</span>
                            </span>
                        </template>
                        <template x-if="remainingMs === 0">
                            <span style="font-size: 14px; color: #DC2626;" class="{{ $fa }}">{{ $isRtl ? 'انتهت صلاحية الرمز' : 'Code expired' }}</span>
                        </template>
                    </div>
                @endif

                <button type="submit" :disabled="!/^\d{6}$/.test(c)"
                        class="calm-press w-full font-bold text-white {{ $fa }}"
                        :style="'margin-top: 18px; padding: 17px; border-radius: 18px; font-size: 16px; transition: background-color 0.2s; background-color: ' + (/^\d{6}$/.test(c) ? '#000' : '#BDBDBD') + ';'"
                        style="margin-top: 18px; padding: 17px; border-radius: 18px; font-size: 16px; background-color: #BDBDBD;">
                    {{ $isRtl ? 'تسجيل الدخول' : 'Sign in' }}
                </button>

                <p class="text-center text-[13px] {{ $fa }}" style="margin-top: 18px; color: #AAAAAA;">
                    {{ $isRtl ? 'لم يصلك الرمز؟' : "Didn't get the code?" }}
                    <a href="{{ route('login', array_filter(['next' => $next ?? null])) }}" class="font-bold text-black hover:underline" style="margin-inline-start: 4px;">
                        {{ $isRtl ? 'إعادة الإرسال' : 'Resend' }}
                    </a>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
    // Local countdown driven by the server-supplied expires_at. We don't trust
    // the client clock to be correct, but the displayed value is for UX only —
    // the server still re-checks `expires_at` on submit.
    function otpCountdown(expiresAtMs) {
        return {
            expiresAtMs,
            remainingMs: Math.max(0, expiresAtMs - Date.now()),
            timer: null,
            get format() {
                const total = Math.floor(this.remainingMs / 1000);
                const m = Math.floor(total / 60);
                const s = total % 60;
                return `${m}:${s.toString().padStart(2, '0')}`;
            },
            start() {
                this.tick();
                this.timer = setInterval(() => this.tick(), 1000);
            },
            tick() {
                this.remainingMs = Math.max(0, this.expiresAtMs - Date.now());
                if (this.remainingMs === 0 && this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            },
        };
    }
</script>

<style>
    /* Modal feel: bottom sheet on mobile, centered fading modal on desktop. */
    .calm-auth-backdrop { background-color: #E9E9E9; }
    .calm-auth-sheet {
        border-radius: 28px 28px 0 0;
        min-height: calc(100vh - 14px);
        box-shadow: 0 -4px 16px rgba(0,0,0,0.08);
        animation: calm-sheet-up 0.42s cubic-bezier(0.22, 0.9, 0.36, 1);
    }
    @media (min-width: 640px) {
        .calm-auth-backdrop { background-color: rgba(25, 25, 25, 0.5); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .calm-auth-sheet {
            max-width: 480px;
            min-height: 0;
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            animation: calm-modal-in 0.3s ease-out;
        }
    }
    @keyframes calm-sheet-up { from { transform: translateY(100%); } to { transform: none; } }
    @keyframes calm-modal-in { from { opacity: 0; transform: scale(0.96) translateY(12px); } to { opacity: 1; transform: none; } }
</style>
@endsection
