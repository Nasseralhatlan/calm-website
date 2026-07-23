@extends('layouts.user')

@php
    use App\Enums\PlaceReviewStatus;
    use App\Enums\PlaceStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    // Saturated pill palette — solid colored background, lighter "live" dot,
    // white text. Catches the eye at a glance instead of melting into the row.
    $reviewPill = fn (PlaceReviewStatus $s): array => match ($s) {
        PlaceReviewStatus::Draft         => ['bg' => '#9ca3af', 'dot' => '#e5e7eb'],
        PlaceReviewStatus::PendingReview => ['bg' => '#f59e0b', 'dot' => '#fde68a'],
        PlaceReviewStatus::Approved      => ['bg' => '#10b981', 'dot' => '#a7f3d0'],
        PlaceReviewStatus::Rejected      => ['bg' => '#ef4444', 'dot' => '#fecaca'],
    };
    $statusPill = fn (PlaceStatus $s): array => $s === PlaceStatus::Active
        ? ['bg' => '#10b981', 'dot' => '#a7f3d0']
        : ['bg' => '#9ca3af', 'dot' => '#e5e7eb'];

    $reviewLabel = fn (PlaceReviewStatus $s): string => $isRtl ? match ($s) {
        PlaceReviewStatus::Draft         => 'مسودة',
        PlaceReviewStatus::PendingReview => 'قيد المراجعة',
        PlaceReviewStatus::Approved      => 'موافق عليه',
        PlaceReviewStatus::Rejected      => 'مرفوض',
    } : str_replace('_', ' ', $s->value);

    $statusLabel = fn (PlaceStatus $s): string => $isRtl
        ? ($s === PlaceStatus::Active ? 'مفعّل' : 'موقوف')
        : $s->value;
@endphp

@section('title', $isRtl ? 'أماكني' : 'My places')
@section('heading', $isRtl ? 'أماكني' : 'My places')

@section('header-action')
    <a href="{{ route('host.places.create') }}"
       class="inline-flex items-center font-semibold text-white bg-[#F88379] hover:bg-[#f56b60] {{ $fa }}"
       style="padding: 10px 18px; gap: 8px; border-radius: 14px; font-size: 14px; box-shadow: 0 6px 14px rgba(248,131,121,0.3);">
        <span>+</span><span>{{ $isRtl ? 'إضافة مكان جديد' : 'Add a new place' }}</span>
    </a>
@endsection

@section('main')
    @if($places->isEmpty())
        @include('user._empty_state', [
            'icon' => '🏡',
            'title' => $isRtl ? 'لم تضف أي مكان بعد' : "You haven't added any places yet",
            'subtitle' => $isRtl ? 'أضف مكانك الأول وابدأ باستقبال الحجوزات.' : 'Add your first place to start accepting bookings.',
        ])
    @else
        <p class="text-[14px] text-[#717171]" style="margin-bottom: 20px;">
            {{ $places->total() }} {{ $isRtl ? 'مكان' : 'places' }}
        </p>

        @php $start = $isRtl ? 'text-right' : 'text-left'; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" style="gap: 28px 20px;">
            @foreach($places as $place)
                @php
                    $rp = $reviewPill($place->review_status);
                    $sp = $statusPill($place->status);
                    $isDraft = $place->review_status === PlaceReviewStatus::Draft;
                    $isRejected = $place->review_status === PlaceReviewStatus::Rejected;
                    $city = $place->cityArea?->city;
                    $cover = $place->coverPhoto?->url;
                    // The card opens the same destination as its primary action:
                    // drafts resume in the wizard, everything else opens the editor.
                    $cardUrl = $isDraft
                        ? route('host.places.create', ['draft' => $place->id])
                        : route('host.places.edit', $place);
                @endphp
                <div class="flex flex-col">
                    {{-- Clickable cover + details → edit/continue (app-style card) --}}
                    <a href="{{ $cardUrl }}" class="block group">
                        <div class="relative overflow-hidden" style="aspect-ratio: 1 / 1; border-radius: 24px; background-color: #f4f4f4;">
                            @if($cover)
                                <img src="{{ $cover }}" alt="" class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-500" loading="lazy">
                            @else
                                <div class="w-full h-full flex items-center justify-center" style="font-size: 52px; opacity: 0.45;">{{ $place->type?->icon ?: '🏠' }}</div>
                            @endif
                            <div class="absolute inset-x-0 top-0 flex items-start justify-between" style="padding: 12px; gap: 8px;">
                                <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                                      style="padding: 4px 10px 4px 8px; border-radius: 999px; gap: 5px; background-color: {{ $sp['bg'] }}; box-shadow: 0 2px 8px rgba(0,0,0,0.25);">
                                    <span style="width: 5px; height: 5px; border-radius: 999px; background-color: {{ $sp['dot'] }};"></span>
                                    {{ $statusLabel($place->status) }}
                                </span>
                                <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                                      style="padding: 4px 10px 4px 8px; border-radius: 999px; gap: 5px; background-color: {{ $rp['bg'] }}; box-shadow: 0 2px 8px rgba(0,0,0,0.25);">
                                    <span style="width: 5px; height: 5px; border-radius: 999px; background-color: {{ $rp['dot'] }};"></span>
                                    {{ $reviewLabel($place->review_status) }}
                                </span>
                            </div>
                        </div>

                        <div style="padding-top: 12px;">
                            <h3 class="font-bold text-[#222] truncate {{ $start }} {{ $fa }}" style="font-size: 16px;">
                                <span style="margin-inline-end: 6px;">{{ $place->type?->icon ?: '🏠' }}</span>{{ $place->title ?: ($isRtl ? '— بدون عنوان —' : '— Untitled —') }}
                                @php
                                    // Classic places count as one unit; Arabic duals/plurals.
                                    $unitsN = max(1, (int) ($place->units_count ?? 0));
                                    $unitsLabel = $isRtl
                                        ? match (true) {
                                            $unitsN === 1 => 'وحدة واحدة',
                                            $unitsN === 2 => 'وحدتان',
                                            $unitsN <= 10 => $unitsN.' وحدات',
                                            default => $unitsN.' وحدة',
                                        }
                                        : ($unitsN === 1 ? '1 unit' : $unitsN.' units');
                                @endphp
                                <span class="inline-flex items-center font-bold text-white bg-[#222] tabular-nums {{ $fa }}" style="padding: 2px 10px; border-radius: 999px; font-size: 11px; vertical-align: 2px;">
                                    {{ $unitsLabel }}
                                </span>
                            </h3>
                            <p class="inline-flex items-center text-[14px] text-[#717171] truncate {{ $start }} {{ $fa }}" style="gap: 6px; margin-top: 3px;">
                                <span>{{ $city?->avatar ?: '📍' }}</span>
                                <span class="truncate">{{ $isRtl ? ($place->type?->name_ar.' · '.$city?->name_ar) : ($place->type?->name_en.' · '.$city?->name_en) }}</span>
                            </p>
                            <p class="text-[15px] font-bold text-[#222] tabular-nums {{ $start }}" dir="ltr" style="margin-top: 4px;">SR {{ number_format($place->price) }}</p>
                        </div>
                    </a>

                    @if($isRejected && $place->rejection_reason)
                        <div class="text-[12px] {{ $fa }}" style="margin-top: 10px; padding: 10px 12px; background-color: #fef2f2; border-radius: 12px;">
                            <span class="font-bold text-[#7a2018]">{{ $isRtl ? 'ملاحظات المراجع:' : 'Reviewer feedback:' }}</span>
                            <span class="text-[#7a2018]">{{ $place->rejection_reason }}</span>
                        </div>
                    @endif

                    {{-- Actions kept under the card --}}
                    <div class="flex items-center flex-wrap" style="margin-top: 12px; gap: 8px 16px;">
                        <a href="{{ route('places.show', $place) }}"
                           class="inline-flex items-center gap-1 text-[13px] font-semibold text-[#717171] hover:text-[#222] {{ $fa }}">
                            {{ $isRtl ? '👁 عرض' : '👁 View' }}
                        </a>
                        @if($isDraft)
                            <a href="{{ route('host.places.create', ['draft' => $place->id]) }}"
                               class="inline-flex items-center gap-1 text-[13px] font-bold text-[#F88379] hover:text-[#f56b60] {{ $fa }}">
                                {{ $isRtl ? '↩ متابعة' : 'Continue ↪' }}
                            </a>
                        @else
                            <a href="{{ route('host.places.edit', $place) }}"
                               class="inline-flex items-center gap-1 text-[13px] font-bold {{ $isRejected ? 'text-[#ef4444] hover:text-[#dc2626]' : 'text-[#222] hover:text-[#000]' }} {{ $fa }}"
                               @if($isRejected) title="{{ $place->rejection_reason }}" @endif>
                                {{ $isRtl ? '✎ تعديل' : '✎ Edit' }}
                            </a>
                        @endif
                        @if($place->review_status === PlaceReviewStatus::Approved)
                            <a href="{{ route('host.places.availability', $place) }}"
                               class="inline-flex items-center gap-1 text-[13px] font-bold text-[#F88379] hover:text-[#f56b60] {{ $fa }}">
                                {{ $isRtl ? '📅 التواريخ' : '📅 Dates' }}
                            </a>
                        @endif
                        <form method="POST" action="{{ route('host.places.destroy', $place) }}" class="inline m-0" style="margin-inline-start: auto;"
                              onsubmit="return confirm('{{ $isRtl ? 'حذف هذا المكان؟ يمكن استعادته لاحقاً.' : 'Delete this place? It can be restored later.' }}');">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 text-[13px] font-bold text-[#dc2626] hover:text-[#b91c1c] {{ $fa }}">
                                {{ $isRtl ? '🗑 حذف' : '🗑 Delete' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        @if($places->hasPages())<div style="margin-top: 24px;">{{ $places->links() }}</div>@endif
    @endif
@endsection
