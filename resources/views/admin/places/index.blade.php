@extends('layouts.admin')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';

    // Same vivid pill palette as host/places/index — saturated bg, lighter dot, white text.
    $reviewPill = fn (PlaceReviewStatus $s): array => match ($s) {
        PlaceReviewStatus::Draft         => ['bg' => '#9ca3af', 'dot' => '#e5e7eb'],
        PlaceReviewStatus::PendingReview => ['bg' => '#f59e0b', 'dot' => '#fde68a'],
        PlaceReviewStatus::Approved      => ['bg' => '#10b981', 'dot' => '#a7f3d0'],
        PlaceReviewStatus::Rejected      => ['bg' => '#ef4444', 'dot' => '#fecaca'],
    };
    $statusPill = fn (PlaceStatus $s): array => $s === PlaceStatus::Active
        ? ['bg' => '#10b981', 'dot' => '#a7f3d0']
        : ['bg' => '#9ca3af', 'dot' => '#e5e7eb'];
@endphp

@section('title', $isRtl ? 'الأماكن' : 'Places')
@section('heading', $isRtl ? 'الأماكن' : 'Places')

@php
    // Stats cards palette — same tints we use across the platform.
    $statCards = [
        ['key' => 'total',          'label_ar' => 'الإجمالي',         'label_en' => 'Total',          'tint' => '#222'],
        ['key' => 'draft',          'label_ar' => 'مسودة',           'label_en' => 'Draft',          'tint' => '#9ca3af'],
        ['key' => 'pending_review', 'label_ar' => 'بانتظار المراجعة',  'label_en' => 'Pending',        'tint' => '#f59e0b'],
        ['key' => 'approved',       'label_ar' => 'موافق عليه',       'label_en' => 'Approved',       'tint' => '#10b981'],
        ['key' => 'rejected',       'label_ar' => 'مرفوض',           'label_en' => 'Rejected',       'tint' => '#ef4444'],
        ['key' => 'active',         'label_ar' => 'مفعّل',            'label_en' => 'Active',         'tint' => '#10b981'],
        ['key' => 'inactive',       'label_ar' => 'موقوف',           'label_en' => 'Inactive',       'tint' => '#9ca3af'],
    ];
@endphp

@section('main')
    {{-- ── Stat cards ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7" style="gap: 10px; margin-bottom: 20px;">
        @foreach($statCards as $card)
            @php $label = $isRtl ? $card['label_ar'] : $card['label_en']; @endphp
            <div class="bg-white" style="padding: 14px 16px; border-radius: 18px; box-shadow: 0px 6px 18px 0px rgba(0,0,0,0.04);">
                <div class="flex items-center justify-between" style="gap: 6px;">
                    <span class="text-[11px] font-semibold text-[#717171] truncate {{ $isRtl ? 'font-arabic' : '' }}">{{ $label }}</span>
                    <span class="block shrink-0" style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $card['tint'] }};"></span>
                </div>
                <div class="text-[22px] font-bold text-[#222] tabular-nums" style="margin-top: 6px; line-height: 1.1;">{{ $counts[$card['key']] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    {{-- ── Search + Start review row ── --}}
    <div class="flex items-center justify-between flex-wrap" style="margin-bottom: 16px; gap: 10px;">
        <form method="GET" action="{{ route('admin.places.index') }}" class="flex items-center bg-white" style="gap: 0; border-radius: 14px; padding: 4px; min-width: 320px; flex: 1; max-width: 480px;">
            <input type="text" name="q" value="{{ $search }}" placeholder="{{ $isRtl ? 'ابحث برقم المكان أو جوال المضيف' : 'Search by place UUID or host phone' }}"
                   class="flex-1 bg-transparent text-[14px] text-[#222] focus:outline-none {{ $isRtl ? 'font-arabic' : '' }}"
                   style="padding: 8px 14px;" dir="auto">
            <button type="submit" class="font-semibold text-white bg-[#222] hover:bg-black {{ $isRtl ? 'font-arabic' : '' }}"
                    style="padding: 8px 16px; border-radius: 10px; font-size: 13px;">
                {{ $isRtl ? 'بحث' : 'Search' }}
            </button>
            @if($search)
                <a href="{{ route('admin.places.index') }}" class="text-[13px] text-[#717171] hover:text-[#222] {{ $isRtl ? 'font-arabic' : '' }}" style="padding: 0 12px;">
                    {{ $isRtl ? '✕ مسح' : '✕ Clear' }}
                </a>
            @endif
        </form>

        <div class="flex items-center" style="gap: 10px;">
            {{-- Admin onboards a host: same wizard with an extra "attach to
                 host phone" field shown only because the current user is an
                 admin. See Host\PlacesController::resolveHost(). --}}
            <a href="{{ route('host.places.create') }}"
               class="inline-flex items-center font-bold text-white bg-[#222] hover:bg-black {{ $isRtl ? 'font-arabic' : '' }}"
               style="padding: 10px 18px; gap: 6px; border-radius: 14px; font-size: 14px;">
                <span>+</span>
                <span>{{ $isRtl ? 'إضافة مكان' : 'Add place' }}</span>
            </a>

            @if($nextReview)
                <a href="{{ route('admin.places.review', $nextReview) }}"
                   class="inline-flex items-center font-bold text-white bg-[#F88379] hover:bg-[#f56b60] {{ $isRtl ? 'font-arabic' : '' }}"
                   style="padding: 10px 18px; gap: 8px; border-radius: 14px; font-size: 14px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
                    <span>{{ $counts['pending_review'] }}</span>
                    <span>{{ $isRtl ? 'بدء المراجعة' : 'Start review' }}</span>
                    <span class="rtl:scale-x-[-1] inline-block">→</span>
                </a>
            @else
                <span class="text-[13px] text-[#717171] {{ $isRtl ? 'font-arabic' : '' }}">{{ $isRtl ? 'لا توجد طلبات مراجعة' : 'No pending reviews' }}</span>
            @endif
        </div>
    </div>

    <p class="text-[14px] text-[#717171]" style="margin-bottom: 12px;">
        {{ $places->total() }} {{ $isRtl ? 'مكان' : 'places' }}
        @if($search) — {{ $isRtl ? 'تصفية:' : 'filtered by:' }} <code class="text-[12px]" dir="ltr">{{ $search }}</code> @endif
    </p>

    @if($places->isEmpty())
        <div class="bg-white text-center text-[#717171]" style="padding: 48px 20px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            {{ $isRtl ? 'لا توجد أماكن بعد.' : 'No places yet.' }}
        </div>
    @else
        @php $start = $isRtl ? 'text-right' : 'text-left'; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 28px 20px;">
            @foreach($places as $place)
                @php
                    $sp = $statusPill($place->status);
                    $rp = $reviewPill($place->review_status);
                    $cover = $place->coverPhoto?->url;
                @endphp
                <div class="flex flex-col">
                    {{-- Clickable cover + details → edit page (app-style card) --}}
                    <a href="{{ route('admin.places.edit', $place) }}" class="block group">
                        <div class="relative overflow-hidden" style="aspect-ratio: 1 / 1; border-radius: 24px; background-color: #f4f4f4;">
                            @if($cover)
                                <img src="{{ $cover }}" alt="" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-500" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center" style="font-size: 52px; opacity: 0.45;">{{ $place->type?->icon ?: '🏠' }}</div>
                            @endif
                            <div class="absolute inset-x-0 top-0 flex items-start justify-between" style="padding: 12px; gap: 8px;">
                                <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wider text-white"
                                      style="padding: 4px 10px 4px 8px; border-radius: 999px; gap: 5px; background-color: {{ $sp['bg'] }}; box-shadow: 0 2px 8px rgba(0,0,0,0.25);">
                                    <span style="width: 5px; height: 5px; border-radius: 999px; background-color: {{ $sp['dot'] }};"></span>
                                    {{ $place->status->value }}
                                </span>
                                <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wider text-white whitespace-nowrap"
                                      style="padding: 4px 10px 4px 8px; border-radius: 999px; gap: 5px; background-color: {{ $rp['bg'] }}; box-shadow: 0 2px 8px rgba(0,0,0,0.25);">
                                    <span style="width: 5px; height: 5px; border-radius: 999px; background-color: {{ $rp['dot'] }};"></span>
                                    {{ str_replace('_', ' ', $place->review_status->value) }}
                                </span>
                            </div>
                        </div>

                        <div style="padding-top: 12px;">
                            <h3 class="font-bold text-[#222] truncate {{ $start }} {{ $isRtl ? 'font-arabic' : '' }}" style="font-size: 16px;">
                                {{ $place->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}
                            </h3>
                            <p class="text-[14px] text-[#717171] truncate {{ $start }}" style="margin-top: 3px;">
                                {{ $isRtl ? $place->type?->name_ar : $place->type?->name_en }}
                                @if($place->cityArea?->city) · {{ $isRtl ? $place->cityArea->city->name_ar : $place->cityArea->city->name_en }} @endif
                            </p>
                            <p class="text-[15px] font-bold text-[#222] tabular-nums {{ $start }}" dir="ltr" style="margin-top: 4px;">SR {{ number_format($place->price) }}</p>
                            <p class="text-[12px] text-[#999] truncate {{ $start }}" dir="ltr" style="margin-top: 2px;">
                                {{ $place->host?->phone ? '+966 '.$place->host->phone : ($place->host?->email ?? '—') }}
                            </p>
                        </div>
                    </a>

                    {{-- Actions kept under the card --}}
                    <div class="flex items-center" style="margin-top: 12px; gap: 16px;">
                        <a href="{{ route('places.show', $place) }}" class="text-[13px] text-[#717171] font-semibold hover:text-[#222]">{{ $isRtl ? 'عرض' : 'View' }}</a>
                        <a href="{{ route('admin.places.edit', $place) }}" class="text-[13px] text-[#222] font-semibold hover:underline">{{ $isRtl ? 'تعديل' : 'Edit' }}</a>
                        <form method="POST" action="{{ route('admin.places.destroy', $place) }}" class="inline" style="margin-inline-start: auto;" onsubmit="return confirm('{{ $isRtl ? 'حذف هذا المكان؟ يمكن استعادته لاحقاً.' : 'Delete this place? It can be restored later.' }}');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[13px] text-[#dc2626] font-semibold hover:underline">{{ $isRtl ? 'حذف' : 'Delete' }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($places->hasPages())<div style="margin-top: 24px;">{{ $places->links() }}</div>@endif
@endsection
