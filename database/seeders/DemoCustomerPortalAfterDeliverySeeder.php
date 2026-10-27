<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Statuses\Models\Status;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Seeder;

/**
 * Démonstration de l'après-livraison du portail (Phase 4) : de quoi créer une
 * réclamation depuis le portail.
 *
 * **Le statut `claim/open` est une donnée de démonstration.** Le référentiel
 * ne décrit aucun statut de réclamation, et le portail refuse d'en inventer
 * un : sans statut par défaut pour la source `claim`, il refuse la création
 * (`CLAIM_STATUS_NOT_CONFIGURED`). En production, c'est l'administrateur qui
 * le choisit (écran « Statuts par défaut »). Le code `open` est celui que la
 * fabrique des réclamations emploie déjà.
 *
 * Réservé aux environnements local et de test. Rejouable sans doublon.
 */
class DemoCustomerPortalAfterDeliverySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        Status::firstOrCreate(
            ['source' => MorphMap::CLAIM, 'code' => 'open'],
            ['status' => 1, 'label' => 'Ouverte', 'active' => true, 'is_default' => true, 'position' => 10],
        );
    }
}
