<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une communication reçue : canal, objet, dates d'envoi et, au détail, le
 * texte tel qu'il est parti — sans balises, l'interface l'affiche en texte.
 * Ni commande, ni règle, ni modèle, ni réponse du prestataire d'envoi ; les
 * pièces jointes ne sont citées que par leur nom.
 *
 * @mixin OrderCommunication
 */
class ProviderCommunicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'channelLabel' => $this->channel->label(),
            'subject' => $this->subject,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'sentAt' => $this->sent_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'body' => $this->when($this->relationLoaded('attachments'), fn (): string => self::text((string) $this->body)),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(static fn (CommunicationAttachment $attachment): array => [
                'fileName' => $attachment->file_name_snapshot,
                'mimeType' => $attachment->mime_type_snapshot,
            ])->values()),
        ];
    }

    /**
     * Le texte d'un e-mail HTML : styles et scripts retirés avec leur contenu,
     * fins de paragraphe et de ligne gardées, balises ôtées, entités décodées.
     */
    private static function text(string $html): string
    {
        $html = (string) preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<br\s*/?>|</(p|div|li|tr|h[1-6])>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/[ \t]*\n\s*\n\s*/", "\n\n", $text));
    }
}
