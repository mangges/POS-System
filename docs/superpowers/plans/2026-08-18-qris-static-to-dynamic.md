# QRIS Static-to-Dynamic + Payment Method Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admin toggle which payment methods are offered and, for QRIS, choose between EDC (status quo) or system-generated dynamic QRIS with the exact order amount baked in — surfaced in both the POS cashier and the self-order landing page.

**Architecture:** A new `payment_method_settings` table (3 seeded rows: cash/qris/transfer) backs a Filament settings page. A pure-PHP `QrisConverter` service ports the EMVCo TLV static→dynamic algorithm (tag 01 rewrite, tag 54 insert, CRC16 recompute) from https://github.com/verssache/qris-dinamis. A shared Livewire trait (`PaymentMethodSelection`) exposes active-methods filtering and dynamic-QR rendering to both `Cashier` and `LandingPage` components, mirroring the existing `CartCalculation` trait pattern already shared by both.

**Tech Stack:** Laravel 13, Filament 5 (schemas), Livewire 4, `simplesoftwareio/simple-qrcode` (already installed, no new dependency), PHPUnit.

## Global Constraints

- No payment gateway/webhook integration — confirmation stays manual (cashier clicks finalize), exactly as today. (Spec: "Out of scope")
- `transfer` is relabeled "Kartu/Debit" in the settings UI only — no new payment method key, no `payments.payment_method` enum/migration change. (Spec: "Scope")
- Inactive payment methods are hidden entirely from both payment modals, not shown disabled. (Design approval)
- All three methods (cash, qris, transfer) can be individually deactivated, no method is protected. (Design approval)
- QR image is rendered on the fly per order total, never persisted to disk/DB. (Spec: "QRIS conversion service")

---

## File Structure

- `app/Enum/Payments/PaymentMethod.php` — new enum (cash/qris/transfer), matches existing `app/Enum/Orders/*` convention.
- `app/Enum/Payments/QrisMode.php` — new enum (edc/dynamic).
- `database/migrations/2026_08_18_090000_create_payment_method_settings_table.php` — new table + seeds 3 rows.
- `app/Models/PaymentMethodSetting.php` — new Eloquent model.
- `app/Services/Qris/QrisConverter.php` — new pure-PHP TLV conversion service.
- `tests/Unit/Services/Qris/QrisConverterTest.php` — new unit tests.
- `tests/Feature/Models/PaymentMethodSettingTest.php` — new feature test.
- `app/Filament/Pages/PaymentMethodSettings.php` — new Filament admin page (auto-discovered/auto-registered, unlike `App\Filament\CustomPages`).
- `resources/views/filament/pages/payment-method-settings.blade.php` — new view.
- `tests/Feature/Filament/PaymentMethodSettingsPageTest.php` — new feature test.
- `app/Traits/PaymentMethodSelection.php` — new shared trait (active methods + dynamic QR computed properties).
- `app/Livewire/Pos/Cashier.php` — modify: use trait, call `ensureActivePaymentMethod()`.
- `resources/views/livewire/pos/payment_modal.blade.php` — modify: gate method options, render QR.
- `resources/css/payment-modal.css` — modify: add `.qris-qr-wrap` styles.
- `tests/Feature/Livewire/Pos/CashierPaymentMethodTest.php` — new feature test.
- `app/Livewire/LandingPage/LandingPage.php` — modify: use trait, call `ensureActivePaymentMethod()`.
- `resources/views/livewire/landing-page/payment_modal.blade.php` — modify: gate method options, render QR.
- `resources/css/landing-page.css` — modify: add `.qris-qr-wrap` styles.
- `tests/Feature/Livewire/LandingPage/LandingPagePaymentMethodTest.php` — new feature test.

---

### Task 1: Enums, migration, and PaymentMethodSetting model

**Files:**
- Create: `app/Enum/Payments/PaymentMethod.php`
- Create: `app/Enum/Payments/QrisMode.php`
- Create: `database/migrations/2026_08_18_090000_create_payment_method_settings_table.php`
- Create: `app/Models/PaymentMethodSetting.php`
- Test: `tests/Feature/Models/PaymentMethodSettingTest.php`

**Interfaces:**
- Produces: `App\Enum\Payments\PaymentMethod` (cases `Cash`, `Qris`, `Transfer`, each with `->value` string and `->label(): string`).
- Produces: `App\Enum\Payments\QrisMode` (cases `Edc`, `Dynamic`, each with `->value` string and `->label(): string`).
- Produces: `App\Models\PaymentMethodSetting` — Eloquent model, table `payment_method_settings`, columns `method` (string), `is_active` (bool cast), `qris_mode` (nullable, cast to `QrisMode`), `qris_static_string` (nullable text). Static method `PaymentMethodSetting::forMethod(PaymentMethod|string $method): ?self`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Models;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_three_methods_active_by_default(): void
    {
        $this->assertSame(3, PaymentMethodSetting::count());
        $this->assertTrue(PaymentMethodSetting::forMethod(PaymentMethod::Cash)->is_active);
        $this->assertTrue(PaymentMethodSetting::forMethod(PaymentMethod::Qris)->is_active);
        $this->assertTrue(PaymentMethodSetting::forMethod(PaymentMethod::Transfer)->is_active);
    }

    public function test_qris_defaults_to_edc_mode(): void
    {
        $this->assertSame(QrisMode::Edc, PaymentMethodSetting::forMethod('qris')->qris_mode);
    }

    public function test_for_method_returns_null_for_unknown_method(): void
    {
        $this->assertNull(PaymentMethodSetting::forMethod('unknown'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentMethodSettingTest`
Expected: FAIL — class `App\Models\PaymentMethodSetting` not found (table/model don't exist yet).

- [ ] **Step 3: Create the enums**

`app/Enum/Payments/PaymentMethod.php`:

```php
<?php

namespace App\Enum\Payments;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Qris => 'QRIS',
            self::Transfer => 'Kartu/Debit',
        };
    }
}
```

`app/Enum/Payments/QrisMode.php`:

```php
<?php

namespace App\Enum\Payments;

enum QrisMode: string
{
    case Edc = 'edc';
    case Dynamic = 'dynamic';

    public function label(): string
    {
        return match ($this) {
            self::Edc => 'EDC',
            self::Dynamic => 'Static → Dynamic (generate otomatis)',
        };
    }
}
```

- [ ] **Step 4: Write the migration**

`database/migrations/2026_08_18_090000_create_payment_method_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_settings', function (Blueprint $table) {
            $table->id();
            $table->string('method')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('qris_mode')->nullable();
            $table->text('qris_static_string')->nullable();
            $table->timestamps();
        });

        DB::table('payment_method_settings')->insert([
            ['method' => 'cash', 'is_active' => true, 'qris_mode' => null, 'qris_static_string' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'qris', 'is_active' => true, 'qris_mode' => 'edc', 'qris_static_string' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'transfer', 'is_active' => true, 'qris_mode' => null, 'qris_static_string' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_settings');
    }
};
```

- [ ] **Step 5: Write the model**

`app/Models/PaymentMethodSetting.php`:

```php
<?php

namespace App\Models;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodSetting extends Model
{
    protected $fillable = [
        'method',
        'is_active',
        'qris_mode',
        'qris_static_string',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'method' => PaymentMethod::class,
        'qris_mode' => QrisMode::class,
    ];

    public static function forMethod(PaymentMethod|string $method): ?self
    {
        $value = $method instanceof PaymentMethod ? $method->value : $method;

        return static::where('method', $value)->first();
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PaymentMethodSettingTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Enum/Payments app/Models/PaymentMethodSetting.php database/migrations/2026_08_18_090000_create_payment_method_settings_table.php tests/Feature/Models/PaymentMethodSettingTest.php
git commit -m "feat: add payment method settings table, model, and enums"
```

---

### Task 2: QrisConverter service

**Files:**
- Create: `app/Services/Qris/QrisConverter.php`
- Test: `tests/Unit/Services/Qris/QrisConverterTest.php`

**Interfaces:**
- Produces: `App\Services\Qris\QrisConverter::toDynamic(string $staticQris, int $amount): string` — throws `InvalidArgumentException` on invalid/mismatched CRC input or a missing currency tag.
- Produces: `App\Services\Qris\QrisConverter::crc16(string $data): string` — CRC16-CCITT-FALSE (poly `0x1021`, init `0xFFFF`), uppercase 4-hex-digit string.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Services/Qris/QrisConverterTest.php`:

```php
<?php

namespace Tests\Unit\Services\Qris;

use App\Services\Qris\QrisConverter;
use InvalidArgumentException;
use Tests\TestCase;

class QrisConverterTest extends TestCase
{
    private const STATIC_QRIS = '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066';

    public function test_crc16_matches_known_ccitt_false_test_vector(): void
    {
        // Standard CRC-16/CCITT-FALSE check value for ASCII "123456789" is 0x29B1.
        $this->assertSame('29B1', QrisConverter::crc16('123456789'));
    }

    public function test_to_dynamic_rewrites_point_of_initiation_inserts_amount_and_recomputes_crc(): void
    {
        $dynamic = QrisConverter::toDynamic(self::STATIC_QRIS, 50000);

        $this->assertSame(
            '0002010102122614TESTMERCHANT015204581253033605405500005802ID5909TOKO TEST6007JAKARTA63040390',
            $dynamic
        );
    }

    public function test_to_dynamic_throws_when_crc_does_not_match(): void
    {
        $corrupted = substr(self::STATIC_QRIS, 0, -4).'0000';

        $this->expectException(InvalidArgumentException::class);

        QrisConverter::toDynamic($corrupted, 50000);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=QrisConverterTest`
Expected: FAIL — class `App\Services\Qris\QrisConverter` not found.

- [ ] **Step 3: Write the implementation**

`app/Services/Qris/QrisConverter.php`:

```php
<?php

namespace App\Services\Qris;

use InvalidArgumentException;

class QrisConverter
{
    public static function toDynamic(string $staticQris, int $amount): string
    {
        $staticQris = trim($staticQris);

        self::assertValidCrc($staticQris);

        $body = substr($staticQris, 0, -4);
        $elements = self::parseTlv($body);

        // The trailing tag 63 (CRC) is malformed after stripping its value above — drop it, we rebuild it fresh.
        array_pop($elements);

        $elements = array_map(function (array $element) {
            if ($element['tag'] === '01') {
                $element['value'] = '12';
            }

            return $element;
        }, $elements);

        $elements = array_values(array_filter($elements, fn (array $element) => $element['tag'] !== '54'));

        $currencyIndex = null;

        foreach ($elements as $index => $element) {
            if ($element['tag'] === '53') {
                $currencyIndex = $index;
                break;
            }
        }

        if ($currencyIndex === null) {
            throw new InvalidArgumentException('QRIS string is missing the currency tag (53).');
        }

        array_splice($elements, $currencyIndex + 1, 0, [['tag' => '54', 'value' => (string) $amount]]);

        $output = self::buildTlv($elements).'6304';

        return $output.self::crc16($output);
    }

    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $crc ^= (ord($data[$i]) << 8);

            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private static function assertValidCrc(string $qris): void
    {
        if (strlen($qris) < 8 || substr($qris, -8, 4) !== '6304') {
            throw new InvalidArgumentException('QRIS string is missing a valid CRC tag (63).');
        }

        $body = substr($qris, 0, -4);
        $expected = strtoupper(substr($qris, -4));
        $actual = self::crc16($body);

        if ($expected !== $actual) {
            throw new InvalidArgumentException("QRIS CRC mismatch: expected {$expected}, computed {$actual}.");
        }
    }

    /**
     * @return array<int, array{tag: string, value: string}>
     */
    private static function parseTlv(string $data): array
    {
        $elements = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $tag = substr($data, $offset, 2);
            $valueLength = (int) substr($data, $offset + 2, 2);
            $value = substr($data, $offset + 4, $valueLength);

            $elements[] = ['tag' => $tag, 'value' => $value];
            $offset += 4 + $valueLength;
        }

        return $elements;
    }

    /**
     * @param  array<int, array{tag: string, value: string}>  $elements
     */
    private static function buildTlv(array $elements): string
    {
        $output = '';

        foreach ($elements as $element) {
            $output .= $element['tag'].str_pad((string) strlen($element['value']), 2, '0', STR_PAD_LEFT).$element['value'];
        }

        return $output;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=QrisConverterTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Qris tests/Unit/Services/Qris
git commit -m "feat: add QRIS static-to-dynamic converter service"
```

---

### Task 3: Admin "Payment Method" settings page

**Files:**
- Create: `app/Filament/Pages/PaymentMethodSettings.php`
- Create: `resources/views/filament/pages/payment-method-settings.blade.php`
- Test: `tests/Feature/Filament/PaymentMethodSettingsPageTest.php`

**Interfaces:**
- Consumes: `App\Models\PaymentMethodSetting::forMethod()` (Task 1), `App\Enum\Payments\PaymentMethod`, `App\Enum\Payments\QrisMode` (Task 1).
- Produces: admin page at `/admin/payment-method-settings` (auto-discovered — `app/Filament/Pages` is registered via `->discoverPages()` in `AdminPanelProvider`, same as `Dashboard.php`; no manual wiring needed). Form state path `data` with keys `cash_active`, `qris_active`, `qris_mode`, `qris_static_string`, `transfer_active`. Public method `save(): void` upserts the 3 `payment_method_settings` rows.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Filament/PaymentMethodSettingsPageTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Enum\Payments\QrisMode;
use App\Filament\Pages\PaymentMethodSettings;
use App\Models\PaymentMethodSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_switch_qris_to_dynamic_mode_and_save(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.qris_active', true)
            ->set('data.qris_mode', 'dynamic')
            ->set('data.qris_static_string', '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066')
            ->call('save');

        $setting = PaymentMethodSetting::forMethod('qris');

        $this->assertTrue($setting->is_active);
        $this->assertSame(QrisMode::Dynamic, $setting->qris_mode);
        $this->assertSame('0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066', $setting->qris_static_string);
    }

    public function test_admin_can_deactivate_transfer(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(PaymentMethodSettings::class)
            ->set('data.transfer_active', false)
            ->call('save');

        $this->assertFalse(PaymentMethodSetting::forMethod('transfer')->is_active);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PaymentMethodSettingsPageTest`
Expected: FAIL — class `App\Filament\Pages\PaymentMethodSettings` not found.

- [ ] **Step 3: Write the page class**

`app/Filament/Pages/PaymentMethodSettings.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Enum\Payments\PaymentMethod;
use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PaymentMethodSettings extends Page
{
    protected static ?string $title = 'Payment Method';

    protected static ?string $navigationIcon = Heroicon::OutlinedCreditCard;

    protected string $view = 'filament.pages.payment-method-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = PaymentMethodSetting::all()->keyBy('method');

        $this->form->fill([
            'cash_active' => $settings[PaymentMethod::Cash->value]?->is_active ?? true,
            'qris_active' => $settings[PaymentMethod::Qris->value]?->is_active ?? true,
            'qris_mode' => $settings[PaymentMethod::Qris->value]?->qris_mode?->value ?? QrisMode::Edc->value,
            'qris_static_string' => $settings[PaymentMethod::Qris->value]?->qris_static_string,
            'transfer_active' => $settings[PaymentMethod::Transfer->value]?->is_active ?? true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Cash')
                    ->schema([
                        Toggle::make('cash_active')->label('Aktif'),
                    ]),
                Section::make('QRIS')
                    ->schema([
                        Toggle::make('qris_active')->label('Aktif')->live(),
                        Radio::make('qris_mode')
                            ->label('Mode')
                            ->options([
                                QrisMode::Edc->value => QrisMode::Edc->label(),
                                QrisMode::Dynamic->value => QrisMode::Dynamic->label(),
                            ])
                            ->default(QrisMode::Edc->value)
                            ->live()
                            ->visible(fn (Get $get) => $get('qris_active')),
                        Textarea::make('qris_static_string')
                            ->label('Static QRIS String')
                            ->rows(4)
                            ->required(fn (Get $get) => $get('qris_active') && $get('qris_mode') === QrisMode::Dynamic->value)
                            ->visible(fn (Get $get) => $get('qris_active') && $get('qris_mode') === QrisMode::Dynamic->value),
                    ]),
                Section::make('Kartu/Debit')
                    ->schema([
                        Toggle::make('transfer_active')->label('Aktif'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Cash->value],
            ['is_active' => $data['cash_active']],
        );

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Qris->value],
            [
                'is_active' => $data['qris_active'],
                'qris_mode' => $data['qris_mode'],
                'qris_static_string' => $data['qris_mode'] === QrisMode::Dynamic->value ? $data['qris_static_string'] : null,
            ],
        );

        PaymentMethodSetting::updateOrCreate(
            ['method' => PaymentMethod::Transfer->value],
            ['is_active' => $data['transfer_active']],
        );

        Notification::make()
            ->title('Payment method settings saved')
            ->success()
            ->send();
    }
}
```

- [ ] **Step 4: Write the view**

`resources/views/filament/pages/payment-method-settings.blade.php`:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Simpan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=PaymentMethodSettingsPageTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/PaymentMethodSettings.php resources/views/filament/pages/payment-method-settings.blade.php tests/Feature/Filament/PaymentMethodSettingsPageTest.php
git commit -m "feat: add Payment Method admin settings page"
```

---

### Task 4: Shared trait + wire into POS cashier

**Files:**
- Create: `app/Traits/PaymentMethodSelection.php`
- Modify: `app/Livewire/Pos/Cashier.php`
- Modify: `resources/views/livewire/pos/payment_modal.blade.php`
- Modify: `resources/css/payment-modal.css`
- Test: `tests/Feature/Livewire/Pos/CashierPaymentMethodTest.php`

**Interfaces:**
- Consumes: `PaymentMethodSetting::forMethod()` (Task 1), `QrisConverter::toDynamic()` (Task 2), and each consuming component's own `$this->paymentMethod` (string property) and `$this->total` (float, from `CartCalculation`, already present on both `Cashier` and `LandingPage`).
- Produces: trait `App\Traits\PaymentMethodSelection` with computed properties `activeMethods(): array` (list of active method value strings, ordered `cash, qris, transfer`) and `qrisImage(): ?string` (inline SVG, or `null` when not applicable), plus a plain method `ensureActivePaymentMethod(): void`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Livewire/Pos/CashierPaymentMethodTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\Cashier;
use App\Models\PaymentMethodSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashierPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_methods_are_excluded_from_active_methods(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->assertSet('activeMethods', ['cash', 'qris']);
    }

    public function test_default_payment_method_falls_back_when_cash_is_inactive(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'cash')->update(['is_active' => false]);

        Livewire::test(Cashier::class)
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_qris_image_is_null_when_mode_is_edc(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', null);
    }

    public function test_qris_image_renders_svg_when_mode_is_dynamic(): void
    {
        $this->actingAs(User::factory()->create());

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        Livewire::test(Cashier::class)
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', fn (?string $svg) => $svg !== null && str_contains($svg, '<svg'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CashierPaymentMethodTest`
Expected: FAIL — `activeMethods`/`qrisImage` are not properties on `Cashier`.

- [ ] **Step 3: Write the trait**

`app/Traits/PaymentMethodSelection.php`:

```php
<?php

namespace App\Traits;

use App\Enum\Payments\QrisMode;
use App\Models\PaymentMethodSetting;
use App\Services\Qris\QrisConverter;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

trait PaymentMethodSelection
{
    #[Computed]
    public function activeMethods(): array
    {
        return PaymentMethodSetting::where('is_active', true)->orderBy('id')->pluck('method')->all();
    }

    #[Computed]
    public function qrisImage(): ?string
    {
        if ($this->paymentMethod !== 'qris') {
            return null;
        }

        $setting = PaymentMethodSetting::forMethod('qris');

        if (! $setting || $setting->qris_mode !== QrisMode::Dynamic || empty($setting->qris_static_string)) {
            return null;
        }

        try {
            $payload = QrisConverter::toDynamic($setting->qris_static_string, (int) round($this->total));
        } catch (InvalidArgumentException) {
            return null;
        }

        return QrCode::size(220)->generate($payload);
    }

    public function ensureActivePaymentMethod(): void
    {
        if (! in_array($this->paymentMethod, $this->activeMethods, true)) {
            $this->paymentMethod = $this->activeMethods[0] ?? 'cash';
        }
    }
}
```

- [ ] **Step 4: Wire the trait into Cashier**

Modify `app/Livewire/Pos/Cashier.php` — add the import alongside the existing ones (after `use App\Services\Order\OrderService;`):

```php
use App\Traits\PaymentMethodSelection;
```

Add the trait use statement next to the existing `CartCalculation` one:

```php
    use CartCalculation {
        addToCart as protected traitAddToCart;
    }
    use PaymentMethodSelection;
```

Update `mount()` to fall back to an active payment method after loading products:

```php
    public function mount()
    {
        $this->categories = Category::all();
        $this->loadProducts();
        $this->ensureActivePaymentMethod();
    }
```

Update `loadDraft()` — add the same call right after the existing `$this->reset(...)` line (`$this->reset(['cart', 'customerName', 'paymentMethod', 'cashReceived', 'currentOrderId', 'orderType', 'activeDraft']);`):

```php
    public function loadDraft(int $id): void
    {
        $this->reset(['cart', 'customerName', 'paymentMethod', 'cashReceived', 'currentOrderId', 'orderType', 'activeDraft']);
        $this->ensureActivePaymentMethod();

        $order = Order::with('items')->findOrFail($id);
```

Update `resetCashier()`:

```php
    public function resetCashier()
    {
        $this->reset(['cart', 'customerName', 'paymentMethod', 'cashReceived', 'currentOrderId', 'orderType']);
        $this->ensureActivePaymentMethod();
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CashierPaymentMethodTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit the component logic**

```bash
git add app/Traits/PaymentMethodSelection.php app/Livewire/Pos/Cashier.php tests/Feature/Livewire/Pos/CashierPaymentMethodTest.php
git commit -m "feat: filter active payment methods and render dynamic QRIS in POS cashier"
```

- [ ] **Step 7: Gate the payment method options in the POS payment modal**

Modify `resources/views/livewire/pos/payment_modal.blade.php` — replace the `payment-methods-grid` block (lines 10-26):

```blade
        <div class="payment-methods-grid">
            @if(in_array('cash', $this->activeMethods))
            <label class="method-option {{ $this->paymentMethod === 'cash' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="cash" class="hidden-radio">
                <i class="fas fa-money-bill-wave icon-cash"></i>
                <span class="method-label label-cash">Tunai</span>
            </label>
            @endif
            @if(in_array('qris', $this->activeMethods))
            <label class="method-option {{ $this->paymentMethod === 'qris' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="qris" class="hidden-radio">
                <i class="fas fa-qrcode icon-gray"></i>
                <span class="method-label label-gray">QRIS</span>
            </label>
            @endif
            @if(in_array('transfer', $this->activeMethods))
            <label class="method-option {{ $this->paymentMethod === 'transfer' ? 'active' : '' }}">
                <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="transfer" class="hidden-radio">
                <i class="fas fa-credit-card icon-gray"></i>
                <span class="method-label label-gray">Debit/Transfer</span>
            </label>
            @endif
        </div>
```

(Dropped the hardcoded `checked` attribute on the cash input — it was static regardless of which method was actually selected; `wire:model.live` already reflects the real state.)

- [ ] **Step 8: Render the dynamic QR in the payment notice**

Replace the notice block (originally lines 44-49):

```blade
        @if($this->paymentMethod !== 'cash')
            @if($this->qrisImage)
                <div class="payment-notice">
                    <i class="bi bi-qr-code"></i>
                    <p>Minta pelanggan scan QR ini untuk membayar Rp {{ number_format($this->total) }}.</p>
                </div>
                <div class="qris-qr-wrap">{!! $this->qrisImage !!}</div>
            @else
                <div class="payment-notice">
                    <i class="bi bi-exclamation-circle"></i>
                    <p>Untuk metode pembayaran selain tunai, silakan selesaikan pembayaran melalui aplikasi terkait.</p>
                </div>
            @endif
        @endif
```

- [ ] **Step 9: Add the QR wrapper styles**

Modify `resources/css/payment-modal.css` — add after the `@keyframes fadeIn` block (around line 426):

```css
.qris-qr-wrap {
    display: flex;
    justify-content: center;
    margin-top: 0.5rem;
}

.qris-qr-wrap svg {
    background: #ffffff;
    padding: 0.75rem;
    border-radius: 10px;
    width: 180px;
    height: 180px;
}
```

- [ ] **Step 10: Commit the view changes**

```bash
git add resources/views/livewire/pos/payment_modal.blade.php resources/css/payment-modal.css
git commit -m "feat: gate POS payment methods and show dynamic QRIS QR"
```

---

### Task 5: Wire into self-order landing page

**Files:**
- Modify: `app/Livewire/LandingPage/LandingPage.php`
- Modify: `resources/views/livewire/landing-page/payment_modal.blade.php`
- Modify: `resources/css/landing-page.css`
- Test: `tests/Feature/Livewire/LandingPage/LandingPagePaymentMethodTest.php`

**Interfaces:**
- Consumes: `App\Traits\PaymentMethodSelection` (Task 4) — no new PHP logic, only wiring.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Livewire/LandingPage/LandingPagePaymentMethodTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\LandingPage;

use App\Livewire\LandingPage\LandingPage;
use App\Models\PaymentMethodSetting;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPagePaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function createTable(): Table
    {
        return Table::create(['number' => '1', 'barcode' => 'T1']);
    }

    public function test_inactive_methods_are_excluded_from_active_methods(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'transfer')->update(['is_active' => false]);

        Livewire::test(LandingPage::class, ['table_token' => $table->qr_token])
            ->assertSet('activeMethods', ['cash', 'qris']);
    }

    public function test_default_payment_method_falls_back_when_cash_is_inactive(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'cash')->update(['is_active' => false]);

        Livewire::test(LandingPage::class, ['table_token' => $table->qr_token])
            ->assertSet('paymentMethod', 'qris');
    }

    public function test_qris_image_renders_svg_when_mode_is_dynamic(): void
    {
        $table = $this->createTable();

        PaymentMethodSetting::where('method', 'qris')->update([
            'qris_mode' => 'dynamic',
            'qris_static_string' => '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066',
        ]);

        Livewire::test(LandingPage::class, ['table_token' => $table->qr_token])
            ->set('paymentMethod', 'qris')
            ->assertSet('qrisImage', fn (?string $svg) => $svg !== null && str_contains($svg, '<svg'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LandingPagePaymentMethodTest`
Expected: FAIL — `activeMethods`/`qrisImage` are not properties on `LandingPage`.

- [ ] **Step 3: Wire the trait into LandingPage**

Modify `app/Livewire/LandingPage/LandingPage.php` — add the import (after `use App\Services\Order\OrderService;`):

```php
use App\Traits\PaymentMethodSelection;
```

Add the trait use statement next to `CartCalculation`:

```php
    use CartCalculation;
    use PaymentMethodSelection;
```

Update `mount()` to resolve a valid default payment method:

```php
    public function mount(string $table_token)
    {
        $this->table = Table::where('qr_token', $table_token)->firstOrFail();
        $this->ensureActivePaymentMethod();
    }
```

Update `closePaymentModal()` — add the call right after the existing reset (`$this->reset(['customerName', 'paymentMethod', 'orderSubmitted', 'lastOrderNumber', 'currentOrderId']);`):

```php
    public function closePaymentModal()
    {
        $this->showPaymentModal = false;

        if ($this->orderSubmitted) {
            $this->reset(['customerName', 'paymentMethod', 'orderSubmitted', 'lastOrderNumber', 'currentOrderId']);
            $this->ensureActivePaymentMethod();
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LandingPagePaymentMethodTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit the component logic**

```bash
git add app/Livewire/LandingPage/LandingPage.php tests/Feature/Livewire/LandingPage/LandingPagePaymentMethodTest.php
git commit -m "feat: filter active payment methods and render dynamic QRIS in self-order"
```

- [ ] **Step 6: Gate the payment method options in the self-order payment modal**

Modify `resources/views/livewire/landing-page/payment_modal.blade.php` — replace the `payment-method-list` block (lines 22-35):

```blade
                    <div class="payment-method-list">
                        @if(in_array('cash', $this->activeMethods))
                        <label class="payment-method-option {{ $paymentMethod === 'cash' ? 'active' : '' }}">
                            <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="cash" class="hidden-radio">
                            <span class="payment-method-label">Tunai</span>
                        </label>
                        @endif
                        @if(in_array('qris', $this->activeMethods))
                        <label class="payment-method-option {{ $paymentMethod === 'qris' ? 'active' : '' }}">
                            <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="qris" class="hidden-radio">
                            <span class="payment-method-label">QRIS</span>
                        </label>
                        @endif
                        @if(in_array('transfer', $this->activeMethods))
                        <label class="payment-method-option {{ $paymentMethod === 'transfer' ? 'active' : '' }}">
                            <input type="radio" name="payment_method" wire:model.live="paymentMethod" value="transfer" class="hidden-radio">
                            <span class="payment-method-label">Debit</span>
                        </label>
                        @endif
                    </div>
```

- [ ] **Step 7: Render the dynamic QR and adjust the notice copy**

Replace the pre-submit notice block (originally lines 53-59):

```blade
                <p class="payment-notice">
                    @if ($paymentMethod === 'cash')
                        Kasir kami akan membawakan bills ke meja Anda untuk pembayaran.
                    @elseif ($this->qrisImage)
                        Scan QR di bawah ini untuk membayar langsung dari HP Anda.
                    @else
                        Kasir kami akan membawakan mesin EDC ke meja Anda untuk pembayaran.
                    @endif
                </p>
                @if ($this->qrisImage)
                    <div class="qris-qr-wrap">{!! $this->qrisImage !!}</div>
                @endif
```

Replace the post-submit success message (originally lines 76-81):

```blade
                <p class="payment-success-message">
                    @if ($paymentMethod === 'cash')
                        Kasir kami akan segera membawakan struk pembayaran ke meja Anda.
                    @elseif ($paymentMethod === 'qris' && $this->qrisImage)
                        Silakan selesaikan pembayaran QRIS di layar sebelumnya. Struk akan diantar kasir setelah pembayaran diterima.
                    @else
                        Kasir kami akan segera membawakan mesin EDC ke meja Anda untuk proses pembayaran.
                    @endif
                </p>
```

- [ ] **Step 8: Add the QR wrapper styles**

Modify `resources/css/landing-page.css` — add after the `.payment-notice` block (around line 1505):

```css
.qris-qr-wrap {
    display: flex;
    justify-content: center;
    margin-top: 0.75rem;
}

.qris-qr-wrap svg {
    background: #ffffff;
    padding: 0.75rem;
    border-radius: 10px;
    width: 180px;
    height: 180px;
}
```

- [ ] **Step 9: Commit the view changes**

```bash
git add resources/views/livewire/landing-page/payment_modal.blade.php resources/css/landing-page.css
git commit -m "feat: gate self-order payment methods and show dynamic QRIS QR"
```

- [ ] **Step 10: Manual verification**

Use the `/run` skill to start the app, then:
1. Visit `/admin/payment-method-settings`, switch QRIS to "Static → Dynamic", paste a real static QRIS string, save.
2. Open the POS cashier, add items to cart, checkout, select QRIS — confirm a QR renders with the correct total and scans correctly (or at minimum decodes back to a valid EMVCo payload with tag 54 = order total).
3. Deactivate "Kartu/Debit" in settings, confirm it disappears from both the POS and self-order payment method lists.
4. Open the self-order landing page (`/order/{table_token}`) on a phone or browser, confirm the same QR flow works there.

---

## Self-Review Notes

- **Spec coverage:** data model (Task 1), admin page (Task 3), QRIS conversion (Task 2), POS wiring (Task 4), landing page wiring (Task 5), manual EDC path left untouched in both blade files (kept via the `@else` branches), `transfer`→"Kartu/Debit" relabel-only (Task 3 section label, no enum/migration touch), no gateway/webhook work added. All covered.
- **Placeholder scan:** none — every step has runnable code, real file paths, and concrete assertions/expected strings (the QRIS conversion test vector was verified against a live PHP execution before writing this plan, not hand-derived).
- **Type consistency:** `activeMethods(): array` and `qrisImage(): ?string` are defined once in Task 4's trait and consumed identically (by name) in Task 4 and Task 5's tests/blades — no divergent naming.
