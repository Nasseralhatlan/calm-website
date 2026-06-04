@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

@section('title', ($isRtl ? 'التحقق' : 'Verify') . ' · Calm')

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
                <a href="{{ route('login') }}" class="hidden sm:inline text-[13px] text-[#717171] hover:text-[#222] {{ $fa }}">
                    {{ $isRtl ? 'تغيير الرقم' : 'Change number' }}
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

    {{-- ── Card ── --}}
    <div class="flex-1 flex items-center justify-center px-6" style="padding-top: 40px; padding-bottom: 40px;">
        <div class="w-full bg-white"
             style="max-width: 440px; padding: 40px 32px; border-radius: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.05);">

            <div class="flex items-center justify-center" style="margin-bottom: 20px;">
                <span class="flex items-center justify-center"
                      style="width: 60px; height: 60px; border-radius: 18px; background-color: #fafafa; color: #222;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="2" width="12" height="20" rx="3"></rect>
                        <line x1="11" y1="18" x2="13" y2="18"></line>
                    </svg>
                </span>
            </div>

            <h1 class="text-[22px] sm:text-[24px] font-bold text-center text-[#222] {{ $fa }}" style="margin-bottom: 8px; line-height: 1.25;">
                {{ $isRtl ? 'أدخل رمز التحقق' : 'Enter the code' }}
            </h1>
            <p class="text-[14px] text-center text-[#717171] {{ $fa }}" style="margin-bottom: 24px; line-height: 1.6;">
                {{ $isRtl ? 'أرسلنا رمزاً مكوناً من 6 أرقام إلى' : 'We sent a 6-digit code to' }}<br>
                <span dir="ltr" class="font-semibold text-[#222]">+966 {{ $phone }}</span>
            </p>

            @if(session('status'))
                <div class="text-[13px] text-[#15803d] bg-[#f0fdf4] border border-[#bbf7d0] {{ $fa }}"
                     style="padding: 12px 14px; border-radius: 14px; margin-bottom: 16px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.verify.submit') }}" novalidate>
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                @if(! empty($next))<input type="hidden" name="next" value="{{ $next }}">@endif

                <input type="text"
                       name="otp"
                       inputmode="numeric"
                       pattern="\d{6}"
                       maxlength="6"
                       autocomplete="one-time-code"
                       required
                       autofocus
                       dir="ltr"
                       placeholder="• • • • • •"
                       class="w-full bg-[#fafafa] border-2 border-[#ebebeb] focus:border-[#222] text-[26px] text-center font-bold text-[#222] tabular-nums focus:outline-none transition-colors"
                       style="padding: 18px 14px; border-radius: 16px; letter-spacing: 0.4em;">

                @error('otp')
                    <p class="text-[13px] text-[#dc2626] text-center {{ $fa }}" style="margin-top: 10px;">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full font-bold text-white bg-[#222] hover:bg-black active:scale-[0.98] transition-all {{ $fa }}"
                        style="margin-top: 22px; padding: 15px 20px; border-radius: 16px; font-size: 15px;">
                    {{ $isRtl ? 'تأكيد ومتابعة' : 'Verify and continue' }}
                </button>
            </form>

            <p class="text-center text-[13px] text-[#717171] {{ $fa }}" style="margin-top: 22px;">
                {{ $isRtl ? 'لم يصلك الرمز؟' : "Didn't get the code?" }}
                <a href="{{ route('login') }}" class="font-semibold text-[#222] hover:underline" style="margin-inline-start: 4px;">
                    {{ $isRtl ? 'إعادة الإرسال' : 'Resend' }}
                </a>
            </p>
        </div>
    </div>
</div>
@endsection
