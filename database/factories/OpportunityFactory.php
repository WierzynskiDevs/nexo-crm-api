<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => null,
            'pipeline_id' => Pipeline::factory(),
            'pipeline_stage_id' => null,
            'lead_id' => null,
            'client_id' => null,
            'owner_id' => null,
            'name' => fake()->company().' — '.ucfirst(fake()->words(2, true)),
            'value_cents' => fake()->numberBetween(5_000_00, 300_000_00),
            'probability' => fake()->numberBetween(10, 90),
            'expected_close_date' => fake()->dateTimeBetween('now', '+90 days'),
            'status' => OpportunityStatus::Open,
            'lost_reason' => null,
            'closed_at' => null,
        ];
    }
}
