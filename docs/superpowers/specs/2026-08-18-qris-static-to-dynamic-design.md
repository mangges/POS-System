# QRIS Static-to-Dynamic + Payment Method Settings

## Problem

Payment method (cash/qris/transfer) is hardcoded in POS cashier and self-order
landing page. QRIS today is just a label — no real QR is generated, customer
pays via a separate physical static QRIS poster or EDC machine, cashier
confirms manually. There is no way to turn admin's own static QRIS into a
dynamic one with the exact order amount baked in, and no way to toggle which
payment methods are even offered.

Reference for the conversion algorithm: https://github.com/verssache/qris-dinamis
(EMVCo TLV parse → static→dynamic tag rewrite → CRC16 recompute). Ported to
PHP directly; the JS/React app itself is not used.

## Scope

- New admin settings page: enable/disable each payment method, and for QRIS
  choose EDC (status quo notice) vs Static→Dynamic (system-generated QR with
  exact amount).
- QRIS static→dynamic conversion service (pure PHP, no new dependency).
- Wire dynamic QR into both POS cashier payment modal and self-order landing
  page payment modal.
- `transfer` payment method is relabeled "Kartu/Debit" in the settings UI —
  no new payment method key, no enum/migration change to `payments.payment_method`.

Out of scope:
- Payment gateway integration / webhook / auto payment verification. Cashier
  still manually confirms payment received and finalizes the order, exactly
  as today — dynamic QRIS only replaces "customer types the amount in
  manually" with "amount is pre-filled in the QR".
- Adding a 4th payment method or renaming `transfer` in the database.

## Data model

New table `payment_method_settings`, one row per method, seeded for
`cash`, `qris`, `transfer`:

| column | type | notes |
|---|---|---|
| `method` | string, unique | `cash` \| `qris` \| `transfer` |
| `is_active` | boolean, default true | hides the method from payment modals when false |
| `qris_mode` | string, nullable | `edc` \| `dynamic`, only meaningful when `method = qris` |
| `qris_static_string` | text, nullable | admin's static QRIS payload, only used when `qris_mode = dynamic` |

`App\Models\PaymentMethodSetting` — plain Eloquent model, no extra
abstraction. Helper: `static::isActive(string $method): bool` and
`static::forMethod(string $method): ?self` used by the Livewire components.

## QRIS conversion service

`App\Services\Qris\QrisConverter` — static, framework-agnostic, ported from
the EMVCo TLV logic in qris-dinamis:

```php
QrisConverter::toDynamic(string $staticQris, int $amount): string
```

Steps:
1. Parse TLV (`[2-digit tag][2-digit length][value]`, repeat).
2. Find tag `01` (Point of Initiation Method), rewrite value `11` → `12`.
3. Insert/replace tag `54` (Transaction Amount) with `$amount`, positioned
   right after tag `53` (Currency) per EMVCo ordering.
4. Recompute tag `63` (CRC16-CCITT/XMODEM over everything up to and
   including `6304`) and append.

Throws `InvalidArgumentException` if the input doesn't parse as valid TLV or
tag `63` CRC doesn't match the input (defends against a garbled static string
being pasted into settings).

QR image: rendered with the already-installed `simplesoftwareio/simple-qrcode`
package, inline SVG, generated on the fly per order total — never persisted
(amount changes every order, no reason to store it).

## Admin page

`App\Filament\CustomPages\PaymentMethodSettings` (matches the existing
`App\Filament\CustomPages` convention alongside `PosCashier`/`PosReceipt`),
nav item "Payment Method".

One Schema form, three sections bound to a flat `$data` array
(`cash_active`, `qris_active`, `qris_mode`, `qris_static_string`,
`transfer_active`):

- **Cash**: Toggle "Aktif".
- **QRIS**: Toggle "Aktif" + Radio "EDC" / "Static → Dynamic" (visible when
  active) + Textarea "Static QRIS String" (visible when mode = dynamic).
- **Kartu/Debit**: Toggle "Aktif".

`save()` upserts the 3 `payment_method_settings` rows from `$data`.

## Payment modal changes

Both `App\Livewire\Pos\Cashier` and `App\Livewire\LandingPage\LandingPage`
get:

- `getActiveMethodsProperty()` — active `payment_method_settings` rows,
  used to render the radio list instead of the current hardcoded 3 options.
  If the currently-selected `paymentMethod` becomes inactive, fall back to
  the first active method.
- `getQrisImageProperty()` — null unless `paymentMethod === 'qris'` and its
  setting's `qris_mode === 'dynamic'`; otherwise
  `QrisConverter::toDynamic($setting->qris_static_string, (int) $this->total)`
  rendered to inline SVG via simple-qrcode.

Blade changes (`payment_modal.blade.php` in both `pos/` and `landing-page/`):
- Payment method options loop over `$this->activeMethods` instead of 3
  hardcoded `<label>` blocks.
- When `qrisImage` is present, render it above/instead of the current
  "selesaikan pembayaran melalui aplikasi terkait" notice. When qris is
  active but mode is EDC, keep that notice exactly as today.

## Testing

- `Tests\Unit\Services\Qris\QrisConverterTest` — one test with a known
  static QRIS sample (from the qris-dinamis README example format): asserts
  tag 01 becomes `12`, tag 54 contains the given amount, and the recomputed
  CRC in tag 63 validates against the full output string. One test for the
  invalid-CRC-input exception path.
- No feature/browser tests for the Filament page or Livewire wiring — out of
  proportion for a settings toggle + conditional render; manual check via
  `/run` covers it.
