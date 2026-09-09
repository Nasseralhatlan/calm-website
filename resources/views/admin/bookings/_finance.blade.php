@php
    use App\Enums\BookingStatus;
    use App\Models\FinancialDocument;
    use App\Models\FinancialMovement;

    $isRtl = app()->getLocale() === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $sr = fn (int $minor) => number_format($minor / 100, 2);

    $docLabels = [
        FinancialDocument::GUEST_BOOKING_INVOICE => $isRtl ? 'فاتورة الضيف' : 'Guest invoice',
        FinancialDocument::HOST_COMMISSION_INVOICE => $isRtl ? 'فاتورة العمولة (المضيف)' : 'Commission invoice (host)',
        FinancialDocument::HOST_PAYOUT_STATEMENT => $isRtl ? 'بيان مستحقات المضيف' : 'Host payout statement',
        FinancialDocument::GUEST_BOOKING_CREDIT_NOTE => $isRtl ? 'إشعار دائن — الضيف' : 'Credit note — guest',
        FinancialDocument::HOST_COMMISSION_CREDIT_NOTE => $isRtl ? 'إشعار دائن — العمولة' : 'Credit note — commission',
    ];
    $docStatus = [
        FinancialDocument::STATUS_ISSUED => ['bg' => '#ecfdf5', 'fg' => '#059669', 'label' => $isRtl ? 'صادرة' : 'Issued'],
        FinancialDocument::STATUS_PENDING_PROVIDER => ['bg' => '#fffbeb', 'fg' => '#b45309', 'label' => $isRtl ? 'بانتظار قيود' : 'Pending Qoyod'],
        FinancialDocument::STATUS_FAILED => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'label' => $isRtl ? 'فشلت المزامنة' : 'Sync failed'],
        FinancialDocument::STATUS_CREDITED => ['bg' => '#f3f4f6', 'fg' => '#6b7280', 'label' => $isRtl ? 'معكوسة بإشعار دائن' : 'Credited'],
    ];

    $movementLabels = [
        FinancialMovement::GUEST_PAYMENT => $isRtl ? 'دفعة الضيف' : 'Guest payment',
        FinancialMovement::COMMISSION_WITHHELD => $isRtl ? 'عمولة كالم (مخصومة)' : 'Commission withheld',
        FinancialMovement::HOST_PAYOUT_PAYABLE => $isRtl ? 'مستحق للمضيف' : 'Payout payable',
        FinancialMovement::HOST_PAYOUT => $isRtl ? 'تحويل للمضيف' : 'Host payout',
        FinancialMovement::GUEST_REFUND => $isRtl ? 'استرداد للضيف' : 'Guest refund',
        FinancialMovement::PAYMENT_PROVIDER_FEE => $isRtl ? 'رسوم بوابة الدفع' : 'Provider fee',
    ];
    $movementStatus = [
        FinancialMovement::STATUS_SUCCEEDED => ['fg' => '#059669', 'label' => $isRtl ? 'تمت' : 'Succeeded'],
        FinancialMovement::STATUS_PENDING => ['fg' => '#b45309', 'label' => $isRtl ? 'قيد الانتظار' : 'Pending'],
        FinancialMovement::STATUS_FAILED => ['fg' => '#b91c1c', 'label' => $isRtl ? 'فشلت' : 'Failed'],
        FinancialMovement::STATUS_REVERSED => ['fg' => '#b91c1c', 'label' => $isRtl ? 'معكوسة' : 'Reversed'],
    ];

    // Admin settlement availability. The money-real conditions only (guest
    // actually paid, booking live, nothing in flight) — the timing gates are
    // deliberately NOT required, that is the point of the admin override.
    $payoutsAutoMode = $payoutsAutoMode ?? false;
    $settleable = $booking->payout_status === 'not_paid'
        && $booking->payment_status === 'paid'
        && in_array($booking->booking_status, [BookingStatus::Confirmed, BookingStatus::Completed], true);
    $earlyRelease = $settleable && ! $booking->isPayable();
    $payoutMethods = [
        'moyasar' => $isRtl ? 'ميسر (تلقائي)' : 'Moyasar (automatic)',
        'bank' => $isRtl ? 'تحويل بنكي (يدوي)' : 'Bank transfer (manual)',
        'cash' => $isRtl ? 'نقداً' : 'Cash',
    ];

    $documents = $booking->financialDocuments->sortBy('created_at');
    $movements = $booking->financialMovements->sortBy('created_at');
    $payableAt = $booking->payableAt();
@endphp

{{-- ── Host payout (automatic via Moyasar) ── --}}
<div style="background:#fff;border-radius:24px;padding:24px;box-shadow:0px 8px 24px 0px rgba(0,0,0,0.05);">
    <div class="flex flex-wrap items-center justify-between" style="gap: 12px;">
        <div>
            <h2 class="text-[15px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'تحويل المضيف' : 'Host payout' }}</h2>
            <p class="text-[12px] text-[#999] {{ $fa }}" style="margin-top: 2px;">
                {{ $isRtl ? 'تلقائي عبر ميسر بعد إصدار الفواتير وانتهاء فترة الحجز.' : 'Automatic via Moyasar once invoices are issued and the hold window passes.' }}
            </p>
        </div>
        <div class="text-end">
            <span class="block text-[10px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">{{ $isRtl ? 'المبلغ' : 'Amount' }}</span>
            <span class="block font-bold text-[#10b981] tabular-nums" style="font-size: 20px;" dir="ltr">SR {{ $sr($booking->hostNetMinor()) }}</span>
        </div>
    </div>

    <div style="margin-top: 14px;">
        @if($booking->payout_status === 'paid')
            <span class="inline-flex items-center text-[13px] font-semibold text-[#059669]" style="gap: 6px; background: #ecfdf5; padding: 6px 14px; border-radius: 999px;">
                ✓ {{ $isRtl ? 'تم التحويل' : 'Paid' }} {{ $booking->payout_paid_at?->isoFormat('D MMM YYYY, h:mm A') }}
            </span>
            @if($booking->payout_reference)
                <span class="block text-[12px] text-[#717171] tabular-nums" dir="ltr" style="margin-top: 6px;">{{ $isRtl ? 'مرجع:' : 'Ref:' }} {{ $booking->payout_reference }}</span>
            @endif
            {{-- How it was settled, by whom, and whether it jumped the queue. --}}
            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 6px;">
                @if($booking->payout_method)
                    <span>{{ $isRtl ? 'طريقة الصرف:' : 'Method:' }} <span class="font-semibold text-[#222]">{{ $payoutMethods[$booking->payout_method] ?? $booking->payout_method }}</span></span>
                @endif
                @if($booking->payoutSettledBy)
                    <span> · {{ $isRtl ? 'سجّله' : 'Recorded by' }} <span class="font-semibold text-[#222]">{{ $booking->payoutSettledBy->name ?: $booking->payoutSettledBy->phone }}</span></span>
                @endif
                @if($booking->payout_forced_at)
                    <span class="inline-flex items-center text-[11px] font-semibold text-[#b45309]" style="gap: 4px; background:#fffbeb; padding: 2px 8px; border-radius: 999px; margin-inline-start: 6px;">
                        ⚡ {{ $isRtl ? 'صرف مبكر' : 'Released early' }}
                    </span>
                @endif
                @if($booking->payout_note)
                    <span class="block" style="margin-top: 4px;">{{ $isRtl ? 'ملاحظة:' : 'Note:' }} {{ $booking->payout_note }}</span>
                @endif
            </div>
        @elseif($booking->payout_status === 'processing')
            <span class="inline-flex items-center text-[13px] font-semibold text-[#1d4ed8]" style="gap: 6px; background: #eff6ff; padding: 6px 14px; border-radius: 999px;">
                ⏳ {{ $isRtl ? 'جارٍ التحويل عبر ميسر — يُسوّى تلقائياً' : 'Transfer in progress via Moyasar — settles automatically' }}
            </span>
            @if($booking->payout_id)
                <span class="block text-[12px] text-[#717171] tabular-nums" dir="ltr" style="margin-top: 6px;">{{ $booking->payout_id }}</span>
            @endif
        @elseif($booking->payout_failure)
            <div class="flex flex-wrap items-center justify-between" style="gap: 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 10px 14px;">
                <span class="text-[13px] text-[#b91c1c] {{ $fa }}">⚠ {{ $isRtl ? 'فشل التحويل الآلي:' : 'Automatic transfer failed:' }}
                    <span dir="ltr">{{ $booking->payout_failure }}</span>
                </span>
                <form method="POST" action="{{ route('admin.bookings.payout.retry', $booking) }}"
                      onsubmit="return confirm('{{ $isRtl ? 'إعادة محاولة التحويل عبر ميسر؟' : 'Retry the Moyasar transfer for this booking?' }}');">
                    @csrf
                    <button type="submit" class="font-semibold text-white bg-[#b91c1c] hover:bg-[#991b1b] {{ $fa }}"
                            style="padding: 7px 14px; border-radius: 10px; font-size: 13px; white-space: nowrap;">
                        {{ $isRtl ? 'إعادة المحاولة' : 'Retry' }}
                    </button>
                </form>
            </div>
        @elseif($booking->booking_status !== BookingStatus::Completed)
            <span class="inline-flex items-center text-[13px] font-semibold text-[#6b7280]" style="gap: 6px; background: #f3f4f6; padding: 6px 14px; border-radius: 999px;">
                {{ $isRtl ? 'يُحوّل بعد انتهاء الإقامة' : 'Transfers after the stay completes' }}
            </span>
        @elseif($booking->financial_completed_at === null)
            <span class="inline-flex items-center text-[13px] font-semibold text-[#b45309]" style="gap: 6px; background: #fffbeb; padding: 6px 14px; border-radius: 999px;">
                🧾 {{ $isRtl ? 'بانتظار إصدار الفواتير' : 'Awaiting invoices' }}
            </span>
        @elseif($payableAt !== null && $payableAt->isFuture())
            <span class="inline-flex items-center text-[13px] font-semibold text-[#b45309]" style="gap: 6px; background: #fffbeb; padding: 6px 14px; border-radius: 999px;">
                ⏸ {{ $isRtl ? 'فترة الحجز حتى' : 'In hold until' }} <span class="tabular-nums" dir="ltr">{{ $payableAt->isoFormat('D MMM, h:mm A') }}</span>
            </span>
        @elseif(! $booking->host?->bank_account)
            {{-- Wait state, not a failure: the sweep skips it and nudges the
                 host daily; the payout fires itself once the IBAN is added. --}}
            <span class="inline-flex items-center text-[13px] font-semibold text-[#b45309]" style="gap: 6px; background: #fffbeb; padding: 6px 14px; border-radius: 999px;">
                🏦 {{ $isRtl ? 'بانتظار بيانات المضيف البنكية — تم إشعاره، ويتم التحويل تلقائياً فور إضافتها' : 'Waiting for the host to add bank details — host notified; transfers automatically once added' }}
            </span>
        @else
            <span class="inline-flex items-center text-[13px] font-semibold text-[#b45309]" style="gap: 6px; background: #fffbeb; padding: 6px 14px; border-radius: 999px;">
                {{ $isRtl ? 'في قائمة التحويل — الدورة القادمة تنفذه تلقائياً' : 'Queued — the next automatic sweep transfers it' }}
            </span>
        @endif

        @if($booking->host?->bank_account)
            <span class="block text-[12px] text-[#717171] tabular-nums" dir="ltr" style="margin-top: 8px;">
                {{ $booking->host->bank ? $booking->host->bank.' · ' : '' }}{{ $booking->host->bank_account }}{{ $booking->host->bank_account_name ? ' · '.$booking->host->bank_account_name : '' }}
            </span>
        @else
            <span class="inline-flex items-center text-[12px] font-semibold text-[#b45309]" style="gap: 5px; margin-top: 8px; background: #fffbeb; padding: 3px 10px; border-radius: 999px;">
                ⚠ {{ $isRtl ? 'لا يوجد آيبان مسجل للمضيف' : 'Host has no IBAN on file' }}
            </span>
        @endif

        {{-- ── Admin settlement ────────────────────────────────────────
             Two deliberate escape hatches from the automatic flow:
               • Pay now   — fire the Moyasar transfer early, before the stay
                             completes or the hold window closes.
               • Mark paid — the money already left the company bank by hand,
                             or was handed to the host in cash.
             Both issue the invoices first, so the books never lag the money.
             Shown on any live, guest-paid, unsettled booking; the server
             re-checks every condition. --}}
        @if($settleable)
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e5e7eb;">
                @if($earlyRelease)
                    <p class="text-[12px] text-[#b45309] {{ $fa }}" style="background: #fffbeb; padding: 8px 12px; border-radius: 10px; margin-bottom: 10px;">
                        ⚠ {{ $isRtl
                            ? 'هذا الحجز لم يستحق التحويل بعد. الصرف الآن يصدر الفواتير فوراً ويُسجَّل كصرف مبكر باسمك.'
                            : 'This booking is not payable yet. Settling now issues the invoices immediately and is recorded as an early payout under your name.' }}
                    </p>
                @endif

                @if($payoutsAutoMode)
                    <form method="POST" action="{{ route('admin.bookings.payout.pay-now', $booking) }}"
                          class="flex flex-wrap items-center" style="gap: 8px; margin-bottom: 10px;"
                          onsubmit="return confirm('{{ $isRtl ? 'تحويل المبلغ للمضيف الآن عبر ميسر؟ سيتم إصدار الفواتير أولاً.' : 'Transfer the money to the host now via Moyasar? Invoices are issued first.' }}');">
                        @csrf
                        <input type="text" name="note" maxlength="500"
                               placeholder="{{ $isRtl ? 'سبب الصرف المبكر (اختياري)' : 'Reason for the early payout (optional)' }}"
                               class="text-[13px] flex-1" style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 10px; min-width: 200px;">
                        <button type="submit" class="font-bold text-white bg-[#1d4ed8] hover:bg-[#1e40af] {{ $fa }}"
                                style="padding: 8px 14px; border-radius: 10px; font-size: 13px; white-space: nowrap;">
                            ⚡ {{ $isRtl ? 'تحويل الآن عبر ميسر' : 'Pay now via Moyasar' }}
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.bookings.payout.mark-paid', $booking) }}"
                      class="flex flex-wrap items-center" style="gap: 8px;"
                      onsubmit="return confirm('{{ $isRtl ? 'تسجيل المبلغ كمدفوع للمضيف؟ سيتم إصدار الفواتير والسند وإشعار المضيف.' : 'Record this payout as settled? Invoices, the voucher and the host notification all fire.' }}');">
                    @csrf
                    <select name="method" class="text-[13px] {{ $fa }}"
                            style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff;">
                        <option value="bank">{{ $isRtl ? 'تحويل بنكي' : 'Bank transfer' }}</option>
                        <option value="cash">{{ $isRtl ? 'نقداً' : 'Cash' }}</option>
                    </select>
                    <input type="text" name="bank_reference" required maxlength="100" dir="ltr"
                           placeholder="{{ $isRtl ? 'مرجع التحويل / رقم سند الاستلام' : 'Transfer reference / cash receipt no.' }}"
                           class="text-[13px] tabular-nums"
                           style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 10px; min-width: 200px;">
                    <input type="text" name="note" maxlength="500"
                           placeholder="{{ $isRtl ? 'ملاحظة (اختياري)' : 'Note (optional)' }}"
                           class="text-[13px] flex-1" style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 10px; min-width: 160px;">
                    <button type="submit" class="font-semibold text-[#065f46] bg-[#ecfdf5] hover:bg-[#d1fae5] {{ $fa }}"
                            style="padding: 8px 14px; border-radius: 10px; font-size: 13px; white-space: nowrap;">
                        {{ $isRtl ? 'تسجيل كمدفوع' : 'Mark as paid' }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

{{-- ── Financial documents ── --}}
<div style="background:#fff;border-radius:24px;padding:24px;box-shadow:0px 8px 24px 0px rgba(0,0,0,0.05);">
    <h2 class="text-[15px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 12px;">{{ $isRtl ? 'المستندات المالية' : 'Financial documents' }}</h2>

    @if($documents->isEmpty())
        <p class="text-[13px] text-[#999] {{ $fa }}">
            {{ $isRtl ? 'لا مستندات بعد — تصدر تلقائياً بعد المغادرة.' : 'No documents yet — issued automatically after checkout.' }}
        </p>
    @else
        <div class="flex flex-col" style="gap: 8px;">
            @foreach($documents as $doc)
                @php $chip = $docStatus[$doc->status] ?? ['bg' => '#f3f4f6', 'fg' => '#6b7280', 'label' => $doc->status]; @endphp
                <div class="flex flex-wrap items-center justify-between" style="gap: 10px; background: #fafafa; border-radius: 12px; padding: 10px 14px;">
                    <div class="min-w-0">
                        <span class="block text-[13px] font-semibold text-[#222] {{ $fa }}">{{ $docLabels[$doc->document_subtype] ?? $doc->document_subtype }}</span>
                        <span class="block text-[12px] text-[#717171] tabular-nums" dir="ltr">
                            {{ $doc->external_document_number ?: '—' }} · {{ $doc->issued_at?->isoFormat('D MMM YYYY') }}
                        </span>
                    </div>
                    <div class="flex items-center" style="gap: 10px;">
                        <span class="tabular-nums font-semibold text-[#222] text-[13px]" dir="ltr">SR {{ $sr((int) $doc->total_amount) }}</span>
                        <span class="text-[12px] font-semibold" style="background: {{ $chip['bg'] }}; color: {{ $chip['fg'] }}; padding: 4px 10px; border-radius: 999px;">{{ $chip['label'] }}</span>
                        @if($doc->is_tax_document && $doc->external_document_id)
                            <a href="{{ route('admin.finance-documents.pdf', $doc) }}" target="_blank" rel="noopener"
                               class="text-[12px] font-semibold text-[#222] hover:bg-[#f3f4f6] {{ $fa }}"
                               style="padding: 5px 12px; border-radius: 10px; border: 1px solid #ebebeb; white-space: nowrap;">
                                PDF ↗
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ── Money trail ── --}}
<div style="background:#fff;border-radius:24px;padding:24px;box-shadow:0px 8px 24px 0px rgba(0,0,0,0.05);">
    <h2 class="text-[15px] font-bold text-[#222] {{ $fa }}" style="margin-bottom: 12px;">{{ $isRtl ? 'حركة الأموال' : 'Money trail' }}</h2>

    @if($movements->isEmpty())
        <p class="text-[13px] text-[#999] {{ $fa }}">
            {{ $isRtl ? 'لا حركات مسجلة بعد.' : 'No movements recorded yet.' }}
        </p>
    @else
        <div class="flex flex-col" style="gap: 6px;">
            @foreach($movements as $movement)
                @php
                    $mStatus = $movementStatus[$movement->status] ?? ['fg' => '#6b7280', 'label' => $movement->status];
                    $reversed = $movement->status === App\Models\FinancialMovement::STATUS_REVERSED;
                @endphp
                <div class="flex flex-wrap items-center justify-between text-[13px]" style="gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                    <span class="{{ $fa }} {{ $reversed ? 'line-through opacity-60' : '' }} text-[#222] font-medium">
                        {{ $movementLabels[$movement->movement_type] ?? $movement->movement_type }}
                        <span class="text-[12px] font-normal text-[#999]" dir="ltr">
                            {{ $movement->provider }}{{ $movement->provider_reference ? ' · '.$movement->provider_reference : '' }}
                        </span>
                    </span>
                    <span class="flex items-center" style="gap: 10px;">
                        <span class="tabular-nums {{ $reversed ? 'line-through opacity-60' : '' }}" dir="ltr">SR {{ $sr((int) $movement->amount) }}</span>
                        <span class="text-[12px] font-semibold" style="color: {{ $mStatus['fg'] }};">{{ $mStatus['label'] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
