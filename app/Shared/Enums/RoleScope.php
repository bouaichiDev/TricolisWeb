<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Portée d'un rôle.
 *
 * C'est la frontière entre les deux niveaux d'administration de Tricolis :
 * la plateforme d'un côté, chaque organisme de l'autre. Un rôle de portée
 * `PLATFORM` confère une autorité qui traverse les organisations ; un rôle
 * `ORGANIZATION` s'arrête à la sienne.
 *
 * Cette distinction est portée par la colonne `roles.scope`, prévue par le
 * diagramme de classes. Elle n'est **jamais** déduite d'un code ni d'un nom :
 * un rôle nommé « SuperAdmin » sans portée plateforme n'a aucun pouvoir
 * particulier, et c'est précisément ce qui empêche un administrateur local de
 * s'élever en choisissant un libellé.
 */
enum RoleScope: string
{
    case PLATFORM = 'platform';
    case ORGANIZATION = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM => 'Plateforme',
            self::ORGANIZATION => 'Organisation',
        };
    }

    /**
     * Portée qu'un administrateur d'organisme peut créer, et la seule.
     */
    public static function default(): self
    {
        return self::ORGANIZATION;
    }

    public static function tryFromValue(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }
}
