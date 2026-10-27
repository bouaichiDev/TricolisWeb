<?php

use App\Modules\Customers\Services\CustomerDefaultAgency;
use App\Modules\Integrations\DTOs\ImportOutcome;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Database\Seeders\CustomerPortalDemoSeeder;
use Tests\Support\CustomerIntegrations;

/**
 * L'Action d'import unique (§8-§9) : création par les Actions Order,
 * idempotence Customer + externalReference, stratégie de doublon explicite.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    $this->configuration = CustomerIntegrations::import('ikea', 'xml');

    $this->run = function (string $xml, array $rules = [], string $channel = ImportRun::PULL) {
        $this->configuration->update(['validation_rules' => ['externalReference' => ['required'], ...$rules]]);
        $configuration = $this->configuration->fresh('customer');
        $run = ImportRun::make($configuration, $configuration->customer->organization_id, authUser(), app(CustomerDefaultAgency::class)->for($configuration->customer), null, OrderSource::XML_IMPORT, true, null, $channel);
        $rows = app(ImportSourceReader::class)->read($xml, 'xml', 'Order');

        return app(ImportPipeline::class)->run($run, $rows);
    };
});

it('crée la commande, ses lignes et sa prestation, avec l’origine imposée', function (): void {
    $summary = ($this->run)(CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-1', [['BILLY', 'Billy', '2'], ['PAX', 'Pax', '1']])));
    $order = Order::with(['lines', 'orderServices'])->where('external_reference', 'IK-1')->sole();

    expect($summary->counts())->toMatchArray(['created' => 1, 'invalid' => 0])
        ->and($order->customer_id)->toBe($this->configuration->customer_id)
        ->and($order->source->value)->toBe(OrderSource::XML_IMPORT->value)
        ->and($order->lines->pluck('article_code')->all())->toBe(['BILLY', 'PAX'])
        ->and($order->orderServices)->toHaveCount(1)
        ->and($order->created_by)->toBe(authUser()->id);
});

it('ne crée pas de doublon quand le même contenu revient (SKIP par défaut)', function (): void {
    $xml = CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-2', [['BILLY', 'Billy', '2']]));

    ($this->run)($xml);
    $again = ($this->run)($xml);

    expect($again->counts())->toMatchArray(['created' => 0, 'skipped' => 1])
        ->and(Order::where('external_reference', 'IK-2')->count())->toBe(1);
});

it('refuse le doublon quand la configuration le demande', function (): void {
    $xml = CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-3', [['BILLY', 'Billy', '2']]));
    ($this->run)($xml);

    $again = ($this->run)($xml, ['@onDuplicate' => 'reject']);

    expect($again->outcomes[0]->status)->toBe(ImportOutcome::INVALID)
        ->and($again->outcomes[0]->errors['orders.0.externalReference'][0])->toContain('déjà été importée');
});

it('met à jour une commande encore modifiable, et refuse celle qui ne l’est plus', function (): void {
    ($this->run)(CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-4', [['BILLY', 'Billy', '2']])));
    $updated = ($this->run)(CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-4', [['BILLY', 'Billy', '5']])), ['@onDuplicate' => 'update']);
    $order = Order::with('lines')->where('external_reference', 'IK-4')->sole();

    expect($updated->counts()['updated'])->toBe(1)
        ->and((float) $order->lines->first()->quantity)->toBe(5.0);

    $order->forceFill(['status' => OrderStatus::COMPLETED])->saveQuietly();
    $refused = ($this->run)(CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-4', [['BILLY', 'Billy', '9']])), ['@onDuplicate' => 'update']);

    expect($refused->outcomes[0]->status)->toBe(ImportOutcome::INVALID)
        ->and(implode(' ', $refused->outcomes[0]->errors['orders.0.externalReference']))->toContain('plus modifiable')
        ->and((float) $order->fresh('lines')->lines->first()->quantity)->toBe(5.0);
});

it('isole chaque commande : une invalide n’empêche pas la suivante', function (): void {
    $summary = ($this->run)(CustomerIntegrations::xmlFile(
        CustomerIntegrations::xmlOrder('IK-5', [['BILLY', 'Billy', '0']]),
        CustomerIntegrations::xmlOrder('IK-6', [['BILLY', 'Billy', '3']]),
    ));

    expect($summary->counts())->toMatchArray(['created' => 1, 'invalid' => 1])
        ->and(Order::where('external_reference', 'IK-5')->exists())->toBeFalse()
        ->and(Order::where('external_reference', 'IK-6')->exists())->toBeTrue();
});

it('exige une référence externe pour un import automatique', function (): void {
    $summary = ($this->run)(CustomerIntegrations::xmlFile(str_replace('<OrderNo>IK-7</OrderNo>', '', CustomerIntegrations::xmlOrder('IK-7', [['BILLY', 'Billy', '1']]))), []);

    expect($summary->outcomes[0]->status)->toBe(ImportOutcome::INVALID)
        ->and(Order::where('customer_id', $this->configuration->customer_id)->whereNull('external_reference')->where('source', OrderSource::XML_IMPORT->value)->exists())->toBeFalse();
});

it('ignore le statut, le numéro et la remarque interne d’un contenu client', function (): void {
    $this->configuration->update(['mapping' => [...$this->configuration->mapping, 'orderNumber' => 'OrderNo', 'internalRemark' => 'OrderNo', 'status' => ['@value' => 'completed']]]);
    ($this->run)(CustomerIntegrations::xmlFile(CustomerIntegrations::xmlOrder('IK-8', [['BILLY', 'Billy', '1']])));
    $order = Order::where('external_reference', 'IK-8')->sole();

    expect($order->order_number)->not->toBe('IK-8')
        ->and($order->internal_remark)->toBeNull()
        ->and($order->status)->not->toBe(OrderStatus::COMPLETED);
});
