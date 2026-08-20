# Landing Page Navbar Redesign

## Context

Landing page (`resources/views/livewire/landing-page/landing-page.blade.php`) is a QR-order menu page opened per-table, used across multiple tenant restaurants (universal/multi-tenant POS product — no single restaurant brand to foreground). Current navbar shows a "POS System" brand mark/text, a table-number chip, and a cart icon+badge, and morphs into a floating blurred pill on scroll. User feedback: navbar feels off — brand text isn't relevant to the customer, and the pill/blur scroll effect adds complexity without payoff.

Explored 4 structural directions via visual mockups (mobile-first, since this is opened on phones at the table): clean split-line, elevated two-row header, refined floating pill, and app-style header with a persistent bottom cart bar (GoFood/GrabFood pattern). User selected the app-style direction (option D).

## Decisions

- **Top navbar**: static, sticky, no scroll-triggered pill/blur transform. Content is only the table name (e.g. "Meja 04"), left-aligned. No brand logo, no brand text, no cart icon/badge, no back arrow.
- **Cart access**: moved off the top navbar entirely. A new bottom cart bar is the sole entry point to the cart drawer.
- **Bottom cart bar**: fixed to the bottom of the viewport, dark background, shows running total + item count and a "Lihat Keranjang" button that opens the existing cart slide-over (`cartOpen = true`). Rendered only when `count($cart) > 0` — matches the existing pattern used by the cart drawer's own empty-state check.
- **Cart slide-over, product modal, payment modal**: unchanged. Only how the drawer is triggered changes.

## Component changes

### Blade (`landing-page.blade.php`)
- Simplify `<nav class="navbar">`: drop `brand-logo`/`brand-mark`/`brand-text`, drop `cart-toggle-btn` (including badge), keep only the table-name chip.
- Remove the `scrolled` Alpine state and the `@scroll.window="scrolled = ..."` listener on the root `x-data`/root div — nothing else reads `scrolled` once the pill effect is gone.
- Add a bottom cart bar block (new markup, e.g. `.bottom-cart-bar`), guarded by `@if (count($cart) > 0)`, placed as a sibling near the cart slide-over markup. Shows `$this->total` and `count($cart)`, with a button/link doing `@click="cartOpen = true"`.

### CSS (`resources/css/landing-page.css`)
- Remove `.navbar.scrolled` and `.navbar.scrolled::before` rules (pill/blur scroll effect) and their responsive overrides.
- Remove `.brand-logo`, `.brand-mark`, `.brand-text` rules (including the `.brand-text { display: none }` mobile override).
- Repurpose/reuse the existing `.cart-toggle-btn` visual style for the new bottom-bar button rather than inventing a new visual language; add `.bottom-cart-bar` layout rules (fixed position, safe-area-inset-bottom padding for notched phones, dark background using existing `--color-primary` token).
- Add fixed `padding-bottom` to `.main-container` so the last row of the product grid isn't hidden behind the bottom bar. Simplest option: a constant padding sized for the bar's height, applied unconditionally (avoids JS-measured dynamic heights for a bar that's a fixed height anyway).
- `--navbar-height` token and the sticky `search-filter-section` offset that depends on it stay as-is — the navbar's height doesn't materially change, just its content.

### JS (`resources/js/landing-page.js`)
- No changes expected; scroll-pill behavior lived in Alpine directives in the Blade file, not in this JS file. Confirm during implementation that nothing else references `.navbar.scrolled` or the removed classes.

## Out of scope

Hero banner, product grid/cards, search/filter bar, footer, FAQ/feature-strip (already disabled), cart slide-over internals, product/payment modals — none of these are touched by this change.
