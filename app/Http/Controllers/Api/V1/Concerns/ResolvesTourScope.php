<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

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
 */
trait ResolvesTourScope
{
    protected function guardTour(Tour $tour): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($tour->organization_id === $organizationId, 404, 'Tournée introuvable.');

        return $organizationId;
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
