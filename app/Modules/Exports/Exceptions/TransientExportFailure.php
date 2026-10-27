<?php

declare(strict_types=1);

namespace App\Modules\Exports\Exceptions;

use RuntimeException;

/**
 * Échec d'envoi qu'un nouvel essai peut corriger : délai dépassé, connexion
 * refusée, HTTP 429 ou 5xx. Seul ce cas ouvre la relance automatique d'un
 * export (`ExportRetryPolicy`) ; une erreur 4xx ou de configuration reste en
 * échec jusqu'à ce qu'un exploitant la reprenne.
 */
final class TransientExportFailure extends RuntimeException {}
