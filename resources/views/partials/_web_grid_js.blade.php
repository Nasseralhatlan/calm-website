{{-- Shared JS for the fetch-rendered place grids (search results, favorites).
     Include ONCE per page, after the markup. Components spread it:
     x-data="{ ...calmGrid('/api/favorites'), ... }" and render cards with
     partials/_web_card_template inside a `<template x-for="p in items">`. --}}
<script>
    window.CALM_WEB = {
        locale: @js(app()->getLocale()),
        signedIn: @js((bool) auth('api')->user()),
        loginUrl: @js(route('login', ['next' => request()->getRequestUri()])),
    };

    function calmGrid(endpoint) {
        return {
            items: [],
            page: 1,
            hasMore: false,
            total: 0,
            loadingGrid: false,
            gridError: false,

            async fetchPage(page, params = {}) {
                this.loadingGrid = true;
                this.gridError = false;
                try {
                    const qs = new URLSearchParams({ ...params, page: String(page) });
                    const res = await fetch(`${endpoint}?${qs}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) throw new Error(String(res.status));
                    const data = (await res.json()).data || {};
                    const items = data.items || [];
                    this.items = page === 1 ? items : this.items.concat(items);
                    this.page = page;
                    this.hasMore = !!(data.pagination && data.pagination.has_more);
                    this.total = (data.pagination && data.pagination.total !== undefined)
                        ? data.pagination.total
                        : this.items.length;
                } catch (e) {
                    this.gridError = true;
                } finally {
                    this.loadingGrid = false;
                }
            },

            loadMore(params = {}) {
                this.fetchPage(this.page + 1, params);
            },

            cardTitle(p) {
                return (CALM_WEB.locale === 'ar'
                    ? (p.title_ar || p.title_en)
                    : (p.title_en || p.title_ar)) || p.title || '';
            },

            cardMeta(p) {
                const n = (o) => o
                    ? ((CALM_WEB.locale === 'ar' ? o.name_ar : o.name_en) || o.name_ar || o.name_en)
                    : null;
                return [n(p.type), n(p.city), n(p.city_area)].filter(Boolean).join(' · ');
            },

            fmtPrice(v) {
                return Number(v || 0).toLocaleString('en-US');
            },

            toggleLike(p) {
                if (!CALM_WEB.signedIn) {
                    window.location.href = CALM_WEB.loginUrl;
                    return;
                }
                p.is_liked = !p.is_liked;
                fetch(`/api/places/${p.id}/like`, {
                    method: p.is_liked ? 'POST' : 'DELETE',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
            },
        };
    }
</script>
