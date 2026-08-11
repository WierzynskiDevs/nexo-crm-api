<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant de demonstração para desenvolvimento local, equivalente à "Acme
 * Industries" do protótipo — não deve rodar em produção.
 */
class DemoTenantSeeder extends Seeder
{
    private const PIPELINE_STAGES = [
        'Novo lead', 'Qualificação', 'Contato', 'Proposta', 'Negociação', 'Fechado', 'Perdido',
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Industries',
            'slug' => 'acme-industries',
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Principal',
            'slug' => 'principal',
            'is_default' => true,
        ]);

        Setting::query()->create(['tenant_id' => $tenant->id]);

        $growthPlan = Plan::query()->where('slug', 'growth')->first();
        if ($growthPlan) {
            Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $growthPlan->id,
                'status' => 'active',
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ]);
        }

        $pipeline = Pipeline::query()->create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'name' => 'Comercial',
            'is_default' => true,
        ]);

        $stages = [];
        foreach (array_values(self::PIPELINE_STAGES) as $index => $name) {
            $stages[$name] = PipelineStage::query()->create([
                'pipeline_id' => $pipeline->id,
                'name' => $name,
                'position' => $index,
                'is_won' => $name === 'Fechado',
                'is_lost' => $name === 'Perdido',
            ]);
        }

        $users = $this->createUsers($tenant);

        Team::factory()->create([
            'tenant_id' => $tenant->id,
            'lead_user_id' => $users['manager']->id,
            'pipeline_id' => $pipeline->id,
        ])->members()->attach([$users['sales']->id, $users['support']->id]);

        Lead::factory(15)
            ->create(['tenant_id' => $tenant->id, 'workspace_id' => $workspace->id, 'owner_id' => $users['sales']->id]);

        Client::factory(6)
            ->create(['tenant_id' => $tenant->id, 'workspace_id' => $workspace->id, 'owner_id' => $users['manager']->id]);

        Opportunity::factory(10)->create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => fn () => $stages[array_rand($stages)]->id,
            'owner_id' => $users['sales']->id,
        ]);

        Task::factory(12)
            ->create(['tenant_id' => $tenant->id, 'workspace_id' => $workspace->id, 'owner_id' => $users['support']->id]);

        Event::factory(8)
            ->create(['tenant_id' => $tenant->id, 'workspace_id' => $workspace->id, 'owner_id' => $users['manager']->id]);
    }

    /**
     * @return array<string, User>
     */
    private function createUsers(Tenant $tenant): array
    {
        $roles = Role::query()->get()->keyBy('slug');

        $definitions = [
            'super_admin' => ['name' => 'Super Admin', 'email' => 'super@nexocrm.com'],
            'admin' => ['name' => 'Admin Acme', 'email' => 'admin@acme.test'],
            'manager' => ['name' => 'Marina Alves', 'email' => 'marina@acme.test'],
            'sales' => ['name' => 'Rafael Souza', 'email' => 'rafael@acme.test'],
            'support' => ['name' => 'Camila Duarte', 'email' => 'camila@acme.test'],
        ];

        $users = [];
        foreach ($definitions as $roleSlug => $definition) {
            $user = User::query()->create([
                'name' => $definition['name'],
                'email' => $definition['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            Membership::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role_id' => $roles[$roleSlug]->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $users[$roleSlug] = $user;
        }

        return $users;
    }
}
