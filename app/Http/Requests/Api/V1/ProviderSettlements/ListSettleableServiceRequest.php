<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProviderSettlements;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres du sélecteur de prestations à régler.
 *
 * Mêmes bornes que pour la facturation : un décompte couvre lui aussi une
 * période, et le §101 la fait choisir avant d'afficher les services.
 */
class ListSettleableServiceRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'periodFrom' => ['sometimes', 'date'],
            'periodTo' => ['sometimes', 'date', 'after_or_equal:periodFrom'],
        ]);
    }
}
