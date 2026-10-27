<?php

use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Shared\Enums\OrganizationStatus;

/**
 * API client par clé (§34, §43, §55) : ce que la Phase 6 ajoute à l'existant
 * — un client fermé ferme ses clés, et le débit est plafonné par clé.
 */
beforeEach(function (): void {
    $this->seed();
    $this->customer = Customer::factory()->create(['organization_id' => authOrganization()->id]);
    $this->key = 'cle-api-de-test-'.str_repeat('x', 40);
    CustomerApiConfiguration::factory()->forCustomer($this->customer)->create([
        'api_key_hash' => app(ApiKeyGenerator::class)->hash($this->key),
        'permissions' => ['orders.view'],
        'allowed_ips' => null,
        'is_active' => true,
    ]);
});

it('ferme les accès API d’un client inactif ou bloqué', function (CustomerStatus $status): void {
    $this->customer->update(['status' => $status]);

    $this->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertForbidden();
})->with([CustomerStatus::INACTIVE, CustomerStatus::BLOCKED]);

it('plafonne le débit par clé', function (): void {
    config(['customer_integrations.api.rate_limit_per_minute' => 2]);

    $this->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertOk();
    $this->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertOk();
    $this->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertTooManyRequests();
});

it('ferme les accès API quand l’organisation du transporteur est suspendue ou fermée', function (OrganizationStatus $status): void {
    authOrganization()->update(['status' => $status]);

    $this->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertForbidden();
})->with([OrganizationStatus::SUSPENDED, OrganizationStatus::CLOSED]);

it('bloque une adresse qui essaie des clés à la chaîne, sans gêner les autres', function (): void {
    config(['customer_integrations.api.failed_attempts_per_minute' => 3]);

    foreach (range(1, 3) as $attempt) {
        $this->withHeaders(['X-Api-Key' => "fausse-cle-{$attempt}"])->getJson('/api/v1/client/me')->assertUnauthorized();
    }

    // Même la bonne clé attend : l'adresse est suspecte.
    $this->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Api-Key' => $this->key])->getJson('/api/v1/client/me')->assertOk();
});
