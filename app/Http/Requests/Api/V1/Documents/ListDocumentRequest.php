<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Documents;

use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Liste des documents, filtrable par entité rattachée.
 *
 * Sans ce filtre, seule la commande disposait d'une route imbriquée : les
 * pièces d'une réclamation, d'un client ou d'un site n'étaient listables
 * nulle part. Le lien existe pourtant — `DocumentLink` est polymorphe — il
 * n'était simplement pas interrogeable.
 *
 * `entityType` reste une chaîne : `EntityLinkResolver` tranche ce qui est
 * résolvable, et recopier ici la liste des alias la ferait diverger.
 */
class ListDocumentRequest extends ListRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'entityType' => ['sometimes', 'string', 'max:64'],
            'entityId' => ['sometimes', 'required_with:entityType', 'ulid'],
            'documentType' => ['sometimes', 'string', 'max:64'],
        ]);
    }
}
