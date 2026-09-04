<?php

declare(strict_types=1);

namespace App\Shared\Database;

use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\CustomerSite;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Résout la cible d'une liaison polymorphe et vérifie qu'elle appartient bien
 * à l'organisation active.
 *
 * Partagé par les liaisons d'adresses, de contacts et de documents : c'est le
 * seul endroit où l'appartenance organisationnelle d'une cible est décidée.
 */
final class EntityLinkResolver
{
    /** @return array{entity_type: string, entity_id: string} */
    public function resolve(?string $entityType, ?string $entityId, string $organizationId): array
    {
        $type = $entityType ?? MorphMap::ORGANIZATION;
        $model = $this->resolveModel($type, $entityId ?? $organizationId, $organizationId);

        return ['entity_type' => $type, 'entity_id' => (string) $model->getKey()];
    }

    /**
     * Résout la cible, ou `null` lorsque aucune liaison n'est demandée.
     */
    public function resolveOptional(?string $entityType, ?string $entityId, string $organizationId): ?Model
    {
        if ($entityType === null || $entityId === null) {
            return null;
        }

        return $this->resolveModel($entityType, $entityId, $organizationId);
    }

    public function resolveModel(string $entityType, string $entityId, string $organizationId): Model
    {
        $class = MorphMap::class($entityType);

        if ($class === null) {
            throw new ModelNotFoundException('L’entité cible est introuvable.');
        }

        /** @var Model|null $model */
        $model = $class::find($entityId);

        if ($model === null) {
            throw new ModelNotFoundException('L’entité cible est introuvable.');
        }

        if (! $this->belongsToOrganization($model, $organizationId)) {
            throw new AuthorizationException('L’entité cible n’appartient pas à l’organisation active.');
        }

        return $model;
    }

    /**
     * Une cible appartient a l'organisation active si elle en porte
     * l'identifiant, ou si celle qui la porte est atteignable.
     *
     * Le cas general vient en dernier et couvre toute entite dotee d'une
     * colonne `organization_id` — commande, colis, reclamation, tournee. Une
     * liste fermee aurait refuse chaque nouvelle entite en silence, avec un
     * message d'appartenance qui aurait fait chercher du cote des droits.
     *
     * Une entite sans cette colonne et sans cas dedie reste refusee : rien
     * n'etablit alors son appartenance, et la deviner serait pire que refuser.
     */
    private function belongsToOrganization(Model $model, string $organizationId): bool
    {
        return match (true) {
            $model instanceof Organization => $model->id === $organizationId,
            $model instanceof Depot => $model->agency->organization_id === $organizationId,
            $model instanceof CustomerSite => $model->customer->organization_id === $organizationId,
            $model instanceof User => $model->organizationUsers()->where('organization_id', $organizationId)->exists(),
            default => $model->getAttribute('organization_id') === $organizationId,
        };
    }
}
