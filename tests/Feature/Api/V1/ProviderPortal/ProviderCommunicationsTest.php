<?php

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use Database\Seeders\DemoProviderPortalSeeder;
use Tests\Support\ProviderPortal;

/**
 * Communications au portail : seulement celles parties vers l'adresse du
 * membre connecté, dans l'organisation de son fournisseur, sur un canal
 * externe — jamais celles d'un autre, ni l'interne, ni un brouillon.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $order = Order::factory()->create(['organization_id' => $this->alpha->organization_id]);
    $sent = fn (array $state = []) => OrderCommunication::factory()->create([
        'order_id' => $order->id, 'recipient_email' => 'ADMIN@transports-alpha.test', 'status' => CommunicationStatus::SENT, 'sent_at' => now(), ...$state,
    ]);
    $this->mine = $sent(['subject' => 'Tournée de demain', 'body' => '<p>Chargement <b>quai 3</b> &amp; badge</p>']);
    CommunicationAttachment::factory()->forCommunication($this->mine)->create();
    $this->hidden = [
        'autre adresse' => $sent(['recipient_email' => 'client@example.test']),
        'interne' => $sent(['channel' => CommunicationChannel::INTERNAL_NOTIFICATION]),
        'brouillon' => $sent(['status' => CommunicationStatus::DRAFT, 'sent_at' => null]),
        'échouée' => $sent(['status' => CommunicationStatus::FAILED]),
        'autre organisation' => $sent(['order_id' => Order::factory()->create(['organization_id' => Organization::factory()->create()->id])->id]),
        'SMS' => $sent(['channel' => CommunicationChannel::SMS]),
        'en tant qu’interne' => $sent(['recipient_role' => RecipientRole::INTERNAL_USER]),
        'avant le rattachement' => $sent(['sent_at' => now()->subYears(2)]),
        'adresse accentuée' => $sent(['recipient_email' => 'admin@transports-alphá.test']),
    ];
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

it('ne liste que les communications parties vers l’adresse du membre', function (): void {
    $rows = $this->withHeaders($this->admin)->getJson('/api/v1/provider/communications')->assertOk()->json('data');

    expect(collect($rows)->pluck('id')->all())->toBe([$this->mine->id])
        ->and($rows[0])->toMatchArray(['subject' => 'Tournée de demain', 'channel' => 'email', 'status' => 'sent'])
        ->and($rows[0])->not->toHaveKey('body');
});

it('rend le détail en texte, pièces jointes par leur nom, sans commande ni destinataire', function (): void {
    $detail = $this->withHeaders($this->admin)->getJson("/api/v1/provider/communications/{$this->mine->id}")->assertOk()->json('data');

    expect($detail['body'])->toBe('Chargement quai 3 & badge')
        ->and($detail['attachments'])->toHaveCount(1)
        ->and(array_keys($detail))->not->toContain('orderId', 'recipientEmail', 'templateVariables', 'providerResponse');
});

it('répond 404 pour toute autre communication', function (string $case): void {
    $this->withHeaders($this->admin)->getJson("/api/v1/provider/communications/{$this->hidden[$case]->id}")->assertNotFound();
})->with(['autre adresse', 'interne', 'brouillon', 'échouée', 'autre organisation', 'SMS', 'en tant qu’interne', 'avant le rattachement', 'adresse accentuée']);

it('garde le texte et les paragraphes d’un e-mail HTML, sans style ni script', function (): void {
    $this->mine->update(['body' => '<style>p{color:red}</style><p>Bonjour</p><p>Quai 3<br>avant 10h</p><script>x()</script>']);

    expect($this->withHeaders($this->admin)->getJson("/api/v1/provider/communications/{$this->mine->id}")->json('data.body'))
        ->toBe("Bonjour\nQuai 3\navant 10h");
});
