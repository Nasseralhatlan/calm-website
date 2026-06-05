@extends('layouts.admin')

@php
    use App\Enums\UserRole;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $currentRole = old('role', $user->role?->value ?? UserRole::User->value);
@endphp

@section('title', $isRtl ? 'تعديل المستخدم' : 'Edit user')
@section('heading', $isRtl ? 'تعديل المستخدم' : 'Edit user')

@section('main')
    <div class="bg-white max-w-2xl"
         style="padding: 24px; border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')

            {{-- Read-only context: how this user authenticates --}}
            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-bottom: 20px;">
                <div>
                    <label class="block text-[12px] font-semibold text-[#717171]" style="margin-bottom: 4px;">
                        {{ $isRtl ? 'الجوال' : 'Phone' }}
                    </label>
                    <div class="text-[14px] tabular-nums text-[#222]" dir="ltr">
                        {{ $user->phone ? '+966 '.$user->phone : '—' }}
                    </div>
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-[#717171]" style="margin-bottom: 4px;">
                        {{ $isRtl ? 'البريد' : 'Email' }}
                    </label>
                    <div class="text-[14px] text-[#222]" dir="ltr">{{ $user->email ?: '—' }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
                        {{ $isRtl ? 'الاسم' : 'Name' }}
                    </label>
                    <input type="text"
                           name="name"
                           value="{{ old('name', $user->name) }}"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;">
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
                        {{ $isRtl ? 'الدور' : 'Role' }}
                    </label>
                    <select name="role" required
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        @foreach(UserRole::cases() as $case)
                            <option value="{{ $case->value }}" @selected($currentRole === $case->value)>
                                {{ $isRtl
                                    ? ($case === UserRole::Admin ? 'مشرف' : 'مستخدم')
                                    : ucfirst($case->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px; margin-top: 16px;">
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
                        {{ $isRtl ? 'الجنس' : 'Gender' }}
                    </label>
                    <select name="gender"
                            class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none"
                            style="padding: 11px 14px; border-radius: 12px;">
                        <option value="">—</option>
                        <option value="male"   @selected(old('gender', $user->gender) === 'male')>{{ $isRtl ? 'ذكر' : 'Male' }}</option>
                        <option value="female" @selected(old('gender', $user->gender) === 'female')>{{ $isRtl ? 'أنثى' : 'Female' }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[13px] font-semibold text-[#222]" style="margin-bottom: 6px;">
                        {{ $isRtl ? 'العمر' : 'Age' }}
                    </label>
                    <input type="number" name="age" min="1" max="150"
                           value="{{ old('age', $user->age) }}"
                           class="w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] tabular-nums focus:outline-none"
                           style="padding: 11px 14px; border-radius: 12px;" dir="ltr">
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
