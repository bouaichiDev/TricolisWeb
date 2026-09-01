<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Integrations\Models\CustomerApiConfiguration;
use App\Modules\Orders\Models\Order;

/**
 * Le portail des clients : ce qu'une clé API ouvre, et ce qu'elle n'ouvre pas.
 *
 * Ces routes sont le seul endroit du projet où une requête n'est pas portée par
 * un utilisateur. Chaque refus vaut donc d'être vérifié : c'est le seul rempart
 * entre une clé volée et les données d'un transporteur.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();

    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->key = Str::random(64);
    $this->access = CustomerApiConfiguration::factory()->create([
        'customer_id' => $this->customer->id,
        'api_key_hash' => hash('sha256', $this->key),
        'permissions' => ['orders.view'],
        'is_active' => true,
    ]);

    $this->call = fn (string $path, ?string $key = null) => $this->getJson(
        $path,
        $key === null ? [] : ['X-Api-Key' => $key],
    );
});

describe('authentification par clé', function (): void {
    it('accepte une clé valide et dit pour qui elle vaut', function (): void {
        $response = ($this->call)('/api/v1/client/me', $this->key)->assertOk();

        expect($response->json('data.customer.id'))->toBe($this->customer->id)
            ->and($response->json('data.access.permissions'))->toBe(['orders.view']);
    })->group('client-api');

    it('refuse une requête sans clé', function (): void {
        ($this->call)('/api/v1/client/me')->assertUnauthorized();
    })->group('client-api');

    it('refuse une clé inconnue', function (): void {
        ($this->call)('/api/v1/client/me', Str::random(64))->assertUnauthorized();
    })->group('client-api');

    /**
     * Un accès désactivé répond comme un accès inconnu : distinguer les deux
     * dirait à un appelant qu'il détient une clé valide, seulement fermée.
     */
    it('refuse une clé désactivée sans dire qu’elle existe', function (): void {
        $this->access->update(['is_active' => false]);

        $response = ($this->call)('/api/v1/client/me', $this->key)->assertUnauthorized();

        expect($response->json('message'))->toBe('Clé API inconnue ou révoquée.');
    })->group('client-api');

    /** La clé ne circule jamais en clair : seule son empreinte est stockée. */
    it('ne stocke jamais la clé présentée', function (): void {
        ($this->call)('/api/v1/client/me', $this->key)->assertOk();

        $this->assertDatabaseMissing('customer_api_configurations', ['api_key_hash' => $this->key]);
    })->group('client-api');
});

describe('adresses autorisées', function (): void {
    /** Sans liste, aucune restriction — c'est ce que l'écran annonce. */
    it('laisse passer toute adresse quand la liste est vide', function (): void {
        $this->access->update(['allowed_ips' => null]);

        ($this->call)('/api/v1/client/me', $this->key)->assertOk();
    })->group('client-api');

    it('refuse une adresse hors de la liste', function (): void {
        $this->access->update(['allowed_ips' => ['203.0.113.7']]);

        ($this->call)('/api/v1/client/me', $this->key)->assertForbidden();
    })->group('client-api');

    /** Les tests s'exécutent depuis 127.0.0.1 : le bloc la contient. */
    it('accepte une adresse comprise dans un bloc CIDR', function (): void {
        $this->access->update(['allowed_ips' => ['127.0.0.0/8']]);

        ($this->call)('/api/v1/client/me', $this->key)->assertOk();
    })->group('client-api');

    it('refuse une adresse hors du bloc CIDR', function (): void {
        $this->access->update(['allowed_ips' => ['10.0.0.0/24']]);

        ($this->call)('/api/v1/client/me', $this->key)->assertForbidden();
    })->group('client-api');
});

describe('droits portés par la clé', function (): void {
    /**
     * `client/me` n'exige aucun droit : une clé sans permission doit pouvoir
     * constater qu'elle n'en a aucune, plutôt que de recevoir un 403 muet.
     */
    it('laisse une clé sans droit lire son identité', function (): void {
        $this->access->update(['permissions' => null]);

        ($this->call)('/api/v1/client/me', $this->key)->assertOk();
    })->group('client-api');

    it('refuse une route dont la clé n’a pas le droit', function (): void {
        $this->access->update(['permissions' => ['claims.view']]);

        ($this->call)('/api/v1/client/orders', $this->key)->assertForbidden();
    })->group('client-api');

    it('ouvre la route quand le droit est porté', function (): void {
        ($this->call)('/api/v1/client/orders', $this->key)->assertOk();
    })->group('client-api');
});

describe('cloisonnement des données', function (): void {
    /**
     * Le point critique. `OrderListQuery` scope par organisation et traite
     * `customerId` comme un filtre : brancher une clé dessus rendrait les
     * commandes de tous les clients. Ici l'appartenance est une contrainte.
     */
    it('ne rend que les commandes du client de la clé', function (): void {
        $mine = Order::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);

        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Order::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
        ]);

        $response = ($this->call)('/api/v1/client/orders', $this->key)->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.id'))->toBe($mine->id);
    })->group('client-api');

    /**
     * Une commande d'un autre client est **introuvable**, pas interdite :
     * répondre 403 confirmerait son existence et permettrait de l'énumérer.
     */
    it('rend introuvable la commande d’un autre client', function (): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Order::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
        ]);

        ($this->call)("/api/v1/client/orders/{$foreign->id}", $this->key)->assertNotFound();
    })->group('client-api');

    /** Un filtre passé par l'appelant ne doit pas élargir sa portée. */
    it('ignore un customerId fourni dans la requête', function (): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Order::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
        ]);

        $response = ($this->call)(
            "/api/v1/client/orders?customerId={$other->id}",
            $this->key,
        )->assertOk();

        expect($response->json('data'))->toBeEmpty();
    })->group('client-api');
});

describe('trace d’utilisation', function (): void {
    /**
     * `lastUsedAt` restait vide faute de lecteur. C'est ce middleware qui
     * l'écrit, et seulement pour les appels admis.
     */
    it('date le dernier appel admis', function (): void {
        expect($this->access->last_used_at)->toBeNull();

        ($this->call)('/api/v1/client/me', $this->key)->assertOk();

        expect($this->access->fresh()->last_used_at)->not->toBeNull();
    })->group('client-api');

    it('ne date pas un appel refusé', function (): void {
        $this->access->update(['allowed_ips' => ['203.0.113.7']]);

        ($this->call)('/api/v1/client/me', $this->key)->assertForbidden();

        expect($this->access->fresh()->last_used_at)->toBeNull();
    })->group('client-api');
});

describe('cloison avec l’administration', function (): void {
    /** Une clé cliente n'ouvre pas les routes de l'organisme. */
    it('ne donne pas accès aux routes d’administration', function (): void {
        $this->getJson('/api/v1/orders', [
            'X-Api-Key' => $this->key,
            'X-Organization-Id' => $this->organization->id,
        ])->assertUnauthorized();
    })->group('client-api');

    /** Et un jeton de session n'ouvre pas les routes clientes. */
    it('n’accepte pas un jeton de session à la place d’une clé', function (): void {
        $this->actingAs(authUser(), 'sanctum')
            ->getJson('/api/v1/client/me')
            ->assertUnauthorized();
    })->group('client-api');
});
