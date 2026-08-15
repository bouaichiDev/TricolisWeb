<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout d'une pièce jointe.
 *
 * Seul le document est accepté : les deux snapshots sont dérivés de lui à
 * l'ajout. Les accepter en entrée permettrait de joindre un document en le
 * présentant sous un autre nom ou un autre type.
 *
 * L'appartenance du document à l'organisation active est vérifiée par
 * `CommunicationScopeGuard` : un `exists` seul laisserait joindre le document
 * d'un autre transporteur.
 */
class StoreCommunicationAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'documentId' => ['required', 'ulid'],
        ];
    }
}
