<?php

declare(strict_types=1);

namespace App\Modules\Templates\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie que client et service d'un modèle appartiennent à l'organisation.
 *
 * Le §105 impose la vérification en chaîne. Un modèle rattaché au client d'un
 * autre transporteur laisserait fuir un nom de client dans une liste, et
 * produirait des factures au nom de quelqu'un d'autre.
 *
 * Les refus sont des 422 nommant le champ fautif : la ressource existe
 * peut-être ailleurs, mais elle n'est pas utilisable ici. Les 404 restent
 * réservés à l'accès direct par URL.
 */
final readonly class TemplateScopeGuard
{
    public function service(?string $serviceId, string $organizationId): ?Service
    {
        if ($serviceId === null) {
            return null;
        }

        $service = Service::where('organization_id', $organizationId)->whereKey($serviceId)->first();

        return $service ?? $this->fail('serviceId', 'Ce service n’appartient pas à l’organisation active.');
    }

    public function customer(?string $customerId, string $organizationId): ?Customer
    {
        if ($customerId === null) {
            return null;
        }

        $customer = Customer::where('organization_id', $organizationId)->whereKey($customerId)->first();

        return $customer ?? $this->fail('customerId', 'Ce client n’appartient pas à l’organisation active.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
