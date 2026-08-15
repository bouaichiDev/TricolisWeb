<?php

namespace Database\Factories\Modules\Communications\Models;

use App\Modules\Communications\Models\CommunicationAttachment;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Documents\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationAttachment>
 */
class CommunicationAttachmentFactory extends Factory
{
    public function modelName(): string
    {
        return CommunicationAttachment::class;
    }

    public function definition(): array
    {
        $document = Document::factory();

        return [
            'communication_id' => OrderCommunication::factory(),
            'document_id' => $document,
            // Les snapshots refletent le document au moment de l'ajout : les
            // inventer produirait un jeu que l'API ne peut pas creer.
            'file_name_snapshot' => fn (array $attributes): string => Document::whereKey($attributes['document_id'])->value('file_name'),
            'mime_type_snapshot' => fn (array $attributes): string => Document::whereKey($attributes['document_id'])->value('mime_type'),
            'created_at' => now(),
        ];
    }

    public function forCommunication(OrderCommunication $communication): static
    {
        return $this->state(fn (): array => ['communication_id' => $communication->id]);
    }

    public function forDocument(Document $document): static
    {
        return $this->state(fn (): array => [
            'document_id' => $document->id,
            'file_name_snapshot' => $document->file_name,
            'mime_type_snapshot' => $document->mime_type,
        ]);
    }
}
