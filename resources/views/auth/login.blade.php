@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', ($isRtl ? 'تسجيل الدخول' : 'Sign in') . ' · Calm')

@section('body')
{{-- App-parity login modal — step 1 of 2 (phone → OTP). --}}
<div class="calm-auth-backdrop min-h-screen flex flex-col justify-end sm:justify-center sm:items-center sm:px-6">
    <div class="calm-auth-sheet bg-white w-full">
        <div style="padding: 26px 24px calc(40px + env(safe-area-inset-bottom, 0px));">

            {{-- Logo + close --}}
            <div class="flex items-center justify-center" style="position: relative; margin-bottom: 34px;">
                <img src="/assets/logo/logo.png" alt="Calm" style="height: 34px; width: auto;" draggable="false">
                <a href="{{ route('landing') }}" aria-label="{{ $isRtl ? 'إغلاق' : 'Close' }}"
                   class="calm-press calm-round flex items-center justify-center bg-white text-black"
                   style="position: absolute; inset-inline-start: 0; width: 44px; height: 44px; border-radius: 50%; box-shadow: 0 0 25px rgba(0,0,0,0.1); font-size: 16px;">✕</a>
            </div>

            <h1 class="text-center font-bold text-black {{ $fa }}" style="font-size: 26px; line-height: 1.3;">
                {{ $isRtl ? 'حياك الله فى كالم' : 'Welcome to Calm' }}
            </h1>
            <p class="text-center {{ $fa }}" style="font-size: 15px; line-height: 1.6; margin-top: 10px; color: #AAAAAA;">
                {{ $isRtl ? 'سجّل دخولك لاكتشاف أجمل الوجهات، وإدارة حجوزاتك بكل سهولة' : 'Sign in to discover the best destinations and manage your bookings with ease' }}
            </p>

            @if(session('status'))
                <div class="text-[13px] text-[#15803d] bg-[#f0fdf4] {{ $fa }}"
                     style="padding: 12px 14px; border-radius: 14px; margin-top: 18px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.request') }}" novalidate x-data="{ p: @js(old('phone', '')) }">
                @csrf
                @if(! empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

                <div class="flex items-stretch" style="gap: 12px; margin-top: 38px;">
                    {{-- Dial-code pill (app: «+966 🇸🇦» box) --}}
                    <div class="shrink-0 flex items-center" dir="ltr"
                         style="background-color: #FAFAFA; border-radius: 18px; padding: 0 6px;">
                        <select name="country_id"
                                class="bg-transparent text-[15px] font-bold text-black tabular-nums focus:outline-none cursor-pointer"
                                style="appearance: none; -webkit-appearance: none; -moz-appearance: none; padding: 16px 10px;"
                                aria-label="Country dial code">
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}"
                                        data-code="{{ $country->country_code }}"
                                        @selected(old('country_id', $countries->first()?->id) === $country->id)>
                                    {{ $country->avatar ? $country->avatar.'  ' : '' }}{{ $country->dial_code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Phone input --}}
                    <input type="tel"
                           name="phone"
                           x-model="p"
                           inputmode="numeric"
                           pattern="5[0-9]{8}"
                           maxlength="9"
                           autocomplete="tel-national"
                           placeholder="5xxxxxxxx"
                           required
                           autofocus
                           dir="ltr"
                           class="calm-input flex-1 bg-white text-[17px] font-bold text-black focus:outline-none tabular-nums"
                           style="padding: 16px 18px; min-width: 0; border: 1.5px solid #E9E9E9; border-radius: 18px; letter-spacing: 0.5px; text-align: end; transition: border-color 0.15s;">
                </div>

                @error('country_id')
                    <p class="text-[13px] text-[#dc2626] {{ $fa }}" style="margin-top: 10px;">{{ $message }}</p>
                @enderror
                @error('phone')
                    <p class="text-[13px] text-[#dc2626] {{ $fa }}" style="margin-top: 10px;">{{ $message }}</p>
                @enderror

                <button type="submit" :disabled="!/^5\d{8}$/.test(p)"
                        class="calm-press w-full font-bold text-white {{ $fa }}"
                        :style="'margin-top: 120px; padding: 17px; border-radius: 18px; font-size: 16px; transition: background-color 0.2s; background-color: ' + (/^5\d{8}$/.test(p) ? '#000' : '#BDBDBD') + ';'"
                        style="margin-top: 120px; padding: 17px; border-radius: 18px; font-size: 16px; background-color: #BDBDBD;">
                    {{ $isRtl ? 'التالى' : 'Next' }}
                </button>

                <p class="text-center text-[12px] {{ $fa }}" style="margin-top: 16px; line-height: 1.6; color: #AAAAAA;">
                    {{ $isRtl ? 'بالمتابعة، أنت توافق على شروط الاستخدام وسياسة الخصوصية الخاصة بكالم.' : 'By continuing, you agree to Calm\'s Terms and Privacy Policy.' }}
                </p>
            </form>
        </div>
    </div>
</div>
<style>
    .calm-input:focus { border-color: #000 !important; }
</style>
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
        /* The tall mobile gap before التالى doesn't suit the compact modal. */
        .calm-auth-sheet button[type="submit"] { margin-top: 44px !important; }
    }
    @keyframes calm-sheet-up { from { transform: translateY(100%); } to { transform: none; } }
    @keyframes calm-modal-in { from { opacity: 0; transform: scale(0.96) translateY(12px); } to { opacity: 1; transform: none; } }
</style>
@endsection
