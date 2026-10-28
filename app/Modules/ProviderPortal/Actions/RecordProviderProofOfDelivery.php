<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Documents\Actions\AttachEntityDocument;
use App\Modules\ProofOfDelivery\Actions\CreateProofOfDeliveryAction;
use App\Modules\ProofOfDelivery\DTOs\CreateProofOfDeliveryData;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use App\Modules\ProviderPortal\Services\ProviderAcquiredServices;
use App\Modules\ProviderPortal\Services\ProviderTourScope;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Support\AuditContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Enregistre une preuve de livraison depuis le portail fournisseur.
 *
 * Tournée du fournisseur courant (404 sinon, verrouillée), arrêt de cette
 * tournée déjà atteint, service **confirmé** sur cet arrêt, une seule preuve
 * par service et par arrêt. Les fichiers deviennent des documents rattachés à
 * l'arrêt (AttachEntityDocument), puis la preuve passe par l'Action métier
 * existante — qui revérifie l'organisation de la commande et des documents.
 * L'heure de livraison est celle du serveur. Si quoi que ce soit échoue, les
 * fichiers déjà écrits sont effacés : aucun orphelin sur le disque.
 */
final readonly class RecordProviderProofOfDelivery
{
    /** Une preuve se saisit pendant la tournée ou après elle, jamais sur une tournée annulée. */
    public const array TOURS = [TourStatus::IN_PROGRESS, TourStatus::COMPLETED];

    public const array REACHED = [TourStopStatus::ARRIVED, TourStopStatus::IN_PROGRESS, TourStopStatus::COMPLETED];

    public function __construct(
        private ProviderTourScope $scope,
        private AttachEntityDocument $documents,
        private CreateProofOfDeliveryAction $proofs,
    ) {}

    /**
     * @param  array{orderServiceId: string, recipientName: string, remark?: string|null}  $data
     */
    public function execute(string $tourId, string $stopId, array $data, UploadedFile $signature, ?UploadedFile $photo, AuditContext $context): ProofOfDelivery
    {
        $written = [];

        try {
            return DB::transaction(function () use ($tourId, $stopId, $data, $signature, $photo, $context, &$written): ProofOfDelivery {
                $tour = $this->scope->tour($tourId, lock: true);
                $stop = TourStop::where('tour_id', $tour->id)->whereKey($stopId)->lockForUpdate()->first()
                    ?? throw new NotFoundHttpException('Arrêt introuvable.');

                if (! in_array($tour->status, self::TOURS, true) || $tour->locked_by !== null) {
                    throw ValidationException::withMessages(['status' => $tour->locked_by !== null
                        ? ExecuteProviderTour::RESERVED
                        : 'Une preuve ne s’enregistre que sur une tournée en cours ou terminée.']);
                }

                if (! in_array($stop->status, self::REACHED, true)) {
                    throw ValidationException::withMessages(['status' => 'Une preuve ne s’enregistre qu’à un arrêt atteint.']);
                }

                $line = ProviderAcquiredServices::lines()->with('orderService:id,order_id')
                    ->where('tour_stop_id', $stop->id)->where('order_service_id', $data['orderServiceId'])->first()
                    ?? throw ValidationException::withMessages(['orderServiceId' => 'Ce service n’est pas confirmé sur cet arrêt.']);

                // Verrouillante : lit le dernier état validé, y compris une preuve
                // saisie au back-office pendant cette transaction.
                if (ProofOfDelivery::where('tour_stop_id', $stop->id)->where('order_service_id', $line->order_service_id)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['orderServiceId' => 'Une preuve existe déjà pour ce service à cet arrêt.']);
                }

                $attach = function (UploadedFile $file, string $type) use ($stop, $tour, $context, &$written): string {
                    $document = $this->documents->execute($stop, $tour->organization_id, $file, ['documentType' => $type, 'status' => 'active'], $context->user, $context->ipAddress);
                    $written[] = $document->storage_path;

                    return $document->id;
                };

                return $this->proofs->execute(new CreateProofOfDeliveryData(
                    orderId: $line->orderService->order_id,
                    recipientName: $data['recipientName'],
                    deliveredAt: now()->toDateTimeString(),
                    orderServiceId: $line->order_service_id,
                    tourStopId: $stop->id,
                    signatureDocumentId: $attach($signature, 'proof_signature'),
                    photoDocumentId: $photo === null ? null : $attach($photo, 'proof_photo'),
                    remark: $data['remark'] ?? null,
                ), $context);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($written);

            throw $exception;
        }
    }
}
