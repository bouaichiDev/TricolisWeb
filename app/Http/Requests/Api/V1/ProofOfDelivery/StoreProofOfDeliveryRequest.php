<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProofOfDelivery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une preuve de livraison.
 *
 * `signatureDocumentId` et `photoDocumentId` sont des identifiants de documents
 * **déjà créés** : aucun fichier n'est reçu ici. Le §13 l'exige, et le module
 * Documents fait déjà ce travail.
 *
 * Les deux sont facultatifs et peuvent désigner le même document : le §11
 * interdit d'imposer qu'ils diffèrent sans besoin métier documenté.
 */
class StoreProofOfDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sur la route imbriquée `/orders/{order}/proofs-of-delivery`, la commande
     * vient de l'URL. L'injecter avant validation évite d'assouplir la règle
     * `required` — et interdit de fournir une commande différente dans le corps.
     */
    protected function prepareForValidation(): void
    {
        $order = $this->route('order');

        if ($order !== null) {
            $this->merge(['orderId' => $order->id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'orderId' => ['required', 'ulid'],
            'orderServiceId' => ['nullable', 'ulid'],
            'tourStopId' => ['nullable', 'ulid'],
            'recipientName' => ['required', 'string', 'max:255'],
            'signatureDocumentId' => ['nullable', 'ulid'],
            'photoDocumentId' => ['nullable', 'ulid'],
            'remark' => ['nullable', 'string'],
            'deliveredAt' => ['required', 'date'],
        ];
    }
}
