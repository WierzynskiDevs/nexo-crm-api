<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Priority;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => null,
            'owner_id' => null,
            'name' => fake()->name(),
            'company' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => fake()->randomElement(LeadStatus::cases()),
            'priority' => fake()->randomElement(Priority::cases()),
            'score' => fake()->numberBetween(0, 100),
            'value_cents' => fake()->numberBetween(2_000_00, 150_000_00),
            'notes' => fake()->optional()->sentence(),
            'due_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
