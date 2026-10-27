<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\SaveFileConnectionRequest;
use App\Http\Requests\Api\V1\Integrations\SaveRestPullConnectionRequest;
use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Mapping\ImportTargetFields;
use App\Modules\Integrations\Services\Mapping\MappingDirectives;
use App\Modules\Integrations\Services\Sources\ImportConnectionStatus;
use App\Modules\Integrations\Services\Sources\ImportConnectionTester;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use App\Modules\Integrations\Services\Sources\InboundFileConnections;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use App\Modules\Integrations\Services\Validation\ImportValidationRules;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Le côté « exécution » d'une configuration d'import, dans le back-office :
 * champs cibles, état de la connexion, connexion REST ou FTP/SFTP, test,
 * passe manuelle.
 *
 * Permissions : lecture `customer_import_configurations.view` ; écrire la
 * connexion, la tester (appel réseau sortant) ou lancer une passe,
 * `customer_import_configurations.update`.
 */
final class ImportConnectionController extends Controller
{
    use ResolvesCustomerScope;

    /** Le vocabulaire accepté par une correspondance, tenu par le backend. */
    public function targetFields(): JsonResponse
    {
        $this->authorize('viewAny', [CustomerImportConfiguration::class, $this->requireOrganizationId()]);

        return ApiResponse::ok([
            'groups' => ImportTargetFields::groups(),
            'types' => MappingDirectives::TYPES,
            'validationRules' => ImportValidationRules::ALLOWED,
            'duplicateStrategies' => ImportValidationRules::DUPLICATE_STRATEGIES,
        ]);
    }

    public function show(CustomerImportConfiguration $configuration, ImportConnectionStatus $status): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);

        return ApiResponse::ok($status->describe($configuration));
    }

    /**
     * Enregistre la connexion : REST pour un pull, FTP/SFTP pour une relève de
     * fichiers. La validation dépend du canal de la configuration. Le secret
     * n'entre jamais dans l'audit — seulement le fait qu'il a changé.
     * (`accessChanged` : un nom contenant « credential » ou « password »
     * serait masqué par `SensitiveData`, et l'information perdue).
     */
    public function update(
        Request $request,
        CustomerImportConfiguration $configuration,
        ImportConnectionStatus $status,
        WriteAuditLog $audit,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('update', $configuration);

        $values = match (ImportSourceKind::of($configuration)) {
            ImportSourceKind::REST_PULL => $this->saveRest(app(SaveRestPullConnectionRequest::class), $configuration),
            ImportSourceKind::FTP, ImportSourceKind::SFTP => $this->saveFile(app(SaveFileConnectionRequest::class), $configuration),
            default => null,
        };

        if ($values === null) {
            return ApiResponse::error(
                'Un import par dépôt de fichier n’a pas de connexion : le fichier est déposé à la main.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $audit->execute($organizationId, $request->user(), 'customer_import_configuration.connection_saved', $configuration, null, $values, $request);

        return ApiResponse::ok($status->describe($configuration->refresh()));
    }

    public function test(CustomerImportConfiguration $configuration, ImportConnectionTester $tester): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('update', $configuration);

        return ApiResponse::ok($tester->test($configuration));
    }

    public function run(CustomerImportConfiguration $configuration, ImportConnectionStatus $status): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('update', $configuration);

        $described = $status->describe($configuration);

        if (! ImportSourceKind::isScheduled($configuration) || ! $described['ready']) {
            return ApiResponse::error(
                'Cette configuration ne peut pas être exécutée : elle doit être active, automatique, avec une correspondance et une connexion.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        RunCustomerPullIntegrationJob::dispatch($configuration->id);

        return ApiResponse::ok(['queued' => true])->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }

    /** @return array<string, mixed> ce que l'audit peut garder */
    private function saveRest(SaveRestPullConnectionRequest $request, CustomerImportConfiguration $configuration): array
    {
        $validated = $request->validated();
        $connection = app(RestPullConnections::class)->save($configuration, $validated, $request->exists('credentials'), $validated['credentials'] ?? null);

        return [
            'connectionCode' => $connection->code,
            'baseUrl' => $connection->base_url,
            'authType' => $connection->auth_type,
            'accessChanged' => $request->exists('credentials'),
        ];
    }

    /** @return array<string, mixed> ce que l'audit peut garder */
    private function saveFile(SaveFileConnectionRequest $request, CustomerImportConfiguration $configuration): array
    {
        $validated = $request->validated();

        try {
            app(InboundFileConnections::class)->save($configuration, $validated, $request->exists('password'), $validated['password'] ?? null);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['host' => [$exception->getMessage()]]);
        }

        return [
            'host' => $validated['host'],
            'port' => $validated['port'] ?? null,
            'directory' => $configuration->connection['directory'] ?? null,
            'accessChanged' => $request->exists('password'),
        ];
    }
}
