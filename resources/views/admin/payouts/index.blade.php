@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $sr = fn (int $minor) => number_format($minor / 100, 2);
    $pct = fn (float $r) => rtrim(rtrim(number_format($r, 2), '0'), '.');
@endphp

@section('title', $isRtl ? 'التحويلات' : 'Payouts')
@section('heading', $isRtl ? 'تحويلات المضيفين' : 'Host payouts')

@section('main')
    {{-- ── Summary cards ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style="gap: 14px; margin-bottom: 18px;">
        <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">
            <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'مستحق الآن' : 'Payable now' }}</div>
            <div class="font-bold text-[#222] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($totals['pending_minor']) }}</div>
            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">{{ $totals['pending_count'] }} {{ $isRtl ? 'حجز مكتمل بانتظار التحويل' : 'completed booking(s) awaiting transfer' }}</div>
        </div>
        <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">
            <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'قيد التحويل' : 'Processing' }}</div>
            <div class="font-bold text-[#3b82f6] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($totals['processing_minor']) }}</div>
            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">{{ $totals['processing_count'] }} {{ $isRtl ? 'تحويل جارٍ عبر ميسر' : 'transfer(s) in flight via Moyasar' }}</div>
        </div>
        <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">
            <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'قادم (لم يكتمل بعد)' : 'Upcoming (not payable yet)' }}</div>
            <div class="font-bold text-[#717171] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($totals['upcoming_minor']) }}</div>
            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">{{ $totals['upcoming_count'] }} {{ $isRtl ? 'حجز مؤكد لم ينته بعد' : 'confirmed stay(s) not finished yet' }}</div>
        </div>
        <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">
            <div class="text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'تم تحويله' : 'Paid out' }}</div>
            <div class="font-bold text-[#10b981] tabular-nums" style="font-size: 22px; margin-top: 6px;" dir="ltr">SR {{ $sr($totals['paid_minor']) }}</div>
            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">{{ $totals['paid_count'] }} {{ $isRtl ? 'حجز مدفوع للمضيف' : 'booking(s) settled' }}</div>
        </div>
    </div>

    {{-- ── Search + tabs ── --}}
    <form method="GET" action="{{ route('admin.payouts.index') }}" class="flex items-center bg-white {{ $fa }}"
          style="margin-bottom: 14px; border-radius: 14px; padding: 4px; max-width: 560px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.04);">
        @if($tab !== 'pending')<input type="hidden" name="tab" value="{{ $tab }}">@endif
        <span class="flex items-center justify-center text-[#9ca3af]" style="padding: 0 6px 0 12px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </span>
        <input type="text" name="q" value="{{ $search }}"
               placeholder="{{ $isRtl ? 'ابحث برقم الحجز أو جوال المضيف' : 'Search booking ref or host phone' }}"
               class="flex-1 bg-transparent text-[14px] text-[#222] focus:outline-none {{ $fa }}"
               style="padding: 8px 6px;" dir="auto">
        <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black {{ $fa }}"
                style="padding: 8px 16px; border-radius: 10px; font-size: 13px;">{{ $isRtl ? 'بحث' : 'Search' }}</button>
        @if($search)
            <a href="{{ route('admin.payouts.index', array_filter(['tab' => $tab === 'pending' ? null : $tab])) }}" class="text-[13px] text-[#717171] hover:text-[#222]" style="padding: 0 12px;">✕</a>
        @endif
    </form>

    <div class="flex items-center flex-wrap" style="gap: 8px; margin-bottom: 18px;">
        @foreach([
            'pending' => $isRtl ? 'بانتظار التحويل' : 'Pending',
            'processing' => $isRtl ? 'قيد التحويل' : 'Processing',
            'paid' => $isRtl ? 'المدفوعة' : 'Paid',
        ] as $key => $label)
            @php
                $active = $tab === $key;
                $tint = ['pending' => '#f59e0b', 'processing' => '#3b82f6', 'paid' => '#10b981'][$key];
                $count = $totals[$key.'_count'];
            @endphp
            <a href="{{ route('admin.payouts.index', array_filter(['q' => $search, 'tab' => $key === 'pending' ? null : $key])) }}"
               class="inline-flex items-center transition-all {{ $fa }}"
               style="gap: 7px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
                      {{ $active ? "background-color: {$tint}; color: #fff;" : 'background-color: #fff; color: #717171; box-shadow: 0px 4px 12px 0px rgba(0,0,0,0.04);' }}">
                <span style="width: 7px; height: 7px; border-radius: 999px; background-color: {{ $active ? '#fff' : $tint }};"></span>
                <span>{{ $label }}</span>
                <span class="tabular-nums {{ $active ? 'text-white/80' : 'text-[#bbb]' }}" style="font-size: 12px;">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    @if($bookings->isEmpty())
        <div class="bg-white text-center text-[#717171] {{ $fa }}" style="padding: 56px 20px; border-radius: 24px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.04);">
            <div style="font-size: 32px; margin-bottom: 8px;">💸</div>
            @if($tab === 'paid')
                {{ $isRtl ? 'لا توجد تحويلات مدفوعة مطابقة.' : 'No settled payouts match.' }}
            @elseif($tab === 'processing')
                {{ $isRtl ? 'لا توجد تحويلات قيد التنفيذ عبر ميسر.' : 'No transfers in flight via Moyasar.' }}
            @else
                {{ $isRtl ? 'لا توجد تحويلات مستحقة — كل المضيفين تم الدفع لهم.' : 'Nothing to pay out — all hosts are settled.' }}
            @endif
        </div>
    @else
        <div class="flex flex-col" style="gap: 14px;">
            @foreach($bookings as $b)
                <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.05);">
                    {{-- Header: place · ref · net amount --}}
                    <div class="flex items-center" style="gap: 14px;">
                        <span class="shrink-0 overflow-hidden bg-[#f3f4f6] flex items-center justify-center" style="width: 56px; height: 56px; border-radius: 14px;">
                            @if($b->place?->coverPhoto?->url)
                                <img src="{{ $b->place->coverPhoto->url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <span style="font-size: 24px;">🏠</span>
                            @endif
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-bold text-[#222] text-[15px] truncate {{ $fa }}">{{ $b->place?->title ?? '—' }}</span>
                            <span class="flex items-center" style="gap: 8px; margin-top: 5px;">
                                <a href="{{ route('admin.bookings.show', $b) }}"
                                   class="inline-flex items-center font-bold tabular-nums hover:opacity-80"
                                   style="background-color: #fff4f3; color: #F88379; padding: 3px 10px; border-radius: 8px; font-size: 12px; letter-spacing: 0.5px;" dir="ltr">{{ $b->reference }}</a>
                                <span class="text-[12px] text-[#717171]" dir="ltr">{{ $b->start_date?->isoFormat('D MMM') }} → {{ $b->checkoutAt()?->isoFormat('D MMM YYYY') }}</span>
                            </span>
                        </span>
                        <span class="shrink-0 text-end">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'المبلغ المستحق للمضيف' : 'Pay host' }}</span>
                            <span class="block font-bold text-[#10b981] tabular-nums" style="font-size: 20px;" dir="ltr">SR {{ $sr($b->hostNetMinor()) }}</span>
                        </span>
                    </div>

                    {{-- Invoice: per-night lines → subtotal → commission → payable.
                         nightlyRates() is null when the host changed prices after
                         this booking — then we show the honest aggregate only. --}}
                    @php $nights = $b->nightlyRates(); @endphp
                    <div style="margin-top: 14px; padding: 14px 18px; background: #fafafa; border-radius: 14px;">
                        @if($nights)
                            @foreach($nights as $i => $line)
                                <div class="flex items-center justify-between text-[13px]" style="padding: 2px 0;">
                                    <span class="text-[#717171]">
                                        <span class="tabular-nums text-[#bbb]" style="margin-inline-end: 8px;">{{ $i + 1 }}</span>
                                        <span dir="ltr">{{ $line['date']->isoFormat('ddd, D MMM YYYY') }}</span>
                                    </span>
                                    <span class="text-[#222] tabular-nums" dir="ltr">SR {{ $sr($line['price_minor']) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="flex items-center justify-between text-[13px]" style="padding: 2px 0;">
                                <span class="text-[#717171] {{ $fa }}">
                                    {{ $b->quantity }} {{ $isRtl ? 'ليلة' : 'night(s)' }}
                                    × <span class="tabular-nums" dir="ltr">SR {{ $sr(intdiv((int) $b->booking_amount, max(1, (int) $b->quantity))) }}</span>
                                    {{ $isRtl ? '(متوسط)' : '(avg)' }}
                                </span>
                                <span class="text-[#222] tabular-nums" dir="ltr">SR {{ $sr($b->booking_amount) }}</span>
                            </div>
                        @endif

                        <div style="height: 1px; background: #e5e7eb; margin: 10px 0 8px;"></div>

                        <div class="flex items-center justify-between text-[13px]" style="padding: 2px 0;">
                            <span class="font-semibold text-[#222] {{ $fa }}">{{ $isRtl ? 'قيمة الحجز' : 'Booking amount' }} ({{ $b->quantity }} {{ $isRtl ? 'ليلة' : 'nights' }})</span>
                            <span class="font-semibold text-[#222] tabular-nums" dir="ltr">SR {{ $sr($b->booking_amount) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-[13px]" style="padding: 2px 0;">
                            <span class="text-[#717171] {{ $fa }}">{{ $isRtl ? 'عمولة كالم' : 'Calm commission' }} ({{ $pct($b->commission_rate) }}%)</span>
                            <span class="text-[#717171] tabular-nums" dir="ltr">− SR {{ $sr($b->commission_amount) }}</span>
                        </div>
                        @if((int) $b->commission_vat_amount > 0)
                            <div class="flex items-center justify-between text-[13px]" style="padding: 2px 0;">
                                <span class="text-[#717171] {{ $fa }}">{{ $isRtl ? 'ضريبة العمولة' : 'Commission VAT' }} ({{ $pct((float) $b->commission_vat_rate) }}%)</span>
                                <span class="text-[#717171] tabular-nums" dir="ltr">− SR {{ $sr((int) $b->commission_vat_amount) }}</span>
                            </div>
                        @endif

                        <div style="height: 2px; background: #d9dbdf; margin: 8px 0;"></div>

                        <div class="flex items-center justify-between" style="padding: 2px 0;">
                            <span class="font-bold text-[#10b981] text-[14px] {{ $fa }}">{{ $isRtl ? 'المستحق للمضيف' : 'Pay host' }}</span>
                            <span class="font-bold text-[#10b981] tabular-nums" style="font-size: 16px;" dir="ltr">SR {{ $sr($b->hostNetMinor()) }}</span>
                        </div>

                        <div class="text-[11px] text-[#bbb] {{ $fa }}" style="margin-top: 8px;">
                            {{ $isRtl
                                ? 'دفع الضيف ' : 'Guest paid ' }}<span class="tabular-nums" dir="ltr">SR {{ $sr($b->total) }}</span>
                            {{ $isRtl
                                ? 'شاملاً ضريبة القيمة المضافة (' . $pct($b->vat_rate) . '%) '
                                : 'incl. VAT (' . $pct($b->vat_rate) . '%) ' }}<span class="tabular-nums" dir="ltr">SR {{ $sr($b->vat_amount) }}</span>
                            — {{ $isRtl ? 'الضريبة تُورَّد للهيئة ولا تدخل في مستحقات المضيف.' : 'VAT is remitted and never part of the host payout.' }}
                        </div>
                    </div>

                    {{-- Failed automatic transfer: bank/API reason + Retry --}}
                    @if($tab === 'pending' && $b->payout_failure)
                        <div class="flex flex-wrap items-center justify-between" style="gap: 10px; margin-top: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 10px 14px;">
                            <span class="text-[13px] text-[#b91c1c] {{ $fa }}">⚠ {{ $isRtl ? 'فشل التحويل الآلي:' : 'Automatic transfer failed:' }}
                                <span dir="ltr">{{ $b->payout_failure }}</span>
                            </span>
                            <form method="POST" action="{{ route('admin.bookings.payout.retry', $b) }}"
                                  onsubmit="return confirm('{{ $isRtl ? 'إعادة محاولة التحويل عبر ميسر؟' : 'Retry the Moyasar transfer for this booking?' }}');">
                                @csrf
                                <button type="submit" class="font-semibold text-white bg-[#b91c1c] hover:bg-[#991b1b] {{ $fa }}"
                                        style="padding: 7px 14px; border-radius: 10px; font-size: 13px; white-space: nowrap;">
                                    {{ $isRtl ? 'إعادة المحاولة' : 'Retry' }}
                                </button>
                            </form>
                        </div>
                    @endif

                    {{-- Host + bank + action --}}
                    <div class="flex flex-wrap items-center justify-between border-t border-[#f0f0f0]" style="gap: 12px; margin-top: 14px; padding-top: 14px;">
                        <div class="min-w-0">
                            <span class="block text-[14px] font-semibold text-[#222] {{ $fa }}">{{ $b->host?->name ?: '—' }}
                                <span class="text-[13px] font-normal text-[#717171]" dir="ltr">@if($b->host?->phone)+966 {{ $b->host->phone }}@endif</span>
                            </span>
                            @if($b->host?->bank_account)
                                <span class="block text-[13px] text-[#717171] tabular-nums" dir="ltr" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $b->host->bank_account }}')" style="cursor: copy;" title="{{ $isRtl ? 'انقر للنسخ' : 'Click to copy' }}">
                                    {{ $b->host->bank ? $b->host->bank.' · ' : '' }}{{ $b->host->bank_account }}
                                </span>
                            @else
                                <span class="inline-flex items-center text-[12px] font-semibold text-[#b45309]" style="gap: 5px; margin-top: 3px; background: #fffbeb; padding: 3px 10px; border-radius: 999px;">⚠ {{ $isRtl ? 'لا يوجد آيبان مسجل' : 'No IBAN on file' }}</span>
                            @endif
                        </div>

                        @if($tab === 'paid')
                            <div class="flex items-center" style="gap: 12px;">
                                <span class="text-end">
                                    <span class="block text-[12px] font-semibold text-[#10b981] {{ $fa }}">✓ {{ $isRtl ? 'دُفع' : 'Paid' }} {{ $b->paid_out_at?->isoFormat('D MMM YYYY, h:mm A') }}</span>
                                    @if($b->payout_reference)<span class="block text-[12px] text-[#717171] tabular-nums" dir="ltr">{{ $isRtl ? 'مرجع:' : 'Ref:' }} {{ $b->payout_reference }}</span>@endif
                                </span>
                                <form method="POST" action="{{ route('admin.bookings.payout', $b) }}"
                                      onsubmit="return confirm('{{ $isRtl ? 'إعادة هذا الحجز لقائمة التحويلات المستحقة؟' : 'Return this booking to the payout queue?' }}');">
                                    @csrf
                                    <input type="hidden" name="payout_status" value="not_paid">
                                    <button type="submit" class="font-semibold text-[#717171] hover:bg-[#f3f4f6] {{ $fa }}"
                                            style="padding: 8px 14px; border-radius: 12px; font-size: 13px; border: 1px solid #ebebeb;">
                                        {{ $isRtl ? 'تراجع' : 'Undo' }}
                                    </button>
                                </form>
                            </div>
                        @elseif($tab === 'processing')
                            <span class="text-end">
                                <span class="inline-flex items-center text-[12px] font-semibold text-[#1d4ed8]" style="gap: 6px; background: #eff6ff; padding: 5px 12px; border-radius: 999px;">
                                    ⏳ {{ $isRtl ? 'جارٍ التحويل عبر ميسر' : 'Transfer in progress via Moyasar' }}
                                </span>
                                @if($b->payout_id)
                                    <span class="block text-[12px] text-[#717171] tabular-nums" dir="ltr" style="margin-top: 4px;">{{ $b->payout_id }}</span>
                                @endif
                                <span class="block text-[11px] text-[#bbb]" dir="ltr">{{ $isRtl ? 'بدأ' : 'Started' }} {{ $b->updated_at?->isoFormat('D MMM, h:mm A') }}</span>
                            </span>
                        @else
                            @php $payableAt = $b->payableAt(); @endphp
                            <div class="flex flex-wrap items-center" style="gap: 10px;">
                                {{-- Documents-before-money + hold-window state: mark-paid
                                     is rejected server-side until these clear. --}}
                                @if($b->financial_completed_at === null)
                                    <span class="inline-flex items-center text-[12px] font-semibold text-[#b45309]" style="gap: 5px; background: #fffbeb; padding: 5px 12px; border-radius: 999px;">
                                        🧾 {{ $isRtl ? 'بانتظار إصدار الفواتير' : 'Awaiting invoices' }}
                                    </span>
                                @elseif($payableAt !== null && $payableAt->isFuture())
                                    <span class="inline-flex items-center text-[12px] font-semibold text-[#b45309]" style="gap: 5px; background: #fffbeb; padding: 5px 12px; border-radius: 999px;">
                                        ⏸ {{ $isRtl ? 'فترة الحجز حتى' : 'In hold until' }} <span class="tabular-nums" dir="ltr">{{ $payableAt->isoFormat('D MMM, h:mm A') }}</span>
                                    </span>
                                @endif
                                <form method="POST" action="{{ route('admin.bookings.payout', $b) }}" class="flex items-center" style="gap: 8px;"
                                      onsubmit="return confirm('{{ $isRtl ? 'تأكيد أنك حولت المبلغ للمضيف؟' : 'Confirm you have transferred this amount to the host?' }}');">
                                    @csrf
                                    <input type="hidden" name="payout_status" value="paid">
                                    <input type="text" name="payout_reference" maxlength="100" dir="ltr"
                                           placeholder="{{ $isRtl ? 'مرجع التحويل (اختياري)' : 'Transfer ref (optional)' }}"
                                           class="text-[13px] bg-[#f7f7f7] focus:outline-none focus:border-[#222] {{ $fa }}"
                                           style="padding: 9px 12px; border-radius: 12px; border: 1px solid #ebebeb; width: 180px;">
                                    <button type="submit" class="font-semibold text-white bg-[#10b981] hover:bg-[#059669] {{ $fa }}"
                                            style="padding: 9px 16px; border-radius: 12px; font-size: 13px; white-space: nowrap;">
                                        {{ $isRtl ? 'تم الدفع ✓' : 'Mark paid ✓' }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($bookings->hasPages())
            <div style="margin-top: 24px;">{{ $bookings->links() }}</div>
        @endif
    @endif
@endsection
