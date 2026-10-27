<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Exceptions;

use RuntimeException;

/**
 * Erreur passagère d'une intégration : délai dépassé, HTTP 429 ou 5xx,
 * serveur FTP/SFTP injoignable. Rejouer plus tard a une chance d'aboutir —
 * le Job la laisse remonter, la file relance avec backoff.
 *
 * Le message ne porte jamais de secret ni de corps de réponse.
 */
final class TransientIntegrationFailure extends RuntimeException {}
