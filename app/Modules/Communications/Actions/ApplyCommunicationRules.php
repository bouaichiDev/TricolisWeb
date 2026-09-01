<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\DTOs\CreateOrderCommunicationData;
use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Services\CommunicationRuleConditionEvaluator;
use App\Modules\Communications\Services\OrderCommunicationContext;
use App\Modules\Orders\Models\Order;
use App\Shared\Support\AuditContext;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Un événement métier survient : quelles règles produisent quel message.
 *
 * L'enchaînement est celui du §52, dans cet ordre :
 *
 * ```text
 * événement → règles de l'organisation → même eventType → active → automatique
 *           → service compatible → conditions vraies → communication créée
 * ```
 *
 * **Une règle qui échoue n'en empêche aucune autre, et n'annule rien.** Un
 * template mal écrit ou un destinataire introuvable ne doit pas faire échouer
 * l'annulation d'une commande : l'échec est journalisé, et le reste continue.
 * L'inverse rendrait une opération métier tributaire d'un modèle de message.
 *
 * **Idempotence sans nouvelle colonne.** Le §53 interdit d'inventer un
 * `eventId`. La clé retenue est le couple déjà présent en base — `order_id` et
 * `communication_rule_id` : une règle ne produit qu'un message par commande.
 * C'est exact pour les trois événements câblés, qui ne surviennent qu'une fois
 * dans la vie d'une commande ; un rejeu de file ou un double-clic ne produit
 * donc pas de doublon.
 */
final readonly class ApplyCommunicationRules
{
    public function __construct(
        private CreateOrderCommunicationAction $create,
        private QueueOrderCommunicationAction $queue,
        private CommunicationRuleConditionEvaluator $evaluator,
        private OrderCommunicationContext $context,
    ) {}

    /**
     * @return list<OrderCommunication> les communications réellement produites
     */
    public function execute(Order $order, CommunicationEventType $event, AuditContext $audit): array
    {
        $rules = CommunicationRule::query()
            ->where('organization_id', $order->organization_id)
            ->where('event_type', $event->value)
            ->where('is_active', true)
            ->where('is_automatic', true)
            ->with('template')
            ->orderBy('created_at')
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $facts = $this->context->build($order);
        $services = $order->orderServices()->pluck('service_id')->all();
        $produced = [];

        foreach ($rules as $rule) {
            $communication = $this->applyOne($rule, $order, $facts, $services, $audit);

            if ($communication instanceof OrderCommunication) {
                $produced[] = $communication;
            }
        }

        return $produced;
    }

    /**
     * @param  array<string, scalar|null>  $facts
     * @param  list<string>  $services  services réellement portés par la commande
     */
    private function applyOne(
        CommunicationRule $rule,
        Order $order,
        array $facts,
        array $services,
        AuditContext $audit,
    ): ?OrderCommunication {
        // Une regle visant un service ne s'applique qu'aux commandes qui le
        // portent. Sans service, elle vaut pour toutes.
        if ($rule->service_id !== null && ! in_array($rule->service_id, $services, true)) {
            return null;
        }

        if (! $this->evaluator->passes($rule->conditions, $facts)) {
            return null;
        }

        if ($this->alreadyProduced($rule, $order)) {
            return null;
        }

        $template = $rule->template;

        if ($template === null || $template->channel === null) {
            $this->report($rule, $order, 'la règle vise un modèle sans canal d’envoi');

            return null;
        }

        try {
            $communication = $this->create->execute(
                new CreateOrderCommunicationData(
                    orderId: $order->id,
                    channel: $template->channel,
                    communicationType: $template->template_type,
                    recipientRole: $rule->recipient_role,
                    templateId: $template->id,
                    communicationRuleId: $rule->id,
                    subject: null,
                    body: null,
                    templateVariables: $this->context->forTemplate($facts, $template->declaredVariables()),
                    scheduledAt: $this->scheduledAt($rule),
                    recipientName: null,
                    recipientEmail: null,
                    recipientPhone: null,
                ),
                $audit,
            );
        } catch (Throwable $exception) {
            $this->report($rule, $order, $exception->getMessage());

            return null;
        }

        // Sans delai, le message part tout de suite. Avec, il reste SCHEDULED :
        // l'ordonnanceur le mettra en file a l'echeance.
        if ($communication->scheduled_at === null) {
            try {
                return $this->queue->execute($communication, $audit);
            } catch (Throwable $exception) {
                $this->report($rule, $order, $exception->getMessage());
            }
        }

        return $communication;
    }

    /**
     * Le délai de la règle, converti en date d'envoi.
     *
     * `null` quand il est nul : le message part immédiatement, et poser une
     * date égale à maintenant le ferait attendre le prochain passage de
     * l'ordonnanceur.
     */
    private function scheduledAt(CommunicationRule $rule): ?string
    {
        if ($rule->delay_value <= 0) {
            return null;
        }

        return CarbonInterval::make("{$rule->delay_value} {$rule->delay_unit}") === null
            ? null
            : now()->add("{$rule->delay_value} {$rule->delay_unit}")->toDateTimeString();
    }

    private function alreadyProduced(CommunicationRule $rule, Order $order): bool
    {
        return OrderCommunication::query()
            ->where('order_id', $order->id)
            ->where('communication_rule_id', $rule->id)
            ->exists();
    }

    /**
     * L'échec est écrit au journal technique, pas remonté à l'appelant.
     *
     * Il porte les identifiants et la raison, jamais le corps du message : le
     * §125 interdit d'y déverser un contenu personnel.
     */
    private function report(CommunicationRule $rule, Order $order, string $reason): void
    {
        Log::warning('La règle de communication n’a produit aucun message.', [
            'rule_id' => $rule->id,
            'order_id' => $order->id,
            'event_type' => $rule->event_type->value,
            'reason' => $reason,
        ]);
    }
}
