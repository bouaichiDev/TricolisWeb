<?php

declare(strict_types=1);

namespace App\Modules\Templates\Exceptions;

use RuntimeException;

/**
 * Échec de rendu d'un modèle.
 *
 * Traduite en 422 par les contrôleurs : la faute est dans les données fournies
 * — un modèle employant une variable non déclarée, ou un jeu de variables
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
        return new self('Le modèle contient une expression non reconnue : seuls {{ chemin }} et {{#liste}} … {{/liste}} sont acceptés.');
    }

    public static function notAList(string $name): self
    {
        return new self("« {$name} » n’est pas une liste : une section ne peut se répéter que sur une liste d’éléments.");
    }

    public static function nestedSection(string $name): self
    {
        return new self("La section « {$name} » en contient une autre : une seule profondeur est acceptée.");
    }

    public static function tooDeep(string $name): self
    {
        return new self("Le chemin « {$name} » est trop profond : quatre niveaux au maximum.");
    }
}
