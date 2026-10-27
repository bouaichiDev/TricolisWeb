<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\UploadedFile;

/**
 * Dépose un fichier et le rattache à une commande.
 *
 * Partagé par l'administration interne et le portail client : même
 * stockage, même lien `DocumentLink`, même audit — ceux d'`AttachEntityDocument`,
 * dont cette action n'est que le cas « commande ».
 */
final readonly class AttachOrderDocument
{
    public function __construct(private AttachEntityDocument $attach) {}

    /**
     * @param  array{documentType: string, status: string, referenceNumber?: string|null, receivedAt?: string|null}  $data
     */
    public function execute(Order $order, UploadedFile $file, array $data, User $user, ?string $ipAddress = null): Document
    {
        return $this->attach->execute($order, $order->organization_id, $file, $data, $user, $ipAddress);
    }
}
