<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusPropagation;
use App\Modules\Statuses\Models\StatusTransition;
use App\Shared\Database\MorphMap;

/**
 * Un statut qui change entraîne ses voisins.
 *
 * Une commande, ses services, ses colis et ses lignes avancent ensemble : quand
 * tous les colis d'un service sont chargés, le service l'est ; quand le service
 * est effectué, ses colis le sont. La règle est déclarée par l'administrateur,
 * jamais écrite dans le code.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();

    $this->order = Order::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'draft',
    ]);
    $this->service = OrderService::factory()->create([
        'order_id' => $this->order->id,
        'status' => 'draft',
    ]);
    $this->packages = Package::factory()->count(2)->create([
        'order_id' => $this->order->id,
        'status' => 'created',
    ]);

    foreach ($this->packages as $package) {
        $this->service->servicePackages()->create(['package_id' => $package->id, 'quantity' => 1]);
    }

    /** Un statut du référentiel, créé au besoin. */
    $this->status = function (string $source, string $code): Status {
        return Status::where('source', $source)->where('code', $code)->first()
            ?? Status::factory()->forSource($source)->create(['code' => $code, 'label' => $code]);
    };

    /** La règle : « ce statut-ci entraîne celui-là ». */
    $this->rule = fn (string $fromSource, string $fromCode, string $toSource, string $toCode, string $mode = 'all') => StatusPropagation::create([
        'from_status_id' => ($this->status)($fromSource, $fromCode)->id,
        'to_status_id' => ($this->status)($toSource, $toCode)->id,
        'mode' => $mode,
    ]);
});

describe('vers le contenant', function (): void {
    it('attend que tous les colis y soient', function (): void {
        ($this->rule)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress');

        $this->packages->first()->update(['status' => 'loaded']);

        expect($this->service->fresh()->status->value)->toBe('draft');

        $this->packages->last()->update(['status' => 'loaded']);

        expect($this->service->fresh()->status->value)->toBe('in_progress');
    });

    it('se contente d’un seul en mode « any »', function (): void {
        ($this->rule)(MorphMap::PACKAGE, 'loaded', MorphMap::ORDER_SERVICE, 'in_progress', 'any');

        $this->packages->first()->update(['status' => 'loaded']);

        expect($this->service->fresh()->status->value)->toBe('in_progress');
    });

    /** Colis → service → commande, d'un seul mouvement. */
    it('remonte la chaîne jusqu’à la commande', function (): void {
        ($this->rule)(MorphMap::PACKAGE, 'delivered', MorphMap::ORDER_SERVICE, 'completed');
        ($this->rule)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::ORDER, 'completed');

        // La commande passe par son Action : la transition doit exister.
        StatusTransition::create([
            'from_status_id' => ($this->status)(MorphMap::ORDER, 'draft')->id,
            'to_status_id' => ($this->status)(MorphMap::ORDER, 'completed')->id,
            'is_manual' => false,
        ]);

        $this->packages->each(fn (Package $package) => $package->update(['status' => 'delivered']));

        expect($this->service->fresh()->status->value)->toBe('completed')
            ->and($this->order->fresh()->status->value)->toBe('completed');
    });

    /**
     * La commande garde son cycle de vie : une transition que l'administrateur
     * n'a pas déclarée n'est pas posée, et rien n'échoue pour autant.
     */
    it('ne force pas une transition de commande non déclarée', function (): void {
        ($this->rule)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::ORDER, 'invoiced');

        $this->service->update(['status' => 'completed']);

        expect($this->order->fresh()->status->value)->toBe('draft');
    });
});

describe('vers le contenu', function (): void {
    it('applique le statut du service à ses colis', function (): void {
        ($this->rule)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::PACKAGE, 'delivered');

        $this->service->update(['status' => 'completed']);

        expect($this->packages->map(fn (Package $p) => $p->fresh()->status)->all())
            ->toBe(['delivered', 'delivered']);
    });

    it('applique le statut de la commande à ses lignes', function (): void {
        $line = OrderLine::factory()->create(['order_id' => $this->order->id, 'status' => 'active']);
        ($this->rule)(MorphMap::ORDER, 'cancelled', MorphMap::ORDER_LINE, 'cancelled');

        // Le semis la déclare déjà : la règle ne l'invente pas.
        StatusTransition::firstOrCreate([
            'from_status_id' => ($this->status)(MorphMap::ORDER, 'draft')->id,
            'to_status_id' => ($this->status)(MorphMap::ORDER, 'cancelled')->id,
        ], ['is_manual' => true]);

        $this->order->update(['status' => 'cancelled']);

        expect($line->fresh()->status)->toBe('cancelled');
    });
});

describe('les garde-fous', function (): void {
    /** Deux règles qui se répondent tourneraient sans fin. */
    it('ne boucle pas sur deux règles qui se répondent', function (): void {
        ($this->rule)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::PACKAGE, 'delivered');
        ($this->rule)(MorphMap::PACKAGE, 'delivered', MorphMap::ORDER_SERVICE, 'completed');

        $this->service->update(['status' => 'completed']);

        expect($this->service->fresh()->status->value)->toBe('completed')
            ->and($this->packages->first()->fresh()->status)->toBe('delivered');
    });

    it('ignore une règle désactivée', function (): void {
        ($this->rule)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::PACKAGE, 'delivered')
            ->update(['active' => false]);

        $this->service->update(['status' => 'completed']);

        expect($this->packages->first()->fresh()->status)->toBe('created');
    });

    /** Sans règle, rien ne bouge : c'est l'état du produit avant ce réglage. */
    it('ne touche à rien sans règle', function (): void {
        $this->service->update(['status' => 'completed']);

        expect($this->packages->first()->fresh()->status)->toBe('created')
            ->and($this->order->fresh()->status->value)->toBe('draft');
    });

    /** Un statut qui change tout seul doit pouvoir s'expliquer. */
    it('note dans le journal d’où vient le changement', function (): void {
        ($this->rule)(MorphMap::ORDER_SERVICE, 'completed', MorphMap::PACKAGE, 'delivered');

        $this->service->update(['status' => 'completed']);

        $log = AuditLog::where('entity_type', MorphMap::PACKAGE)
            ->where('entity_id', $this->packages->first()->id)
            ->where('action', 'status_changed')
            ->firstOrFail();

        expect($log->new_values['propagated_from'] ?? '')->toContain('order_service')
            ->and($log->new_values['status'])->toBe('delivered');
    });
});
