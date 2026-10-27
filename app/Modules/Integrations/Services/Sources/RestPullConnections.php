<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;

/**
 * La connexion REST d'une configuration d'import en pull.
 *
 * `CustomerImportConfiguration` décrit la **transformation** (format,
 * correspondance, règles) mais ne porte ni URL ni secret. La connexion vit
 * dans le mécanisme existant des API appelées par l'organisation,
 * `OrganizationApiConfiguration` — URL HTTPS, secret chiffré (`Crypt`),
 * en-têtes, délai, `last_used_at` — sous un **code conventionnel** dérivé de
 * l'import : `customer-import-{id}`. C'est la convention déjà employée par le
 * géocodage (une configuration désignée par son code), et l'unicité
 * (organisation, code) garantit une seule connexion par import.
 *
 * Aucune colonne n'est ajoutée : voir le rapport de phase.
 */
final readonly class RestPullConnections
{
    public const string CODE_PREFIX = 'customer-import-';

    public static function codeFor(CustomerImportConfiguration $configuration): string
    {
        return self::CODE_PREFIX.$configuration->id;
    }

    public function find(CustomerImportConfiguration $configuration, bool $activeOnly = true): ?OrganizationApiConfiguration
    {
        $organizationId = $configuration->customer?->organization_id;

        if ($organizationId === null) {
            return null;
        }

        return OrganizationApiConfiguration::where('organization_id', $organizationId)
            ->where('code', self::codeFor($configuration))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->first();
    }

    /**
     * Crée ou met à jour la connexion. Le secret n'est touché que s'il est
     * fourni : absent, il est conservé ; `null`, il est effacé.
     *
     * @param  array<string, mixed>  $attributes  baseUrl, authType, headers, settings, timeoutSeconds, isActive
     */
    public function save(CustomerImportConfiguration $configuration, array $attributes, bool $secretProvided, ?string $secret): OrganizationApiConfiguration
    {
        $connection = $this->find($configuration, false) ?? new OrganizationApiConfiguration([
            'organization_id' => $configuration->customer->organization_id,
            'code' => self::codeFor($configuration),
        ]);

        $connection->fill([
            'name' => mb_substr('Import — '.$configuration->name, 0, 255),
            'base_url' => $attributes['baseUrl'],
            'auth_type' => $attributes['authType'] ?? 'none',
            'headers' => $attributes['headers'] ?? null,
            'settings' => $attributes['settings'] ?? null,
            'timeout_seconds' => $attributes['timeoutSeconds'] ?? 30,
            'is_active' => $attributes['isActive'] ?? true,
        ]);

        if ($secretProvided) {
            $connection->setCredentials($secret);
        }

        $connection->save();

        return $connection;
    }
}
