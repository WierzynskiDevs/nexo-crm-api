<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Catálogo de planos do Nexo CRM, com os preços do protótipo (seção "Planos").
 * Limites de seats/storage são uma decisão de produto tomada aqui, não
 * extraída do protótipo (o mock só continha preço e nomes de features).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'price_cents' => 24_900,
                'seats_limit' => 5,
                'storage_limit_mb' => 10_240,
                'features' => ['Pipeline único', 'Leads e clientes ilimitados', 'Suporte por e-mail'],
            ],
            [
                'slug' => 'growth',
                'name' => 'Growth',
                'price_cents' => 74_900,
                'seats_limit' => 20,
                'storage_limit_mb' => 51_200,
                'features' => ['Múltiplos pipelines', 'Equipes e metas', 'Automação de tarefas', 'Suporte prioritário'],
            ],
            [
                'slug' => 'scale',
                'name' => 'Scale',
                'price_cents' => 189_000,
                'seats_limit' => 100,
                'storage_limit_mb' => 256_000,
                'features' => ['API e integrações', 'Auditoria avançada', 'Workspaces múltiplos', 'Suporte dedicado'],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'price_cents' => 490_000,
                'seats_limit' => null,
                'storage_limit_mb' => null,
                'features' => ['Seats e armazenamento ilimitados', 'SLA contratual', 'SSO', 'Gerente de conta dedicado'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
