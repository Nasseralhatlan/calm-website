{{--
    Payout bank-account editor. Self-contained form (its own @csrf + PATCH to
    profile.update) so it can drop onto both the profile and finance pages.
    Required vars: $user.
--}}
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
@endphp

<div class="bg-white" style="padding: 24px; border-radius: 24px; box-shadow: 0px 8px 24px 0px rgba(0,0,0,0.05);">
    <div class="flex items-center" style="gap: 10px; margin-bottom: 4px;">
        <span style="font-size: 20px;">🏦</span>
        <h2 class="text-[16px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'الحساب البنكي للتحويلات' : 'Bank account for payouts' }}</h2>
    </div>
    <p class="text-[13px] text-[#999] {{ $fa }}" style="margin-bottom: 18px;">
        {{ $isRtl ? 'نحول أرباحك إلى هذا الحساب. تأكد من أن الآيبان باسمك.' : 'We send your earnings to this account. Make sure the IBAN is in your name.' }}
    </p>

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
            {{-- Bank (free text, informational) --}}
            <div>
                <label for="bank" class="block text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'البنك' : 'Bank' }}</label>
                <input type="text" name="bank" id="bank"
                       value="{{ old('bank', $user->bank) }}"
                       placeholder="{{ $isRtl ? 'اسم البنك' : 'Bank name' }}" maxlength="120"
                       class="w-full bg-[#f7f7f7] text-[14px] text-[#222] focus:outline-none {{ $fa }}"
                       style="padding: 12px 14px; border-radius: 14px; border: 1px solid #ebebeb;">
            </div>

            {{-- IBAN --}}
            <div>
                <label for="bank_account" class="block text-[12px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 6px;">{{ $isRtl ? 'رقم الآيبان' : 'IBAN' }}</label>
                <input type="text" name="bank_account" id="bank_account"
                       value="{{ old('bank_account', $user->bank_account) }}"
                       placeholder="SA0000000000000000000000" dir="ltr" maxlength="34"
                       class="w-full bg-[#f7f7f7] text-[14px] text-[#222] tabular-nums focus:outline-none"
                       style="padding: 12px 14px; border-radius: 14px; border: 1px solid #ebebeb;">
            </div>
        </div>

        <div class="flex items-center justify-{{ $isRtl ? 'start' : 'end' }}" style="margin-top: 18px;">
            <button type="submit"
                    class="font-bold text-white bg-[#222] hover:bg-black transition-colors {{ $fa }}"
                    style="padding: 11px 24px; border-radius: 14px; font-size: 14px;">
                {{ $isRtl ? 'حفظ' : 'Save' }}
            </button>
        </div>
    </form>
</div>
