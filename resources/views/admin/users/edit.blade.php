@extends('layouts.admin')

@php
    use App\Enums\UserRole;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $currentRole = old('role', $user->role?->value ?? UserRole::User->value);
    $maxBirth = \Carbon\CarbonImmutable::yesterday()->toDateString();
    $input = 'w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none';
    $inputStyle = 'padding: 11px 14px; border-radius: 12px;';
    $labelCls = 'block text-[13px] font-semibold text-[#222]';
@endphp

@section('title', $isRtl ? 'تعديل المستخدم' : 'Edit user')
@section('heading', $isRtl ? 'تعديل المستخدم' : 'Edit user')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            {{-- Avatar — uploaded from the app, shown for reference (not editable here) --}}
            <div class="flex items-center" style="gap: 16px; margin-bottom: 20px;">
                <span class="flex items-center justify-center bg-[#f2f2f2] overflow-hidden shrink-0"
                      style="width: 72px; height: 72px; border-radius: 999px;">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="" class="w-full h-full object-cover">
                    @else
                        <span class="text-[#717171]" style="font-size: 28px;">{{ strtoupper(mb_substr($user->name ?: ($user->phone ?: '؟'), 0, 1)) }}</span>
                    @endif
                </span>
                <div>
                    <div class="text-[13px] font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'الصورة الشخصية' : 'Avatar' }}</div>
                    <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">
                        {{ $user->avatar_url ? ($isRtl ? 'تم رفعها من التطبيق' : 'Uploaded from the app') : ($isRtl ? 'لا توجد صورة' : 'No avatar') }}
                    </div>
                </div>
            </div>

            {{-- Phone (read-only — the login identifier, not admin-editable) + Email --}}
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الجوال' : 'Phone' }}</label>
                    <div class="bg-[#f7f7f7] border border-[#ebebeb] text-[15px] text-[#717171] tabular-nums" style="{{ $inputStyle }}" dir="ltr">
                        {{ $user->phone ? '+966 '.$user->phone : '—' }}
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'البريد' : 'Email' }}</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" dir="ltr"
                           class="{{ $input }}" style="{{ $inputStyle }}">
                    @error('email')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Name + Role --}}
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الاسم' : 'Name' }}</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="{{ $input }}" style="{{ $inputStyle }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الدور' : 'Role' }}</label>
                    <select name="role" required class="{{ $input }}" style="{{ $inputStyle }}">
                        @foreach(UserRole::cases() as $case)
                            <option value="{{ $case->value }}" @selected($currentRole === $case->value)>
                                {{ $isRtl ? ($case === UserRole::Admin ? 'مشرف' : 'مستخدم') : ucfirst($case->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Gender + Birth date --}}
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الجنس' : 'Gender' }}</label>
                    <select name="gender" class="{{ $input }}" style="{{ $inputStyle }}">
                        <option value="">—</option>
                        <option value="male"   @selected(old('gender', $user->gender) === 'male')>{{ $isRtl ? 'ذكر' : 'Male' }}</option>
                        <option value="female" @selected(old('gender', $user->gender) === 'female')>{{ $isRtl ? 'أنثى' : 'Female' }}</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'تاريخ الميلاد' : 'Birth date' }}</label>
                    <input type="date" name="birth_date" max="{{ $maxBirth }}" min="1900-01-01"
                           value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                           class="{{ $input }} tabular-nums" style="{{ $inputStyle }}" dir="ltr">
                    @error('birth_date')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
                    @if($user->birth_date)
                        <p class="text-[12px] text-[#717171]" style="margin-top: 4px;">{{ $isRtl ? 'العمر' : 'Age' }}: {{ $user->birth_date->age }}</p>
                    @endif
                </div>
            </div>

            {{-- Country --}}
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="{{ $labelCls }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الدولة' : 'Country' }}</label>
                    <select name="country_id" class="{{ $input }}" style="{{ $inputStyle }}">
                        <option value="">—</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected(old('country_id', $user->country_id) === $country->id)>
                                {{ $isRtl ? $country->name_ar : $country->name_en }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
                <button type="submit"
                        class="font-semibold text-white bg-[#222] hover:bg-black"
                        style="padding: 11px 22px; border-radius: 12px; corner-shape: squircle; font-size: 14px;">
                    {{ $isRtl ? 'تحديث' : 'Update' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="text-[14px] text-[#717171] hover:text-[#222]">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
            </div>
        </form>
    </div>
@endsection
