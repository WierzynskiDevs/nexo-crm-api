<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates a task with checklist items', function () {
    ['token' => $token] = actingAsTenantUser('support');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tasks', [
            'title' => 'Preparar proposta',
            'checklist' => ['Levantar requisitos', 'Enviar para revisão'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Preparar proposta')
        ->assertJsonCount(2, 'data.checklist_items');
});

it('moves a task between columns', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('support');
    $task = Task::factory()->create(['tenant_id' => $tenant->id, 'column' => 'backlog']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/tasks/{$task->id}/move", ['column' => 'in_progress', 'position' => 0])
        ->assertOk()
        ->assertJsonPath('data.column', 'in_progress');
});

it('manages checklist items independently and toggles completion', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('support');
    $task = Task::factory()->create(['tenant_id' => $tenant->id]);

    $item = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/tasks/{$task->id}/checklist-items", ['title' => 'Item 1'])
        ->assertCreated()
        ->json('data');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/tasks/{$task->id}/checklist-items/{$item['id']}", ['is_done' => true])
        ->assertOk()
        ->assertJsonPath('data.is_done', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/tasks/{$task->id}/checklist-items/{$item['id']}")
        ->assertStatus(204);
});

it('returns 404 for a task belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('support');
    $foreignTask = Task::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tasks/{$foreignTask->id}")
        ->assertStatus(404);
});

it('rejects deleting a task without tarefas.excluir permission', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $task = Task::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertStatus(403);
});
