<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\DTOs\CreateOrderCommunicationData;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\CommunicationTemplate;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Services\CommunicationScopeGuard;
use App\Modules\Communications\Services\CommunicationTemplateRenderer;
use App\Modules\Communications\Services\RenderedContent;
use App\Modules\Communications\Services\ResolveOrderCommunicationRecipient;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création d'une communication de commande.
 *
 * Quatre origines possibles, toutes couvertes : depuis un template, depuis une
 * règle, manuelle sans template, ou programmée. Le déroulé est identique —
 * vérifier le périmètre, résoudre le destinataire, produire le contenu, le
 * figer.
 *
 * Le statut initial n'est **jamais inventé** (§23) : `SCHEDULED` si une date est
 * fournie, `DRAFT` sinon. Aucune autre valeur n'est posée ici.
 */
final readonly class CreateOrderCommunicationAction
{
    /** @var list<string> */
    private const array AUDITED = [
        'order_id', 'template_id', 'communication_rule_id', 'channel', 'communication_type',
        'recipient_role', 'recipient_name', 'status', 'scheduled_at',
    ];

    public function __construct(
        private CommunicationScopeGuard $guard,
        private ResolveOrderCommunicationRecipient $recipients,
        private CommunicationTemplateRenderer $renderer,
        private WriteCommunicationAudit $writer,
    ) {}

    public function execute(CreateOrderCommunicationData $data, AuditContext $context): OrderCommunication
    {
        $organizationId = $context->organizationId;

        $order = $this->guard->order($data->orderId, $organizationId);
        $rule = $data->communicationRuleId === null
            ? null
            : $this->guard->rule($data->communicationRuleId, $organizationId);
        $template = $this->resolveTemplate($data, $rule, $organizationId);

        if ($template instanceof CommunicationTemplate) {
            $this->guard->ruleMatchesTemplateService($template, $rule?->service_id);
        }

        $recipient = $this->recipients->resolve(
            $data->recipientRole,
            $order,
            $context->user,
            $data->explicitRecipient(),
        );

        $this->assertRecipientMatchesChannel($data, $recipient->email, $recipient->phone);

        $content = $this->buildContent($data, $template);

        $attributes = [
            'organization_id' => $organizationId,
            'order_id' => $order->id,
            'template_id' => $template?->id,
            'communication_rule_id' => $rule?->id,
            'channel' => $data->channel,
            'communication_type' => $data->communicationType,
            'recipient_role' => $data->recipientRole,
            ...$recipient->toAttributes(),
            'subject' => $content->subject,
            'body' => $content->body,
            'template_variables' => $content->variables === [] ? null : $content->variables,
            'status' => $data->scheduledAt === null ? CommunicationStatus::DRAFT : CommunicationStatus::SCHEDULED,
            'scheduled_at' => $data->scheduledAt,
            'created_by' => $context->user?->id,
        ];

        return DB::transaction(function () use ($attributes, $context): OrderCommunication {
            $communication = OrderCommunication::create($attributes)->refresh();

            $this->writer->created($communication, 'order_communication.created', self::AUDITED, $context);

            return $communication;
        });
    }

    /**
     * Le template vient du payload ou, à défaut, de la règle.
     *
     * Une règle porte toujours un template : l'en déduire évite de le répéter
     * dans le payload, et garantit qu'ils concordent.
     */
    private function resolveTemplate(
        CreateOrderCommunicationData $data,
        ?CommunicationRule $rule,
        string $organizationId,
    ): ?CommunicationTemplate {
        if ($data->templateId !== null) {
            return $this->guard->template($data->templateId, $organizationId);
        }

        return $rule?->template;
    }

    /**
     * Produit le contenu : rendu du template, ou saisie libre.
     *
     * Sans template, `body` est obligatoire — une communication sans corps n'a
     * rien à transmettre.
     */
    private function buildContent(CreateOrderCommunicationData $data, ?CommunicationTemplate $template): RenderedContent
    {
        if ($template instanceof CommunicationTemplate) {
            return $this->renderer->render($template, $data->templateVariables ?? []);
        }

        if ($data->body === null || trim($data->body) === '') {
            throw ValidationException::withMessages([
                'body' => ['Une communication sans modèle doit porter son propre corps de message.'],
            ]);
        }

        return new RenderedContent($data->subject, $data->body, $data->templateVariables ?? []);
    }

    /**
     * Le canal impose son champ de contact — jamais l'autre.
     *
     * Le §20 l'ordonne dans les deux sens : l'e-mail n'est pas exigé pour un
     * SMS, ni le téléphone pour un e-mail.
     */
    private function assertRecipientMatchesChannel(
        CreateOrderCommunicationData $data,
        ?string $email,
        ?string $phone,
    ): void {
        if ($data->channel->requiresEmail() && ($email === null || $email === '')) {
            throw ValidationException::withMessages([
                'recipientEmail' => ['Le canal e-mail exige une adresse pour ce destinataire.'],
            ]);
        }

        if ($data->channel->requiresPhone() && ($phone === null || $phone === '')) {
            throw ValidationException::withMessages([
                'recipientPhone' => ['Ce canal exige un numéro de téléphone pour ce destinataire.'],
            ]);
        }
    }
}
