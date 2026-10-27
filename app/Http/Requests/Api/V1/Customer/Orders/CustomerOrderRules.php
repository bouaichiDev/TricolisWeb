<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use App\Http\Requests\Api\V1\Customer\ServerControlledFields;
use App\Shared\Enums\ContactRole;
use Illuminate\Validation\Rule;

/**
 * Forme d'une commande saisie dans le portail — création et modification.
 *
 * Volontairement plus étroite que `StoreOrderRequest` : ni client, ni agence,
 * ni statut, ni prix, ni coût, ni remarque interne, ni code-barres. Ce que le
 * client ne saisit pas, le portail le déduit ou l'impose
 * (`CustomerOrderPayload`). Les règles métier profondes — article du bon
 * catalogue, adresse du bon client — restent dans les services.
 */
final class CustomerOrderRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            // Phase 7 : ce que le serveur impose est refusé, pas ignoré.
            ...ServerControlledFields::prohibit(ServerControlledFields::ORDER),
            'orderDate' => ['nullable', 'date'],
            'customerReference' => ['nullable', 'string', 'max:255'],
            'externalReference' => ['nullable', 'string', 'max:255'],
            'workerRemark' => ['nullable', 'string', 'max:2000'],

            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.catalogItemId' => ['nullable', 'string', 'ulid'],
            'lines.*.name' => ['required_without:lines.*.catalogItemId', 'nullable', 'string', 'max:255'],
            'lines.*.articleCode' => ['nullable', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.weight' => ['nullable', 'numeric', 'min:0'],
            'lines.*.volume' => ['nullable', 'numeric', 'min:0'],
            'lines.*.length' => ['nullable', 'numeric', 'min:0'],
            'lines.*.width' => ['nullable', 'numeric', 'min:0'],
            'lines.*.height' => ['nullable', 'numeric', 'min:0'],

            'packages' => ['nullable', 'array', 'max:200'],
            'packages.*.key' => ['required', 'string', 'max:64', 'distinct'],
            'packages.*.packageTypeId' => ['nullable', 'string', 'ulid'],
            'packages.*.groupingTypeId' => ['nullable', 'string', 'ulid'],
            'packages.*.reference' => ['nullable', 'string', 'max:255'],
            'packages.*.description' => ['nullable', 'string', 'max:2000'],
            'packages.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'packages.*.weight' => ['nullable', 'numeric', 'min:0'],
            'packages.*.volume' => ['nullable', 'numeric', 'min:0'],
            'packages.*.length' => ['nullable', 'numeric', 'min:0'],
            'packages.*.width' => ['nullable', 'numeric', 'min:0'],
            'packages.*.height' => ['nullable', 'numeric', 'min:0'],
            'packages.*.lines' => ['nullable', 'array'],
            'packages.*.lines.*.lineKey' => ['required', 'string', 'max:64'],
            'packages.*.lines.*.quantity' => ['required', 'numeric', 'gt:0'],

            'services' => ['required', 'array', 'min:1', 'max:20'],
            'services.*.serviceId' => ['required', 'string', 'ulid'],
            'services.*.addressId' => ['nullable', 'required_without:services.*.address', 'string', 'ulid'],
            'services.*.address' => ['nullable', 'required_without:services.*.addressId', 'array'],
            'services.*.address.company' => ['nullable', 'string', 'max:255'],
            // Le destinataire devient un contact : prénom et nom sont obligatoires en base.
            'services.*.address.firstName' => ['required_with:services.*.address', 'string', 'max:255'],
            'services.*.address.lastName' => ['required_with:services.*.address', 'string', 'max:255'],
            'services.*.address.addressLine1' => ['required_with:services.*.address', 'string', 'max:255'],
            'services.*.address.addressLine2' => ['nullable', 'string', 'max:255'],
            'services.*.address.postalCode' => ['required_with:services.*.address', 'string', 'max:20'],
            'services.*.address.city' => ['required_with:services.*.address', 'string', 'max:255'],
            'services.*.address.country' => ['required_with:services.*.address', 'string', 'size:2'],
            'services.*.address.instructions' => ['nullable', 'string', 'max:2000'],
            'services.*.address.phone' => ['nullable', 'string', 'max:64'],
            'services.*.address.mobile' => ['nullable', 'string', 'max:64'],
            'services.*.address.email' => ['nullable', 'email', 'max:255'],
            'services.*.requestedDate' => ['required', 'date'],
            'services.*.requestedFrom' => ['nullable', 'date_format:H:i'],
            'services.*.requestedTo' => ['nullable', 'date_format:H:i'],
            'services.*.instructions' => ['nullable', 'string', 'max:2000'],
            'services.*.contacts' => ['nullable', 'array', 'max:5'],
            'services.*.contacts.*.contactRole' => ['nullable', Rule::enum(ContactRole::class)],
            'services.*.contacts.*.isPrimary' => ['nullable', 'boolean'],
            'services.*.contacts.*.firstName' => ['required', 'string', 'max:255'],
            'services.*.contacts.*.lastName' => ['nullable', 'string', 'max:255'],
            'services.*.contacts.*.phone' => ['nullable', 'string', 'max:64'],
            'services.*.contacts.*.mobile' => ['nullable', 'string', 'max:64'],
            'services.*.contacts.*.email' => ['nullable', 'email', 'max:255'],
            'services.*.packages' => ['nullable', 'array'],
            'services.*.packages.*.packageKey' => ['required', 'string', 'max:64'],
            'services.*.packages.*.quantity' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Un créneau se lit « de … à … » : la fin ne précède pas le début.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function windowErrors(array $data): array
    {
        $errors = [];

        foreach ($data['services'] ?? [] as $index => $service) {
            $from = $service['requestedFrom'] ?? null;
            $to = $service['requestedTo'] ?? null;

            if (is_string($from) && is_string($to) && $to <= $from) {
                $errors["services.$index.requestedTo"] = 'La fin du créneau doit suivre son début.';
            }
        }

        return $errors;
    }
}
