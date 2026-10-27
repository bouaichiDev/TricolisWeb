<?php

declare(strict_types=1);

namespace App\Modules\Documents\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Dépose un fichier et le rattache à une entité, par `DocumentLink`.
 *
 * Le lien générique du diagramme (`Document "1" -- "0..*" DocumentLink`) :
 * une commande, une réclamation, toute entité de la morph map. Stockage privé
 * (`local`, sous `documents/{organisation}`), nom de fichier haché par
 * Laravel, type MIME lu dans le contenu, audit `created`. Le fichier écrit
 * est supprimé si l'enregistrement échoue : aucun orphelin sur le disque.
 */
final readonly class AttachEntityDocument
{
    public function __construct(private WriteAuditLog $audit) {}

    /**
     * @param  array{documentType: string, status: string, referenceNumber?: string|null, receivedAt?: string|null}  $data
     */
    public function execute(Model $entity, string $organizationId, UploadedFile $file, array $data, User $user, ?string $ipAddress = null): Document
    {
        $alias = MorphMap::aliasFor($entity::class)
            ?? throw new InvalidArgumentException('Entité absente de la morph map : '.$entity::class);
        $path = $file->store("documents/$organizationId", 'local');

        try {
            return DB::transaction(function () use ($entity, $alias, $organizationId, $file, $data, $user, $ipAddress, $path): Document {
                $document = Document::create([
                    'organization_id' => $organizationId,
                    'reference_number' => $data['referenceNumber'] ?? null,
                    'document_type' => $data['documentType'],
                    'status' => $data['status'],
                    'file_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize(),
                    'received_at' => $data['receivedAt'] ?? null,
                    'created_by' => $user->id,
                ]);

                $document->links()->create(['entity_type' => $alias, 'entity_id' => $entity->getKey()]);
                $this->audit->execute($organizationId, $user, 'created', $document, null, $document->toArray(), null, $ipAddress);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }
}
