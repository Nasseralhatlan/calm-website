import imageCompression from 'browser-image-compression';
import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';
import Sortable from 'sortablejs';

window.Alpine = Alpine;
// x-sort: reactive drag-and-drop reordering that plays nicely with x-for
// (used on the merged admin attributes page).
Alpine.plugin(sort);
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
