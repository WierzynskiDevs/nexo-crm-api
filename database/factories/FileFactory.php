<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\File;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'fileable_type' => null,
            'fileable_id' => null,
            'disk' => 'local',
            'path' => Str::uuid7()->toString().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
