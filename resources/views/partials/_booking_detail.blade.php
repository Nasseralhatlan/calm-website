{{--
    Shared booking detail cards. Same design for admin + host + guest.
    Required vars:
      $booking   — Booking with place.coverPhoto, place.type, place.cityArea.city,
                   place.publishedReviews.guest, guest, host loaded.
      $audience  — 'admin' | 'host' | 'guest'
    The parent view appends its own trailing card (admin: cancel actions,
    host/guest: support numbers) inside the same stack.
--}}
@php
    use App\Enums\BookingStatus;

    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $isAdmin = $audience === 'admin';
    $isHost = $audience === 'host';
    $isGuest = $audience === 'guest';

    $st = $booking->booking_status;
    $place = $booking->place;
    $city = $place?->cityArea?->city;

    $reviews = $place?->publishedReviews ?? collect();
    $reviewCount = $reviews->count();
    $avgRate = $reviewCount ? round((float) $reviews->avg('rate'), 1) : null;
    $reviewers = $reviews->map(fn ($r) => $r->guest)->filter()->unique('id')->take(6)->values();

    $fmtTime = fn (?string $t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : '—';
    $sar = fn (int $minor): string => number_format($minor / 100, 2);
    $cur = $isRtl ? 'ر.س' : 'SAR';
    $rate = fn (float $r): string => rtrim(rtrim(number_format($r, 2), '0'), '.');

    // Frozen payout snapshot: gross − commission − commission VAT (legacy
    // rows carry commission VAT 0, so their payout is unchanged).
    $payout = $booking->hostNetMinor();
    $commissionVat = (int) ($booking->commission_vat_amount ?? 0);
    $payoutPaid = $booking->payout_status === 'paid';

    $card = 'background:#fff;border-radius:24px;padding:24px;box-shadow:0px 8px 24px 0px rgba(0,0,0,0.05);';
    $row = 'display:flex;align-items:center;justify-content:space-between;padding:9px 0;';

    // A small avatar circle (image when present, coloured initial otherwise).
    $tints = ['#fde68a' => '#92400e', '#bfdbfe' => '#1e40af', '#bbf7d0' => '#166534', '#fbcfe8' => '#9d174d', '#ddd6fe' => '#5b21b6'];
@endphp

{{-- ── 1 · Booking (hero) ─────────────────────────────────────────── --}}
<div style="{{ $card }}">
    <div class="flex items-start justify-between flex-wrap" style="gap: 12px;">
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'رقم الحجز' : 'Booking reference' }}</span>
            <span class="block font-extrabold text-[#222] tabular-nums" style="font-size: 28px; letter-spacing: 1px; line-height: 1.1;" dir="ltr">{{ $booking->reference }}</span>
        </div>
        <span class="inline-flex items-center text-white font-semibold {{ $fa }}"
              style="gap: 7px; padding: 7px 16px; border-radius: 999px; font-size: 13px; background-color: {{ $st->pill() }};">
            <span style="width: 8px; height: 8px; border-radius: 999px; background-color: rgba(255,255,255,0.7);"></span>
            {{ $st->label($isRtl) }}
        </span>
    </div>

    {{-- Stay summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4" style="gap: 16px; margin-top: 22px; padding-top: 22px; border-top: 1px solid #f0f0f0;">
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'الدخول' : 'Check-in' }}</span>
            <span class="block text-[14px] font-semibold text-[#222] {{ $fa }}">{{ $booking->start_date?->isoFormat('ddd D MMM') ?: '—' }}</span>
            <span class="block text-[13px] text-[#717171] tabular-nums"><span dir="ltr">{{ $fmtTime($booking->check_in_time) }}</span></span>
        </div>
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'الخروج' : 'Check-out' }}</span>
            <span class="block text-[14px] font-semibold text-[#222] {{ $fa }}">
                {{ optional($booking->checkoutAt())->isoFormat('ddd D MMM') ?: '—' }}
                @if($booking->checkout_next_day)<span class="text-[11px] font-semibold text-[#F88379]">{{ $isRtl ? '· التالي' : '· next day' }}</span>@endif
            </span>
            <span class="block text-[13px] text-[#717171] tabular-nums"><span dir="ltr">{{ $fmtTime($booking->check_out_time) }}</span></span>
        </div>
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'عدد الأيام' : 'Days' }}</span>
            <span class="block text-[14px] font-semibold text-[#222] {{ $fa }}">{{ $booking->quantity }}</span>
        </div>
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 4px;">{{ $isRtl ? 'الضيوف' : 'Guests' }}</span>
            <span class="block text-[14px] font-semibold text-[#222] {{ $fa }}">{{ $booking->guests }}</span>
        </div>
    </div>

    <div class="flex items-center flex-wrap text-[12px] text-[#999] {{ $fa }}" style="gap: 6px 16px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f0f0f0;">
        <span>{{ $isRtl ? 'تاريخ الحجز' : 'Booked' }}: <span dir="ltr">{{ $booking->created_at?->isoFormat('D MMM YYYY, h:mm a') }}</span></span>
        @if($booking->confirmed_at)
            <span>· {{ $isRtl ? 'تأكيد الدفع' : 'Confirmed' }}: <span dir="ltr">{{ $booking->confirmed_at->isoFormat('D MMM YYYY, h:mm a') }}</span></span>
        @endif
        @if($booking->canceled_at)
            <span class="text-[#ef4444]">· {{ $isRtl ? 'أُلغي' : 'Cancelled' }}: <span dir="ltr">{{ $booking->canceled_at->isoFormat('D MMM YYYY, h:mm a') }}</span></span>
        @endif
    </div>
</div>

{{-- ── 2 · Place + reviews ────────────────────────────────────────── --}}
<div style="{{ $card }}">
    <div class="flex items-center" style="gap: 16px;">
        <span class="shrink-0 overflow-hidden bg-[#f3f4f6] flex items-center justify-center" style="width: 72px; height: 72px; border-radius: 18px;">
            @if($place?->coverPhoto?->url)
                <img src="{{ $place->coverPhoto->url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
            @else
                <span style="font-size: 30px;">{{ $place?->type?->icon ?: '🏠' }}</span>
            @endif
        </span>
        <div class="flex-1 min-w-0">
            <span class="block font-bold text-[#222] text-[17px] truncate {{ $fa }}">{{ $place?->title ?: '—' }}</span>
            <span class="block text-[13px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">
                {{ $isRtl ? $place?->type?->name_ar : $place?->type?->name_en }}
                @if($city) · {{ $isRtl ? $city->name_ar : $city->name_en }} @endif
            </span>
            @if($avgRate !== null)
                <span class="inline-flex items-center {{ $fa }}" style="gap: 6px; margin-top: 8px;">
                    <span class="inline-flex items-center font-bold text-[#222] text-[13px]" style="gap: 3px;">
                        <span style="color: #fbbf24;">★</span><span class="tabular-nums" dir="ltr">{{ $avgRate }}</span>
                    </span>
                    <span class="text-[12px] text-[#999]">·</span>
                    <span class="text-[12px] text-[#717171]">{{ $reviewCount }} {{ $isRtl ? 'تقييم' : ($reviewCount === 1 ? 'review' : 'reviews') }}</span>
                </span>
            @else
                <span class="block text-[12px] text-[#bbb] {{ $fa }}" style="margin-top: 8px;">{{ $isRtl ? 'لا توجد تقييمات بعد' : 'No reviews yet' }}</span>
            @endif
        </div>
        @if($place)
            <a href="{{ route('places.show', $place) }}" target="_blank" rel="noopener"
               class="shrink-0 inline-flex items-center font-semibold text-[#F88379] hover:text-[#f56b60] {{ $fa }}" style="gap: 4px; font-size: 13px;">
                {{ $isRtl ? 'عرض المكان' : 'View place' }} ↗
            </a>
        @endif
    </div>

    {{-- Reviewer avatars --}}
    @if($reviewers->isNotEmpty())
        <div class="flex items-center" style="margin-top: 18px; padding-top: 18px; border-top: 1px solid #f0f0f0;">
            <div class="flex items-center" dir="ltr">
                @foreach($reviewers as $i => $rv)
                    @php $bg = array_keys($tints)[$i % count($tints)]; @endphp
                    <span class="shrink-0 overflow-hidden flex items-center justify-center font-bold text-[12px]"
                          style="width: 36px; height: 36px; border-radius: 999px; border: 2px solid #fff; background-color: {{ $bg }}; color: {{ $tints[$bg] }}; {{ $i > 0 ? 'margin-left: -10px;' : '' }} box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                        @if($rv->avatar_url)
                            <img src="{{ $rv->avatar_url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($rv->name ?? '?', 0, 1)) }}
                        @endif
                    </span>
                @endforeach
            </div>
            <span class="text-[13px] text-[#717171] {{ $fa }}" style="margin-{{ $isRtl ? 'right' : 'left' }}: 12px;">
                {{ $isRtl ? 'من ضيوف سابقين' : 'from past guests' }}
            </span>
        </div>
    @endif

    @if($isAdmin)
        <div class="text-[12px] text-[#bbb] {{ $fa }}" style="margin-top: 14px;">
            {{ $isRtl ? 'رقم المكان' : 'Place ID' }}: <span dir="ltr" class="text-[#717171]">{{ $booking->place_id }}</span>
        </div>
    @endif
</div>

{{-- ── 3 · Host & Guest ───────────────────────────────────────────── --}}
@php
    // Phone visibility: only admin sees phone numbers. The host does NOT see the
    // guest's phone; guests are routed to support instead of the host's number.
    $showGuestPhone = $isAdmin;
    $showHostPhone = $isAdmin;
    $people = [
        ['label' => $isRtl ? 'المضيف' : 'Host', 'user' => $booking->host, 'phone' => $showHostPhone, 'self' => $isHost],
        ['label' => $isRtl ? 'الضيف' : 'Guest', 'user' => $booking->guest, 'phone' => $showGuestPhone, 'self' => $isGuest],
    ];
@endphp
<div style="{{ $card }}">
    <h2 class="font-bold text-[#222] text-[15px] {{ $fa }}" style="margin-bottom: 16px;">{{ $isRtl ? 'الأطراف' : 'People' }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2" style="gap: 16px;">
        @foreach($people as $p)
            @php $u = $p['user']; $bg = array_keys($tints)[$loop->index % count($tints)]; @endphp
            <div class="flex items-center" style="gap: 12px;">
                <span class="shrink-0 overflow-hidden flex items-center justify-center font-bold text-[15px]"
                      style="width: 48px; height: 48px; border-radius: 999px; background-color: {{ $bg }}; color: {{ $tints[$bg] }};">
                    @if($u?->avatar_url)
                        <img src="{{ $u->avatar_url }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                    @else
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($u?->name ?? '?', 0, 1)) }}
                    @endif
                </span>
                <div class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}">
                        {{ $p['label'] }}@if($p['self'])<span class="text-[#10b981]"> · {{ $isRtl ? 'أنت' : 'you' }}</span>@endif
                    </span>
                    <span class="block text-[15px] font-semibold text-[#222] truncate {{ $fa }}">{{ $u?->name ?: '—' }}</span>
                    @if($p['phone'] && $u?->phone)
                        <a href="tel:+966{{ $u->phone }}" class="block text-[13px] text-[#717171] hover:text-[#F88379]" dir="ltr">+966 {{ $u->phone }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ── 4 · Financials ─────────────────────────────────────────────── --}}
<div style="{{ $card }}">
    <h2 class="font-bold text-[#222] text-[15px] {{ $fa }}" style="margin-bottom: 8px;">
        {{ $isHost ? ($isRtl ? 'الأرباح' : 'Earnings') : ($isRtl ? 'الدفع' : 'Payment') }}
    </h2>
    <div class="text-[14px] {{ $fa }}">
        <div style="{{ $row }}">
            <span class="text-[#717171]">{{ $isRtl ? 'قيمة الحجز' : 'Booking amount' }}</span>
            <span class="font-semibold text-[#222] tabular-nums" dir="ltr">{{ $sar($booking->booking_amount) }} {{ $cur }}</span>
        </div>

        @if($isHost)
            <div style="{{ $row }}">
                <span class="text-[#717171]">{{ $isRtl ? 'عمولة كالم' : 'Calm commission' }} ({{ $rate($booking->commission_rate) }}%)</span>
                <span class="font-semibold text-[#717171] tabular-nums" dir="ltr">− {{ $sar($booking->commission_amount) }} {{ $cur }}</span>
            </div>
            @if($commissionVat > 0)
                <div style="{{ $row }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'ضريبة العمولة' : 'Commission VAT' }} ({{ $rate((float) $booking->commission_vat_rate) }}%)</span>
                    <span class="font-semibold text-[#717171] tabular-nums" dir="ltr">− {{ $sar($commissionVat) }} {{ $cur }}</span>
                </div>
            @endif
            <div style="{{ $row }} border-top:1px solid #f0f0f0;margin-top:4px;">
                <span class="font-bold text-[#222]">{{ $isRtl ? 'المبلغ المستحق' : 'Your payout' }}</span>
                <span class="font-bold text-[#10b981] tabular-nums" dir="ltr">{{ $sar($payout) }} {{ $cur }}</span>
            </div>
            <div style="{{ $row }}">
                <span class="text-[#717171]">{{ $isRtl ? 'الضريبة المحصلة' : 'VAT collected' }} ({{ $rate($booking->vat_rate) }}%)</span>
                <span class="text-[#717171] tabular-nums" dir="ltr">{{ $sar($booking->vat_amount) }} {{ $cur }}</span>
            </div>
        @else
            <div style="{{ $row }}">
                <span class="text-[#717171]">{{ $isRtl ? 'ضريبة القيمة المضافة' : 'VAT' }} ({{ $rate($booking->vat_rate) }}%)</span>
                <span class="font-semibold text-[#222] tabular-nums" dir="ltr">{{ $sar($booking->vat_amount) }} {{ $cur }}</span>
            </div>
            @if($isAdmin)
                <div style="{{ $row }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'عمولة كالم' : 'Calm commission' }} ({{ $rate($booking->commission_rate) }}%)</span>
                    <span class="text-[#717171] tabular-nums" dir="ltr">{{ $sar($booking->commission_amount) }} {{ $cur }}</span>
                </div>
            @endif
            <div style="{{ $row }} border-top:1px solid #f0f0f0;margin-top:4px;">
                <span class="font-bold text-[#222]">{{ $isRtl ? 'الإجمالي' : 'Total' }}</span>
                <span class="font-bold text-[#222] tabular-nums" dir="ltr">{{ $sar($booking->total) }} {{ $cur }}</span>
            </div>
            @if($isAdmin)
                <div style="{{ $row }}">
                    <span class="text-[#717171]">{{ $isRtl ? 'صافي للمضيف' : 'Host payout' }}</span>
                    <span class="text-[#222] tabular-nums" dir="ltr">{{ $sar($payout) }} {{ $cur }}</span>
                </div>
            @endif
        @endif
    </div>

    {{-- Statuses --}}
    <div class="grid grid-cols-2 lg:grid-cols-3" style="gap: 14px; margin-top: 18px; padding-top: 18px; border-top: 1px solid #f0f0f0;">
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'طريقة الدفع' : 'Payment method' }}</span>
            <span class="block text-[14px] font-medium text-[#222]" dir="ltr">{{ $booking->payment_method ?: '—' }}</span>
        </div>
        @if(! $isHost)
            <div class="min-w-0">
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'حالة الدفع' : 'Payment status' }}</span>
                <span class="inline-flex items-center font-semibold" style="gap: 5px; font-size: 13px; color: {{ $booking->payment_status === 'paid' ? '#10b981' : '#717171' }};">
                    <span style="width: 7px; height: 7px; border-radius: 999px; background-color: {{ $booking->payment_status === 'paid' ? '#10b981' : '#d1d5db' }};"></span>
                    <span dir="ltr">{{ $booking->payment_status ?: '—' }}</span>
                </span>
            </div>
        @endif
        <div class="min-w-0">
            <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'حالة التحويل للمضيف' : 'Payout status' }}</span>
            <span class="inline-flex items-center font-semibold {{ $fa }}" style="gap: 5px; font-size: 13px; color: {{ $payoutPaid ? '#10b981' : '#717171' }};">
                <span style="width: 7px; height: 7px; border-radius: 999px; background-color: {{ $payoutPaid ? '#10b981' : '#d1d5db' }};"></span>
                {{ $payoutPaid ? ($isRtl ? 'تم التحويل' : 'Paid') : ($isRtl ? 'بانتظار التحويل' : 'Not paid') }}
            </span>
        </div>
        @if($isAdmin && $booking->payment_id)
            <div class="min-w-0 col-span-2 lg:col-span-3">
                <span class="block text-[11px] font-semibold uppercase tracking-wider text-[#bbb] {{ $fa }}" style="margin-bottom: 3px;">{{ $isRtl ? 'معرّف الدفع' : 'Payment ID' }}</span>
                <span class="block text-[13px] text-[#717171] truncate" dir="ltr">{{ $booking->payment_id }}</span>
            </div>
        @endif
    </div>
</div>
