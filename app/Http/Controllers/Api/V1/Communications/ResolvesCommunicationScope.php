<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Order;
use App\Modules\Templates\Models\Template;

/**
 * Vérifie qu'une ressource de communication relève de l'organisation active.
 *
 * Un identifiant valide hors périmètre renvoie **404**, jamais 403 : l'existence
 * d'une ressource appartenant à un autre transporteur ne se révèle pas. C'est la
 * convention des Phases 4 à 8.
 *
 * La pièce jointe tient son périmètre de sa communication : les deux liens sont
 * vérifiés — la communication est bien dans l'organisation, et la pièce est bien
 * de cette communication.
 */
trait ResolvesCommunicationScope
{
    protected function guardTemplate(Template $template): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($template->organization_id === $organizationId, 404, 'Modèle de message introuvable.');

        return $organizationId;
    }

    protected function guardRule(CommunicationRule $rule): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($rule->organization_id === $organizationId, 404, 'Règle de communication introuvable.');

        return $organizationId;
    }

    protected function guardCommunication(OrderCommunication $communication): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($communication->organization_id === $organizationId, 404, 'Communication introuvable.');

        return $organizationId;
    }

    protected function guardAttachment(OrderCommunication $communication, CommunicationAttachment $attachment): string
    {
        $organizationId = $this->guardCommunication($communication);
        abort_unless($attachment->communication_id === $communication->id, 404, 'Pièce jointe introuvable.');

        return $organizationId;
    }

    protected function guardOrder(Order $order): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($order->organization_id === $organizationId, 404, 'Commande introuvable.');

        return $organizationId;
    }
}
