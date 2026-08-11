<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventKind;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 days', '+14 days');
        $end = (clone $start)->modify('+'.fake()->numberBetween(30, 90).' minutes');

        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => null,
            'owner_id' => null,
            'title' => fake()->sentence(3),
            'kind' => fake()->randomElement(EventKind::cases()),
            'starts_at' => $start,
            'ends_at' => $end,
            'location' => fake()->optional()->city(),
            'notes' => fake()->optional()->sentence(),
            'related_type' => null,
            'related_id' => null,
            'canceled_at' => null,
        ];
    }
}
