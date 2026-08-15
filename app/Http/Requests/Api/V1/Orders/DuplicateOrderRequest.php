<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Options de duplication d'une commande.
 *
 * Les documents sont exclus par défaut : recopier une pièce justificative sur
 * une nouvelle commande demande une décision explicite.
 */
class DuplicateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['sometimes', 'boolean'],
            'packages' => ['sometimes', 'boolean'],
            'services' => ['sometimes', 'boolean'],
            'contacts' => ['sometimes', 'boolean'],
            'documents' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{lines: bool, packages: bool, services: bool, contacts: bool, documents: bool}
     */
    public function options(): array
    {
        return [
            'lines' => $this->boolean('lines', true),
            'packages' => $this->boolean('packages', true),
            'services' => $this->boolean('services', true),
            'contacts' => $this->boolean('contacts', true),
            'documents' => $this->boolean('documents', false),
        ];
    }
}
