@extends('layouts.admin')

@php
    use App\Enums\OccasionRequestStatus;
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';

    $types = config('occasions.types');
    $needs = config('occasions.needs');
    $venues = config('occasions.venue_statuses');

    // Stored keys → readable label. Unknown keys (the app shipped a new option
    // before the web caught up) fall back to the raw key rather than vanishing.
    $label = fn (array $catalogue, ?string $key): string => $key === null
        ? '—'
        : ($catalogue[$key][$isRtl ? 'ar' : 'en'] ?? $key);

    $filters = [
        ['key' => null, 'ar' => 'الكل', 'en' => 'All'],
        ['key' => 'new', 'ar' => 'جديد', 'en' => 'New'],
        ['key' => 'under_processing', 'ar' => 'قيد المعالجة', 'en' => 'Under processing'],
        ['key' => 'ongoing', 'ar' => 'جارٍ التنفيذ', 'en' => 'Ongoing'],
        ['key' => 'completed', 'ar' => 'مكتمل', 'en' => 'Completed'],
    ];
@endphp

@section('title', $isRtl ? 'طلبات المناسبات' : 'Occasion requests')
@section('heading', $isRtl ? 'طلبات المناسبات' : 'Occasion requests')

@section('main')
    {{-- Status filter + search --}}
    <div class="flex items-center justify-between flex-wrap" style="margin-bottom: 16px; gap: 12px;">
        <div class="flex items-center flex-wrap" style="gap: 8px;">
            @foreach($filters as $f)
                <a href="{{ route('admin.occasion-requests.index', array_filter(['status' => $f['key'], 'q' => $search])) }}"
                   class="text-[13px] font-semibold {{ $fa }}"
                   style="padding: 7px 14px; border-radius: 999px; {{ ($status === $f['key']) ? 'background-color:#222;color:#fff;' : 'background-color:#fff;color:#717171;border:1px solid #ebebeb;' }}">
                    {{ $isRtl ? $f['ar'] : $f['en'] }}
                </a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('admin.occasion-requests.index') }}" class="flex items-center bg-white" style="border-radius: 14px; padding: 4px;">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="text" name="q" value="{{ $search }}" placeholder="{{ $isRtl ? 'ابحث بالاسم أو الجوال' : 'Search name or phone' }}"
                   class="bg-transparent text-[14px] text-[#222] focus:outline-none {{ $fa }}" style="padding: 8px 14px;">
            <button type="submit" class="font-semibold text-white bg-[#222]" style="padding: 8px 16px; border-radius: 10px; font-size: 13px;">{{ $isRtl ? 'بحث' : 'Search' }}</button>
        </form>
    </div>

    <p class="text-[14px] text-[#717171]" style="margin-bottom: 12px;">{{ $requests->total() }} {{ $isRtl ? 'طلب' : 'requests' }}</p>

    @if($requests->isEmpty())
        <div class="bg-white text-center text-[#717171]" style="padding: 48px 20px; border-radius: 28px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
            {{ $isRtl ? 'لا توجد طلبات.' : 'No occasion requests.' }}
        </div>
    @else
        <div class="space-y-4">
            @foreach($requests as $req)
                @php
                    $d = $req->details ?? [];
                    $typeLabel = $label($types, $req->occasion_type);
                    // "Other" carries the guest's own words — show those instead.
                    if ($req->occasion_type === 'other' && ! empty($d['occasion_type_other'])) {
                        $typeLabel = $d['occasion_type_other'];
                    }
                    $needKeys = $d['needs'] ?? [];
                @endphp
                <div class="bg-white" style="padding: 18px 20px; border-radius: 20px; box-shadow: 0px 10px 30px 0px rgba(0,0,0,0.05);">
                    <div class="flex items-start justify-between flex-wrap" style="gap: 12px;">
                        <div style="flex: 1; min-width: 260px;">
                            <div class="flex items-center flex-wrap" style="gap: 10px;">
                                <span class="text-[16px] font-bold text-[#222] {{ $fa }}">
                                    {{ $types[$req->occasion_type]['emoji'] ?? '✨' }} {{ $typeLabel }}
                                </span>
                                <span class="text-[14px] font-semibold text-[#222] tabular-nums">
                                    {{ $d['guests'] ?? '—' }} {{ $isRtl ? 'ضيف' : 'guests' }}
                                </span>
                            </div>

                            {{-- Who to call. This is the whole point of the lead. --}}
                            <div class="text-[13px] text-[#222] {{ $fa }}" style="margin-top: 6px;">
                                {{ $req->user?->name ?: ($isRtl ? 'ضيف' : 'Guest') }}
                                @if($req->user?->phone)
                                    · <a href="tel:{{ $req->user->phone }}" class="font-semibold" style="color: #F88379;"><bdi dir="ltr">{{ $req->user->phone }}</bdi></a>
                                @endif
                            </div>
                            <div class="text-[12px] text-[#717171] {{ $fa }}" style="margin-top: 2px;">
                                {{ $req->created_at?->diffForHumans() }}
                            </div>

                            @if($needKeys !== [])
                                <div class="flex items-center flex-wrap" style="gap: 6px; margin-top: 10px;">
                                    @foreach($needKeys as $k)
                                        <span class="text-[12px] text-[#222] {{ $fa }}" style="padding: 4px 10px; border-radius: 999px; background-color: #F7F7F7;">
                                            {{ $needs[$k]['emoji'] ?? '' }} {{ $label($needs, $k) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if(! empty($d['needs_other']))
                                <p class="text-[13px] text-[#222] {{ $fa }}" style="margin-top: 8px;">
                                    <span class="text-[#717171]">{{ $isRtl ? 'شيء آخر:' : 'Something else:' }}</span> {{ $d['needs_other'] }}
                                </p>
                            @endif

                            @if(! empty($d['venue_status']))
                                <p class="text-[13px] {{ $fa }}" style="margin-top: 8px; color: {{ $d['venue_status'] === 'help' ? '#F88379' : '#717171' }};">
                                    {{ $label($venues, $d['venue_status']) }}
                                </p>
                            @endif

                            @if(! empty($d['notes']))
                                <p class="text-[14px] text-[#222] {{ $fa }}" style="margin-top: 10px; white-space: pre-line;">{{ $d['notes'] }}</p>
                            @endif
                        </div>

                        <span class="inline-flex items-center text-[11px] font-bold uppercase tracking-wider text-white {{ $fa }}"
                              style="padding: 4px 12px; border-radius: 999px; gap: 6px; background-color: {{ $req->status?->pill() }};">
                            {{ $req->status?->label($isRtl) }}
                        </span>
                    </div>

                    {{-- Move it along the pipeline. Tinted pills rather than bare
                         text — these are the only actions on the row, so they have
                         to read as buttons at a glance. --}}
                    <div class="flex items-center flex-wrap border-t border-[#ebebeb]" style="margin-top: 14px; padding-top: 14px; gap: 8px;">
                        <span class="text-[12px] text-[#717171] {{ $fa }}" style="margin-inline-end: 4px;">
                            {{ $isRtl ? 'نقل إلى' : 'Move to' }}
                        </span>
                        @foreach(OccasionRequestStatus::cases() as $case)
                            @if($req->status !== $case)
                                <form method="POST" action="{{ route('admin.occasion-requests.status', $req) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $case->value }}">
                                    <button type="submit"
                                            class="calm-press calm-round inline-flex items-center {{ $fa }}"
                                            style="gap: 6px; padding: 8px 15px; border-radius: 999px; font-size: 12.5px; font-weight: 700;
                                                   color: {{ $case->pill() }}; background-color: {{ $case->tint() }};
                                                   border: 1px solid {{ $case->pill() }}33;">
                                        <span style="width: 6px; height: 6px; border-radius: 999px; background-color: {{ $case->pill() }};"></span>
                                        {{ $case->label($isRtl) }}
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @if($requests->hasPages())<div style="margin-top: 24px;">{{ $requests->links() }}</div>@endif
    @endif
@endsection
