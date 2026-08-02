<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Service;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie le périmètre organisationnel des références d'une communication.
 *
 * Le §32 impose la vérification en chaîne : template, règle, commande, service
 * et document doivent tous appartenir à l'organisation active, et la règle doit
 * être cohérente avec son template.
 *
 * Les refus sont des 422 nommant le champ fautif : la ressource existe peut-être
 * ailleurs, mais elle n'est pas utilisable ici. Les 404 sont réservés à l'accès
 * direct par URL, traité par le trait de contrôleur.
 */
final readonly class CommunicationScopeGuard
{
    public function service(?string $serviceId, string $organizationId): ?Service
    {
        if ($serviceId === null) {
            return null;
        }

        $service = Service::where('organization_id', $organizationId)->whereKey($serviceId)->first();

        return $service ?? $this->fail('serviceId', 'Ce service n’appartient pas à l’organisation active.');
    }

    public function template(string $templateId, string $organizationId): CommunicationTemplate
    {
        $template = CommunicationTemplate::inOrganization($organizationId)->whereKey($templateId)->first();

        return $template ?? $this->fail('templateId', 'Ce modèle de message n’appartient pas à l’organisation active.');
    }

    public function rule(string $ruleId, string $organizationId): CommunicationRule
    {
        $rule = CommunicationRule::inOrganization($organizationId)->whereKey($ruleId)->first();

        return $rule ?? $this->fail('communicationRuleId', 'Cette règle n’appartient pas à l’organisation active.');
    }

    public function order(string $orderId, string $organizationId): Order
    {
        $order = Order::where('organization_id', $organizationId)->whereKey($orderId)->first();

        return $order ?? $this->fail('orderId', 'Cette commande n’appartient pas à l’organisation active.');
    }

    public function document(string $documentId, string $organizationId): Document
    {
        $document = Document::where('organization_id', $organizationId)->whereKey($documentId)->first();

        return $document ?? $this->fail('documentId', 'Ce document n’appartient pas à l’organisation active.');
    }

    /**
     * Cohérence du service entre une règle et son template.
     *
     * Si le template est restreint à un service, la règle ne peut pas viser un
     * autre service : elle produirait un message conçu pour une prestation
     * différente. Une règle sans service hérite du périmètre du template, ce qui
     * reste cohérent.
     */
    public function ruleMatchesTemplateService(CommunicationTemplate $template, ?string $ruleServiceId): void
    {
        if ($template->service_id === null || $ruleServiceId === null) {
            return;
        }

        if ($template->service_id !== $ruleServiceId) {
            $this->fail('serviceId', 'Ce modèle est réservé à un autre service que celui de la règle.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
