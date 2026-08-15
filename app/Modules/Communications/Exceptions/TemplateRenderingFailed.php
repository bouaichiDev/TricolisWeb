<?php

declare(strict_types=1);

namespace App\Modules\Communications\Exceptions;

use RuntimeException;

/**
 * Échec de rendu d'un template.
 *
 * Traduite en 422 par les contrôleurs : la faute est dans les données fournies
 * — un template employant une variable non déclarée, ou un jeu de variables
 * incomplet.
 */
final class TemplateRenderingFailed extends RuntimeException
{
    public static function undeclaredVariable(string $name): self
    {
        return new self("La variable « {$name} » n’est pas déclarée dans les variables disponibles du modèle.");
    }

    public static function missingValue(string $name): self
    {
        return new self("Aucune valeur n’a été fournie pour la variable « {$name} ».");
    }

    public static function nonScalarValue(string $name): self
    {
        return new self("La variable « {$name} » doit recevoir une valeur simple : texte, nombre ou booléen.");
    }

    public static function malformedPlaceholder(): self
    {
        return new self('Le modèle contient une expression non reconnue : seul le motif {{ nom_de_variable }} est accepté.');
    }
}
