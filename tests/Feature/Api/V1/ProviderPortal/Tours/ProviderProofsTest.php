<?php

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Modules\Tours\Models\TourStopService;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProviderPortal;

/**
 * Preuves de livraison depuis le portail : sur un arrêt de sa tournée, pour un
 * service confirmé de cet arrêt, avec une signature qui est réellement une
 * image ; l'heure est celle du serveur.
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $org = $this->alpha->organization;
    $this->tour = Tour::factory()->forOrganization($org)->create(['provider_id' => $this->alpha->id, 'status' => TourStatus::IN_PROGRESS]);
    $this->stop = TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::ARRIVED]);
    $order = Order::factory()->create(['organization_id' => $org->id]);
    $this->service = OrderService::factory()->create(['order_id' => $order->id]);
    TourStopService::factory()->forStop($this->stop)->create(['order_service_id' => $this->service->id, 'confirmed_at' => now()]);
    $otherStop = TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::ARRIVED]);
    $this->otherService = OrderService::factory()->create(['order_id' => $order->id]);
    TourStopService::factory()->forStop($otherStop)->create(['order_service_id' => $this->otherService->id, 'confirmed_at' => now()]);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
    $this->record = fn (array $extra = [], ?Tour $tour = null) => $this->withHeaders($this->admin)->post(
        '/api/v1/provider/tours/'.($tour ?? $this->tour)->id."/stops/{$this->stop->id}/proofs",
        ['orderServiceId' => $this->service->id, 'recipientName' => 'Marie Réception', 'signature' => UploadedFile::fake()->image('signature.png', 300, 120), ...$extra],
        ['Accept' => 'application/json'],
    );
});

it('enregistre une preuve signée, à l’heure du serveur, et la liste', function (): void {
    ($this->record)(['deliveredAt' => null])->assertUnprocessable();
    ($this->record)()->assertCreated()->assertJsonPath('data.recipientName', 'Marie Réception');

    $proof = ProofOfDelivery::where('tour_stop_id', $this->stop->id)->firstOrFail();
    expect($proof->signature_document_id)->not->toBeNull()
        ->and($proof->delivered_at->diffInMinutes(now()))->toBeLessThan(2);

    $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->tour->id}/stops/{$this->stop->id}/proofs")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.signatureDocumentId', $proof->signature_document_id);
});

it('refuse une signature qui n’est pas réellement une image', function (string $case): void {
    $file = $case === 'svg'
        ? UploadedFile::fake()->createWithContent('signature.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')
        : UploadedFile::fake()->createWithContent('signature.png', '<?php echo "pirate"; ?>');

    ($this->record)(['signature' => $file])->assertUnprocessable()->assertJsonValidationErrors('signature');
    expect(ProofOfDelivery::count())->toBe(0);
})->with(['svg', 'php déguisé']);

it('refuse un service d’un autre arrêt, un arrêt non atteint, une tournée étrangère', function (string $case): void {
    match ($case) {
        'autre arrêt' => ($this->record)(['orderServiceId' => $this->otherService->id])->assertUnprocessable()->assertJsonValidationErrors('orderServiceId'),
        'arrêt en attente' => tap(null, fn () => $this->stop->update(['status' => TourStopStatus::PENDING])) ?? ($this->record)()->assertUnprocessable()->assertJsonValidationErrors('status'),
        'tournée étrangère' => ($this->record)([], Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => ProviderPortal::provider('transports-beta')->id, 'status' => TourStatus::IN_PROGRESS]))->assertNotFound(),
    };

    expect(ProofOfDelivery::count())->toBe(0);
})->with(['autre arrêt', 'arrêt en attente', 'tournée étrangère']);

it('refuse une seconde preuve pour le même service au même arrêt, sans fichier orphelin', function (): void {
    ($this->record)()->assertCreated();
    ($this->record)()->assertUnprocessable()->assertJsonValidationErrors('orderServiceId');

    expect(ProofOfDelivery::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('n’offre la saisie d’une preuve qu’aux arrêts atteints, et à qui peut la faire', function (): void {
    $stops = fn (string $who) => collect($this->withHeaders(ProviderPortal::headers($who))->getJson("/api/v1/provider/tours/{$this->tour->id}")->assertOk()->json('data.stops'));
    $pending = TourStop::factory()->forTour($this->tour)->create(['status' => TourStopStatus::PENDING]);

    expect($stops('admin@transports-alpha.test')->firstWhere('id', $this->stop->id)['actions']['canRecordProof'])->toBeTrue()
        ->and($stops('admin@transports-alpha.test')->firstWhere('id', $pending->id)['actions']['canRecordProof'])->toBeFalse()
        ->and($stops('viewer@transports-alpha.test')->firstWhere('id', $this->stop->id)['actions']['canRecordProof'])->toBeFalse();
});

it('refuse une preuve pour un service retiré de l’arrêt, sur une tournée annulée ou réservée', function (string $case): void {
    $field = 'status';
    match ($case) {
        'service retiré' => [TourStopService::where('order_service_id', $this->service->id)->update(['is_active_assignment' => false]), $field = 'orderServiceId'],
        'tournée annulée' => $this->tour->update(['status' => TourStatus::CANCELLED]),
        'tournée réservée' => $this->tour->update(['locked_by' => authUser()->id, 'locked_at' => now()]),
    };

    ($this->record)()->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(ProofOfDelivery::count())->toBe(0);
})->with(['service retiré', 'tournée annulée', 'tournée réservée']);

it('ne montre ni la preuve du back-office hors service acquis, ni sa remarque interne', function (): void {
    $order = $this->service->order;
    ProofOfDelivery::create(['order_id' => $order->id, 'order_service_id' => $this->otherService->id, 'tour_stop_id' => $this->stop->id, 'recipient_name' => 'Hors arrêt', 'delivered_at' => now()]);
    ProofOfDelivery::create(['order_id' => $order->id, 'order_service_id' => $this->service->id, 'tour_stop_id' => $this->stop->id, 'recipient_name' => 'Interne', 'remark' => 'Note interne', 'delivered_at' => now(), 'created_by' => authUser()->id]);

    $rows = $this->withHeaders($this->admin)->getJson("/api/v1/provider/tours/{$this->tour->id}/stops/{$this->stop->id}/proofs")->assertOk()->json('data');

    expect(collect($rows)->pluck('recipientName')->all())->toBe(['Interne'])
        ->and($rows[0]['remark'])->toBeNull();
});
