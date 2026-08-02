<?php

declare(strict_types=1);

namespace App\Modules\Stock\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\Models\StockMovement;
use App\Modules\Stock\Services\StockBalanceLocker;
use App\Modules\Stock\Services\StockScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un mouvement de stock et déplace les quantités.
 *
 * La séquence du §20, dans une transaction : valider l'article, valider les
 * emplacements, verrouiller les soldes, contrôler la disponibilité, écrire le
 * mouvement, mettre à jour les soldes, auditer.
 *
 * Les soldes sont verrouillés **dans un ordre déterministe** — par identifiant
 * croissant — pour qu'un transfert A→B et un transfert B→A concurrents ne
 * s'interbloquent pas.
 *
 * Aucun type de mouvement n'est interprété : le diagramme n'en énumère aucun, et
 * le §21 interdit d'inventer une liste métier. Seules les règles structurelles
 * s'appliquent.
 */
final readonly class CreateStockMovementAction
{
    public function __construct(
        private StockScopeGuard $guard,
        private StockBalanceLocker $locker,
        private RecalculateStockBalance $balances,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateStockMovementData $data, AuditContext $context, string $now): StockMovement
    {
        $this->assertStructure($data);

        $item = $this->guard->stockItem($data->stockItemId, $context->organizationId);

        $source = $data->sourceLocationId !== null
            ? $this->guard->stockLocation($data->sourceLocationId, $context->organizationId, 'sourceLocationId')
            : null;

        $destination = $data->destinationLocationId !== null
            ? $this->guard->stockLocation($data->destinationLocationId, $context->organizationId, 'destinationLocationId')
            : null;

        if ($source !== null && $destination !== null && $source->depot_id !== $destination->depot_id) {
            throw ValidationException::withMessages([
                'destinationLocationId' => ['Un mouvement ne peut pas traverser deux dépôts : enregistrez une sortie puis une entrée.'],
            ]);
        }

        return DB::transaction(function () use ($data, $item, $source, $destination, $context, $now): StockMovement {
            // Ordre deterministe : evite l'interblocage entre A→B et B→A.
            $locations = array_filter([$source, $destination]);
            usort($locations, static fn ($a, $b): int => strcmp($a->id, $b->id));

            $locked = [];
            foreach ($locations as $location) {
                $locked[$location->id] = $this->locker->lockOrCreate($item, $location, $now);
            }

            if ($source !== null) {
                $this->balances->assertAvailable($locked[$source->id], $data->quantity);
                $this->balances->execute($locked[$source->id], '-'.$data->quantity, '0', $now);
            }

            if ($destination !== null) {
                $this->balances->execute($locked[$destination->id], $data->quantity, '0', $now);
            }

            $movement = StockMovement::create($data->toAttributes($context->user?->id, $now));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'stock_movement.created',
                $movement,
                null,
                $movement->only(['stock_item_id', 'source_location_id', 'destination_location_id', 'movement_type', 'quantity']),
                null,
                $context->ipAddress,
            );

            return $movement;
        });
    }

    /**
     * Les deux seules règles structurelles du §21.
     */
    private function assertStructure(CreateStockMovementData $data): void
    {
        if ($data->sourceLocationId === null && $data->destinationLocationId === null) {
            throw ValidationException::withMessages([
                'sourceLocationId' => ['Un mouvement doit avoir au moins une source ou une destination.'],
            ]);
        }

        if ($data->sourceLocationId !== null && $data->sourceLocationId === $data->destinationLocationId) {
            throw ValidationException::withMessages([
                'destinationLocationId' => ['La source et la destination doivent être différentes.'],
            ]);
        }
    }
}
