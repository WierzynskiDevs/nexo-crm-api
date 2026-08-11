<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lead_user_id' => null,
            'pipeline_id' => null,
            'name' => fake()->randomElement(['Squad Enterprise', 'Squad SMB', 'Squad Renovação', 'Squad Parceiros']),
            'goal_amount_cents' => fake()->numberBetween(100_000_00, 2_000_000_00),
        ];
    }
}
