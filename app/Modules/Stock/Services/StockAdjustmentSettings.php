<?php

declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Modules\Organizations\Models\Organization;

/**
 * Type du mouvement créé par l'acceptation d'une demande de correction.
 *
 * `movement_type` est une chaîne libre (`inbound`, `outbound`,
 * `order_confirmation` sont en usage) : le module de stock n'en énumère
 * aucun, et le type de correction n'est donc pas écrit en dur. Il vit dans
 * les réglages de l'organisation, comme `planning.loadingServiceCodes` :
 *
 * ```json
 * { "stock": { "adjustmentMovementType": "adjustment" } }
 * ```
 *
 * Sans réglage, `adjustment` : décidé le 2026-09-30 (Phase 5b du portail),
 * pour qu'une correction se distingue d'une réception ou d'une sortie dans
 * l'historique des mouvements. Le sens (entrée ou sortie) se lit sur la
 * source et la destination, pas sur le type.
 */
final readonly class StockAdjustmentSettings
{
    /** Chemin du réglage dans `organizations.settings`. */
    public const string SETTING_PATH = 'stock.adjustmentMovementType';

    public const string DEFAULT_MOVEMENT_TYPE = 'adjustment';

    public function movementType(string $organizationId): string
    {
        $settings = Organization::query()->select(['id', 'settings'])->find($organizationId)?->settings;
        $type = data_get(is_array($settings) ? $settings : [], self::SETTING_PATH);

        return is_string($type) && trim($type) !== '' ? trim($type) : self::DEFAULT_MOVEMENT_TYPE;
    }
}
