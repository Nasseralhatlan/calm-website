/**
 * Behavioural event tracking — see docs/feature-analytics.md.
 *
 * Every call site goes through this module, never through `posthog` directly,
 * so the vendor stays swappable and every event is forced through the same
 * `type:target` naming. With no VITE_POSTHOG_KEY set (local dev, CI) every
 * function here is a silent no-op — tracking must never be a dependency.
 *
 * Rules enforced here rather than at call sites:
 *  - names are always `type:target` (view | click | scroll | end)
 *  - null/undefined properties are stripped, so events stay readable
 *  - scroll/end fire at most once per target per session
 */
import posthog from 'posthog-js';

const KEY = import.meta.env.VITE_POSTHOG_KEY;
const HOST = import.meta.env.VITE_POSTHOG_HOST || 'https://eu.i.posthog.com';

let ready = false;

/** Native app vs phone-browser vs desktop — `$device_type` conflates the first two. */
function platform() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) ? 'web_mobile' : 'web_desktop';
}

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
    posthog.register({ platform: platform() });

    // Exposed for debugging from the browser console (posthog.capture(...),
    // posthog.get_session_replay_url()). Not used by app code — call sites go
    // through track() so the vendor stays swappable.
    window.posthog = posthog;
}

/** Strip empties so a property is either meaningful or absent. */
function clean(props) {
    return Object.fromEntries(
        Object.entries(props).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    );
}

/**
 * @param {'view'|'click'|'scroll'|'end'} type
 * @param {string} target
 */
export function track(type, target, props = {}) {
    if (!ready) return;
    posthog.capture(`${type}:${target}`, clean(props));
}

/**
 * Once per target per session — scroll fires continuously, especially on the
 * photo carousels, and untamed it would be most of our volume.
 */
export function trackOnce(type, target, props = {}) {
    if (!ready) return;
    const key = `ph:${type}:${target}:${props.place_id ?? ''}`;
    try {
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
    } catch (e) {
        // Storage blocked (private mode): fire anyway rather than lose the event.
    }
    track(type, target, props);
}

export function identify(userId) {
    if (ready && userId) posthog.identify(userId);
}

export function resetIdentity() {
    if (ready) posthog.reset();
}

/**
 * Where a listing view came from. Distinguishes someone who searched from
 * someone a friend sent a WhatsApp link — different acquisition stories.
 */
export function referrerSource() {
    const ref = document.referrer;
    if (!ref) return 'direct';
    try {
        const url = new URL(ref);
        if (url.origin !== window.location.origin) return 'external';
        return url.searchParams.has('city') ? 'results' : 'home';
    } catch (e) {
        return 'direct';
    }
}
