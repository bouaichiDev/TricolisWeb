<?php

declare(strict_types=1);

namespace App\Shared\Http\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Ce qu'une erreur montre au **périmètre externe** — portail client, lien
 * public, API par clé (Phase 8 portail, §25).
 *
 * Deux fuites fermées, quel que soit `APP_DEBUG` :
 *
 * - un introuvable ne nomme plus la classe interne (« No query results for
 *   model [App\Modules\…] ») : 404 « Ressource introuvable. » ;
 * - une erreur imprévue (SQL, fichier, réseau) ne rend plus son message ni sa
 *   trace : 500 générique. Le détail part au journal, jamais au client.
 *
 * Les refus prévus (401, 403, 409, 422, 429) gardent leur message : il est
 * écrit par le code du portail, pour le client.
 */
final class ExternalApiErrors
{
    /** @var list<string> */
    private const array PATHS = ['api/v1/customer', 'api/v1/customer/*', 'api/v1/public/*', 'api/v1/client/*'];

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(static function (Throwable $exception, Request $request): ?JsonResponse {
            if (! $request->is(...self::PATHS)) {
                return null;
            }

            if ($exception instanceof ModelNotFoundException
                || ($exception instanceof NotFoundHttpException && $exception->getPrevious() instanceof ModelNotFoundException)) {
                return new JsonResponse(['message' => 'Ressource introuvable.'], JsonResponse::HTTP_NOT_FOUND);
            }

            if ($exception instanceof HttpExceptionInterface
                || $exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException) {
                return null;
            }

            return new JsonResponse(
                ['message' => 'Une erreur interne est survenue. Réessayez plus tard ; si elle persiste, contactez votre transporteur.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        });
    }
}
