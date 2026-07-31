<?php

namespace Database\Factories\Modules\Organizations\Models;

use App\Modules\Organizations\Enums\SubscriptionStatus;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function modelName(): string
    {
        return Subscription::class;
    }

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan_code' => fake()->randomElement(['starter', 'business', 'enterprise']),
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addYear(),
            'trial_ends_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function trialing(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::EXPIRED,
            'ends_at' => now()->subDay(),
        ]);
    }
}
