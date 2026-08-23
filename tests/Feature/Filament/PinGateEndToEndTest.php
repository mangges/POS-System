<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminPinGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the full redirect -> PIN gate -> unlock -> return loop over real
 * HTTP requests, not just Livewire::test()'s synthetic component params.
 *
 * This is the test that would have caught two Critical bugs found in final
 * review:
 *  - AdminPinGate::mount() never actually received resource/redirect from a
 *    real request's query string (Livewire::test()'s explicit params masked
 *    this in every other test).
 *  - EnsureResourcePinUnlocked passed an absolute URL as `redirect`, which
 *    safeRedirectUrl() always rejected, so the cashier could never land
 *    back on the resource they unlocked.
 */
class PinGateEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_pin_unlock_flow_works_over_real_http_requests(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Permission::firstOrCreate(['name' => 'access-categories']);
        User::factory()->create(['pin' => '999999'])->assignRole('admin');
        $cashier = User::factory()->create(['pin' => '222222']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        // 1. Real request to a Tier-2 resource the cashier can't access yet.
        $blocked = $this->get('/admin/categories');
        $blocked->assertRedirect();
        $location = $blocked->headers->get('Location');
        $this->assertStringContainsString('resource=categories', $location);

        $query = [];
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('categories', $query['resource']);
        // Critical #2: the redirect value must be a bare relative path, not
        // an absolute URL (which safeRedirectUrl() would reject).
        $this->assertSame('/admin/categories', $query['redirect']);

        // 2. Real request to the PIN gate itself: mount() must actually pick
        // up resource/redirect from the query string (Critical #1).
        $gatePage = $this->get($location);
        $gatePage->assertOk();
        // wire:snapshot is rendered as an HTML attribute, so its JSON quotes
        // are HTML-entity-encoded (&quot;) in the raw response body.
        $this->assertStringContainsString(
            '&quot;resource&quot;:&quot;categories&quot;,&quot;redirectUrl&quot;:&quot;\/admin\/categories&quot;',
            $gatePage->getContent(),
        );

        // 3. Submit the correct admin PIN using the same resource/redirect
        // values the real request resolved, and confirm it redirects back
        // to the exact original resource URL (not the /admin fallback).
        Livewire::test(AdminPinGate::class, ['resource' => $query['resource'], 'redirect' => $query['redirect']])
            ->set('pin', '999999')
            ->call('submit')
            ->assertRedirect('/admin/categories');

        $this->assertTrue($cashier->fresh()->can('access-categories'));

        // 4. Real subsequent request to the resource now passes straight
        // through the middleware.
        $this->get('/admin/categories')->assertOk();
    }
}
