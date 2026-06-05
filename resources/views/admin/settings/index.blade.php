@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

@section('title', $isRtl ? 'الإعدادات' : 'Settings')
@section('heading', $isRtl ? 'الإعدادات' : 'Settings')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">
            {{ $settings->total() }} {{ $isRtl ? 'مفتاح' : 'keys' }}
        </p>
        <a href="{{ route('admin.settings.store') ?? '#' }}"
           x-data x-on:click.prevent="window.location='{{ url('admin/settings/create-redirect') }}'"
           class="hidden"></a>
    </div>

    <div class="bg-white"
         style="padding: 24px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05); margin-bottom: 20px;">
        <h3 class="text-[14px] font-bold text-[#222]" style="margin-bottom: 14px;">{{ $isRtl ? 'إضافة مفتاح جديد' : 'Add a new key' }}</h3>
        <form method="POST" action="{{ route('admin.settings.store') }}" class="grid grid-cols-1 sm:grid-cols-[1fr_2fr_auto]" style="gap: 12px;">
            @csrf
            <input type="text" name="key" placeholder="{{ $isRtl ? 'المفتاح' : 'Key' }}" required dir="ltr"
                   class="bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] font-mono focus:outline-none"
                   style="padding: 10px 12px; border-radius: 12px;">
            <input type="text" name="value" placeholder="{{ $isRtl ? 'القيمة' : 'Value' }}"
                   class="bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] focus:outline-none"
                   style="padding: 10px 12px; border-radius: 12px;">
            <button type="submit"
                    class="font-semibold text-white bg-[#222] hover:bg-black"
                    style="padding: 10px 18px; border-radius: 12px; font-size: 14px;">{{ $isRtl ? 'إضافة' : 'Add' }}</button>
        </form>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المفتاح' : 'Key' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'القيمة' : 'Value' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($settings as $setting)
                    <tr class="border-t border-[#ebebeb]">
                        <td style="padding: 14px 20px;" class="font-mono text-[13px] text-[#222]">{{ $setting->key }}</td>
                        <td style="padding: 14px 20px;" class="text-[#717171]">{{ Str::limit($setting->value, 80) }}</td>
                        <td style="padding: 14px 20px;" class="text-end whitespace-nowrap">
                            <a href="{{ route('admin.settings.edit', $setting) }}" class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.settings.destroy', $setting) }}"
                                  class="inline" style="margin-inline-start: 14px;"
                                  onsubmit="return confirm('{{ $isRtl ? 'حذف هذا المفتاح؟' : 'Delete this key?' }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="padding: 32px 20px;" class="text-center text-[#717171]">{{ $isRtl ? 'لا توجد إعدادات بعد.' : 'No settings yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($settings->hasPages())
        <div style="margin-top: 20px;">{{ $settings->links() }}</div>
    @endif
@endsection
