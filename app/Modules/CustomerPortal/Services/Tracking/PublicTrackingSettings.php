<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Tracking;

use App\Modules\Organizations\Models\Organization;

/**
 * Ce que le lien public montre au client final, réglé par le transporteur.
 *
 * Pas de table : les réglages vivent dans `organizations.settings`, comme ceux
 * de la planification (`planning.loadingServiceCodes`).
 *
 * ```json
 * { "publicTracking": { "showMap": true, "linkLifetimeDays": 30 } }
 * ```
 *
 * **La carte est fermée par défaut.** Montrer la position d'un véhicule à qui
 * détient un lien — sans compte — est une décision du transporteur, pas un
 * effet de l'installation. Le reste est ouvert : statut, créneau, parcours et
 * ETA sont précisément ce que le lien sert à dire.
 *
 * **Aucune durée de vie par défaut.** Sans réglage, un lien vit jusqu'à sa
 * révocation : inventer « trente jours » serait une décision métier que
 * personne n'a prise.
 */
final readonly class PublicTrackingSettings
{
    public const string SETTING_PATH = 'publicTracking';

    /** @var array<string, bool> */
    private const array DEFAULTS = [
        'showReference' => true,
        'showStatus' => true,
        'showDeliveryWindow' => true,
        'showTimeline' => true,
        'showEta' => true,
        'showMap' => false,
    ];

    /**
     * @return array{showReference: bool, showStatus: bool, showDeliveryWindow: bool, showTimeline: bool, showEta: bool, showMap: bool}
     */
    public function visibility(Organization $organization): array
    {
        $settings = $this->settings($organization);
        $visibility = [];

        foreach (self::DEFAULTS as $key => $default) {
            $visibility[$key] = is_bool($settings[$key] ?? null) ? $settings[$key] : $default;
        }

        /** @var array{showReference: bool, showStatus: bool, showDeliveryWindow: bool, showTimeline: bool, showEta: bool, showMap: bool} $visibility */
        return $visibility;
    }

    /** Durée de vie d'un lien en jours, ou `null` : jusqu'à révocation. */
    public function linkLifetimeDays(Organization $organization): ?int
    {
        $days = $this->settings($organization)['linkLifetimeDays'] ?? null;

        return is_int($days) && $days > 0 ? $days : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(Organization $organization): array
    {
        $settings = data_get($organization->settings ?? [], self::SETTING_PATH, []);

        return is_array($settings) ? $settings : [];
    }
}
