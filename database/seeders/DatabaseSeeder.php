<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * IMPORTANTE: nenhum seeder aqui pode usar WithoutModelEvents — a geração
     * do UUID v7 (HasUuidV7) depende do evento "creating" do Eloquent.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PlanSeeder::class,
            DemoTenantSeeder::class,
        ]);
    }
}
