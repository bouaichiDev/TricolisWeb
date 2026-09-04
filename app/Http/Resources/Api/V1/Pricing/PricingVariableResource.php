<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pricing;

use App\Modules\Pricing\Models\PricingVariable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une variable du catalogue.
 *
 * La source est rendue en clair — table et colonne — parce qu'un
 * administrateur qui écrit une formule doit savoir ce que `{P:poids}` va
 * chercher. Un identifiant opaque l'obligerait à demander.
 *
 * @mixin PricingVariable
 */
class PricingVariableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $source = $this->source();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'description' => $this->description,
            'kind' => $this->kind,
            'sourceKey' => $this->source_key,
            'sourceTable' => $source['table'] ?? null,
            'sourceColumn' => $source['column'] ?? null,
            'unit' => $this->unit,
            'position' => $this->position,
            'isActive' => $this->is_active,
        ];
    }
}
