<?php

namespace Database\Factories\Modules\Identity\Models;

use App\Modules\Identity\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function modelName(): string
    {
        return Permission::class;
    }

    public function definition(): array
    {
        $module = fake()->unique()->word();
        $action = fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'code' => $module.'.'.$action,
            'name' => ucfirst($action).' '.$module,
            'module' => $module,
            'action' => $action,
        ];
    }
}
