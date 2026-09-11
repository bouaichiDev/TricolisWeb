<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Claims;

use App\Modules\Statuses\Services\DefaultStatuses;
use App\Shared\Database\MorphMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une réclamation.
 *
 * Les champs de résolution — `decision`, `followUp`, `result`, `cost`,
 * `closedAt` — ne sont pas acceptés : le §15 interdit de les exiger à la
 * création, et une réclamation naît ouverte. Ils se renseignent par `PATCH`.
 *
 * Aucun `claimNumber` : le §14 le dit explicitement, le diagramme n'en contient
 * pas.
 */
class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sur la route imbriquée `/customers/{customer}/claims`, le client vient de
     * l'URL. L'injecter avant validation évite d'assouplir la règle `required`
     * — et interdit d'en désigner un autre dans le corps.
     */
    protected function prepareForValidation(): void
    {
        $customer = $this->route('customer');

        if ($customer !== null) {
            $this->merge(['customerId' => $customer->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customerId' => ['required', 'ulid'],
            'orderId' => ['nullable', 'ulid'],
            'orderServiceId' => ['nullable', 'ulid'],
            'tourId' => ['nullable', 'ulid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'claimType' => ['required', 'string', 'max:64'],
            'cause' => ['nullable', 'string', 'max:255'],
            // Obligatoire seulement si rien ne le fournit : ni statut par defaut
            // choisi dans le referentiel, ni valeur par defaut de la colonne.
            'status' => [
                Rule::requiredIf(static fn (): bool => app(DefaultStatuses::class)->chosen(MorphMap::CLAIM) === null
                    && DefaultStatuses::columnDefault('claims') === null),
                'string',
                'max:32',
            ],
            'responsibleUserId' => ['nullable', 'ulid'],
        ];
    }
}
