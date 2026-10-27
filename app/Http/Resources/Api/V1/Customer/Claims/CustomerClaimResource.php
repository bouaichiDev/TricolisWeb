<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Claims;

use App\Modules\Claims\Models\Claim;
use Illuminate\Http\Request;

/**
 * Le détail d'une réclamation pour le client (§10) : la ligne de liste, plus
 * la description et ce que le membre peut encore en faire.
 *
 * Cause, décision, suivi, résultat, coût et responsable restent internes :
 * le diagramme les décrit, mais aucune règle ne prévoit de les communiquer.
 *
 * @mixin Claim
 */
class CustomerClaimResource extends CustomerClaimListResource
{
    /**
     * @param  array{canEdit: bool, canAttach: bool}  $actions
     */
    public function __construct(Claim $claim, private readonly array $actions)
    {
        parent::__construct($claim);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'actions' => $this->actions,
        ];
    }
}
