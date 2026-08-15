<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders\Concerns;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrôles d'appartenance communs aux sous-ressources d'une commande.
 *
 * Les sous-ressources répondent 404 et non 403 lorsqu'elles sortent du
 * périmètre : un 403 confirmerait l'existence de l'identifiant.
 */
trait ResolvesOrderScope
{
    /**
     * Vérifie que la commande appartient à l'organisation active.
     */
    protected function guardOrder(Order $order): string
    {
        $organizationId = $this->requireOrganizationId();

        abort_unless($order->organization_id === $organizationId, 404, 'Commande introuvable.');

        return $organizationId;
    }

    /**
     * Vérifie qu'une sous-ressource appartient bien à la commande de l'URL.
     */
    protected function guardBelongsToOrder(Order $order, Model $child, string $label): void
    {
        abort_unless($child->getAttribute('order_id') === $order->id, 404, "$label introuvable pour cette commande.");
    }

    /**
     * Refuse la modification du contenu d'une commande verrouillée.
     *
     * Au-delà de `READY`, lignes, colis et services sont engagés auprès de
     * l'exploitation : les modifier fausserait la planification en cours.
     */
    protected function assertOrderIsEditable(Order $order): void
    {
        abort_unless(
            $order->allowsContentChanges(),
            409,
            sprintf('Le contenu d’une commande au statut « %s » ne peut plus être modifié.', $order->status->label()),
        );
    }
}
