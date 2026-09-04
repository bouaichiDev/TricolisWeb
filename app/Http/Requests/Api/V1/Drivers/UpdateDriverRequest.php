<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Drivers;

use App\Modules\Addresses\Models\Address;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Providers\Models\Provider;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use App\Shared\Http\Rules\ExistsInStatusReferential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $driver = $this->route('driver');
        // L'unicite du code s'apprecie chez le fournisseur cible, qui peut
        // changer dans la meme requete.
        $providerId = $this->input('providerId', $driver?->provider_id);

        return [
            // Facultatif : un transporteur emploie ses propres chauffeurs.
            'providerId' => [
                'sometimes', 'nullable', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'addressId' => [
                'sometimes', 'nullable', 'ulid',
                new BelongsToActiveOrganization(Address::class, 'entityAddresses', 'Cette adresse n’appartient pas à l’organisation active.'),
            ],
            'contactId' => [
                'sometimes', 'nullable', 'ulid',
                new BelongsToActiveOrganization(Contact::class, 'entityContacts', 'Ce contact n’appartient pas à l’organisation active.'),
            ],
            'code' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('drivers', 'code')->where('organization_id', $driver?->organization_id)->ignore($driver?->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32', new ExistsInStatusReferential('driver')],
        ];
    }
}
