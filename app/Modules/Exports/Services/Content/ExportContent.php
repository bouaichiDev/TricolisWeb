<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Content;

/**
 * Ce qu'un export produit : le fichier à transmettre.
 */
final readonly class ExportContent
{
    public function __construct(
        public string $contents,
        public string $fileName,
        public string $contentType,
    ) {}
}
