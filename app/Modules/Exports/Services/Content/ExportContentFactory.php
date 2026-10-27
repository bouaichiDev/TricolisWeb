<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Content;

use App\Shared\Database\MorphMap;
use RuntimeException;

/**
 * Le constructeur de contenu d'un `ExportJob`, selon son type d'entité.
 *
 * Deux contenus existent : la facture clôturée et le statut de commande. Un
 * job qui porte sur autre chose n'a pas de fichier à produire — il est
 * refusé plutôt que d'envoyer un contenu inventé.
 */
final readonly class ExportContentFactory
{
    public function __construct(
        private InvoiceExportContent $invoice,
        private OrderStatusExportContent $orderStatus,
    ) {}

    public function supports(?string $entityType): bool
    {
        return in_array($entityType, [MorphMap::INVOICE, MorphMap::ORDER], true);
    }

    public function for(?string $entityType): ExportContentBuilder
    {
        return match ($entityType) {
            MorphMap::INVOICE => $this->invoice,
            MorphMap::ORDER => $this->orderStatus,
            default => throw new RuntimeException('Cet envoi ne porte ni sur une facture ni sur une commande.'),
        };
    }
}
