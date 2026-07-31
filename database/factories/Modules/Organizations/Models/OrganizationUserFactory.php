<?php

namespace Database\Factories\Modules\Organizations\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUser>
 */
class OrganizationUserFactory extends Factory
{
    public function modelName(): string
    {
        return OrganizationUser::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'is_owner' => false,
            'is_primary' => true,
            'status' => UserStatus::ACTIVE,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['is_owner' => true]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
