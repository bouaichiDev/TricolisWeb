<?php

use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Tours\Models\Tour;
use Illuminate\Support\Facades\DB;

/**
 * Budget de requêtes — §22.
 *
 * Un N+1 ne se voit pas dans une réponse : elle est correcte, seulement lente.
 * Ces tests comptent les requêtes réellement exécutées sur une liste **de vingt
 * éléments** et vérifient que ce nombre ne dépend pas du nombre de lignes.
 *
 * Le seuil retenu est volontairement généreux : il ne mesure pas une
 * optimisation, il attrape une régression. Vingt lignes qui coûtent vingt
 * requêtes de plus le franchiraient immédiatement.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->countQueries = function (string $url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson($url)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
});

describe('list endpoints do not scale with row count', function (): void {
    it('keeps a constant query budget on orders', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        Order::factory(3)->forOrganization($this->organization)->create(['customer_id' => $customer->id]);
        $few = ($this->countQueries)('/api/v1/orders');

        Order::factory(17)->forOrganization($this->organization)->create(['customer_id' => $customer->id]);
        $many = ($this->countQueries)('/api/v1/orders');

        // Le budget ne doit pas croître avec le nombre de lignes.
        expect($many)->toBe($few);
    });

    it('keeps a constant query budget on tours', function (): void {
        Tour::factory(3)->create(['organization_id' => $this->organization->id]);
        $few = ($this->countQueries)('/api/v1/tours');

        Tour::factory(17)->create(['organization_id' => $this->organization->id]);
        $many = ($this->countQueries)('/api/v1/tours');

        expect($many)->toBe($few);
    });

    it('keeps a constant query budget on communications', function (): void {
        $order = Order::factory()->forOrganization($this->organization)->create();

        OrderCommunication::factory(3)->forOrder($order)->create();
        $few = ($this->countQueries)('/api/v1/order-communications');

        OrderCommunication::factory(17)->forOrder($order)->create();
        $many = ($this->countQueries)('/api/v1/order-communications');

        expect($many)->toBe($few);
    });

    it('keeps a constant query budget on organization members', function (): void {
        // `OrganizationUserResource` lit `user` et `roles` sans `whenLoaded` :
        // sans le chargement anticipe du controleur, ce test verrait passer le
        // budget de quelques requetes a plus de quarante.
        OrganizationUser::factory(3)->forOrganization($this->organization)->create();
        $few = ($this->countQueries)('/api/v1/organization-users');

        OrganizationUser::factory(17)->forOrganization($this->organization)->create();
        $many = ($this->countQueries)('/api/v1/organization-users');

        expect($many)->toBe($few);
    });

    it('keeps a constant query budget on customers', function (): void {
        Customer::factory(3)->create(['organization_id' => $this->organization->id]);
        $few = ($this->countQueries)('/api/v1/customers');

        Customer::factory(17)->create(['organization_id' => $this->organization->id]);
        $many = ($this->countQueries)('/api/v1/customers');

        expect($many)->toBe($few);
    });
});
