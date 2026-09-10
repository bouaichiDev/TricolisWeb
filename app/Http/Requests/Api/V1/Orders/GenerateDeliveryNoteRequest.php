<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Génération d'un bon de livraison.
 *
 * `templateId` est **facultatif** : sans lui, la résolution habituelle choisit
 * — modèle du client s'il existe, sinon celui du transporteur. Son existence et
 * son applicabilité ne sont pas vérifiées ici mais par l'action, qui seule
 * connaît la portée : une règle `exists` aurait accepté le modèle d'un autre
 * client, et une règle plus fine aurait dupliqué cette logique.
 */
class GenerateDeliveryNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'templateId' => ['sometimes', 'nullable', 'ulid'],
        ];
    }
}
