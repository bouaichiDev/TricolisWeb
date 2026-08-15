<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Packages;

use App\Modules\Packages\Models\GroupingType;

/**
 * Référentiel des types de regroupement de colis.
 *
 * Le diagramme nomme la classe `GroupingType` ; les routes publiques restent
 * `package-grouping-types`, conformément au cahier des charges.
 */
class GroupingTypeController extends PackageReferentialController
{
    protected function modelClass(): string
    {
        return GroupingType::class;
    }

    protected function tableName(): string
    {
        return 'grouping_types';
    }

    protected function entityLabel(): string
    {
        return 'type de regroupement';
    }
}
