@extends('layouts.app')

@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $fa = $isRtl ? 'font-arabic' : '';
    $me = auth('api')->user();
@endphp

@section('title', ($isRtl ? 'حجوزاتي' : 'Bookings').' — Calm')

@section('body')
{{-- Tab view — fires for guests too; the visit happened either way. --}}
<script>document.addEventListener('DOMContentLoaded', () => window.calmTrack?.('view', 'bookings'));</script>
<div class="min-h-screen" style="background-color: #FBFBFB;" @if($me) x-data="calmTrips()" x-init="load(1); loadOccasions()" @endif>

    {{-- Fixed (sticky) page header --}}
    <header class="sticky top-0 z-30" style="background-color: rgba(255,255,255,0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
        <div class="mx-auto w-full" style="max-width: 720px; padding: 18px 20px;">
            <h1 class="font-bold text-black {{ $fa }}" style="font-size: 20px; line-height: 1.3;">{{ $isRtl ? 'حـجوزاتـي' : 'My bookings' }}</h1>
        </div>
    </header>

    <main class="mx-auto w-full" style="max-width: 720px; padding: 6px 20px 36px;">

        @if(! $me)
            @include('partials._web_login_prompt', [
                'promptTitle' => $isRtl ? 'سجّل دخولك' : 'Sign in',
                'promptSubtitle' => $isRtl ? 'سجّل دخولك لعرض حجوزاتك ومتابعتها.' : 'Sign in to see and track your bookings.',
            ])
        @else
            {{-- Occasion requests — tracked here because the wizard's confirmation
                 promises «يمكنك متابعة طلبك في صفحة الحجوزات». Same endpoint the
                 app uses. Additive: a failure here never blocks the bookings. --}}
            <div x-show="occasions.length > 0" x-cloak style="margin-top: 20px;">
                <h2 class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-bottom: 12px;">
                    {{ $isRtl ? 'طلبات المناسبات' : 'Occasion requests' }}
                </h2>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <template x-for="o in occasions" :key="o.id">
                        <div class="flex items-center bg-white" style="border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 50px rgba(0,0,0,0.05); padding: 14px; gap: 14px;">
                            <span class="shrink-0 flex items-center justify-center"
                                  style="width: 58px; height: 58px; border-radius: 18px; corner-shape: squircle; -webkit-corner-shape: squircle; background: linear-gradient(140deg, #FFF1EF 0%, #FFD9D3 100%); font-size: 26px; line-height: 1;"
                                  x-text="occEmoji(o)"></span>
                            <span class="flex-1 min-w-0 flex flex-col" style="gap: 4px;">
                                <span class="flex items-start justify-between" style="gap: 8px;">
                                    <span class="font-bold text-black truncate {{ $fa }}" style="font-size: 16px; line-height: 21px;" x-text="occTitle(o)"></span>
                                    <span class="shrink-0 font-bold calm-round {{ $fa }}" style="font-size: 11px; padding: 4px 12px; border-radius: 999px;"
                                          :style="occStatusStyle(o.status)" x-text="o.status_label"></span>
                                </span>
                                <span class="truncate {{ $fa }}" style="font-size: 13px; color: #AAAAAA;" x-text="occMeta(o)"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <h2 x-show="occasions.length > 0 && items.length > 0" x-cloak
                class="font-bold text-black {{ $fa }}" style="font-size: 15px; margin-top: 26px;">
                {{ $isRtl ? 'الحجوزات' : 'Bookings' }}
            </h2>

            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 14px;">
                <template x-for="b in items" :key="b.id">
                    <a :href="`/bookings/${b.id}`"
                       class="calm-press-card flex bg-white" style="border-radius: 24px; corner-shape: squircle; -webkit-corner-shape: squircle; box-shadow: 0 0 50px rgba(0,0,0,0.05); padding: 14px; gap: 14px;">
                        <span class="shrink-0 overflow-hidden" style="width: 96px; height: 96px; border-radius: 18px; corner-shape: squircle; -webkit-corner-shape: squircle; background-color: #F3F4F6;">
                            <template x-if="b.place && b.place.cover_photo_url">
                                <img :src="b.place.cover_photo_url" alt="" loading="lazy" class="w-full h-full object-cover">
                            </template>
                        </span>
                        <span class="flex-1 min-w-0 flex flex-col" style="gap: 3px;">
                            <span class="flex items-start justify-between" style="gap: 8px;">
                                <span class="font-bold text-black truncate {{ $fa }}" style="font-size: 16px; line-height: 21px;" x-text="tripTitle(b)"></span>
                                <span class="shrink-0 font-bold {{ $fa }}" style="font-size: 11px; padding: 4px 12px; border-radius: 999px;"
                                      :style="statusStyle(b.status)" x-text="statusLabel(b.status)"></span>
                            </span>
                            <span class="truncate {{ $fa }}" style="font-size: 13px; color: #AAAAAA;" x-text="tripMeta(b)"></span>
                            <span class="flex items-end justify-between" style="gap: 8px; margin-top: auto;">
                                <span class="{{ $fa }}" style="font-size: 13px; color: #AAAAAA;" x-text="tripDate(b)"></span>
                                <span class="font-bold text-black tabular-nums"><bdi dir="ltr" x-text="fmt(b.pricing ? b.pricing.total : 0) + ' SR'"></bdi></span>
                            </span>
                        </span>
                    </a>
                </template>
            </div>

            {{-- Loading skeletons --}}
            <div x-show="loading && items.length === 0" style="margin-top: 20px; display: flex; flex-direction: column; gap: 14px;">
                @for($sk = 0; $sk < 4; $sk++)
                    <div class="calm-skeleton" style="height: 124px; border-radius: 24px;"></div>
                @endfor
            </div>

            {{-- Empty --}}
            <div x-show="!loading && items.length === 0 && occasions.length === 0 && !error" x-cloak class="text-center" style="padding: 70px 0;">
                <div style="font-size: 40px; margin-bottom: 12px;">🗓️</div>
                <p class="font-bold text-black {{ $fa }}" style="font-size: 17px;">{{ $isRtl ? 'لا توجد حجوزات بعد' : 'No bookings yet' }}</p>
                <a href="{{ route('landing') }}"
                   class="calm-press inline-flex items-center font-bold text-white {{ $fa }}"
                   style="margin-top: 18px; padding: 13px 28px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; font-size: 14px; background-color: #000;">
                    {{ $isRtl ? 'استكشف الأماكن' : 'Explore places' }}
                </a>
            </div>
            <div x-show="error" x-cloak class="text-center text-[#DC2626] {{ $fa }}" style="padding: 50px 0;">
                {{ $isRtl ? 'حدث خطأ — أعد المحاولة.' : 'Something went wrong — please retry.' }}
            </div>

            {{-- Load more --}}
            <div class="text-center" style="margin-top: 28px;" x-show="hasMore && !error" x-cloak>
                <button type="button" @click="load(page + 1)" :disabled="loading"
                        class="calm-press inline-flex items-center font-bold text-white hover:opacity-90 disabled:opacity-60 transition-opacity {{ $fa }}"
                        style="padding: 13px 34px; border-radius: 16px; corner-shape: squircle; -webkit-corner-shape: squircle; font-size: 14px; background-color: #000;">
                    {{ $isRtl ? 'عرض المزيد' : 'Load more' }}
                </button>
            </div>
        @endif
    </main>

    @include('partials._web_floating_nav')
    @include('partials._web_footer')
</div>

@if($me)
    <script>
        function calmTrips() {
            const AR = @js($isRtl);

            // Occasion catalogue, so a stored key renders as a label + emoji.
            // Same config the wizard renders from; the app mirrors it.
            const TYPES = @js(config('occasions.types'));

            return {
                items: [], page: 1, hasMore: false, loading: false, error: false,
                occasions: [],

                async loadOccasions() {
                    try {
                        const res = await fetch('/api/occasion-requests', {
                            headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                        });
                        if (!res.ok) return;
                        this.occasions = ((await res.json()).data || {}).items || [];
                        // Only when there's a list to see — an empty section
                        // isn't a view of it.
                        if (this.occasions.length) window.calmTrack?.('view', 'occasions_list');
                    } catch (e) {
                        // Bookings are this page's job — a failed occasions fetch
                        // stays silent rather than showing the page-level error.
                    }
                },

                occEmoji(o) { return (TYPES[o.occasion_type] || {}).emoji || '🎉'; },

                occTitle(o) {
                    // «أخرى» carries the guest's own words — show those.
                    const other = (o.details || {}).occasion_type_other;
                    if (o.occasion_type === 'other' && other) return other;
                    const t = TYPES[o.occasion_type];
                    return t ? (AR ? t.ar : t.en) : o.occasion_type;
                },

                occMeta(o) {
                    const d = o.details || {};
                    const parts = [];
                    if (d.guests) parts.push(d.guests + (AR ? ' ضيف' : ' guests'));
                    if (o.created_at) {
                        parts.push(new Intl.DateTimeFormat(AR ? 'ar-SA-u-ca-gregory' : 'en', { day: 'numeric', month: 'long' })
                            .format(new Date(o.created_at)));
                    }
                    return parts.join(' · ');
                },

                occStatusStyle(s) {
                    const M = {
                        new: 'background-color: #FEF4E6; color: #F59E0B;',
                        under_processing: 'background-color: #EAF1FE; color: #3B82F6;',
                        ongoing: 'background-color: #F2ECFE; color: #8B5CF6;',
                        completed: 'background-color: #E8F6EC; color: #16A34A;',
                    };
                    return M[s] || M.new;
                },

                async load(page) {
                    this.loading = true; this.error = false;
                    try {
                        const res = await fetch(`/api/bookings?page=${page}&per_page=20`, {
                            headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                        });
                        if (!res.ok) throw new Error(String(res.status));
                        const data = (await res.json()).data || {};
                        // The app hides expired holds from the list.
                        const items = (data.items || []).filter((b) => b.status !== 'expired');
                        this.items = page === 1 ? items : this.items.concat(items);
                        this.page = page;
                        this.hasMore = !!(data.pagination && data.pagination.has_more);
                    } catch (e) {
                        this.error = true;
                    } finally {
                        this.loading = false;
                    }
                },

                tripTitle(b) {
                    const p = b.place || {};
                    return (AR ? (p.title_ar || p.title_en) : (p.title_en || p.title_ar)) || p.title || '';
                },
                tripMeta(b) {
                    const p = b.place || {};
                    const n = (o) => o ? ((AR ? o.name_ar : o.name_en) || o.name_ar || o.name_en) : null;
                    return [n(p.city), n(p.city_area)].filter(Boolean).join(' · ');
                },
                tripDate(b) {
                    if (!b.start_date) return '';
                    return new Intl.DateTimeFormat(AR ? 'ar-SA-u-ca-gregory' : 'en', { day: 'numeric', month: 'long' })
                        .format(new Date(b.start_date + 'T00:00:00'));
                },
                fmt(v) { return Number(v || 0).toLocaleString('en-US'); },
                statusLabel(s) {
                    const L = AR
                        ? { pending_payment: 'بانتظار الدفع', confirmed: 'مؤكد', completed: 'مكتمل', canceled_by_guest: 'ملغى', canceled_by_host: 'ملغى', canceled_by_admin: 'ملغى', expired: 'منتهي' }
                        : { pending_payment: 'Pending', confirmed: 'Confirmed', completed: 'Completed', canceled_by_guest: 'Cancelled', canceled_by_host: 'Cancelled', canceled_by_admin: 'Cancelled', expired: 'Expired' };
                    return L[s] || s;
                },
                statusStyle(s) {
                    if (s === 'completed') return 'background-color: #E7F6EC; color: #16A34A;';
                    if (s === 'confirmed') return 'background-color: #E8F0FE; color: #2563EB;';
                    if (s === 'pending_payment') return 'background-color: #FEF3E2; color: #F59E0B;';
                    return 'background-color: #FDECEC; color: #DC2626;';
                },
            };
        }
    </script>
@endif
<style>[x-cloak] { display: none !important; }</style>
@endsection
