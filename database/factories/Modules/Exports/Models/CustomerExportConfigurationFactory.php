<?php

namespace Database\Factories\Modules\Exports\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Shared\Support\Secret;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerExportConfiguration>
 */
class CustomerExportConfigurationFactory extends Factory
{
    public function modelName(): string
    {
        return CustomerExportConfiguration::class;
    }

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->unique()->bothify('EXPORT-####'),
            'export_type' => 'orders',
            // MANUAL par defaut : n'exige aucun hote, donc composable partout.
            'format' => ExportFormat::CSV,
            'transport' => ExportTransport::MANUAL,
            'host' => null,
            'port' => null,
            'username' => null,
            'encrypted_password' => null,
            'remote_directory' => null,
            'file_name_pattern' => null,
            'encoding' => 'UTF-8',
            'frequency' => 'daily',
            'settings' => null,
            'is_active' => true,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->id]);
    }

    public function sftp(string $host = 'sftp.example.test'): static
    {
        return $this->state(fn (): array => [
            'transport' => ExportTransport::SFTP,
            'host' => $host,
            'port' => 22,
            'username' => 'tricolis',
            'encrypted_password' => Secret::encrypt('s3cret'),
            'remote_directory' => '/in',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
