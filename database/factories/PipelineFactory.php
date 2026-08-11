<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => null,
            'name' => fake()->randomElement(['Comercial', 'Enterprise / RFP', 'Renovações & upsell', 'Canais e parcerias']),
            'is_default' => false,
        ];
    }
}
