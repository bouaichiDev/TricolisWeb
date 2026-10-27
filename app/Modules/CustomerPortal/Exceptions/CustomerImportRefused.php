<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Exceptions;

use RuntimeException;

/**
 * Un dépôt de fichier que le portail refuse, avec une raison lisible par le
 * client : intégration désactivée ou automatique, fichier illisible, trop
 * gros, XML dangereux, correspondance absente. Rendu en 422.
 */
final class CustomerImportRefused extends RuntimeException {}
