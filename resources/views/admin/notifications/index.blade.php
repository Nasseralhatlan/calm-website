@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'الإشعارات' : 'Notifications')
@section('heading', $isRtl ? 'إرسال إشعار للمستخدمين' : 'Send a notification')

@section('main')
    @if(session('status'))
        <div class="bg-[#ecfdf5] border border-[#a7f3d0] text-[#065f46] text-[14px]"
             style="padding: 12px 16px; border-radius: 14px; margin-bottom: 18px;">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-[#fef2f2] border border-[#fecaca] text-[#7a2018] text-[14px]"
             style="padding: 12px 16px; border-radius: 14px; margin-bottom: 18px;">
            @foreach($errors->all() as $err)<div>· {{ $err }}</div>@endforeach
        </div>
    @endif

    {{-- Compose --}}
    <div class="bg-white" style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05); margin-bottom: 24px;">
        <p class="text-[13px] text-[#717171]" style="margin-bottom: 16px;">
            {{ $isRtl
                ? 'يُرسل الإشعار عبر الرسائل النصية + الإشعارات + داخل التطبيق دفعة واحدة، وبلغة كل مستخدم.'
                : 'Sent over SMS + push + in-app at once, in each user\'s language.' }}
        </p>

        <form method="POST" action="{{ route('admin.notifications.store') }}">
            @csrf

            <label class="block" style="margin-bottom: 14px;">
                <span class="text-[13px] font-bold text-[#222]">{{ $isRtl ? 'الجمهور' : 'Audience' }}</span>
                <select name="audience" required dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
                        class="block w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                        style="padding: 10px 12px; border-radius: 12px; margin-top: 6px;">
                    <option value="all">{{ $isRtl ? 'كل المستخدمين' : 'All users' }}</option>
                    <option value="hosts">{{ $isRtl ? 'المضيفون فقط' : 'Hosts only' }}</option>
                    <option value="guests">{{ $isRtl ? 'الضيوف فقط' : 'Guests only' }}</option>
                </select>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 14px; margin-bottom: 14px;">
                <label class="block">
                    <span class="text-[13px] font-bold text-[#222]">{{ $isRtl ? 'العنوان (عربي)' : 'Title (Arabic)' }}</span>
                    <input type="text" name="title_ar" required maxlength="255" dir="rtl" value="{{ old('title_ar') }}"
                           class="block w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none font-arabic"
                           style="padding: 10px 12px; border-radius: 12px; margin-top: 6px;">
                </label>
                <label class="block">
                    <span class="text-[13px] font-bold text-[#222]">{{ $isRtl ? 'العنوان (إنجليزي)' : 'Title (English)' }}</span>
                    <input type="text" name="title_en" required maxlength="255" dir="ltr" value="{{ old('title_en') }}"
                           class="block w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                           style="padding: 10px 12px; border-radius: 12px; margin-top: 6px;">
                </label>
                <label class="block">
                    <span class="text-[13px] font-bold text-[#222]">{{ $isRtl ? 'النص (عربي)' : 'Body (Arabic)' }}</span>
                    <textarea name="body_ar" required maxlength="2000" rows="3" dir="rtl"
                              class="block w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none resize-none font-arabic"
                              style="padding: 10px 12px; border-radius: 12px; margin-top: 6px;">{{ old('body_ar') }}</textarea>
                </label>
                <label class="block">
                    <span class="text-[13px] font-bold text-[#222]">{{ $isRtl ? 'النص (إنجليزي)' : 'Body (English)' }}</span>
                    <textarea name="body_en" required maxlength="2000" rows="3" dir="ltr"
                              class="block w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none resize-none"
                              style="padding: 10px 12px; border-radius: 12px; margin-top: 6px;">{{ old('body_en') }}</textarea>
                </label>
            </div>

            <button type="submit"
                    class="font-semibold text-white bg-[#222] hover:bg-black"
                    style="padding: 11px 22px; border-radius: 12px; font-size: 14px;"
                    onclick="return confirm('{{ $isRtl ? 'إرسال هذا الإشعار للجمهور المحدد؟' : 'Send this notification to the selected audience?' }}');">
                {{ $isRtl ? 'إرسال' : 'Send' }}
            </button>
        </form>
    </div>

    {{-- History --}}
    <div class="bg-white overflow-hidden" style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full text-[14px]">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-start" style="padding: 12px 18px;">{{ $isRtl ? 'العنوان' : 'Title' }}</th>
                    <th class="text-start" style="padding: 12px 18px;">{{ $isRtl ? 'الجمهور' : 'Audience' }}</th>
                    <th class="text-start" style="padding: 12px 18px;">{{ $isRtl ? 'المستلمون' : 'Recipients' }}</th>
                    <th class="text-start" style="padding: 12px 18px;">{{ $isRtl ? 'التاريخ' : 'Sent' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($broadcasts as $b)
                    <tr class="border-t border-[#ebebeb]">
                        <td class="text-start" style="padding: 12px 18px;">{{ $isRtl ? $b->title_ar : $b->title_en }}</td>
                        <td class="text-start text-[#717171]" style="padding: 12px 18px;">
                            {{ $isRtl ? ['all' => 'الكل', 'hosts' => 'المضيفون', 'guests' => 'الضيوف'][$b->audience] : ucfirst($b->audience) }}
                        </td>
                        <td class="text-start tabular-nums" style="padding: 12px 18px;">{{ number_format($b->recipients_count) }}</td>
                        <td class="text-start text-[#717171]" style="padding: 12px 18px;">{{ $b->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding: 28px 18px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد إشعارات بعد.' : 'No broadcasts yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($broadcasts->hasPages())<div style="margin-top: 18px;">{{ $broadcasts->links() }}</div>@endif
@endsection
