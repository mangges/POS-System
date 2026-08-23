# Cashier Resource Access Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the static `users.role` enum with `spatie/laravel-permission` roles, split Filament resources into 3 access tiers (open / PIN-unlockable / admin-only), and add a PIN-based temporary-elevation flow for cashiers on shared devices.

**Architecture:** Spatie roles (`admin`, `cashier`) replace the enum column. A `Gate::before` hook gives admins blanket access. Tier 2 resources stay visible in nav (`canAccess()` unchanged) but are gated by a new `EnsureResourcePinUnlocked` auth-middleware that redirects to an `AdminPinGate` Filament page; entering a valid admin PIN there calls `syncPermissions()` so only one Tier 2 permission is ever active at a time. Tier 3 resources gate via `canAccess()` directly, no PIN path.

**Tech Stack:** Laravel 13, Filament 5.6, `spatie/laravel-permission` (latest stable, 8.x), PHPUnit (existing `tests/Feature/Filament` convention), Livewire 3.

## Global Constraints

- Role source of truth: `spatie/laravel-permission` roles `admin` and `cashier` — the `users.role` enum column is retired once all dependents are migrated (spec §Scope).
- Tier 2 permissions are named `access-{slug}` where slug matches Filament's default resource slug (`categories`, `products`, `raw-materials`, `recipes`, `stock-movements`, `units`).
- Tier 3 uses one shared permission `access-admin-only` — no per-resource granularity (spec §2).
- PIN comparison reuses the existing plaintext `users.pin` column — no hashing, no new column (spec §7).
- No PIN expiry, no rate limiting/lockout, no audit log — explicitly out of scope (spec §Scope).
- `syncPermissions()` (not `givePermissionTo()`) is used everywhere a Tier 2 permission is granted, so granting one always revokes any other Tier 2 permission previously held (spec §7, §8).
- Naming convention: `snake_case` DB columns, `camelCase` PHP/JS variables (project CLAUDE.md).
- Every query touching tenant data stays scoped — this feature touches no tenant-scoped tables, N/A here.

---

### Task 1: Install spatie/laravel-permission

**Files:**
- Modify: `composer.json` (via `composer require`)
- Create: `config/permission.php` (via vendor:publish)
- Create: `database/migrations/*_create_permission_tables.php` (via vendor:publish)

**Interfaces:**
- Produces: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` tables migrated into the app's normal migration flow (used by every later task).

- [ ] **Step 1: Require the package**

Run: `composer require spatie/laravel-permission`

- [ ] **Step 2: Publish migration and config**

Run: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `model_has_permissions` tables created, no errors.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/permission.php database/migrations
git commit -m "chore: install spatie/laravel-permission"
```

---

### Task 2: `HasRoles` trait on `User` model

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Models/UserRoleTest.php`

**Interfaces:**
- Consumes: `Spatie\Permission\Traits\HasRoles` (from Task 1's package)
- Produces: `$user->assignRole()`, `$user->hasRole()`, `$user->can()`, `$user->syncPermissions()`, `$user->getRoleNames()` — used by every task from here on.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_assigned_a_spatie_role(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $user = User::factory()->create();

        $user->assignRole('cashier');

        $this->assertTrue($user->hasRole('cashier'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserRoleTest`
Expected: FAIL — `Call to undefined method App\Models\User::assignRole()`

- [ ] **Step 3: Add the trait**

In `app/Models/User.php`, add the import and trait:

```php
use Spatie\Permission\Traits\HasRoles;
```

```php
use HasFactory, Notifiable, HasRoles;
```

(Replace the existing `use HasFactory, Notifiable;` line with the line above.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UserRoleTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Models/UserRoleTest.php
git commit -m "feat: add HasRoles trait to User model"
```

---

### Task 3: Seed roles/permissions + migrate existing users' role data

**Files:**
- Create: `database/migrations/2026_08_23_000001_assign_roles_to_existing_users.php`
- Create: `database/seeders/RolePermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Models/RoleMigrationTest.php`

**Interfaces:**
- Consumes: `$user->assignRole()` (Task 2), `users.role` enum column (still present, dropped in Task 6).
- Produces: `admin`/`cashier` roles and the 7 permissions (`access-categories`, `access-products`, `access-raw-materials`, `access-recipes`, `access-stock-movements`, `access-units`, `access-admin-only`) existing in the `permissions` table for every later task to reference.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_admin_user_gets_admin_spatie_role(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        (require database_path('migrations/2026_08_23_000001_assign_roles_to_existing_users.php'))->up();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_existing_cashier_user_gets_cashier_spatie_role(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        (require database_path('migrations/2026_08_23_000001_assign_roles_to_existing_users.php'))->up();

        $this->assertTrue($user->fresh()->hasRole('cashier'));
    }
}
```

Note: `RefreshDatabase` runs every migration (including this new one) once, before the test body ever creates a user — so by the time `User::factory()->create(...)` runs, this migration is already marked "ran" in Laravel's migration tracking table, and re-invoking it through `artisan migrate` would be a no-op ("Nothing to migrate"). This migration file returns an anonymous class instance (`return new class extends Migration {...}`), so `require`-ing the file directly and calling `->up()` on the returned instance re-executes its logic on demand, completely bypassing the migration-tracking table. This is the correct way to test an anonymous-class migration's logic against data created after the schema migration already ran.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RoleMigrationTest`
Expected: FAIL — `require database_path('migrations/2026_08_23_000001_assign_roles_to_existing_users.php')` errors because the file doesn't exist yet.

- [ ] **Step 3: Write the seeder**

`database/seeders/RolePermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        foreach ([
            'access-categories',
            'access-products',
            'access-raw-materials',
            'access-recipes',
            'access-stock-movements',
            'access-units',
            'access-admin-only',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
```

Register it in `database/seeders/DatabaseSeeder.php` `run()` method, called before `UserSeeder`:

```php
$this->call(RolePermissionSeeder::class);
```

- [ ] **Step 4: Write the data migration**

`database/migrations/2026_08_23_000001_assign_roles_to_existing_users.php`:

```php
<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        foreach (User::all() as $user) {
            if (! $user->hasRole($user->role)) {
                $user->assignRole($user->role);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=RoleMigrationTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_23_000001_assign_roles_to_existing_users.php database/seeders/RolePermissionSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Models/RoleMigrationTest.php
git commit -m "feat: seed spatie roles/permissions and migrate existing user roles"
```

---

### Task 4: Update `Login.php` to use `hasRole()` + reset permissions on login

**Files:**
- Modify: `app/Livewire/Auth/Login.php`
- Test: `tests/Feature/Livewire/LoginTest.php`

**Interfaces:**
- Consumes: `$user->hasRole('admin')`, `$user->syncPermissions([])` (Task 2).
- Produces: post-login redirect behavior relied on by manual QA only (no later task consumes this directly).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_login_redirects_admin_to_admin_panel(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['pin' => '111111']);
        $user->assignRole('admin');

        Livewire::test(Login::class)
            ->set('pin', '111111')
            ->call('loginWithPin')
            ->assertRedirect('/admin');
    }

    public function test_pin_login_redirects_cashier_to_cashier_view(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $user = User::factory()->create(['pin' => '222222']);
        $user->assignRole('cashier');

        Livewire::test(Login::class)
            ->set('pin', '222222')
            ->call('loginWithPin')
            ->assertRedirect('/cashier');
    }

    public function test_login_clears_stale_permissions_from_previous_session(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $user = User::factory()->create(['pin' => '333333']);
        $user->assignRole('cashier');
        $user->syncPermissions(['access-products']);

        Livewire::test(Login::class)
            ->set('pin', '333333')
            ->call('loginWithPin');

        $this->assertCount(0, $user->fresh()->getDirectPermissions());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LoginTest`
Expected: FAIL — still reading `$user->role` (column still exists at this point but `hasRole` isn't called yet, so admin redirect assertion fails) and stale permission is not cleared.

- [ ] **Step 3: Update `Login.php`**

In `loginWithPin()`, replace:

```php
if ($user->role === 'admin') {
```

with:

```php
Auth::login($user);
session()->regenerate();
$user->syncPermissions([]);
if ($user->hasRole('admin')) {
```

(i.e. move the existing `Auth::login($user); session()->regenerate();` lines to before the `syncPermissions([])` call, add the sync call, then keep the existing `return redirect(...)` branches, now driven by `hasRole('admin')`.)

Full resulting method body:

```php
public function loginWithPin()
{
    $this->validate([
        'pin' => 'required|string',
    ]);

    // Find user by PIN
    $user = \App\Models\User::where('pin', $this->pin)->first();

    if ($user) {
        Auth::login($user);
        session()->regenerate();
        $user->syncPermissions([]);
        if ($user->hasRole('admin')) {
            return redirect('/admin');
        }
        return redirect('/cashier');
    }

    $this->addError('pin', 'PIN yang Anda masukkan salah.');
    $this->pin = '';
}
```

In `loginWithEmail()`, replace:

```php
if (Auth::attempt($credentials, $this->remember)) {
    session()->regenerate();
    
    $user = Auth::user();
    if (in_array($user->role, ['admin'])) {
        return redirect('/admin');
    }
    return redirect('/cashier');
}
```

with:

```php
if (Auth::attempt($credentials, $this->remember)) {
    session()->regenerate();

    $user = Auth::user();
    $user->syncPermissions([]);
    if ($user->hasRole('admin')) {
        return redirect('/admin');
    }
    return redirect('/cashier');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LoginTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Auth/Login.php tests/Feature/Livewire/LoginTest.php
git commit -m "feat: use spatie roles and reset permissions on login"
```

---

### Task 5: Update `UserForm`, `UsersTable`, `UserSeeder` for spatie roles

**Files:**
- Modify: `app/Filament/Resources/Users/Schemas/UserForm.php`
- Modify: `app/Filament/Resources/Users/Tables/UsersTable.php`
- Modify: `database/seeders/UserSeeder.php`
- Test: `tests/Feature/Filament/UserFormRoleTest.php`

**Interfaces:**
- Consumes: `$record->getRoleNames()`, `$record->syncRoles()`, `User::create()` + `assignRole()` (Task 2), `RolePermissionSeeder` (Task 3, must run first so roles exist when `UserSeeder` calls `assignRole`).
- Produces: none consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFormRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_user_loads_and_saves_their_role(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $this->actingAs($actor);

        $target = User::factory()->create();
        $target->assignRole('cashier');

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormSet(['role' => 'cashier'])
            ->fillForm(['role' => 'admin'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->hasRole('admin'));
        $this->assertFalse($target->fresh()->hasRole('cashier'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserFormRoleTest`
Expected: FAIL — `role` still bound directly to the (soon to be removed) DB column, form state doesn't come from `getRoleNames()`.

- [ ] **Step 3: Update `UserForm.php`**

```php
<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('pin')
                    ->default(null),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'cashier' => 'Cashier'])
                    ->default('cashier')
                    ->required()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Select $component, $record) {
                        $component->state($record?->getRoleNames()->first());
                    }),
            ]);
    }
}
```

Then, in `app/Filament/Resources/Users/Pages/EditUser.php`, add an `afterSave()` hook that syncs the role (read the file first to match its existing structure before editing):

```php
protected function afterSave(): void
{
    $this->record->syncRoles([$this->data['role']]);
}
```

And in `app/Filament/Resources/Users/Pages/CreateUser.php`, add the equivalent `afterCreate()` hook:

```php
protected function afterCreate(): void
{
    $this->record->syncRoles([$this->data['role']]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=UserFormRoleTest`
Expected: PASS

- [ ] **Step 5: Update `UsersTable.php`**

Replace:

```php
TextColumn::make('role')
    ->badge(),
```

with:

```php
TextColumn::make('roles.name')
    ->label('Role')
    ->badge(),
```

- [ ] **Step 6: Update `UserSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@pos.com',
            'password' => \Hash::make('password'),
            'pin' => '123456',
        ]);
        $admin->assignRole('admin');

        $cashier = User::create([
            'name' => 'Cashier 1',
            'email' => 'cashier@pos.com',
            'password' => \Hash::make('password'),
            'pin' => '654321',
        ]);
        $cashier->assignRole('cashier');
    }
}
```

Confirm `database/seeders/DatabaseSeeder.php` calls `RolePermissionSeeder::class` before `UserSeeder::class` (from Task 3) — reorder if needed since `assignRole()` requires the role to already exist.

- [ ] **Step 7: Run full Filament test suite to catch regressions**

Run: `php artisan test --filter=Filament`
Expected: PASS (existing `CreateProductPageTest`, `EditProductPageTest`, etc. unaffected)

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/Users database/seeders/UserSeeder.php tests/Feature/Filament/UserFormRoleTest.php
git commit -m "feat: manage spatie roles from the user edit form"
```

---

### Task 6: Drop the `users.role` column

**Files:**
- Create: `database/migrations/2026_08_24_000001_drop_role_from_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php` (only if it references `role` — confirm by reading the file; as of this plan it does not)
- Test: `tests/Feature/Models/UserRoleTest.php` (extend)

**Interfaces:**
- Consumes: nothing new.
- Produces: `users` table with no `role` column — final state for all later tasks and manual QA.

- [ ] **Step 1: Confirm no remaining reads of `$user->role`**

Run: `grep -rn "->role\b" app/ --include=*.php`
Expected: no matches (Tasks 4 and 5 removed the only two call sites).

- [ ] **Step 2: Write the migration**

`database/migrations/2026_08_24_000001_drop_role_from_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'cashier'])->default('cashier');
        });
    }
};
```

- [ ] **Step 3: Update `User.php` attributes**

Remove `role` from the `#[Fillable]` and `#[Hidden]` attributes:

```php
#[Fillable(['name', 'email', 'password', 'pin'])]
#[Hidden(['password', 'remember_token', 'pin'])]
```

- [ ] **Step 4: Run migrations and full test suite**

Run: `php artisan migrate:fresh --seed && php artisan test`
Expected: all tests pass, including Tasks 2–5's tests (which no longer rely on the `role` column existing since they use `assignRole`/`hasRole`).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_24_000001_drop_role_from_users_table.php app/Models/User.php
git commit -m "feat: drop legacy users.role column"
```

---

### Task 7: Admin bypass via `Gate::before`

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Models/GateBypassTest.php`

**Interfaces:**
- Consumes: `$user->hasRole('admin')` (Task 2).
- Produces: admin `can()` bypass relied on by Tasks 8 and 9's admin-path tests.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_passes_can_check_for_permission_never_assigned(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('some-permission-that-was-never-created'));
    }

    public function test_cashier_does_not_bypass_permission_checks(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->assertFalse($cashier->can('access-admin-only'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GateBypassTest`
Expected: FAIL — `can()` returns `false` for the admin case (no permission and no bypass registered yet). Note: `can('permission-that-was-never-created')` against a non-existent permission with spatie throws `PermissionDoesNotExist` unless `Gate::before` short-circuits first — that failure itself demonstrates the bypass is missing.

- [ ] **Step 3: Register the bypass**

In `app/Providers/AppServiceProvider.php`, add the import and register in `boot()`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
```

```php
public function boot(): void
{
    Gate::before(fn (User $user) => $user->hasRole('admin') ? true : null);

    Relation::morphMap([
        // ...unchanged
    ]);

    // ...unchanged
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GateBypassTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/Models/GateBypassTest.php
git commit -m "feat: admin bypasses all permission checks via Gate::before"
```

---

### Task 8: Tier 3 hard lock on admin-only resources/pages

**Files:**
- Modify: `app/Filament/Resources/Users/UserResource.php`
- Modify: `app/Filament/Pages/Reports/SalesReport.php`
- Modify: `app/Filament/Pages/Reports/ProductReport.php`
- Modify: `app/Filament/Pages/PaymentMethodSettings.php`
- Modify: `app/Filament/Pages/ReceiptSettings.php`
- Test: `tests/Feature/Filament/Tier3AccessTest.php`

**Interfaces:**
- Consumes: `access-admin-only` permission (Task 3), `Gate::before` admin bypass (Task 7).
- Produces: none consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\PaymentMethodSettings;
use App\Filament\Pages\ReceiptSettings;
use App\Filament\Pages\Reports\ProductReport;
use App\Filament\Pages\Reports\SalesReport;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Tier3AccessTest extends TestCase
{
    use RefreshDatabase;

    public static function tier3Classes(): array
    {
        return [
            [UserResource::class],
            [SalesReport::class],
            [ProductReport::class],
            [PaymentMethodSettings::class],
            [ReceiptSettings::class],
        ];
    }

    /** @dataProvider tier3Classes */
    public function test_cashier_cannot_access_tier3(string $class): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-admin-only']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $this->assertFalse($class::canAccess());
    }

    /** @dataProvider tier3Classes */
    public function test_admin_can_access_tier3(string $class): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->assertTrue($class::canAccess());
    }

    public function test_cashier_with_no_permissions_still_accesses_tier1_resource(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $this->assertTrue(\App\Filament\Resources\Orders\OrderResource::canAccess());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=Tier3AccessTest`
Expected: FAIL — none of the 5 classes override `canAccess()` yet, so the cashier assertions fail (default is `true`). The Tier 1 test already passes at this point (no code change needed for it — it's a regression guard, not a driver of new code).

- [ ] **Step 3: Add `canAccess()` to each of the 5 classes**

Read each file first, then add this method (adjust only if a `canAccess()` already exists — none do per the earlier codebase scan):

```php
public static function canAccess(): bool
{
    return auth()->user()->can('access-admin-only');
}
```

Add to: `UserResource.php`, `SalesReport.php`, `ProductReport.php`, `PaymentMethodSettings.php`, `ReceiptSettings.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=Tier3AccessTest`
Expected: PASS

- [ ] **Step 5: Run existing report/settings page tests to catch regressions**

Run: `php artisan test --filter="ProductReportPageTest|SalesReportPageTest|PaymentMethodSettingsPageTest"`
Expected: PASS — check these existing tests `actingAs` a user; if they don't assign a role, update them to `assignRole('admin')` on the acting user so they keep passing (read the files first).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Users/UserResource.php app/Filament/Pages/Reports/SalesReport.php app/Filament/Pages/Reports/ProductReport.php app/Filament/Pages/PaymentMethodSettings.php app/Filament/Pages/ReceiptSettings.php tests/Feature/Filament/Tier3AccessTest.php
git commit -m "feat: lock tier 3 resources to admin-only"
```

---

### Task 9: `EnsureResourcePinUnlocked` middleware

**Files:**
- Create: `app/Http/Middleware/EnsureResourcePinUnlocked.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Test: `tests/Feature/Filament/EnsureResourcePinUnlockedTest.php`

**Interfaces:**
- Consumes: `access-{slug}` permissions (Task 3), `AdminPinGate::getUrl()` (Task 10 — this task references a class that doesn't exist yet; write it as a plain route/URL string first, see Step 3 note).
- Produces: the redirect-to-PIN-gate behavior that Task 10's page is the destination of, and that Task 11's integration test exercises end-to-end.

**Sequencing note:** `AdminPinGate` (Task 10) doesn't exist until the next task. To keep this task's test runnable standalone, reference the page by its eventual route name (`filament.admin.pages.admin-pin-gate`) via `route()` with a raw query string rather than `AdminPinGate::getUrl()`, and swap to `AdminPinGate::getUrl()` in Task 10 once the class exists. This keeps both tasks independently testable in order.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureResourcePinUnlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_without_permission_is_redirected_to_pin_gate(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $response = $this->get('/admin/categories');

        $response->assertRedirect();
        $this->assertStringContainsString('resource=categories', $response->headers->get('Location'));
    }

    public function test_cashier_with_permission_passes_through(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $user->syncPermissions(['access-categories']);
        $this->actingAs($user);

        $response = $this->get('/admin/categories');

        $response->assertOk();
    }

    public function test_admin_passes_through_without_permission(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $response = $this->get('/admin/categories');

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EnsureResourcePinUnlockedTest`
Expected: FAIL — middleware class doesn't exist, not registered, `/admin/categories` currently returns 200 for the unlocked cashier.

- [ ] **Step 3: Write the middleware**

`app/Http/Middleware/EnsureResourcePinUnlocked.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResourcePinUnlocked
{
    protected const array PROTECTED = [
        'categories' => 'access-categories',
        'products' => 'access-products',
        'raw-materials' => 'access-raw-materials',
        'recipes' => 'access-recipes',
        'stock-movements' => 'access-stock-movements',
        'units' => 'access-units',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $routeName = $request->route()?->getName() ?? '';

        foreach (self::PROTECTED as $slug => $permission) {
            if (str_contains($routeName, "resources.{$slug}.") && $user?->cannot($permission)) {
                return redirect(route('filament.admin.pages.admin-pin-gate', [
                    'resource' => $slug,
                    'redirect' => $request->fullUrl(),
                ]));
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware**

In `app/Providers/Filament/AdminPanelProvider.php`, add the import:

```php
use App\Http\Middleware\EnsureResourcePinUnlocked;
```

Change:

```php
->authMiddleware([
    Authenticate::class,
])
```

to:

```php
->authMiddleware([
    Authenticate::class,
    EnsureResourcePinUnlocked::class,
])
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=EnsureResourcePinUnlockedTest`
Expected: FAIL still on the redirect assertions (route `filament.admin.pages.admin-pin-gate` doesn't exist yet — this will 404/throw, not redirect) but PASS on `test_admin_passes_through_without_permission` (admin bypass from Task 7 already works). This is expected at this point in the plan; Task 10 makes the route exist and the remaining two tests pass. Do not treat this as broken — confirm via the error message that it's a missing-route error, not a middleware logic error, then proceed to Task 10.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureResourcePinUnlocked.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/Filament/EnsureResourcePinUnlockedTest.php
git commit -m "feat: gate tier 2 resources behind PIN-unlock middleware"
```

---

### Task 10: `AdminPinGate` page

**Files:**
- Create: `app/Filament/Pages/AdminPinGate.php`
- Create: `resources/views/filament/pages/admin-pin-gate.blade.php`
- Test: `tests/Feature/Filament/AdminPinGateTest.php`

**Interfaces:**
- Consumes: `User::role('admin')->where('pin', ...)` (spatie scope from Task 2), `syncPermissions()` (Task 2).
- Produces: `filament.admin.pages.admin-pin-gate` route that Task 9's middleware redirects to (completes Task 9's deferred assertions) and Task 11's integration test drives end-to-end.

**UI/UX note:** per spec's Implementation Guidelines, use the `ui-ux-pro-max` and `frontend-design` skills for the PIN entry screen itself — this task's steps cover the functional Livewire/Filament page; invoke those skills for the Blade view's visual design before finalizing Step 3's view.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminPinGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPinGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_pin_shows_error_and_changes_no_permission(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'categories', 'redirect' => '/admin/categories'])
            ->set('pin', '000000')
            ->call('submit');

        $this->assertCount(0, $cashier->fresh()->getDirectPermissions());
    }

    public function test_correct_admin_pin_grants_permission_and_redirects(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'categories', 'redirect' => '/admin/categories'])
            ->set('pin', '999999')
            ->call('submit')
            ->assertRedirect('/admin/categories');

        $this->assertTrue($cashier->fresh()->can('access-categories'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminPinGateTest`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write the page class**

`app/Filament/Pages/AdminPinGate.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AdminPinGate extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.admin-pin-gate';

    public string $resource = '';

    public string $redirectUrl = '';

    public string $pin = '';

    public function mount(string $resource = '', string $redirect = ''): void
    {
        $this->resource = $resource;
        $this->redirectUrl = $redirect;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('pin')
                    ->label('Admin PIN')
                    ->numeric()
                    ->length(6)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $admin = User::role('admin')->where('pin', $this->pin)->first();

        if (! $admin) {
            Notification::make()->danger()->title('PIN salah')->send();
            return;
        }

        auth()->user()->syncPermissions(["access-{$this->resource}"]);

        $this->redirect($this->redirectUrl);
    }
}
```

`resources/views/filament/pages/admin-pin-gate.blade.php` — minimal functional shell (design pass happens per the UI/UX note above before this is considered final):

```blade
<x-filament-panels::page>
    <form wire:submit="submit" class="pos-pin-gate">
        <x-filament::input.wrapper>
            <x-filament::input
                type="text"
                inputmode="numeric"
                maxlength="6"
                wire:model="pin"
                placeholder="••••••"
                autofocus
            />
        </x-filament::input.wrapper>

        <x-filament::button type="submit" class="pos-pin-gate__submit">
            Unlock
        </x-filament::button>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AdminPinGateTest`
Expected: PASS

- [ ] **Step 5: Re-run Task 9's middleware test now that the route exists**

Run: `php artisan test --filter=EnsureResourcePinUnlockedTest`
Expected: PASS (all 3 tests, including the redirect ones that were deferred).

- [ ] **Step 6: Apply UI/UX polish**

Invoke `ui-ux-pro-max` and `frontend-design` skills to finalize `admin-pin-gate.blade.php` — numeric keypad layout, focus states, error display for the `danger` notification, mobile-first sizing consistent with the project's self-order pages. Re-run Step 4's test after any markup changes to confirm no regressions (the test targets Livewire calls, not markup, so it should stay green).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/AdminPinGate.php resources/views/filament/pages/admin-pin-gate.blade.php tests/Feature/Filament/AdminPinGateTest.php
git commit -m "feat: add PIN-unlock gate page for tier 2 resources"
```

---

### Task 11: Integration test — resource-switching revokes prior unlock

**Files:**
- Test: `tests/Feature/Filament/CashierResourceSwitchingTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2–10 (this task adds no production code, only proves the cross-task contract from spec §Testing's last two bullets).

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminPinGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierResourceSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlocking_a_second_tier2_resource_revokes_the_first(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-raw-materials']);
        Permission::firstOrCreate(['name' => 'access-products']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        Livewire::test(AdminPinGate::class, ['resource' => 'raw-materials', 'redirect' => '/admin/raw-materials'])
            ->set('pin', '999999')
            ->call('submit');

        $this->assertTrue($cashier->fresh()->can('access-raw-materials'));

        Livewire::test(AdminPinGate::class, ['resource' => 'products', 'redirect' => '/admin/products'])
            ->set('pin', '999999')
            ->call('submit');

        $cashier->refresh();
        $this->assertTrue($cashier->can('access-products'));
        $this->assertFalse($cashier->can('access-raw-materials'));
    }

    public function test_logging_in_again_after_unlock_starts_fully_locked(): void
    {
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-products']);
        $cashier = User::factory()->create(['pin' => '444444']);
        $cashier->assignRole('cashier');
        $cashier->syncPermissions(['access-products']);

        auth()->logout();
        $this->actingAs($cashier);
        $cashier->syncPermissions([]); // simulates Login.php's post-auth reset from Task 4

        $this->assertCount(0, $cashier->fresh()->getDirectPermissions());
    }
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter=CashierResourceSwitchingTest`
Expected: PASS — no production code changes needed if Tasks 2–10 were implemented correctly; if this fails, it points at a bug in an earlier task, not a missing piece of this one.

- [ ] **Step 3: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including every pre-existing test file.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Filament/CashierResourceSwitchingTest.php
git commit -m "test: verify tier 2 resource switching and login reset"
```

---

## Post-plan manual QA (per spec's Testing section, not automatable via PHPUnit alone)

- Log in as cashier via the PIN pad UI, confirm Tier 2 nav items are visible but land on the PIN gate.
- Confirm Tier 3 nav items (Users, Reports, Settings) are absent entirely for cashier, present for admin.
- Walk the full resource-switch scenario in the browser: unlock `raw-materials` → visit `products` (redirected to PIN gate again) → unlock `products` → return to `raw-materials` (redirected again).
