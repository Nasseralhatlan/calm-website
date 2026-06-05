@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', ($isRtl ? 'تسجيل الدخول' : 'Sign in') . ' · Calm')

@section('body')
<div class="min-h-screen bg-[#fafafa] flex flex-col">
    {{-- ── Top bar ── --}}
    <header class="bg-white border-b border-[#ebebeb]"
            style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto flex items-center justify-between px-6 sm:px-10" style="max-width: 1280px; height: 72px;">
            <a href="{{ route('landing') }}" class="flex items-center">
                <img src="/assets/logo/logo.png" alt="Calm" class="h-9 w-auto select-none" draggable="false">
            </a>
            <div class="flex items-center" style="gap: 8px;">
                <a href="{{ route('landing') }}" class="hidden sm:inline text-[13px] text-[#717171] hover:text-[#222] {{ $fa }}">
                    {{ $isRtl ? 'العودة للرئيسية' : 'Back to home' }}
                </a>
                <form method="POST" action="{{ url('/locale/'.($locale === 'ar' ? 'en' : 'ar')) }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="text-[13px] font-semibold text-[#222] hover:bg-[#f7f7f7] {{ $locale === 'en' ? 'font-arabic' : '' }}"
                            style="padding: 8px 14px; border-radius: 12px;">
                        {{ $locale === 'ar' ? 'English' : 'العربية' }}
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- ── Centered card ── --}}
    <div class="flex-1 flex items-center justify-center px-6" style="padding-top: 40px; padding-bottom: 40px;">
        <div class="w-full bg-white"
             style="max-width: 440px; padding: 40px 32px; border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.05);">

            <h1 class="text-[26px] sm:text-[28px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 8px; line-height: 1.25;">
                {{ $isRtl ? 'مرحباً بعودتك' : 'Welcome back' }}
            </h1>
            <p class="text-[14px] sm:text-[15px] text-[#717171] {{ $fa }}" style="margin-bottom: 28px; line-height: 1.5;">
                {{ $isRtl ? 'أدخل رقم جوالك لتصلك رسالة برمز التحقق' : 'Enter your phone number and we\'ll text you a verification code' }}
            </p>

            @if(session('status'))
                <div class="text-[13px] text-[#15803d] bg-[#f0fdf4] border border-[#bbf7d0] {{ $fa }}"
                     style="padding: 12px 14px; border-radius: 14px; margin-bottom: 18px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.request') }}" novalidate>
                @csrf
                @if(! empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

                <label class="block text-[13px] font-semibold text-[#222] {{ $fa }}" style="margin-bottom: 8px;">
                    {{ $isRtl ? 'رقم الجوال' : 'Phone number' }}
                </label>

                {{-- Force LTR on the phone field so the dial-code prefix never flips --}}
                <div class="flex items-center bg-[#fafafa] border-2 border-[#ebebeb] focus-within:border-[#222] transition-colors"
                     dir="ltr"
                     style="border-radius: 16px;">
                    {{-- Active-countries dial-code dropdown. New active rows seed in here automatically. --}}
                    <select name="country_id"
                            class="bg-transparent text-[15px] font-semibold text-[#222] tabular-nums shrink-0 focus:outline-none cursor-pointer"
                            style="padding: 14px; padding-inline-end: 28px; border-right: 1px solid #ebebeb;"
                            aria-label="Country dial code">
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}"
                                    data-code="{{ $country->country_code }}"
                                    @selected(old('country_id', $countries->first()?->id) === $country->id)>
                                {{ $country->avatar ? $country->avatar.'  ' : '' }}{{ $country->dial_code }}
                            </option>
                        @endforeach
                    </select>
                    <input type="tel"
                           name="phone"
                           inputmode="numeric"
                           pattern="5[0-9]{8}"
                           maxlength="9"
                           autocomplete="tel-national"
                           value="{{ old('phone') }}"
                           placeholder="5xxxxxxxx"
                           required
                           autofocus
                           class="flex-1 bg-transparent text-[17px] text-[#222] focus:outline-none tabular-nums"
                           style="padding: 14px; min-width: 0; letter-spacing: 0.5px;">
                </div>

                @error('country_id')
                    <p class="text-[13px] text-[#dc2626] {{ $fa }}" style="margin-top: 10px;">{{ $message }}</p>
                @enderror

                @error('phone')
                    <p class="text-[13px] text-[#dc2626] {{ $fa }}" style="margin-top: 10px;">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full font-bold text-white bg-[#222] hover:bg-black active:scale-[0.98] transition-all {{ $fa }}"
                        style="margin-top: 24px; padding: 15px 20px; border-radius: 16px; font-size: 15px;">
                    {{ $isRtl ? 'إرسال رمز التحقق' : 'Send verification code' }}
                </button>

                <p class="text-center text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 18px; line-height: 1.6;">
                    {{ $isRtl ? 'بالمتابعة، أنت توافق على شروط الاستخدام وسياسة الخصوصية الخاصة بكالم.' : 'By continuing, you agree to Calm\'s Terms and Privacy Policy.' }}
                </p>
            </form>
        </div>
    </div>
</div>
@endsection
