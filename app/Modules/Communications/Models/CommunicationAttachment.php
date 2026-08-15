<?php

declare(strict_types=1);

namespace App\Modules\Communications\Models;

use App\Modules\Documents\Models\Document;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Document joint à une communication.
 *
 * Le diagramme ne donne que `createdAt` : `$timestamps` est désactivé et la
 * date est posée à la création. Aucune route `PATCH` n'existe — les deux
 * snapshots sont immuables par construction.
 *
 * Le fichier n'est pas recopié : la pièce jointe référence le document et fige
 * son nom et son type au moment de l'ajout, pour que le message reste lisible
 * si le document est renommé plus tard.
 */
#[Fillable([
    'communication_id',
    'document_id',
    'file_name_snapshot',
    'mime_type_snapshot',
    'created_at',
])]
class CommunicationAttachment extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'communication_attachments';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderCommunication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(OrderCommunication::class, 'communication_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * Fige le nom et le type d'un document au moment de l'ajout.
     *
     * @return array<string, string>
     */
    public static function snapshotFrom(Document $document): array
    {
        return [
            'file_name_snapshot' => $document->file_name,
            'mime_type_snapshot' => $document->mime_type,
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas(
            'communication',
            fn (Builder $c) => $c->where('organization_id', $organizationId),
        );
    }
}
