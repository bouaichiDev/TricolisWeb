<?php

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use App\Modules\Templates\Actions\ResolveTemplateAction;
use App\Modules\Templates\DTOs\TemplateQuery;
use App\Modules\Templates\Enums\TemplateType;
use App\Modules\Templates\Models\Template;

/**
 * Le repli « client → global », et ce qu'il ne fait jamais.
 *
 * Le §0.9 tient en deux phrases : le modèle du client s'il existe, sinon celui
 * du transporteur — et **jamais** celui d'un autre client.
 */
beforeEach(function (): void {
    $this->seed();
    $this->organization = authOrganization();
    $this->resolve = app(ResolveTemplateAction::class);

    $this->ikea = Customer::factory()->create(['organization_id' => $this->organization->id, 'code' => 'IKEA']);
    $this->qoqa = Customer::factory()->create(['organization_id' => $this->organization->id, 'code' => 'QOQA']);

    $this->invoiceTemplate = fn (array $attributes = []): Template => Template::factory()
        ->invoice()
        ->create(array_merge(['organization_id' => $this->organization->id], $attributes));
});

describe('invoice fallback', function (): void {
    it('serves the global template when the customer has none', function (): void {
        $global = ($this->invoiceTemplate)(['code' => 'INVOICE_DEFAULT', 'is_default' => true]);

        $resolved = $this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->ikea->id),
        );

        expect($resolved?->id)->toBe($global->id);
    });

    it('prefers the customer template once it exists', function (): void {
        ($this->invoiceTemplate)(['code' => 'INVOICE_DEFAULT', 'is_default' => true]);
        $forIkea = ($this->invoiceTemplate)(['code' => 'INVOICE_IKEA', 'customer_id' => $this->ikea->id]);

        $resolved = $this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->ikea->id),
        );

        expect($resolved?->id)->toBe($forIkea->id);
    });

    it('leaves the other customers on the global template', function (): void {
        $global = ($this->invoiceTemplate)(['code' => 'INVOICE_DEFAULT']);
        ($this->invoiceTemplate)(['code' => 'INVOICE_IKEA', 'customer_id' => $this->ikea->id]);

        $resolved = $this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->qoqa->id),
        );

        expect($resolved?->id)->toBe($global->id);
    });

    it('never serves the template of another customer', function (): void {
        // Aucun modele global : le seul candidat appartient a IKEA.
        ($this->invoiceTemplate)(['code' => 'INVOICE_IKEA', 'customer_id' => $this->ikea->id]);

        $resolved = $this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->qoqa->id),
        );

        expect($resolved)->toBeNull();
    });

    it('never crosses the organization boundary', function (): void {
        Template::factory()->invoice()->create(['code' => 'INVOICE_ELSEWHERE']);

        $resolved = $this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->ikea->id),
        );

        expect($resolved)->toBeNull();
    });

    it('ignores an inactive template', function (): void {
        ($this->invoiceTemplate)(['code' => 'INVOICE_OFF', 'is_active' => false]);

        expect($this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->ikea->id),
        ))->toBeNull();
    });

    /**
     * Un document n'a pas de canal. Sans ce filtre, une facture piocherait dans
     * les modeles d'e-mail — et partirait avec un objet et un corps de message.
     */
    it('never picks a template carrying a channel', function (): void {
        Template::factory()->create([
            'organization_id' => $this->organization->id,
            'template_type' => TemplateType::INVOICE,
            'channel' => CommunicationChannel::EMAIL,
        ]);

        expect($this->resolve->execute(
            TemplateQuery::forInvoice($this->organization->id, $this->ikea->id),
        ))->toBeNull();
    });
});

describe('communication resolution', function (): void {
    it('prefers customer and service over the generic template', function (): void {
        $service = Service::factory()->create(['organization_id' => $this->organization->id]);

        $generic = Template::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'DELIVERY_GLOBAL',
        ]);
        $precise = Template::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'DELIVERY_IKEA_SERVICE',
            'customer_id' => $this->ikea->id,
            'service_id' => $service->id,
        ]);

        $resolved = $this->resolve->execute(new TemplateQuery(
            organizationId: $this->organization->id,
            templateType: TemplateType::DELIVERY_CONFIRMATION,
            customerId: $this->ikea->id,
            serviceId: $service->id,
            channel: CommunicationChannel::EMAIL,
        ));

        expect($resolved?->id)->toBe($precise->id)
            ->and($resolved?->id)->not->toBe($generic->id);
    });

    it('never picks a template of another service', function (): void {
        $wanted = Service::factory()->create(['organization_id' => $this->organization->id]);
        $other = Service::factory()->create(['organization_id' => $this->organization->id]);

        Template::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'FOR_OTHER_SERVICE',
            'service_id' => $other->id,
        ]);

        $resolved = $this->resolve->execute(new TemplateQuery(
            organizationId: $this->organization->id,
            templateType: TemplateType::DELIVERY_CONFIRMATION,
            serviceId: $wanted->id,
            channel: CommunicationChannel::EMAIL,
        ));

        expect($resolved)->toBeNull();
    });

    /** Deux modeles egalement precis : `is_default` tranche, puis le code. */
    it('stays deterministic when two templates tie', function (): void {
        Template::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'AAA', 'is_default' => false,
        ]);
        $preferred = Template::factory()->create([
            'organization_id' => $this->organization->id, 'code' => 'ZZZ', 'is_default' => true,
        ]);

        $query = new TemplateQuery(
            organizationId: $this->organization->id,
            templateType: TemplateType::DELIVERY_CONFIRMATION,
            channel: CommunicationChannel::EMAIL,
        );

        expect($this->resolve->execute($query)?->id)->toBe($preferred->id)
            ->and($this->resolve->execute($query)?->id)->toBe($preferred->id);
    });
});
