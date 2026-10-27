<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Exceptions;

use RuntimeException;

/**
 * Erreur qu'un nouvel essai ne corrigera pas : HTTP 401/403/404, réponse qui
 * n'est pas du JSON, connexion non configurée, destination interdite. Le Job
 * échoue sans relance : boucler ne ferait que répéter l'erreur.
 *
 * Le message ne porte jamais de secret ni de corps de réponse.
 */
final class PermanentIntegrationFailure extends RuntimeException {}
