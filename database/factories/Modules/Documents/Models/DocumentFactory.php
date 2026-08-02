<?php

namespace Database\Factories\Modules\Documents\Models;

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function modelName(): string
    {
        return Document::class;
    }

    public function definition(): array
    {
        $fileName = fake()->unique()->slug(2).'.pdf';

        return [
            'organization_id' => Organization::factory(),
            'reference_number' => fake()->optional()->bothify('DOC-####'),
            'document_type' => fake()->randomElement(['proof', 'invoice', 'contract']),
            'status' => 'active',
            'file_name' => $fileName,
            'storage_path' => 'documents/'.fake()->uuid().'/'.$fileName,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1048576),
            'received_at' => fake()->optional()->dateTimeThisYear(),
            'created_by' => User::factory(),
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
