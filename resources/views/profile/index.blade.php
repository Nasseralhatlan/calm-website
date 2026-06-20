@extends('layouts.user')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $inputCss = 'width:100%;background:#f7f7f7;padding:12px 14px;border-radius:14px;border:1px solid #ebebeb;font-size:14px;color:#222;';
    $labelCss = 'display:block;margin-bottom:6px;';
@endphp

@section('title', $isRtl ? 'الملف الشخصي' : 'Profile')
@section('heading', $isRtl ? 'الملف الشخصي' : 'Profile')

@section('main')
    <div style="max-width: 760px; display: flex; flex-direction: column; gap: 16px;">

        {{-- ── Profile details (phone is not editable) ── --}}
        <div class="bg-white" style="padding: 24px; border-radius: 24px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
            <h2 class="text-[16px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 18px;">{{ $isRtl ? 'البيانات الشخصية' : 'Personal details' }}</h2>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                {{-- Avatar — multipart POST (spoofed to PATCH) so PHP parses the file. --}}
                <div class="flex items-center" style="gap: 16px; margin-bottom: 22px;">
                    <span class="shrink-0 overflow-hidden flex items-center justify-center bg-[#f3f4f6] text-[#9ca3af] font-bold text-[24px]"
                          style="width: 72px; height: 72px; border-radius: 999px;">
                        @if($user->avatar_url)
                            <img src="{{ $user->avatar_url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ strtoupper(mb_substr($user->name ?: ($user->phone ?: '?'), 0, 1)) }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <label for="avatar" class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'الصورة الشخصية' : 'Profile photo' }}</label>
                        <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp"
                               class="block text-[13px] text-[#717171] file:cursor-pointer file:border-0 file:bg-[#222] file:text-white file:font-semibold file:px-4 file:py-2 file:rounded-[12px] file:mr-3 {{ $fa }}">
                        <p class="text-[12px] text-[#bbb] {{ $fa }}" style="margin-top: 6px;">{{ $isRtl ? 'JPG أو PNG أو WEBP، حتى 5 ميجابايت.' : 'JPG, PNG or WEBP, up to 5MB.' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                    {{-- Name --}}
                    <div>
                        <label for="name" class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'الاسم' : 'Name' }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" class="{{ $fa }}" style="{{ $inputCss }}">
                    </div>

                    {{-- Phone — read-only (login identifier) --}}
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'رقم الجوال' : 'Phone' }}</label>
                        <div class="flex items-center justify-between" style="background:#f0f0f0;padding:12px 14px;border-radius:14px;border:1px solid #ebebeb;">
                            <span class="text-[14px] text-[#717171]" dir="ltr">{{ $user->phone ? '+966 '.$user->phone : '—' }}</span>
                            <span class="text-[11px] text-[#bbb] {{ $fa }}">{{ $isRtl ? 'غير قابل للتعديل' : 'Not editable' }}</span>
                        </div>
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'البريد الإلكتروني' : 'Email' }}</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" dir="ltr" style="{{ $inputCss }}">
                    </div>

                    {{-- Gender --}}
                    <div>
                        <label for="gender" class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'الجنس' : 'Gender' }}</label>
                        <select name="gender" id="gender" class="{{ $fa }}" style="{{ $inputCss }}">
                            <option value="">{{ $isRtl ? '— غير محدد —' : '— Not set —' }}</option>
                            <option value="male" @selected(old('gender', $user->gender) === 'male')>{{ $isRtl ? 'ذكر' : 'Male' }}</option>
                            <option value="female" @selected(old('gender', $user->gender) === 'female')>{{ $isRtl ? 'أنثى' : 'Female' }}</option>
                        </select>
                    </div>

                    {{-- Birth date --}}
                    <div>
                        <label for="birth_date" class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'تاريخ الميلاد' : 'Birth date' }}</label>
                        <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date', $user->birth_date?->toDateString()) }}" dir="ltr" style="{{ $inputCss }}">
                    </div>

                    {{-- Member since — read-only --}}
                    <div>
                        <label class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="{{ $labelCss }}">{{ $isRtl ? 'انضممت في' : 'Member since' }}</label>
                        <div style="padding:12px 14px;border-radius:14px;border:1px solid #ebebeb;">
                            <span class="text-[14px] text-[#717171]">{{ $user->created_at?->locale($locale)->translatedFormat('F Y') }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-{{ $isRtl ? 'start' : 'end' }}" style="margin-top: 18px;">
                    <button type="submit" class="font-bold text-white bg-[#222] hover:bg-black transition-colors {{ $fa }}" style="padding: 11px 24px; border-radius: 14px; font-size: 14px;">
                        {{ $isRtl ? 'حفظ' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Bank account for payouts ── --}}
        @include('partials._bank_account_form', ['user' => $user])
    </div>
@endsection
