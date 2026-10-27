<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Refus métier sur une commande du portail, au format commun :
 *
 * ```json
 * {"message": "…", "code": "ORDER_NOT_EDITABLE", "errors": {}}
 * ```
 *
 * Le `code` est ce que le portail lit pour réagir (recharger sur un conflit
 * de version, par exemple) ; le message s'affiche tel quel.
 */
final class CustomerOrderException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function notEditable(): self
    {
        return new self('Cette commande ne peut plus être modifiée.', 'ORDER_NOT_EDITABLE', JsonResponse::HTTP_CONFLICT);
    }

    public static function notCancellable(): self
    {
        return new self(
            'Cette commande ne peut plus être annulée depuis le portail. Contactez votre transporteur.',
            'ORDER_NOT_CANCELLABLE',
            JsonResponse::HTTP_CONFLICT,
        );
    }

    public static function versionConflict(): self
    {
        return new self(
            'Cette commande a été modifiée entre-temps. Rechargez-la avant d’enregistrer.',
            'ORDER_VERSION_CONFLICT',
            JsonResponse::HTTP_CONFLICT,
        );
    }

    public static function invalidAddress(string $field): self
    {
        return new self(
            'Cette adresse n’appartient pas à votre carnet d’adresses.',
            'INVALID_ORDER_SERVICE',
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            [$field => ['Cette adresse n’appartient pas à votre carnet d’adresses.']],
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'errors' => (object) $this->errors,
        ], $this->status);
    }
}
