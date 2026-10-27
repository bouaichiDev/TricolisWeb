<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Support\CustomerFeatureGate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tout ce que le portail doit savoir pour se dessiner : qui, pour quel
 * client, avec quels droits. Les permissions sont celles que le backend
 * appliquera — le frontend s'en sert pour masquer, jamais pour protéger.
 *
 * @property-read CurrentCustomerContext $resource
 */
class CustomerContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->resource;
        $membership = $context->membership()->loadMissing('roles');
        $customer = $context->customer()->loadMissing('organization');
        $user = $context->user();

        return [
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'preferredLanguage' => $user->preferred_language,
            ],
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'legalName' => $customer->legal_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'features' => CustomerFeatureGate::featuresOf($customer),
            ],
            'carrier' => [
                'id' => $customer->organization_id,
                'name' => $customer->organization?->name,
            ],
            'membership' => [
                'id' => $membership->id,
                'isOwner' => $membership->is_owner,
                'status' => $membership->status->value,
            ],
            'roles' => $membership->roles->map(static fn ($role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
            ])->values()->all(),
            'permissions' => $context->permissions(),
        ];
    }
}
