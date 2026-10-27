<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Integrations\ExecuteCustomerImportRequest;
use App\Http\Requests\Api\V1\Customer\Integrations\PreviewCustomerImportRequest;
use App\Modules\CustomerPortal\Actions\ExecuteCustomerImport;
use App\Modules\CustomerPortal\Actions\PreviewCustomerImport;
use App\Modules\CustomerPortal\Exceptions\CustomerImportRefused;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\Integrations\CustomerIntegrationScope;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Uploads\ImportUploadStore;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Import de commandes par fichier dans le portail : prévisualiser, confirmer,
 * relire le résumé d'un import parti en file.
 *
 * La configuration est cherchée **dans le périmètre du client** : celle d'un
 * autre client répond 404, comme un jeton qui ne lui appartient pas.
 */
class CustomerImportController extends Controller
{
    public function __construct(private readonly CustomerIntegrationScope $scope) {}

    public function preview(PreviewCustomerImportRequest $request, PreviewCustomerImport $preview): JsonResponse
    {
        $configuration = $this->configuration((string) $request->validated('configurationId'));
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $mismatch = PreviewCustomerImportRequest::mismatch($file, (string) $configuration->file_format);

        if ($mismatch !== null) {
            return ApiResponse::error($mismatch, JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            return ApiResponse::ok($preview->execute($configuration, $file));
        } catch (CustomerImportRefused $refused) {
            return ApiResponse::error($refused->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function execute(ExecuteCustomerImportRequest $request, ExecuteCustomerImport $import): JsonResponse
    {
        $configuration = $this->configuration((string) $request->validated('configurationId'));

        try {
            $result = $import->execute($configuration, (string) $request->validated('token'), $request);
        } catch (CustomerImportRefused $refused) {
            return ApiResponse::error($refused->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Un import parti en file est accepté, pas encore fait.
        return $result['status'] === 'queued'
            ? ApiResponse::ok($result)->setStatusCode(JsonResponse::HTTP_ACCEPTED)
            : ApiResponse::ok($result);
    }

    public function result(string $token, ImportUploadStore $uploads, CurrentCustomerContext $context): JsonResponse
    {
        $result = preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1 ? $uploads->result($token, $context->customerId()) : null;

        return $result === null
            ? ApiResponse::error('Aucun import ne correspond à ce jeton.', JsonResponse::HTTP_NOT_FOUND)
            : ApiResponse::ok($result);
    }

    private function configuration(string $id): CustomerImportConfiguration
    {
        /** @var CustomerImportConfiguration $configuration */
        $configuration = $this->scope->find($this->scope->importConfigurations()->with('customer'), $id);

        return $configuration;
    }
}
