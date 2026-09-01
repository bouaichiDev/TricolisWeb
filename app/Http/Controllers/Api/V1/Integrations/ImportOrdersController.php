<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\ImportOrdersRequest;
use App\Http\Resources\Api\V1\Orders\OrderListResource;
use App\Modules\Integrations\Actions\ImportOrdersFromFile;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Orders\Models\Order;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Importer réellement un fichier client.
 *
 * La prévisualisation dit ce qu'une correspondance produirait ; celle-ci
 * l'écrit. C'est la même lecture et le même interpréteur — seule la fin
 * diffère.
 *
 * **Deux permissions sont exigées, et ce n'est pas excessif.** Consulter la
 * configuration ne suffit pas : ce qui se crée ici, ce sont des commandes, et
 * c'est `orders.create` qui en décide. Un utilisateur autorisé à lire les
 * intégrations mais pas à créer de commande ne doit pas contourner cette limite
 * par un fichier.
 *
 * **Aucune configuration inactive.** Une correspondance désactivée a été
 * retirée du service ; l'employer quand même viderait ce réglage de son sens.
 */
final class ImportOrdersController extends Controller
{
    use ResolvesCustomerScope;

    public function __invoke(
        ImportOrdersRequest $request,
        CustomerImportConfiguration $configuration,
        ImportSourceReader $reader,
        ImportOrdersFromFile $import,
    ): JsonResponse {
        $organizationId = $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);
        $this->authorize('create', [Order::class, $organizationId]);

        if (! $configuration->is_active) {
            return ApiResponse::error(
                'Cette configuration est désactivée : elle ne peut pas servir à importer.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $mapping = $configuration->mapping;

        if (! is_array($mapping) || $mapping === []) {
            return ApiResponse::error(
                'Cette configuration ne porte aucune correspondance.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $contents = $request->file('file')?->get();

        if (! is_string($contents)) {
            return ApiResponse::error('Le fichier n’a pas pu être lu.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $rows = $reader->read($contents, (string) $configuration->file_format);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($rows === []) {
            return ApiResponse::error('Ce fichier ne contient aucune ligne.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Les erreurs de validation remontent en 422 depuis l'Action, préfixées
        // par le rang de la commande dans le fichier. Rien n'est créé dans ce
        // cas : l'Action valide tout avant d'ouvrir la transaction.
        $orders = $import->execute(
            $configuration,
            $rows,
            (string) $request->validated('agencyId'),
            $request->validated('depotId'),
            $organizationId,
            $request->user(),
            $request,
        );

        return ApiResponse::created([
            'rowCount' => count($rows),
            'orders' => OrderListResource::collection($orders)->resolve($request),
        ]);
    }
}
