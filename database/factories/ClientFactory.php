<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClientHealth;
use App\Enums\ClientSegment;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => null,
            'converted_from_lead_id' => null,
            'owner_id' => null,
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mrr_cents' => fake()->numberBetween(1_000_00, 50_000_00),
            'health' => fake()->randomElement(ClientHealth::cases()),
            'segment' => fake()->randomElement(ClientSegment::cases()),
            'client_since' => fake()->dateTimeBetween('-3 years', '-1 month'),
            'archived_at' => null,
        ];
    }
}
