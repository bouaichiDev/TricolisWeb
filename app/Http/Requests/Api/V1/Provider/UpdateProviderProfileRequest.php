<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;

/**
 * Le profil personnel du membre connecté — jamais le fournisseur lui-même,
 * dont la fiche reste au back-office.
 */
class UpdateProviderProfileRequest extends UpdateProfileRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [...ProviderServerControlledFields::prohibit(), ...parent::rules()];
    }
}
