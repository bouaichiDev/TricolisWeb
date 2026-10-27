<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\CustomerPortal\Models\PublicTrackingLink;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerPortal;
use Tests\Support\CustomerPortalOrders;

/**
 * Gestion du lien public depuis le portail (§22-§25, §33, §44).
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->ikea = CustomerPortalOrders::order('ikea');
    $this->url = "/api/v1/customer/orders/{$this->ikea->id}/public-tracking-link";
});

function tokenOf(string $publicUrl): string
{
    return substr($publicUrl, strrpos($publicUrl, '/') + 1);
}

it('generates a link and stores only the hash of its token', function (): void {
    $response = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->postJson($this->url)->assertCreated()
        ->assertJsonPath('data.state', 'active')
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.expiresAt', null);

    $token = tokenOf($response->json('data.publicUrl'));
    $link = PublicTrackingLink::where('order_id', $this->ikea->id)->sole();

    expect($response->json('data.publicUrl'))->toStartWith(rtrim(config('customer_portal.public_tracking.url'), '/').'/t/')
        ->and($token)->toMatch('/^[A-Za-z0-9]{64}$/')
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and(PublicTrackingLink::where('token_hash', $token)->exists())->toBeFalse()
        ->and($link->created_by)->toBe(CustomerPortal::member('admin@ikea.test')->user_id);
});

it('audits creation and revocation without the token', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $token = tokenOf($this->withHeaders($headers)->postJson($this->url)->json('data.publicUrl'));
    $this->withHeaders($headers)->deleteJson($this->url)->assertOk();

    $created = AuditLog::where('action', 'customer_portal.public_tracking_link_created')->sole();

    expect(AuditLog::where('action', 'customer_portal.public_tracking_link_revoked')->count())->toBe(1)
        ->and(json_encode($created->new_values))->not->toContain($token)
        ->and($created->new_values['orderId'])->toBe($this->ikea->id);
});

it('makes the previous token useless when regenerating', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $first = tokenOf($this->withHeaders($headers)->postJson($this->url)->json('data.publicUrl'));
    $second = tokenOf($this->withHeaders($headers)->postJson($this->url)->json('data.publicUrl'));

    expect($first)->not->toBe($second)
        ->and(PublicTrackingLink::where('order_id', $this->ikea->id)->count())->toBe(1);

    $this->getJson("/api/v1/public/tracking/{$first}")->assertNotFound();
    $this->getJson("/api/v1/public/tracking/{$second}")->assertOk();
});

it('shows the link state without ever returning the token again', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $this->withHeaders($headers)->getJson($this->url)->assertOk()->assertJsonPath('data.state', 'none');

    $this->withHeaders($headers)->postJson($this->url)->assertCreated();

    $this->withHeaders($headers)->getJson($this->url)->assertOk()
        ->assertJsonPath('data.state', 'active')
        ->assertJsonPath('data.publicUrl', null);
});

it('revokes the link immediately', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $token = tokenOf($this->withHeaders($headers)->postJson($this->url)->json('data.publicUrl'));
    $this->getJson("/api/v1/public/tracking/{$token}")->assertOk();

    $this->withHeaders($headers)->deleteJson($this->url)->assertOk()
        ->assertJsonPath('data.state', 'revoked')
        ->assertJsonPath('data.active', false);

    expect(PublicTrackingLink::where('order_id', $this->ikea->id)->sole())
        ->is_active->toBeFalse()
        ->revoked_at->not->toBeNull();
    $this->getJson("/api/v1/public/tracking/{$token}")->assertNotFound()->assertJsonPath('message', 'Tracking indisponible');
});

it('supports an expiry date, then expires', function (): void {
    $headers = CustomerPortal::headers('admin@ikea.test');
    $token = tokenOf($this->withHeaders($headers)->postJson($this->url, ['expiresAt' => now()->addDays(2)->toDateString()])
        ->assertCreated()->assertJsonPath('data.state', 'active')->json('data.publicUrl'));

    $this->travel(3)->days();

    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->getJson($this->url)->assertJsonPath('data.state', 'expired');
    $this->getJson("/api/v1/public/tracking/{$token}")->assertNotFound();
});

it('applies the lifetime set by the carrier', function (): void {
    $organization = CustomerPortal::customer('ikea')->organization;
    $organization->update(['settings' => [...($organization->settings ?? []), 'publicTracking' => ['linkLifetimeDays' => 10]]]);

    $expires = $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->postJson($this->url)->json('data.expiresAt');

    expect(now()->diffInDays($expires))->toBeGreaterThan(9.9)->toBeLessThan(10.1);
});

it('refuses a past expiry date', function (): void {
    $this->withHeaders(CustomerPortal::headers('admin@ikea.test'))->postJson($this->url, ['expiresAt' => now()->subDay()->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors('expiresAt');
});

it('requires the public link permissions', function (): void {
    // Le lecteur ne gère pas les liens publics : aucun rôle par défaut sauf l'administrateur.
    $viewer = CustomerPortal::headers('viewer@ikea.test');

    $this->withHeaders($viewer)->getJson($this->url)->assertForbidden();
    $this->withHeaders($viewer)->postJson($this->url)->assertForbidden();
    $this->withHeaders(CustomerPortal::headers('orders@ikea.test'))->deleteJson($this->url)->assertForbidden();
});

it('answers 404 for the link of another customer order', function (): void {
    $qoqa = CustomerPortalOrders::order('qoqa');
    $headers = CustomerPortal::headers('admin@ikea.test');

    $this->withHeaders($headers)->getJson("/api/v1/customer/orders/{$qoqa->id}/public-tracking-link")->assertNotFound();
    $this->withHeaders($headers)->postJson("/api/v1/customer/orders/{$qoqa->id}/public-tracking-link")->assertNotFound();
    $this->withHeaders($headers)->deleteJson("/api/v1/customer/orders/{$qoqa->id}/public-tracking-link")->assertNotFound();

    expect(PublicTrackingLink::where('order_id', $qoqa->id)->exists())->toBeFalse();
});
