<?php

declare(strict_types=1);

namespace App\Modules\Templates\Enums;

/**
 * Le rayon dans lequel un modèle se range.
 *
 * Une seule table porte les treize natures ; un comptable qui cherche sa mise
 * en page de facture n'a pas à la trouver au milieu des SMS de rendez-vous, ni
 * un exploitant son BL au milieu des courriels.
 *
 * Le filtre existe **côté serveur** parce que « communication » se définit par
 * la négative — tout ce qui n'est pas un document — et qu'un écran ne peut pas
 * exprimer cela avec un filtre `templateType` à valeur unique. Il aurait fallu
 * énumérer onze natures dans l'URL, et en oublier une au premier ajout.
 */
enum TemplateCategory: string
{
    case COMMUNICATION = 'communication';

    case INVOICE = 'invoice';

    case DELIVERY_NOTE = 'delivery_note';

    /**
     * Les natures que cette catégorie recouvre.
     *
     * @return list<TemplateType>
     */
    public function types(): array
    {
        return match ($this) {
            self::COMMUNICATION => array_values(array_filter(
                TemplateType::cases(),
                static fn (TemplateType $type): bool => ! $type->isDocument(),
            )),
            self::INVOICE => [TemplateType::INVOICE],
            self::DELIVERY_NOTE => [TemplateType::DELIVERY_NOTE],
        };
    }

    /**
     * @return list<string>
     */
    public function typeValues(): array
    {
        return array_map(static fn (TemplateType $type): string => $type->value, $this->types());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
