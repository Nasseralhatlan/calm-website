@extends('layouts.admin')

@php
    use App\Enums\GeoStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';

    // Same vivid pill palette as places/users listings — saturated bg, lighter dot.
    $statusPill = fn (?GeoStatus $s): array => $s === GeoStatus::Active
        ? ['bg' => '#10b981', 'dot' => '#a7f3d0']
        : ['bg' => '#9ca3af', 'dot' => '#e5e7eb'];
@endphp

@section('title', $isRtl ? 'الدول' : 'Countries')
@section('heading', $isRtl ? 'الدول' : 'Countries')

@section('main')
    <div class="flex items-center justify-between" style="margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171]">
            {{ $countries->total() }} {{ $isRtl ? 'دولة' : 'countries' }}
        </p>
        <a href="{{ route('admin.countries.create') }}"
           class="inline-flex items-center font-semibold text-white bg-[#222] hover:bg-black"
           style="padding: 10px 16px; gap: 6px; border-radius: 12px; corner-shape: squircle; font-size: 14px;">
            <span>+</span>
            <span>{{ $isRtl ? 'إضافة دولة' : 'Add country' }}</span>
        </a>
    </div>

    <div class="bg-white overflow-hidden"
         style="border-radius: 28px; corner-shape: squircle; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full">
            <thead class="bg-[#fafafa] text-[12px] uppercase text-[#717171] tracking-wider">
                <tr>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الدولة' : 'Country' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الرمز' : 'Code' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'رمز الاتصال' : 'Dial' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'المدن' : 'Cities' }}</th>
                    <th class="text-start" style="padding: 14px 20px;">{{ $isRtl ? 'الحالة' : 'Status' }}</th>
                    <th class="text-end" style="padding: 14px 20px;">{{ $isRtl ? 'إجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="text-[14px]">
                @forelse($countries as $country)
                    @php $sp = $statusPill($country->status); @endphp
                    <tr class="border-t border-[#ebebeb]">
                        <td class="text-start" style="padding: 14px 20px;">
                            <span class="inline-flex items-center" style="gap: 10px;">
                                <span style="font-size: 22px; line-height: 1;">{{ $country->avatar ?: '🌐' }}</span>
                                <span class="font-medium text-[#222]">{{ $isRtl ? $country->name_ar : $country->name_en }}</span>
                            </span>
                        </td>
                        <td class="text-start font-mono text-[13px] text-[#717171]" style="padding: 14px 20px;">{{ $country->country_code }}</td>
                        <td class="text-start tabular-nums text-[#717171]" style="padding: 14px 20px;" dir="ltr">{{ $country->dial_code ?: '—' }}</td>
                        <td class="text-start tabular-nums text-[#717171]" style="padding: 14px 20px;">{{ $country->cities_count }}</td>
                        <td class="text-start" style="padding: 14px 20px;">
                            <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white"
                                  style="padding: 4px 12px 4px 9px; border-radius: 999px; gap: 6px; background-color: {{ $sp['bg'] }};">
                                <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $sp['dot'] }};"></span>
                                {{ $country->status?->value ?? 'inactive' }}
                            </span>
                        </td>
                        <td class="text-end whitespace-nowrap" style="padding: 14px 20px;">
                            <a href="{{ route('admin.countries.edit', $country) }}"
                               class="text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                            <form method="POST" action="{{ route('admin.countries.destroy', $country) }}"
                                  class="inline" style="margin-inline-start: 14px;"
                                  onsubmit="return confirm('{{ $isRtl ? 'حذف هذه الدولة؟' : 'Delete this country?' }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-[#717171]" style="padding: 32px 20px;">{{ $isRtl ? 'لا توجد دول بعد.' : 'No countries yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($countries->hasPages())
        <div style="margin-top: 20px;">{{ $countries->links() }}</div>
    @endif
@endsection
