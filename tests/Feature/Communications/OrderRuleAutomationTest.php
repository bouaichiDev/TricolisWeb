<?php

use App\Modules\Communications\Actions\ApplyCommunicationRules;
use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Actions\ChangeOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Templates\Enums\TemplateType;
use App\Modules\Templates\Models\Template;
use App\Shared\Support\AuditContext;

/**
 * De l'événement métier au message : le chaînon qui manquait.
 *
 * Les règles étaient enregistrées, validées, et leur évaluateur livré — mais
 * rien n'émettait les événements. Une règle « commande annulée » ne produisait
 * donc jamais rien, et l'utilisateur attendait un message qui ne partait pas.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();

    $this->template = fn (array $attributes = []): Template => Template::factory()->create(array_merge([
        'organization_id' => $this->organization->id,
        'channel' => CommunicationChannel::INTERNAL_NOTIFICATION,
        'template_type' => TemplateType::ORDER_CANCELLED,
        'subject_template' => null,
        'body_template' => 'Commande {{ order_number }} annulée.',
        'available_variables' => ['order_number'],
    ], $attributes));

    $this->rule = fn (Template $template, array $attributes = []): CommunicationRule => CommunicationRule::factory()
        ->create(array_merge([
            'organization_id' => $this->organization->id,
            'service_id' => null,
            'template_id' => $template->id,
            'event_type' => CommunicationEventType::ORDER_CANCELLED,
            'recipient_role' => RecipientRole::INTERNAL_USER,
            'delay_value' => 0,
            'delay_unit' => 'minutes',
            'conditions' => null,
            'is_automatic' => true,
            'is_active' => true,
        ], $attributes));

    $this->order = fn (): Order => Order::factory()->forOrganization($this->organization)->create([
        'status' => OrderStatus::DRAFT,
    ]);

    $this->cancel = fn (Order $order) => app(ChangeOrderStatus::class)
        ->execute($order, OrderStatus::CANCELLED, $this->user, null, 'Client injoignable');
});

describe('règle déclenchée par une annulation', function (): void {
    /**
     * Le statut d'arrivee depend du transporteur : la file tourne en synchrone
     * ici, et la notification interne aboutit dans la foulee. Ce que le test
     * verifie est qu'elle a **quitte le brouillon** — que la regle l'a bien
     * mise en file, et non laissee attendre une relecture.
     */
    it('produit une communication et la met en file', function (): void {
        $rule = ($this->rule)(($this->template)());
        $order = ($this->order)();

        ($this->cancel)($order);

        $communication = OrderCommunication::where('order_id', $order->id)->first();

        expect($communication)->not->toBeNull()
            ->and($communication->communication_rule_id)->toBe($rule->id)
            ->and($communication->queued_at)->not->toBeNull()
            ->and($communication->status)->not->toBe(CommunicationStatus::DRAFT);
    });

    /** Le corps est figé au moment de l'envoi, variables résolues. */
    it('rend le modèle avec les données de la commande', function (): void {
        ($this->rule)(($this->template)());
        $order = ($this->order)();

        ($this->cancel)($order);

        $communication = OrderCommunication::where('order_id', $order->id)->firstOrFail();

        expect($communication->body)->toBe("Commande {$order->order_number} annulée.")
            ->and($communication->template_variables)->toBe(['order_number' => $order->order_number]);
    });

    /** Le délai diffère l'envoi : la communication attend son heure. */
    it('programme l’envoi quand la règle porte un délai', function (): void {
        ($this->rule)(($this->template)(), ['delay_value' => 30, 'delay_unit' => 'minutes']);
        $order = ($this->order)();

        ($this->cancel)($order);

        $communication = OrderCommunication::where('order_id', $order->id)->firstOrFail();

        expect($communication->status)->toBe(CommunicationStatus::SCHEDULED)
            ->and($communication->scheduled_at)->not->toBeNull();
    });

    it('produit autant de messages que de règles compatibles', function (): void {
        ($this->rule)(($this->template)(['code' => 'A']));
        ($this->rule)(($this->template)(['code' => 'B']));
        $order = ($this->order)();

        ($this->cancel)($order);

        expect(OrderCommunication::where('order_id', $order->id)->count())->toBe(2);
    });
});

describe('ce qui ne déclenche rien', function (): void {
    it('ignore une règle inactive', function (): void {
        ($this->rule)(($this->template)(), ['is_active' => false]);
        $order = ($this->order)();

        ($this->cancel)($order);

        expect(OrderCommunication::where('order_id', $order->id)->exists())->toBeFalse();
    });

    it('ignore une règle non automatique', function (): void {
        ($this->rule)(($this->template)(), ['is_automatic' => false]);
        $order = ($this->order)();

        ($this->cancel)($order);

        expect(OrderCommunication::where('order_id', $order->id)->exists())->toBeFalse();
    });

    it('ignore une règle dont les conditions sont fausses', function (): void {
        ($this->rule)(($this->template)(), [
            'conditions' => ['all' => [['field' => 'order_type', 'operator' => 'eq', 'value' => 'jamais']]],
        ]);
        $order = ($this->order)();

        ($this->cancel)($order);

        expect(OrderCommunication::where('order_id', $order->id)->exists())->toBeFalse();
    });

    it('applique une règle dont les conditions sont vraies', function (): void {
        $order = ($this->order)();
        ($this->rule)(($this->template)(), [
            'conditions' => ['all' => [
                ['field' => 'order_number', 'operator' => 'eq', 'value' => $order->order_number],
            ]],
        ]);

        ($this->cancel)($order);

        expect(OrderCommunication::where('order_id', $order->id)->exists())->toBeTrue();
    });

    it('ignore la règle d’une autre organisation', function (): void {
        $foreign = Template::factory()->create(['channel' => CommunicationChannel::INTERNAL_NOTIFICATION]);
        CommunicationRule::factory()->create([
            'organization_id' => $foreign->organization_id,
            'template_id' => $foreign->id,
            'event_type' => CommunicationEventType::ORDER_CANCELLED,
            'service_id' => null,
        ]);

        $order = ($this->order)();

        ($this->cancel)($order);

        expect(OrderCommunication::where('order_id', $order->id)->exists())->toBeFalse();
    });

    /** Une transition sans correspondance ne réveille aucune règle d'annulation. */
    it('ne confond pas deux événements', function (): void {
        ($this->rule)(($this->template)());
        $order = ($this->order)();

        app(ChangeOrderStatus::class)->execute($order, OrderStatus::CONFIRMED, $this->user);

        expect(OrderCommunication::where('order_id', $order->id)->exists())->toBeFalse();
    });
});

/**
 * Le §53 interdit d'inventer une colonne `eventId` : la clé d'idempotence est
 * le couple `order_id` + `communication_rule_id`, déjà en base.
 */
it('ne produit pas deux fois le même message pour une même règle', function (): void {
    $rule = ($this->rule)(($this->template)());
    $order = ($this->order)();

    ($this->cancel)($order);
    // Rejoue l'evenement tel qu'une file le ferait apres un incident.
    app(ApplyCommunicationRules::class)->execute(
        $order->refresh(),
        CommunicationEventType::ORDER_CANCELLED,
        new AuditContext($this->organization->id, $this->user),
    );

    expect(OrderCommunication::where('communication_rule_id', $rule->id)->count())->toBe(1);
});

/**
 * Un modèle qui ne se rend pas ne doit pas empêcher d'annuler la commande.
 *
 * L'inverse rendrait une opération métier tributaire d'un texte : personne ne
 * pourrait plus annuler tant que le modèle n'est pas corrigé.
 */
it('n’empêche pas l’annulation quand le rendu échoue', function (): void {
    ($this->rule)(($this->template)([
        'body_template' => 'Manque {{ inconnue }}.',
        'available_variables' => ['inconnue'],
    ]));
    $order = ($this->order)();

    ($this->cancel)($order);

    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and(OrderCommunication::where('order_id', $order->id)->exists())->toBeFalse();
});
