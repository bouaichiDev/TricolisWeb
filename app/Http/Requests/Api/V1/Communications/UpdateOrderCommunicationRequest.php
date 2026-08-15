<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une communication en brouillon.
 *
 * La liste est volontairement courte : ni commande, ni canal, ni modèle, ni
 * champ d'exécution. Changer de commande ou de canal reviendrait à créer une
 * autre communication.
 */
class UpdateOrderCommunicationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipientName' => ['sometimes', 'string', 'max:255'],
            'recipientEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            'recipientPhone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'body' => ['sometimes', 'string'],
            'templateVariables' => ['sometimes', 'nullable', 'array'],
            'scheduledAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
