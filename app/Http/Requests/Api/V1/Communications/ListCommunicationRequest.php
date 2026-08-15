<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres communs aux trois listes de communication.
 *
 * Les modèles, les règles et les communications partagent `serviceId`,
 * `channel`, `templateId` et `isActive` ; les filtres propres à chacun s'y
 * ajoutent. Trois Requests n'auraient dupliqué que des lignes identiques —
 * même raisonnement qu'à la Phase 8.
 */
class ListCommunicationRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'organizationId' => ['sometimes', 'ulid'],
            'serviceId' => ['sometimes', 'ulid'],
            'templateId' => ['sometimes', 'ulid'],
            'channel' => ['sometimes', 'string', 'max:32'],
            'isActive' => ['sometimes', 'boolean'],
            // Modèles
            'templateType' => ['sometimes', 'string', 'max:32'],
            'language' => ['sometimes', 'string', 'max:10'],
            'isDefault' => ['sometimes', 'boolean'],
            // Règles
            'eventType' => ['sometimes', 'string', 'max:32'],
            'recipientRole' => ['sometimes', 'string', 'max:32'],
            'delayUnit' => ['sometimes', 'string', 'max:16'],
            'isAutomatic' => ['sometimes', 'boolean'],
            // Communications
            'orderId' => ['sometimes', 'ulid'],
            'communicationRuleId' => ['sometimes', 'ulid'],
            'communicationType' => ['sometimes', 'string', 'max:32'],
            'createdBy' => ['sometimes', 'ulid'],
            'scheduledFrom' => ['sometimes', 'date'],
            'scheduledTo' => ['sometimes', 'date', 'after_or_equal:scheduledFrom'],
            'sentFrom' => ['sometimes', 'date'],
            'sentTo' => ['sometimes', 'date', 'after_or_equal:sentFrom'],
            'failedFrom' => ['sometimes', 'date'],
            'failedTo' => ['sometimes', 'date', 'after_or_equal:failedFrom'],
        ]);
    }
}
