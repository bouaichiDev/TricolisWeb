<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Stock;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de correction de stock par le client (§19).
 *
 * Le client dit ce qu'il a constaté — quantité, motif, commentaire — sur un
 * de ses articles, à un emplacement ou pour tout l'article. La quantité
 * affichée, le statut, le client et le demandeur sont posés par le backend :
 * les envoyer est refusé.
 */
class StoreCustomerStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...array_fill_keys(['customerId', 'displayedQuantity', 'status', 'requestedBy', 'reviewedBy', 'reviewedAt', 'reviewComment', 'stockMovementId', 'quantity'], ['prohibited']),
            'stockItemId' => ['required', 'string', 'ulid'],
            'stockLocationId' => ['sometimes', 'nullable', 'string', 'ulid'],
            'requestedQuantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:255'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.prohibited' => 'Ce champ est géré par votre transporteur.',
            'requestedQuantity.required' => 'Indiquez la quantité constatée.',
            'requestedQuantity.decimal' => 'Trois décimales au plus.',
            'reason.required' => 'Indiquez le motif de la demande.',
        ];
    }
}
