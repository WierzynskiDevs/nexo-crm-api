<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventGuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventGuest>
 */
class EventGuestFactory extends Factory
{
    protected $model = EventGuest::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'response_status' => 'pending',
        ];
    }
}
