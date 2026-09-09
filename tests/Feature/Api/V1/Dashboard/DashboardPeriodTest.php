<?php

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\OrganizationUser;

/**
 * Le filtre de dates, et ce qu'il ne touche pas.
 *
 * Le tableau de bord répond d'abord à « où en est-on », ce qui n'a pas
 * d'intervalle : une commande à planifier l'est aujourd'hui, pas la semaine
 * dernière. Filtrer ces compteurs-là aurait rendu des chiffres justes sous des
 * titres qui les contredisent — et personne ne vérifie un chiffre qui a l'air
 * juste.
 *
 * Le partage est donc déclaré widget par widget dans le catalogue, et
 * `DashboardDataSources` fait en sorte qu'une source ne **puisse pas** voir la
 * période pour un widget qui ne l'a pas demandée. Ce fichier tient les deux
 * moitiés : ce que la période déplace, et ce qu'elle laisse en place.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->create(['is_owner' => false]);
    $this->user = $this->membership->user;

    $role = organizationRole($this->organization, 'exploitant');
    givePermissions($role, ['dashboard.view', 'orders.view']);
    giveRoles($this->organization->id, $this->user->id, [$role]);

    // Un widget de chaque bord. `customers_count` est du second : un
    // dénombrement compte ce qui **existe**, et « les clients du mois d'août »
    // ne veut rien dire — c'est pourquoi il ne porte pas la période, et
    // pourquoi l'écran le retire dès qu'on en règle une.
    givePermissions($role, ['dashboard.view', 'orders.view', 'customers.view']);

    RoleDashboardConfiguration::updateOrCreate(
        ['role_id' => $role->id],
        ['widgets' => [
            ['key' => 'orders_by_status', 'position' => 1],
            ['key' => 'customers_count', 'position' => 2],
        ]],
    );

    // Deux commandes prêtes, à un mois d'écart. Le même statut des deux côtés :
    // ce qui les sépare est leur date, et rien d'autre.
    foreach (['2026-08-10', '2026-09-08'] as $date) {
        Order::factory()->forOrganization($this->organization)->create([
            'order_date' => $date,
            'status' => OrderStatus::READY,
        ]);
    }
});

function widgets(array $query = []): array
{
    $response = test()->actingAs(test()->user, 'sanctum')->withHeaders(test()->headers)
        ->getJson('/api/v1/dashboard'.($query === [] ? '' : '?'.http_build_query($query)))
        ->assertOk();

    return collect($response->json('data.widgets'))->keyBy('key')->all();
}

describe('ce que la période déplace', function (): void {
    it('compte tout quand aucune période n’est demandée', function (): void {
        expect(widgets()['orders_by_status']['data']['series'])
            ->toBe([['code' => 'ready', 'value' => 2]]);
    });

    it('ne compte que l’intervalle demandé', function (): void {
        expect(widgets(['from' => '2026-09-01', 'to' => '2026-09-30'])['orders_by_status']['data']['series'])
            ->toBe([['code' => 'ready', 'value' => 1]]);
    });

    /**
     * La raison d'être des deux contextes : une carte sans date voit la même
     * chose quoi qu'on demande. Le serveur la rend quand même — c'est l'écran
     * qui la retire — mais sa valeur ne bouge pas d'un iota, ce qui est la seule
     * réponse honnête pour un dénombrement.
     */
    it('laisse intactes les cartes sans date', function (): void {
        $before = widgets()['customers_count']['data']['value'];

        expect(widgets(['from' => '2026-09-01', 'to' => '2026-09-30'])['customers_count']['data']['value'])
            ->toBe($before);
    });

    it('dit à l’écran laquelle des deux cartes il filtre', function (): void {
        $served = widgets();

        expect($served['orders_by_status']['periodAware'])->toBeTrue()
            ->and($served['customers_count']['periodAware'])->toBeFalse();
    });

    /**
     * Neuf cartes nomment un instant — « Commandes du jour ». Filtrées sur un
     * mois, elles comptaient juste et annonçaient faux : le serveur sert donc
     * une seconde clé de libellé, que l'écran emploie quand une période est là.
     */
    it('donne un second libellé aux cartes qui nomment le jour', function (): void {
        expect(widgets()['orders_by_status']['periodLabelKey'])
            ->toBe('dashboardWidgets.orders_by_status.periodLabel')
            ->and(widgets()['customers_count']['periodLabelKey'])->toBeNull();
    });

    it('rappelle la période sur laquelle il a compté', function (): void {
        $response = test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard?from=2026-09-01&to=2026-09-30')
            ->assertOk();

        expect($response->json('data.period'))->toBe(['from' => '2026-09-01', 'to' => '2026-09-30']);
        expect(widgets()['orders_by_status']['data']['series'])->not->toBeEmpty();
    });
});

describe('ce que la période refuse', function (): void {
    /**
     * Une seule borne n'est pas une période. Compléter la manquante — par
     * aujourd'hui, par le début du mois — aurait filtré sur un intervalle que
     * personne n'a saisi, et rendu des chiffres justes pour une question qui
     * n'a pas été posée.
     */
    it('refuse une borne sans l’autre', function (): void {
        test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard?from=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    });

    it('refuse une fin antérieure au début', function (): void {
        test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard?from=2026-09-30&to=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    });

    /**
     * Les graphes temporels tracent une colonne par jour : une année en
     * demanderait trois cent soixante-cinq. La borne est refusée avec son
     * motif, jamais rognée en silence — un tableau de bord qui répond à une
     * autre question que celle posée ne le dirait pas.
     */
    it('refuse une période plus longue qu’un trimestre', function (): void {
        test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard?from=2026-01-01&to=2026-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    });

    it('refuse une date qui n’en est pas une', function (): void {
        test()->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/dashboard?from=next+friday&to=2026-09-30')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    });
});
