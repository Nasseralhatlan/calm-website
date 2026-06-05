@extends('layouts.admin')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $cards = [
        ['key' => 'users',          'label_ar' => 'المستخدمون',   'label_en' => 'Users',           'route' => 'admin.users.index',  'tint' => '#C4A5D6'],
        ['key' => 'places',         'label_ar' => 'الأماكن',       'label_en' => 'Places',          'route' => 'admin.places.index', 'tint' => '#F88379'],
        ['key' => 'hosts',          'label_ar' => 'المضيفون',      'label_en' => 'Hosts',           'route' => 'admin.users.index',  'tint' => '#7BA9D6'],
        ['key' => 'pending_review', 'label_ar' => 'بانتظار المراجعة','label_en' => 'Pending review','route' => 'admin.places.index', 'tint' => '#f59e0b'],
    ];

    /**
     * Tiny inline-SVG sparkline. Pass an array of ints, get back an SVG path
     * sized to its container. Zero-only series renders a flat line.
     */
    $sparkline = function (array $series, string $stroke = '#222', int $w = 220, int $h = 44) {
        if ($series === []) return '';
        $max = max(max($series), 1);
        $n = count($series);
        $stepX = $n > 1 ? $w / ($n - 1) : 0;
        $points = [];
        foreach ($series as $i => $v) {
            $x = round($i * $stepX, 2);
            $y = round($h - ($v / $max) * ($h - 4) - 2, 2);
            $points[] = "{$x},{$y}";
        }
        $polyline = implode(' ', $points);
        // Polygon for the fill, polyline for the stroke.
        $fillPoints = "0,{$h} ".$polyline." {$w},{$h}";
        return <<<SVG
            <svg viewBox="0 0 {$w} {$h}" width="100%" height="{$h}" preserveAspectRatio="none" style="display: block;">
                <polygon points="{$fillPoints}" fill="{$stroke}" opacity="0.08"></polygon>
                <polyline points="{$polyline}" fill="none" stroke="{$stroke}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></polyline>
            </svg>
        SVG;
    };
@endphp

@section('title', $isRtl ? 'الرئيسية' : 'Dashboard')
@section('heading', $isRtl ? 'لوحة التحكم' : 'Dashboard')

@section('main')
    <p class="text-[14px] text-[#717171] {{ $fa }}" style="margin-bottom: 24px;">
        {{ $isRtl ? 'مرحباً بك. هذه نظرة سريعة على بيانات المنصة.' : "Welcome back. Here's a quick look at the platform." }}
    </p>

    {{-- Top stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" style="gap: 14px;">
        @foreach($cards as $card)
            @php
                $label = $isRtl ? $card['label_ar'] : $card['label_en'];
                $count = $counts[$card['key']];
            @endphp
            <a href="{{ route($card['route']) }}"
               class="block bg-white hover:-translate-y-0.5 transition-all"
               style="padding: 22px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                <div class="flex items-center justify-between">
                    <span class="text-[12px] font-bold text-[#717171] {{ $fa }}">{{ $label }}</span>
                    <span class="block w-2 h-2 rounded-full" style="background-color: {{ $card['tint'] }};"></span>
                </div>
                <div class="text-[36px] font-bold text-[#222] tabular-nums" style="margin-top: 10px; line-height: 1;">{{ $count }}</div>
                <div class="flex items-center text-[12px] font-semibold text-[#717171] hover:text-[#222] rtl:[&_svg]:-scale-x-100 {{ $fa }}" style="margin-top: 14px; gap: 4px;">
                    <span>{{ $isRtl ? 'فتح' : 'Open' }}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Timeline cards: last 14-day sparklines --}}
    <div class="grid grid-cols-1 lg:grid-cols-3" style="margin-top: 24px; gap: 14px;">
        {{-- Users timeline --}}
        <div class="bg-white" style="padding: 22px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            <div class="flex items-center justify-between" style="margin-bottom: 4px;">
                <span class="text-[13px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'تسجيلات المستخدمين' : 'User signups' }}</span>
                <span class="text-[11px] text-[#717171] {{ $fa }}">{{ $isRtl ? 'آخر ١٤ يوماً' : 'Last 14 days' }}</span>
            </div>
            <div class="text-[24px] font-bold text-[#222] tabular-nums" style="margin-bottom: 12px;">{{ array_sum($timelines['users']) }}</div>
            {!! $sparkline($timelines['users'], '#C4A5D6') !!}
        </div>

        {{-- Places timeline --}}
        <div class="bg-white" style="padding: 22px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            <div class="flex items-center justify-between" style="margin-bottom: 4px;">
                <span class="text-[13px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'أماكن جديدة' : 'New places' }}</span>
                <span class="text-[11px] text-[#717171] {{ $fa }}">{{ $isRtl ? 'آخر ١٤ يوماً' : 'Last 14 days' }}</span>
            </div>
            <div class="text-[24px] font-bold text-[#222] tabular-nums" style="margin-bottom: 12px;">{{ array_sum($timelines['places']) }}</div>
            {!! $sparkline($timelines['places'], '#F88379') !!}
        </div>

        {{-- Bookings — placeholder until the feature ships --}}
        <div class="bg-white relative overflow-hidden" style="padding: 22px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            <div class="flex items-center justify-between" style="margin-bottom: 4px;">
                <span class="text-[13px] font-bold text-[#222] {{ $fa }}">{{ $isRtl ? 'الحجوزات' : 'Bookings' }}</span>
                <span class="text-[11px] text-[#717171] {{ $fa }}">{{ $isRtl ? 'آخر ١٤ يوماً' : 'Last 14 days' }}</span>
            </div>
            <div class="text-[24px] font-bold text-[#cccccc] tabular-nums" style="margin-bottom: 12px;">—</div>
            <div class="text-[12px] text-[#bababa] {{ $fa }}" style="margin-top: 2px;">
                {{ $isRtl ? 'قريباً — لم يتم تفعيل الحجوزات بعد' : 'Coming soon — bookings not yet implemented' }}
            </div>
        </div>
    </div>
@endsection
