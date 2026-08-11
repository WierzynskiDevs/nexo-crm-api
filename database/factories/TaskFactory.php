<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskColumn;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'workspace_id' => null,
            'owner_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'column' => fake()->randomElement(TaskColumn::cases()),
            'priority' => fake()->randomElement(Priority::cases()),
            'tag' => fake()->optional()->word(),
            'position' => 0,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
