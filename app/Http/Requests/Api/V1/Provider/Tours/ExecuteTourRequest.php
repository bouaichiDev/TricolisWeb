<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Tours;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Une transition d'exécution ne prend aucune donnée du client : ni statut, ni
 * heure — le serveur les fixe. Seul le passage d'un arrêt exige un motif.
 */
class ExecuteTourRequest extends FormRequest
{
    public const array SERVER_TIMES = ['actualStartAt', 'actualEndAt', 'actualArrivalAt', 'actualDepartureAt', 'occurredAt'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = ProviderServerControlledFields::prohibit([...ProviderServerControlledFields::TENANT, ...self::SERVER_TIMES]);

        if ($this->route()?->getName() === 'provider.tours.stops.skip') {
            $rules['reason'] = ['required', 'string', 'min:3', 'max:500'];
        }

        return $rules;
    }
}
