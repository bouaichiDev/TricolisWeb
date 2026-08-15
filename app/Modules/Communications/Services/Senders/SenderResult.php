<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

/**
 * Résultat normalisé d'un envoi, quel que soit le canal.
 *
 * `providerResponse` ne doit jamais contenir de secret : les transporteurs n'y
 * placent que des métadonnées d'acheminement (§26).
 */
final readonly class SenderResult
{
    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    private function __construct(
        public bool $successful,
        public ?string $providerMessageId,
        public ?array $providerResponse,
        public ?string $errorMessage,
    ) {}

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public static function success(?string $providerMessageId = null, ?array $providerResponse = null): self
    {
        return new self(true, $providerMessageId, $providerResponse, null);
    }

    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public static function failure(string $errorMessage, ?array $providerResponse = null): self
    {
        return new self(false, null, $providerResponse, $errorMessage);
    }
}
