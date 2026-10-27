<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Refus métier sur une réclamation du portail, au format commun
 * `{message, code, errors}` — celui de `CustomerOrderException`.
 */
final class CustomerClaimException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status)
    {
        parent::__construct($message);
    }

    /** Une réclamation clôturée par le transporteur ne se modifie plus. */
    public static function notEditable(): self
    {
        return new self('Cette réclamation est clôturée : elle ne peut plus être modifiée.', 'CLAIM_NOT_EDITABLE', JsonResponse::HTTP_CONFLICT);
    }

    /**
     * Le statut d'une réclamation naissante vient du référentiel (statut par
     * défaut de la source `claim`) ; le portail n'en invente aucun.
     */
    public static function statusNotConfigured(): self
    {
        return new self(
            'Votre transporteur n’a pas encore configuré les réclamations. Contactez-le directement.',
            'CLAIM_STATUS_NOT_CONFIGURED',
            JsonResponse::HTTP_CONFLICT,
        );
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode, 'errors' => (object) []], $this->status);
    }
}
