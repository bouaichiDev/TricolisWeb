<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Packages;

use App\Modules\Packages\Models\PackageType;

/**
 * Référentiel des types de colis : palette, carton, rolls, bac…
 */
class PackageTypeController extends PackageReferentialController
{
    protected function modelClass(): string
    {
        return PackageType::class;
    }

    protected function tableName(): string
    {
        return 'package_types';
    }

    protected function entityLabel(): string
    {
        return 'type de colis';
    }
}
