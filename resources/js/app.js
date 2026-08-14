import imageCompression from 'browser-image-compression';
import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';
import Sortable from 'sortablejs';

window.Alpine = Alpine;
// x-sort: reactive drag-and-drop reordering that plays nicely with x-for
// (used on the merged admin attributes page).
Alpine.plugin(sort);
// ── Open-in-new-tab (desktop only) ──────────────────────────────────────────
// On the web view a search or a place opens in its own tab so the browse/
// results page you came from stays put. Mobile + tablet keep app-style
// in-place navigation, where extra tabs are a nuisance.
window.calmIsDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

window.calmOpen = (href) => {
    if (!href) return;
    if (window.calmIsDesktop()) window.open(href, '_blank', 'noopener');
    else window.location.href = href;
};

// Anchors opt in with data-newtab. Delegated so it also covers cards Alpine
// renders later (search results, favorites). Modifier/middle clicks are left
// to the browser.
document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (!window.calmIsDesktop()) return;
    const link = e.target.closest('a[data-newtab]');
    if (!link || !link.href) return;
    e.preventDefault();
    window.open(link.href, '_blank', 'noopener');
});

// Mouse drag-to-scroll for card photo carousels (touch scrolls natively).
// Suppresses the card link's click after a real drag and blocks native
// image dragging so the gesture always pans the carousel.
window.calmDragScroll = (el) => {
    let down = false;
    let moved = false;
    let startX = 0;
    let startLeft = 0;
    const snapValue = el.style.scrollSnapType; // restore the ORIGINAL inline value

    el.addEventListener('dragstart', (e) => e.preventDefault());

    el.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse') return;
        down = true;
        moved = false;
        startX = e.clientX;
        startLeft = el.scrollLeft;
        // Keep receiving moves even when the cursor exits the small card.
        try { el.setPointerCapture(e.pointerId); } catch (err) { /* no-op */ }
        // Mandatory snap re-snaps every partial position — pause it while dragging.
        el.style.scrollSnapType = 'none';
    });

    el.addEventListener('pointermove', (e) => {
        if (!down) return;
        const dx = e.clientX - startX;
        if (Math.abs(dx) > 5) moved = true;
        el.scrollLeft = startLeft - dx;
    });

    const settle = () => {
        if (!down) return;
        down = false;
        const w = el.clientWidth || 1;
        // Page one slide in the drag direction once past a 20% pull —
        // rounding alone made short drags spring back.
        const startIdx = Math.round(Math.abs(startLeft) / w);
        const delta = el.scrollLeft - startLeft;
        // The carousel's own direction — card carousels are dir=ltr even on RTL pages.
        const rtl = getComputedStyle(el).direction === 'rtl';
        const forward = rtl ? delta < 0 : delta > 0;
        const stepped = Math.abs(delta) > w * 0.2 ? (forward ? 1 : -1) : 0;
        const maxIdx = Math.max(0, Math.round(el.scrollWidth / w) - 1);
        const idx = Math.min(maxIdx, Math.max(0, startIdx + stepped));
        const sign = rtl ? -1 : 1;
        el.scrollTo({ left: sign * idx * w, behavior: 'smooth' });
        // Restore snapping only AFTER the smooth settle finishes — re-enabling
        // mandatory snap mid-flight cancels the animation and yanks back.
        const restore = () => {
            el.style.scrollSnapType = snapValue;
            el.removeEventListener('scrollend', restore);
        };
        el.addEventListener('scrollend', restore);
        setTimeout(restore, 800);
    };
    el.addEventListener('pointerup', settle);
    el.addEventListener('pointercancel', settle);

    // A drag must not activate the card link.
    el.addEventListener('click', (e) => {
        if (moved) {
            e.preventDefault();
            e.stopPropagation();
            moved = false;
        }
    }, true);
};

Alpine.start();

// Raw SortableJS still exposed for any non-Alpine page that needs it.
window.Sortable = Sortable;

// ── Client-side image compression for the host photo wizard ──────────────────
// The wizard runs as an inline <script> (not through this bundle), so it reads
// these helpers off `window`. browser-image-compression is small → eager.
// heic2any bundles libheif WASM (heavy) → code-split + loaded on demand only
// when an iPhone HEIC/HEIF file is actually picked.
window.imageCompression = imageCompression;
window.loadHeic2any = () => import('heic2any').then((m) => m.default);
// Modern HEIC decoder (libheif 1.19) — handles the iOS 17/18 HDR HEICs the
// older heic2any build chokes on. heic2any stays as the fallback.
window.loadHeicTo = () => import('heic-to').then((m) => m.heicTo);
// Lottie renders the booking status animations (loader→success, pending);
// lazy so only the pages that play animations pay for it.
window.loadLottie = () => import('lottie-web').then((m) => m.default);

/**
 * Global submit-loading handler.
 *
 * When any non-GET <form> is submitted, every <button type="submit"> (and
 * <input type="submit">) inside it is disabled and gets its label swapped
 * for a spinning indicator. Stays in that state until the page navigates
 * away (which a normal Blade form submit always does), so the user can't
 * double-click → double-submit.
 *
 * Opt-out per-button with `data-no-loading` (e.g. the locale switcher,
 * which is fire-and-forget and re-renders the page anyway).
 *
 * Why this lives in one place: every login/OTP/admin CRUD/place-review/
 * wizard-final-submit form goes through here, so we don't have to wire
 * Alpine state into each individual page.
 */
const CALM_SPINNER_SVG =
    '<svg class="calm-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">'
    + '<circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>'
    + '<path d="M22 12a10 10 0 0 1-10 10"/>'
    + '</svg>';

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.method && form.method.toUpperCase() === 'GET') return;

    const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    buttons.forEach((btn) => {
        if (btn.dataset.noLoading !== undefined) return;
        if (btn.dataset.loadingApplied) return;
        btn.dataset.loadingApplied = '1';

        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');

        // For <input type="submit"> there's no inner DOM to splice into —
        // just leave the disabled state to convey loading.
        if (btn.tagName !== 'BUTTON') return;

        // Prepend a zero-width slot containing the SVG spinner. The CSS
        // transitions on .calm-spinner-slot animate it open (width + margin
        // + opacity) so the spinner glides in next to the existing button
        // text — the label stays visible the whole time.
        const slot = document.createElement('span');
        slot.className = 'calm-spinner-slot';
        slot.setAttribute('aria-hidden', 'true');
        slot.innerHTML = CALM_SPINNER_SVG;
        btn.insertBefore(slot, btn.firstChild);

        // Two requestAnimationFrames so the initial 0-width state is painted
        // before the transition starts — without this, browsers can collapse
        // the change and skip the animation.
        requestAnimationFrame(() => requestAnimationFrame(() => {
            slot.classList.add('is-active');
        }));
    });
}, true);
