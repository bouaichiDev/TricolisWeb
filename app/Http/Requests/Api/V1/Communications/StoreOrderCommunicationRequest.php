<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Communications;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Orders\Models\Order;
use App\Modules\Templates\Enums\TemplateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une communication de commande.
 *
 * Aucun champ d'exécution n'est accepté : ni `status`, ni `sentAt`, ni
 * `providerMessageId`. Ils sont produits par les Actions et le Job ; les
 * accepter permettrait de déclarer envoyé ce qui ne l'est pas.
 *
 * Sur la route imbriquée `/orders/{order}/communications`, `orderId` est injecté
 * depuis l'URL **avant validation** : sans cela, la règle `required` échouerait
 * sur une donnée pourtant présente dans le chemin.
 */
class StoreOrderCommunicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $order = $this->route('order');

        if ($order instanceof Order) {
            $this->merge(['orderId' => $order->id]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orderId' => ['required', 'ulid'],
            'templateId' => ['sometimes', 'nullable', 'ulid'],
            'communicationRuleId' => ['sometimes', 'nullable', 'ulid'],
            'channel' => ['required', Rule::in(CommunicationChannel::values())],
            'communicationType' => ['required', Rule::in(TemplateType::values())],
            'recipientRole' => ['required', Rule::in(RecipientRole::values())],
            'recipientName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recipientEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            'recipientPhone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'body' => ['sometimes', 'nullable', 'string'],
            'templateVariables' => ['sometimes', 'nullable', 'array'],
            'scheduledAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
