@php
    use App\Enums\PromoDiscountType;
    $isRtl = app()->getLocale() === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $sarOf = fn (?int $minor) => $minor === null ? '' : rtrim(rtrim(number_format($minor / 100, 2, '.', ''), '0'), '.');
    $riyadh = fn ($ts) => $ts?->copy()->setTimezone('Asia/Riyadh')->toDateString();
    $input = 'w-full bg-[#fafafa] border border-[#ebebeb] focus:border-[#222] text-[15px] text-[#222] focus:outline-none';
    $inputStyle = 'padding: 11px 14px; border-radius: 12px;';
    $label = 'block text-[13px] font-semibold text-[#222] '.$fa;
    $hint = 'text-[12px] text-[#717171] '.$fa;
    $init = [
        'type' => old('discount_type', $promo->discount_type?->value ?? PromoDiscountType::Percentage->value),
        'value' => (string) old('discount_value', $promo->discount_value ?? ''),
        'cap' => (string) old('max_discount_sar', $sarOf($promo->max_discount_amount)),
        'minStay' => (string) old('min_stay_sar', $sarOf($promo->min_stay_amount)),
        'commission' => $commissionRate,
    ];
@endphp

{{--
    Live "does this code eat past Calm's commission?" check. The discount is
    Calm-funded (host payout never changes), so Calm keeps
    commission − discount per booking; past that, Calm pays from its pocket.
    Mirrors PromoCode::commissionCheck() — the server re-checks on save.
--}}
<div x-data="promoForm(@js($init), {{ $isRtl ? 'true' : 'false' }})">
    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الكود' : 'Code' }}</label>
            <input type="text" name="code" value="{{ old('code', $promo->code) }}" required maxlength="40" dir="ltr"
                   class="{{ $input }} uppercase tracking-wider font-semibold" style="{{ $inputStyle }}" placeholder="SUMMER25">
            <p class="{{ $hint }}" style="margin-top: 6px;">{{ $isRtl ? 'حروف إنجليزية وأرقام و - _ فقط. لا يفرق بين الحروف الكبيرة والصغيرة.' : 'Letters, digits, - and _ only. Case-insensitive for guests.' }}</p>
            @error('code')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'نوع الخصم' : 'Discount type' }}</label>
            <select name="discount_type" x-model="type" class="{{ $input }}" style="{{ $inputStyle }}">
                <option value="percentage">{{ $isRtl ? 'نسبة مئوية من قيمة الإقامة' : 'Percentage of the stay' }}</option>
                <option value="fixed">{{ $isRtl ? 'مبلغ ثابت (ر.س)' : 'Fixed amount (SR)' }}</option>
            </select>
            @error('discount_type')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">
                <span x-show="type === 'percentage'">{{ $isRtl ? 'نسبة الخصم (%)' : 'Discount (%)' }}</span>
                <span x-show="type === 'fixed'" x-cloak>{{ $isRtl ? 'مبلغ الخصم (ر.س)' : 'Discount (SR)' }}</span>
            </label>
            <input type="number" name="discount_value" x-model="value" required step="0.01" min="0.01" dir="ltr"
                   class="{{ $input }} tabular-nums" style="{{ $inputStyle }}">
            <p class="{{ $hint }}" style="margin-top: 6px;">{{ $isRtl ? 'يُخصم من قيمة الإقامة قبل الضريبة.' : 'Taken off the stay before VAT.' }}</p>
            @error('discount_value')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div x-show="type === 'percentage'">
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'أقصى خصم (ر.س، اختياري)' : 'Max discount (SR, optional)' }}</label>
            <input type="number" name="max_discount_sar" x-model="cap" step="0.01" min="0.01" dir="ltr"
                   class="{{ $input }} tabular-nums" style="{{ $inputStyle }}">
            @error('max_discount_sar')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- ── Commission check ── --}}
    <div x-show="valueNum() > 0" x-cloak style="margin-top: 18px; padding: 14px 16px; border-radius: 16px;"
         :style="check().exceeds
            ? 'background:#fef2f2;border:1px solid rgba(185,28,28,0.3);'
            : 'background:#ecfdf5;border:1px solid rgba(5,150,105,0.25);'">
        <p class="text-[14px] font-bold {{ $fa }}" :class="check().exceeds ? 'text-[#b91c1c]' : 'text-[#047857]'" x-text="check().title"></p>
        <p class="text-[13px] {{ $fa }}" style="margin-top: 4px;" :class="check().exceeds ? 'text-[#7f1d1d]' : 'text-[#065f46]'" x-text="check().body"></p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3" style="gap: 16px; margin-top: 18px;">
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'الحد الأدنى للإقامة (ر.س)' : 'Minimum stay (SR)' }}</label>
            <input type="number" name="min_stay_sar" x-model="minStay" step="0.01" min="0.01" dir="ltr"
                   class="{{ $input }} tabular-nums" style="{{ $inputStyle }}">
            <p class="{{ $hint }}" style="margin-top: 6px;">{{ $isRtl ? 'قبل الضريبة. اتركه فارغاً بلا حد.' : 'Before VAT. Blank = no minimum.' }}</p>
            @error('min_stay_sar')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'إجمالي مرات الاستخدام' : 'Total uses' }}</label>
            <input type="number" name="max_uses" value="{{ old('max_uses', $promo->max_uses) }}" min="1" dir="ltr"
                   class="{{ $input }} tabular-nums" style="{{ $inputStyle }}">
            <p class="{{ $hint }}" style="margin-top: 6px;">{{ $isRtl ? 'فارغ = بلا حد.' : 'Blank = unlimited.' }}</p>
            @error('max_uses')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'مرات الاستخدام لكل ضيف' : 'Uses per guest' }}</label>
            <input type="number" name="max_uses_per_user" value="{{ old('max_uses_per_user', $promo->max_uses_per_user) }}" min="1" dir="ltr"
                   class="{{ $input }} tabular-nums" style="{{ $inputStyle }}">
            <p class="{{ $hint }}" style="margin-top: 6px;">{{ $isRtl ? 'فارغ = بلا حد.' : 'Blank = unlimited.' }}</p>
            @error('max_uses_per_user')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'يبدأ في' : 'Starts' }}</label>
            <input type="date" name="starts_at" value="{{ old('starts_at', $riyadh($promo->starts_at)) }}" dir="ltr"
                   class="{{ $input }}" style="{{ $inputStyle }}">
            @error('starts_at')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'ينتهي في' : 'Ends' }}</label>
            <input type="date" name="ends_at" value="{{ old('ends_at', $riyadh($promo->ends_at)) }}" dir="ltr"
                   class="{{ $input }}" style="{{ $inputStyle }}">
            <p class="{{ $hint }}" style="margin-top: 6px;">{{ $isRtl ? 'شاملاً، بتوقيت الرياض.' : 'Inclusive, Riyadh time.' }}</p>
            @error('ends_at')<p class="text-[12px] text-[#dc2626]" style="margin-top: 4px;">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-end" style="padding-bottom: 12px;">
            <label class="inline-flex items-center text-[14px] font-semibold text-[#222] {{ $fa }}" style="gap: 8px;">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promo->is_active))>
                {{ $isRtl ? 'مفعّل' : 'Active' }}
            </label>
        </div>
    </div>

    <div style="margin-top: 16px;">
        <label class="{{ $label }}" style="margin-bottom: 6px;">{{ $isRtl ? 'ملاحظة داخلية (اختياري)' : 'Internal note (optional)' }}</label>
        <input type="text" name="note" value="{{ old('note', $promo->note) }}" maxlength="500"
               class="{{ $input }}" style="{{ $inputStyle }}">
    </div>

    <div class="flex items-center" style="gap: 12px; margin-top: 24px;">
        <button type="submit" @click="if (check().exceeds && !confirm(confirmText())) $event.preventDefault()"
                class="font-semibold text-white bg-[#222] hover:bg-black"
                style="padding: 11px 22px; border-radius: 12px; font-size: 14px;">{{ $submitLabel ?? ($isRtl ? 'حفظ' : 'Save') }}</button>
        <a href="{{ route('admin.promo-codes.index') }}" class="text-[14px] text-[#717171] hover:text-[#222] {{ $fa }}">{{ $isRtl ? 'إلغاء' : 'Cancel' }}</a>
    </div>
</div>

<script>
    function promoForm(init, isRtl) {
        const num = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
        const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString('en-US', { maximumFractionDigits: 2 });
        return {
            ...init,
            valueNum() { return num(this.value); },
            check() {
                const c = this.commission, v = this.valueNum();
                if (this.type === 'percentage') {
                    const over = Math.round((v - c) * 100) / 100;
                    if (over > 0) return {
                        exceeds: true,
                        title: isRtl ? `⚠ تجاوزت نسبة كالم: الخصم ${fmt(v)}% والعمولة ${fmt(c)}% فقط` : `⚠ Exceeds Calm's share: ${fmt(v)}% off vs a ${fmt(c)}% commission`,
                        body: isRtl
                            ? `في كل حجز ستدفع كالم ${fmt(over)}% من قيمة الإقامة من جيبها، فوق التنازل عن كامل العمولة. مستحقات المضيف لا تتغير.`
                            : `On every booking Calm pays ${fmt(over)}% of the stay out of its own pocket, on top of giving up the whole commission. The host's payout doesn't change.`,
                    };
                    return {
                        exceeds: false,
                        title: isRtl ? `✓ ضمن نسبة كالم (${fmt(c)}%)` : `✓ Within Calm's share (${fmt(c)}%)`,
                        body: isRtl ? `تحتفظ كالم بـ ${fmt(c - v)}% من قيمة الإقامة في كل حجز.` : `Calm still keeps ${fmt(c - v)}% of the stay on every booking.`,
                    };
                }
                // Fixed amount: Calm loses money on any stay below amount ÷ rate.
                const breakEven = c > 0 ? Math.ceil(v * 100 * 100 / c) / 100 : Infinity;
                const minStay = num(this.minStay);
                if (!(minStay > 0) || minStay < breakEven) return {
                    exceeds: true,
                    title: isRtl ? `⚠ قد يتجاوز نسبة كالم على الإقامات الأقل من ${fmt(breakEven)} ر.س` : `⚠ Can exceed Calm's share on stays below SR ${fmt(breakEven)}`,
                    body: isRtl
                        ? `عمولة كالم ${fmt(c)}%، فخصم ${fmt(v)} ر.س يتجاوزها على أي إقامة أقل من ${fmt(breakEven)} ر.س. اجعل الحد الأدنى للإقامة ${fmt(breakEven)} ر.س على الأقل لتبقى ضمن العمولة.`
                        : `Calm's commission is ${fmt(c)}%, so SR ${fmt(v)} off is more than it on any stay below SR ${fmt(breakEven)}. Set the minimum stay to at least SR ${fmt(breakEven)} to stay within it.`,
                };
                return {
                    exceeds: false,
                    title: isRtl ? `✓ ضمن نسبة كالم (${fmt(c)}%)` : `✓ Within Calm's share (${fmt(c)}%)`,
                    body: isRtl
                        ? `الحد الأدنى ${fmt(minStay)} ر.س يغطي نقطة التعادل (${fmt(breakEven)} ر.س)، فالخصم لا يتجاوز العمولة أبداً.`
                        : `The SR ${fmt(minStay)} minimum covers the SR ${fmt(breakEven)} break-even, so the discount never exceeds the commission.`,
                };
            },
            confirmText() {
                return isRtl
                    ? 'هذا الكود يتجاوز عمولة كالم — ستدفع كالم الفرق من جيبها. هل تريد الحفظ؟'
                    : "This code exceeds Calm's commission — Calm will pay the difference out of pocket. Save anyway?";
            },
        };
    }
</script>
