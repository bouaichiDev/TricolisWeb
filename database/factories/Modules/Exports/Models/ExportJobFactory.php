<?php

namespace Database\Factories\Modules\Exports\Models;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportJob>
 */
class ExportJobFactory extends Factory
{
    public function modelName(): string
    {
        return ExportJob::class;
    }

    public function definition(): array
    {
        $configuration = CustomerExportConfiguration::factory();

        return [
            'configuration_id' => $configuration,
            // Le client est celui de la configuration : l'API force cette
            // coherence, un jeu qui la romprait serait invalide.
            'customer_id' => fn (array $attributes): string => CustomerExportConfiguration::whereKey($attributes['configuration_id'])->value('customer_id'),
            'entity_type' => null,
            'entity_id' => null,
            'file_name' => null,
            'storage_path' => null,
            'status' => 'pending',
            'attempt_count' => 0,
            'generated_at' => null,
            'sent_at' => null,
            'error_message' => null,
        ];
    }

    public function forConfiguration(CustomerExportConfiguration $configuration): static
    {
        return $this->state(fn (): array => [
            'configuration_id' => $configuration->id,
            'customer_id' => $configuration->customer_id,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => 'sent',
            'generated_at' => now(),
            'sent_at' => now(),
        ]);
    }

    public function failed(string $message = 'Connexion refusée'): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'attempt_count' => 1,
            'generated_at' => now(),
            'error_message' => $message,
        ]);
    }
}
