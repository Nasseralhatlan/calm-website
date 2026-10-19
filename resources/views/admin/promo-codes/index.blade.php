@extends('layouts.admin')

@php
    $isRtl = app()->getLocale() === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $sr = fn (int $minor) => number_format($minor / 100, 2);
    $rate = fn (float $r) => rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.');
    $riyadh = fn ($ts) => $ts?->copy()->setTimezone('Asia/Riyadh')->isoFormat('D MMM YYYY');
    $warned = session('promo_warning');
@endphp

@section('title', $isRtl ? 'أكواد الخصم' : 'Promo codes')
@section('heading', $isRtl ? 'أكواد الخصم' : 'Promo codes')

@section('main')
    <div class="flex flex-wrap items-center justify-between" style="gap: 12px; margin-bottom: 20px;">
        <p class="text-[14px] text-[#717171] max-w-2xl {{ $fa }}">
            {{ $isRtl
                ? 'الخصم على حساب كالم: يُخصم من عمولة كالم ولا يمس مستحقات المضيف. عمولة كالم الحالية '.$rate($commissionRate).'%.'
                : "Discounts are Calm-funded: they come out of Calm's commission and never touch the host's payout. Calm's current commission is ".$rate($commissionRate).'%.' }}
        </p>
        <a href="{{ route('admin.promo-codes.create') }}"
           class="font-semibold text-white bg-[#222] hover:bg-black {{ $fa }}"
           style="padding: 10px 20px; border-radius: 12px; font-size: 14px;">
            {{ $isRtl ? '+ كود جديد' : '+ New code' }}
        </a>
    </div>

    @if($warned)
        <div class="text-[14px] text-[#7f1d1d] max-w-3xl {{ $fa }}"
             style="margin-bottom: 16px; padding: 14px 16px; border-radius: 18px; background:#fef2f2; border:1px solid rgba(185,28,28,0.3);">
            <strong>⚠ {{ $warned }}</strong> —
            {{ $isRtl
                ? 'هذا الكود يتجاوز عمولة كالم ('.$rate($commissionRate).'%). ستدفع كالم الفرق من جيبها على الحجوزات المتأثرة.'
                : "this code exceeds Calm's commission (".$rate($commissionRate).'%). Calm pays the difference out of pocket on affected bookings.' }}
        </div>
    @endif

    <div class="bg-white overflow-x-auto" style="border-radius: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
        <table class="w-full text-[14px] {{ $fa }}">
            <thead>
                <tr class="text-[12px] text-[#717171] text-start border-b border-[#f0f0f0]">
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'الكود' : 'Code' }}</th>
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'الخصم' : 'Discount' }}</th>
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'مقابل عمولة كالم' : "vs Calm's commission" }}</th>
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'الاستخدام' : 'Uses' }}</th>
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'خصومات مدفوعة' : 'Discount given' }}</th>
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'من جيب كالم' : 'Calm out of pocket' }}</th>
                    <th class="text-start font-semibold" style="padding: 14px 16px;">{{ $isRtl ? 'الصلاحية' : 'Valid' }}</th>
                    <th style="padding: 14px 16px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($codes as $promo)
                    @php
                        $check = $promo->commissionCheck($commissionRate);
                        $live = $promo->is_active && ($promo->ends_at === null || $promo->ends_at->isFuture());
                    @endphp
                    <tr class="border-t border-[#f5f5f5] align-top">
                        <td style="padding: 14px 16px;">
                            <span class="font-bold text-[#222] tracking-wider" dir="ltr">{{ $promo->code }}</span>
                            <span class="block text-[12px] {{ $live ? 'text-[#059669]' : 'text-[#999]' }}" style="margin-top: 2px;">
                                {{ $live ? ($isRtl ? 'مفعّل' : 'Active') : ($isRtl ? 'متوقف' : 'Inactive') }}
                            </span>
                            @if($promo->note)
                                <span class="block text-[12px] text-[#999]" style="margin-top: 2px;">{{ $promo->note }}</span>
                            @endif
                        </td>
                        <td style="padding: 14px 16px;">
                            <span class="font-semibold text-[#222] tabular-nums" dir="ltr">{{ $promo->label() }}</span>
                            @if($promo->max_discount_amount)
                                <span class="block text-[12px] text-[#717171]" dir="ltr">{{ $isRtl ? 'حتى' : 'up to' }} SR {{ $sr($promo->max_discount_amount) }}</span>
                            @endif
                            @if($promo->min_stay_amount)
                                <span class="block text-[12px] text-[#717171]">{{ $isRtl ? 'إقامة من' : 'min stay' }} <span dir="ltr">SR {{ $sr($promo->min_stay_amount) }}</span></span>
                            @endif
                        </td>
                        <td style="padding: 14px 16px;">
                            @if($check['exceeds'])
                                <span class="inline-block text-[12px] font-semibold text-[#b91c1c]" style="background:#fef2f2; padding: 4px 10px; border-radius: 999px;">
                                    @if($check['over_by_rate'] !== null)
                                        ⚠ {{ $isRtl ? 'يتجاوز بـ '.$rate($check['over_by_rate']).'%' : 'Over by '.$rate($check['over_by_rate']).'%' }}
                                    @else
                                        ⚠ {{ $isRtl ? 'يتجاوز تحت' : 'Over below' }} <span dir="ltr">SR {{ $sr($check['break_even_stay_minor']) }}</span>
                                    @endif
                                </span>
                            @else
                                <span class="inline-block text-[12px] font-semibold text-[#047857]" style="background:#ecfdf5; padding: 4px 10px; border-radius: 999px;">
                                    ✓ {{ $isRtl ? 'ضمن العمولة' : 'Within' }}
                                </span>
                            @endif
                        </td>
                        <td class="tabular-nums" style="padding: 14px 16px;" dir="ltr">
                            {{ $promo->paid_uses }}{{ $promo->max_uses ? ' / '.$promo->max_uses : '' }}
                            @if($promo->max_uses_per_user)
                                <span class="block text-[12px] text-[#717171]">{{ $promo->max_uses_per_user }} {{ $isRtl ? 'لكل ضيف' : 'per guest' }}</span>
                            @endif
                        </td>
                        <td class="tabular-nums text-[#222]" style="padding: 14px 16px;" dir="ltr">SR {{ $sr($promo->discount_given_minor) }}</td>
                        <td class="tabular-nums font-semibold {{ $promo->calm_loss_minor > 0 ? 'text-[#b91c1c]' : 'text-[#999]' }}" style="padding: 14px 16px;" dir="ltr">
                            SR {{ $sr($promo->calm_loss_minor) }}
                        </td>
                        <td class="text-[13px] text-[#717171]" style="padding: 14px 16px;">
                            {{ $riyadh($promo->starts_at) ?? '—' }} → {{ $riyadh($promo->ends_at) ?? ($isRtl ? 'مفتوح' : 'open') }}
                        </td>
                        <td style="padding: 14px 16px;">
                            <div class="flex items-center justify-end" style="gap: 14px;">
                                <a href="{{ route('admin.promo-codes.edit', $promo) }}" class="text-[13px] font-semibold text-[#717171] hover:text-[#222]">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                                <form method="POST" action="{{ route('admin.promo-codes.destroy', $promo) }}" class="inline"
                                      onsubmit="return confirm('{{ $isRtl ? 'حذف هذا الكود؟ إن كان مستخدماً سيتم إيقافه فقط.' : 'Delete this code? If bookings used it, it is deactivated instead.' }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[13px] font-semibold text-[#dc2626] hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-[13px] text-[#999]" style="padding: 20px 16px;">{{ $isRtl ? 'لا توجد أكواد بعد.' : 'No promo codes yet.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-[12px] text-[#999] {{ $fa }}" style="margin-top: 10px;">
        {{ $isRtl
            ? 'الأرقام للحجوزات المدفوعة (مؤكدة أو مكتملة). "من جيب كالم" = الجزء من الخصم الذي تجاوز عمولة الحجز.'
            : 'Figures cover paid bookings (confirmed or completed). "Calm out of pocket" = the part of each discount that went past that booking\'s commission.' }}
    </p>
@endsection
