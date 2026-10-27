<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Security\SensitiveData;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Masque les secrets du contexte de chaque ligne de journal (Phase 7, §26).
 *
 * Branché par `tap` sur les canaux fichiers de `config/logging.php` : un
 * appel comme `Log::info('…', ['apiKey' => …])` écrit `[REDACTED]`, quel que
 * soit le module qui journalise. Les règles sont celles de l'audit
 * (`SensitiveData`) — une seule définition du secret dans tout le projet.
 */
final class RedactSensitiveContext
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof \Monolog\Logger) {
            return;
        }

        $monolog->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(
            context: SensitiveData::redact($record->context),
            extra: SensitiveData::redact($record->extra),
        ));
    }
}
