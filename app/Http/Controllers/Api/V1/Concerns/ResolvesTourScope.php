<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Modules\Planning\Services\DraftOwnership;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourPeriod;
use App\Modules\Tours\Models\TourPeriodAssignment;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;

/**
 * Vérifie la chaîne de parenté d'une ressource de planification.
 *
 * Seule la tournée porte `organization_id` : ses enfants tiennent leur périmètre
 * d'elle. Chaque route imbriquée doit donc vérifier deux choses — la tournée
 * appartient bien à l'organisation active, et l'enfant appartient bien à cette
 * tournée. Un identifiant valide sous un mauvais parent renvoie **404**, jamais
 * 403 : l'existence d'une ressource hors périmètre ne se révèle pas.
 *
 * `guardDraftOwner` ajoute la réservation du brouillon : tant qu'une tournée
 * est au brouillon, seul son créateur la modifie. C'est un **403** et non un
 * 404 — la tournée existe, elle est visible, elle est simplement en cours de
 * préparation par quelqu'un d'autre, et le dire évite qu'on la croie perdue.
 */
trait ResolvesTourScope
{
    protected function guardTour(Tour $tour): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($tour->organization_id === $organizationId, 404, 'Tournée introuvable.');

        return $organizationId;
    }

    /**
     * Refuse la modification d'un brouillon qui appartient à un autre.
     *
     * À appeler avant toute écriture sur une tournée ou l'un de ses enfants.
     * La lecture reste ouverte : voir la planification d'un collègue ne gêne
     * personne, la modifier en même temps que lui, si.
     */
    protected function guardDraftOwner(Tour $tour): void
    {
        /** @var DraftOwnership $ownership */
        $ownership = app(DraftOwnership::class);
        $userId = (string) request()->user()?->id;

        if ($ownership->canModify($tour, $userId)) {
            return;
        }

        $creator = $ownership->creatorOf($tour);
        $name = $creator === null
            ? 'un autre utilisateur'
            : trim($creator->first_name.' '.$creator->last_name);

        abort(403, sprintf('Planification en cours par %s. Cette tournée est en lecture seule.', $name));
    }

    protected function guardStop(Tour $tour, TourStop $stop): string
    {
        $organizationId = $this->guardTour($tour);
        abort_unless($stop->tour_id === $tour->id, 404, 'Arrêt introuvable.');

        return $organizationId;
    }

    protected function guardStopService(Tour $tour, TourStop $stop, TourStopService $service): string
    {
        $organizationId = $this->guardStop($tour, $stop);
        abort_unless($service->tour_stop_id === $stop->id, 404, 'Service planifié introuvable.');

        return $organizationId;
    }

    protected function guardPeriod(Tour $tour, TourPeriod $period): string
    {
        $organizationId = $this->guardTour($tour);
        abort_unless($period->tour_id === $tour->id, 404, 'Période introuvable.');

        return $organizationId;
    }

    protected function guardAssignment(Tour $tour, TourPeriod $period, TourPeriodAssignment $assignment): string
    {
        $organizationId = $this->guardPeriod($tour, $period);
        abort_unless($assignment->tour_period_id === $period->id, 404, 'Affectation introuvable.');

        return $organizationId;
    }
}
