<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Tours;

use App\Http\Requests\Api\V1\Provider\ProviderServerControlledFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Une preuve de livraison saisie au portail fournisseur.
 *
 * Le client choisit le service livré, le nom du réceptionnaire, la signature
 * et, au besoin, une photo et une remarque. Tout le reste vient du serveur :
 * l'heure de livraison, la commande (déduite du service), l'arrêt (celui de
 * la route) et les documents (créés à partir des fichiers reçus).
 *
 * `mimes` **et** `image` : le premier lit le type dans le contenu, le second
 * exclut le SVG, qui peut porter du script. Un faux PNG est donc refusé.
 */
class RecordProofRequest extends FormRequest
{
    public const array SERVER_FIELDS = ['deliveredAt', 'delivered_at', 'orderId', 'tourStopId', 'signatureDocumentId', 'photoDocumentId', 'createdBy'];

    private const array IMAGE = ['file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'];

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
            ...ProviderServerControlledFields::prohibit([...ProviderServerControlledFields::TENANT, ...self::SERVER_FIELDS]),
            'orderServiceId' => ['required', 'string', 'ulid'],
            'recipientName' => ['required', 'string', 'min:2', 'max:120'],
            'remark' => ['nullable', 'string', 'max:500'],
            'signature' => ['required', ...self::IMAGE],
            'photo' => ['nullable', ...self::IMAGE],
        ];
    }
}
