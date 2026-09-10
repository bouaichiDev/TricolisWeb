<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Exceptions;

use RuntimeException;

/**
 * Aucun modèle de BL n'est employable pour ce service.
 *
 * Traduite en 409 par le contrôleur, jamais en 404 : la commande et le service
 * existent, c'est la **configuration** qui manque. Un 404 aurait envoyé
 * l'utilisateur chercher une commande disparue au lieu de créer son modèle.
 *
 * Le message nomme l'écran où corriger : « Modèles ». Dire seulement « aucun
 * modèle » laisse chercher où en créer un.
 */
final class NoDeliveryNoteTemplate extends RuntimeException
{
    public static function forService(string $serviceNumber): self
    {
        return new self(
            "Aucun modèle de bon de livraison actif ne s’applique au service « {$serviceNumber} ». "
            .'Créez-en un depuis « Modèles », général ou propre à ce client.'
        );
    }

    /** Le modèle demandé existe, mais ne peut pas servir ici. */
    public static function notApplicable(): self
    {
        return new self(
            'Ce modèle ne s’applique pas à ce service : il est inactif, appartient à un autre '
            .'client, ou vise une autre prestation.'
        );
    }
}
