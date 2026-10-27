<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Mapping\MappingSyntax;

/**
 * L'état d'une configuration d'import, tel que l'écran interne l'affiche.
 *
 * Il distingue ce que le §49 demande — configuration valide, inactive,
 * correspondance incomplète, secret manquant, connexion non testable — et ne
 * prétend **jamais** qu'une connexion fonctionne : seul le bouton de test le
 * dit, après un appel réel.
 *
 * Aucun secret : `hasCredentials` dit s'il existe, jamais ce qu'il vaut.
 */
final readonly class ImportConnectionStatus
{
    public function __construct(
        private RestPullConnections $rest,
        private InboundFileConnections $files,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(CustomerImportConfiguration $configuration): array
    {
        $channel = ImportSourceKind::of($configuration);
        $mapping = is_array($configuration->mapping) ? $configuration->mapping : [];
        $connection = match ($channel) {
            ImportSourceKind::REST_PULL => $this->restConnection($configuration),
            ImportSourceKind::FTP, ImportSourceKind::SFTP => $this->fileConnection($configuration),
            default => null,
        };

        $diagnosis = MappingSyntax::diagnose($mapping);
        $flags = [
            'inactive' => ! $configuration->is_active,
            'mappingMissing' => $mapping === [],
            'mappingIncomplete' => $mapping !== [] && $diagnosis['missingRequired'] !== [],
            'connectionMissing' => $connection !== null && ! $connection['configured'],
            'credentialMissing' => $connection !== null && $connection['configured'] && ! $connection['hasCredentials'],
            'testable' => $connection !== null && $connection['configured'],
        ];

        return [
            'channel' => $channel,
            'frequency' => $channel === ImportSourceKind::UPLOAD ? 'manual' : 'hourly',
            'orderSource' => ImportSourceKind::orderSource($configuration)->value,
            'connection' => $connection,
            'mapping' => [...$diagnosis, 'problems' => MappingSyntax::problems($mapping)],
            'flags' => $flags,
            'ready' => ! $flags['inactive'] && ! $flags['mappingMissing'] && ! $flags['connectionMissing'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restConnection(CustomerImportConfiguration $configuration): array
    {
        $api = $this->rest->find($configuration, false);

        if ($api === null) {
            return ['kind' => 'rest', 'configured' => false, 'hasCredentials' => false, 'code' => RestPullConnections::codeFor($configuration)];
        }

        $settings = $api->settings ?? [];

        return [
            'kind' => 'rest',
            'configured' => true,
            'code' => $api->code,
            'isActive' => (bool) $api->is_active,
            'baseUrl' => $api->base_url,
            'authType' => $api->auth_type,
            'hasCredentials' => $api->hasCredentials() || $api->auth_type === 'none',
            'timeoutSeconds' => $api->timeout_seconds,
            'headerNames' => array_keys($api->headers ?? []),
            // Les réglages de pull ne portent pas de secret : le secret est
            // dans `encrypted_credentials`, jamais dans `settings`.
            'settings' => array_intersect_key($settings, array_flip(['path', 'recordsPath', 'query', 'since', 'pagination', 'healthPath', 'actorUserId', 'apiKeyHeader'])),
            'lastUsedAt' => $api->last_used_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileConnection(CustomerImportConfiguration $configuration): array
    {
        if (! $this->files->isConfigured($configuration)) {
            return ['kind' => 'file', 'configured' => false, 'hasCredentials' => false];
        }

        try {
            return ['kind' => 'file', 'configured' => true, ...$this->files->for($configuration)->describe()];
        } catch (PermanentIntegrationFailure $failure) {
            // Enregistrée mais refusée (hôte devenu interne…) : on rend ce qui est
            // stocké — jamais le secret — pour que le formulaire le corrige.
            return [
                ...(array) $configuration->connection,
                'kind' => 'file',
                'configured' => false,
                'hasCredentials' => $configuration->encrypted_password !== null,
                'problem' => $failure->getMessage(),
            ];
        }
    }
}
