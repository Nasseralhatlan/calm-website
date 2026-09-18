/**
 * Behavioural event tracking — see docs/feature-analytics.md.
 *
 * Every call site goes through this module, never through `posthog` directly,
 * so the vendor stays swappable and every event name is built the same way.
 * With no VITE_POSTHOG_KEY set (local dev, CI) every function here is a silent
 * no-op — tracking must never be a dependency.
 *
 * Rules enforced here rather than at call sites:
 *  - names are always `group:target`, matching mobile exactly
 *  - arrays are comma-joined, because mobile sends them that way and PostHog
 *    would otherwise split the funnel on property type
 *  - null/undefined/empty properties are stripped, so events stay readable
 *  - scroll/end fire at most once per target (+ its qualifiers) per session
 */
import posthog from 'posthog-js';

const KEY = import.meta.env.VITE_POSTHOG_KEY;
const HOST = import.meta.env.VITE_POSTHOG_HOST || 'https://eu.i.posthog.com';

let ready = false;

export function initAnalytics() {
    if (!KEY || ready) return;

    posthog.init(KEY, {
        api_host: HOST,
        // We send a deliberate, named set of events — autocapture would bury
        // them in noise and burn the quota.
        autocapture: false,
        capture_pageview: false,
        capture_pageleave: true,
        // Anonymous events still record; we just don't mint a person profile
        // for every bounce.
        person_profiles: 'identified_only',
        session_recording: {
            // Phone numbers, OTP codes and host IBANs all live in inputs.
            maskAllInputs: true,
        },
    });

    ready = true;
    // The ONLY property that differs from mobile (app_ios / app_android).
    // Device detail is already in $device_type; this splits platform, not screen.
    posthog.register({ platform: 'web' });

    // Exposed for debugging from the browser console (posthog.capture(...),
    // posthog.get_session_replay_url()). Not used by app code — call sites go
    // through track() so the vendor stays swappable.
    window.posthog = posthog;
}

/**
 * Strip empties, and flatten arrays to comma-joined strings so web and mobile
 * agree on the shape (`place_type_ids: "a,b"`).
 */
function clean(props) {
    const out = {};

    for (const [k, v] of Object.entries(props)) {
        if (v === null || v === undefined || v === '') continue;

        if (Array.isArray(v)) {
            const joined = v.filter((x) => x !== null && x !== undefined && x !== '').join(',');
            if (joined !== '') out[k] = joined;
            continue;
        }

        out[k] = v;
    }

    return out;
}

/**
 * @param {string} group  view | click | scroll | end | search | filter | login | occasion | photo | results | checkout
 * @param {string} target
 */
export function track(group, target, props = {}) {
    if (!ready) return;
    posthog.capture(`${group}:${target}`, clean(props));
}

/**
 * Once per target per session. Scroll fires continuously — especially on the
 * photo carousels — and untamed it would be most of our volume. Qualifying
 * properties (which listing, which carousel, which direction) are part of the
 * key, so two different carousels each report once.
 */
export function trackOnce(group, target, props = {}) {
    if (!ready) return;

    const p = clean(props);
    const qualifier = [p.place_id, p.surface, p.direction].filter(Boolean).join('|');
    const key = `ph:${group}:${target}:${qualifier}`;

    try {
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
    } catch (e) {
        // Storage blocked (private mode): fire anyway rather than lose the event.
    }

    track(group, target, p);
}

/**
 * distinct_id MUST be the Calm user UUID — it's what joins this session to the
 * same person in the app and to backend-sent events.
 */
export function identify(userId) {
    if (ready && userId) posthog.identify(userId);
}

export function resetIdentity() {
    if (ready) posthog.reset();
}

/**
 * Where a listing view came from. Distinguishes someone who searched from
 * someone a friend sent a WhatsApp link — different acquisition stories.
 * Constrained to the three values mobile sends.
 *
 * @returns {'home'|'results'|'external'}
 */
export function referrerSource() {
    const ref = document.referrer;
    if (!ref) return 'external'; // opened cold: a shared link, a bookmark, search

    try {
        const url = new URL(ref);
        if (url.origin !== window.location.origin) return 'external';
        return url.searchParams.has('city') ? 'results' : 'home';
    } catch (e) {
        return 'external';
    }
}

/** Guest-count bucket for the occasions funnel. Mobile uses these exact labels. */
export function guestsRange(n) {
    const g = parseInt(n, 10) || 0;
    if (g <= 10) return '1-10';
    if (g <= 25) return '11-25';
    if (g <= 50) return '26-50';
    if (g <= 100) return '51-100';
    if (g <= 200) return '101-200';
    if (g <= 500) return '201-500';
    return '500+';
}
