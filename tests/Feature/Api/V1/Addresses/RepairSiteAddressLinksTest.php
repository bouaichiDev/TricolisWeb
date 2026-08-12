<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerSite;
use App\Shared\Database\MorphMap;

/**
 * Réparation des liaisons d'adresses de sites.
 *
 * Le frontend rattachait l'adresse d'un site au **client** : elle apparaissait
 * dans les adresses du client, à côté de ses adresses de livraison et de
 * facturation, alors qu'elle appartient au site.
 *
 * La règle de réparation est vérifiable : une adresse désignée par
 * `customer_sites.address_id` **est** l'adresse d'un site, donc une liaison
 * vers le client portant cette même adresse ne peut être que l'artefact du
 * défaut.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
});

/** Crée un site dont l'adresse porte la liaison fautive vers le client. */
function siteWithStrayLink(Customer $customer, string $organizationId): CustomerSite
{
    $address = Address::factory()->create();

    EntityAddress::create([
        'organization_id' => $organizationId,
        'address_id' => $address->id,
        'entity_type' => MorphMap::CUSTOMER,
        'entity_id' => $customer->id,
        'address_type' => 'delivery',
        'is_default' => false,
    ]);

    return CustomerSite::factory()->create([
        'customer_id' => $customer->id,
        'address_id' => $address->id,
    ]);
}

it('links the address to the site and drops the stray customer link', function (): void {
    $site = siteWithStrayLink($this->customer, $this->organization->id);

    $this->artisan('tricolis:repair-site-address-links')->assertSuccessful();

    $this->assertDatabaseHas('entity_addresses', [
        'address_id' => $site->address_id,
        'entity_type' => MorphMap::CUSTOMER_SITE,
        'entity_id' => $site->id,
    ]);

    $this->assertDatabaseMissing('entity_addresses', [
        'address_id' => $site->address_id,
        'entity_type' => MorphMap::CUSTOMER,
        'entity_id' => $this->customer->id,
    ]);
});

it('leaves the database untouched in dry run', function (): void {
    $site = siteWithStrayLink($this->customer, $this->organization->id);

    $this->artisan('tricolis:repair-site-address-links', ['--dry-run' => true])->assertSuccessful();

    $this->assertDatabaseHas('entity_addresses', [
        'address_id' => $site->address_id,
        'entity_type' => MorphMap::CUSTOMER,
    ]);

    $this->assertDatabaseMissing('entity_addresses', [
        'address_id' => $site->address_id,
        'entity_type' => MorphMap::CUSTOMER_SITE,
    ]);
});

/** Rejouable : une base déjà saine ne bouge plus. */
it('is idempotent', function (): void {
    $site = siteWithStrayLink($this->customer, $this->organization->id);

    $this->artisan('tricolis:repair-site-address-links')->assertSuccessful();
    $this->artisan('tricolis:repair-site-address-links')->assertSuccessful();

    expect(EntityAddress::where('address_id', $site->address_id)->count())->toBe(1);
});

/**
 * Une adresse du client qui n'est l'adresse d'aucun site n'est pas touchée :
 * la commande ne se fie qu'à `customer_sites.address_id`.
 */
it('leaves genuine customer addresses alone', function (): void {
    $address = Address::factory()->create();

    EntityAddress::create([
        'organization_id' => $this->organization->id,
        'address_id' => $address->id,
        'entity_type' => MorphMap::CUSTOMER,
        'entity_id' => $this->customer->id,
        'address_type' => 'billing',
        'is_default' => true,
    ]);

    $this->artisan('tricolis:repair-site-address-links')->assertSuccessful();

    $this->assertDatabaseHas('entity_addresses', [
        'address_id' => $address->id,
        'entity_type' => MorphMap::CUSTOMER,
        'address_type' => 'billing',
    ]);
});
