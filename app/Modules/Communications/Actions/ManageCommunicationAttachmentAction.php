<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\DTOs\AddCommunicationAttachmentData;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Services\CommunicationScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ajout et retrait des pièces jointes d'une communication.
 *
 * Deux règles portées ici, toutes deux issues du §30 et du §42 :
 *
 * - **le nom et le type sont figés à l'ajout** ; renommer le document ensuite ne
 *   touche pas la communication, qui doit rester le reflet de ce qui est parti ;
 * - **plus de modification passé le brouillon** : ajouter une pièce à un message
 *   déjà remis au transporteur ne l'y ajouterait pas, et en retirer une
 *   effacerait la trace de ce qui a été transmis.
 *
 * Le fichier n'est jamais recopié : seule la référence est enregistrée.
 */
final readonly class ManageCommunicationAttachmentAction
{
    /** @var list<string> */
    private const array AUDITED = ['communication_id', 'document_id', 'file_name_snapshot', 'mime_type_snapshot'];

    public function __construct(
        private CommunicationScopeGuard $guard,
        private WriteCommunicationAudit $writer,
    ) {}

    public function add(
        OrderCommunication $communication,
        AddCommunicationAttachmentData $data,
        AuditContext $context,
    ): CommunicationAttachment {
        $this->assertModifiable($communication);

        $document = $this->guard->document($data->documentId, $communication->organization_id);

        if ($communication->attachments()->where('document_id', $document->id)->exists()) {
            throw CommunicationNotEditable::duplicateAttachment();
        }

        return DB::transaction(function () use ($communication, $document, $context): CommunicationAttachment {
            $attachment = CommunicationAttachment::create([
                'communication_id' => $communication->id,
                'document_id' => $document->id,
                ...CommunicationAttachment::snapshotFrom($document),
                'created_at' => Carbon::now(),
            ])->refresh();

            $this->writer->created($attachment, 'communication_attachment.created', self::AUDITED, $context);

            return $attachment;
        });
    }

    public function remove(CommunicationAttachment $attachment, AuditContext $context): void
    {
        $communication = $attachment->communication;

        if ($communication instanceof OrderCommunication) {
            $this->assertModifiable($communication);
        }

        DB::transaction(function () use ($attachment, $context): void {
            $this->writer->deleted($attachment, 'communication_attachment.deleted', self::AUDITED, $context);
            $attachment->delete();
        });
    }

    private function assertModifiable(OrderCommunication $communication): void
    {
        if (! $communication->status->allowsContentChanges()) {
            throw CommunicationNotEditable::forAttachment($communication->status);
        }
    }
}
