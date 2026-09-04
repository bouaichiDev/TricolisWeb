<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\PreviewImportRequest;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\ImportPreviewValidator;
use App\Modules\Integrations\Services\ImportSourceReader;
use App\Modules\Integrations\Services\MappingInterpreter;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Éprouver une correspondance sur un vrai fichier, sans rien créer.
 *
 * C'est ce qui manquait pour qu'une configuration d'import soit utilisable :
 * on pouvait la décrire, jamais vérifier qu'elle était juste. Un transporteur à
 * qui l'on demande de saisir une correspondance à l'aveugle ne peut ni la
 * valider, ni s'en servir.
 *
 * **Aucune écriture.** Ni commande, ni ligne, ni trace : le fichier est lu en
 * mémoire, la correspondance appliquée, le résultat rendu. Il n'existe toujours
 * pas de table `Import`, et le §5 reste tenu — prévisualiser n'est pas
 * importer.
 *
 * Le verdict porte sur les règles réelles de `StoreOrderRequest`, moins les
 * identifiants qu'aucun fichier client ne porte : les exiger ferait échouer
 * toute prévisualisation sur des champs qui ne relèvent pas de la
 * correspondance.
 */
final class ImportPreviewController extends Controller
{
    use ResolvesCustomerScope;

    public function __invoke(
        PreviewImportRequest $request,
        CustomerImportConfiguration $configuration,
        ImportSourceReader $reader,
        MappingInterpreter $interpreter,
        ImportPreviewValidator $verdict,
    ): JsonResponse {
        $this->guardCustomerOwned($configuration);
        // Éprouver une correspondance, c'est la lire : le droit de consultation
        // suffit, et exiger celui de modification interdirait le test à qui
        // n'a que la lecture.
        $this->authorize('view', $configuration);

        $mapping = $configuration->mapping;

        if (! is_array($mapping) || $mapping === []) {
            return ApiResponse::error(
                'Cette configuration ne porte aucune correspondance à éprouver.',
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
            // Le message dit ce qui cloche dans le fichier — séparateur,
            // en-tête, JSON malformé. Un « fichier illisible » sans raison
            // obligerait à deviner.
            return ApiResponse::error($exception->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($rows === []) {
            return ApiResponse::error(
                'Ce fichier ne contient aucune ligne.',
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $payload = $interpreter->apply($mapping, $rows);
        $checked = $verdict->verdict($payload, (new StoreOrderRequest)->rules());

        return ApiResponse::ok([
            'rowCount' => count($rows),
            // Les colonnes réellement lues : c'est ce qui permet de repérer un
            // nom mal orthographié dans la correspondance.
            'columns' => array_keys($rows[0]),
            'payload' => $payload,
            'errors' => $checked['errors'],
            'resolvedElsewhere' => $checked['resolvedElsewhere'],
        ]);
    }
}
