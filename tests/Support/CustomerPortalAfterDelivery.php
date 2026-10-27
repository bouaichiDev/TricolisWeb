<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Claims\Models\Claim;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Modules\ProofOfDelivery\Models\ProofOfDelivery;
use Illuminate\Support\Facades\Storage;

/**
 * Jeux de données de l'après-livraison du portail (Phase 4) : réclamations,
 * documents réellement présents sur le disque (faux), preuves de livraison.
 *
 * Les appelants posent `Storage::fake('local')` : les fichiers écrits ici
 * n'atteignent jamais le vrai disque privé.
 */
final class CustomerPortalAfterDelivery
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function claim(string $customerCode, array $attributes = []): Claim
    {
        return Claim::factory()->forOrder(CustomerPortalOrders::order($customerCode))->create($attributes);
    }

    /** Un document lié à une entité, dont le fichier existe sur le disque. */
    public static function document(Order $order, string $entityType, string $entityId, string $type = 'customer_upload', string $mime = 'application/pdf'): Document
    {
        $path = "documents/{$order->organization_id}/".uniqid('test-', true).'.bin';
        Storage::disk('local')->put($path, 'contenu');

        $document = Document::factory()->create([
            'organization_id' => $order->organization_id,
            'document_type' => $type,
            'storage_path' => $path,
            'mime_type' => $mime,
            'file_name' => $type.'.pdf',
        ]);
        $document->links()->create(['entity_type' => $entityType, 'entity_id' => $entityId]);

        return $document;
    }

    /** Une preuve de livraison, avec signature et photo si demandé. */
    public static function proof(Order $order, bool $withFiles = true, ?string $serviceId = null): ProofOfDelivery
    {
        $file = static function () use ($order): string {
            $path = "documents/{$order->organization_id}/".uniqid('pod-', true).'.png';
            Storage::disk('local')->put($path, 'png');

            return Document::factory()->create([
                'organization_id' => $order->organization_id, 'document_type' => 'pod',
                'storage_path' => $path, 'mime_type' => 'image/png', 'file_name' => 'pod.png',
            ])->id;
        };

        return ProofOfDelivery::factory()->forOrder($order)->create([
            'order_service_id' => $serviceId ?? $order->orderServices()->value('id'),
            'recipient_name' => 'Anna Berg',
            'remark' => 'Quai 3',
            'signature_document_id' => $withFiles ? $file() : null,
            'photo_document_id' => $withFiles ? $file() : null,
        ]);
    }
}
