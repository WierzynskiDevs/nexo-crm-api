<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\Notification as NotificationModel;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Scopes\TenantScope;
use App\Models\Task;
use App\Models\User;
use App\Notifications\EventInviteNotification;
use App\Notifications\OpportunityWonNotification;
use App\Notifications\TaskAssignedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('notifies the rest of the tenant when an opportunity is won', function () {
    Notification::fake();

    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');

    $colleague = User::factory()->create();
    memberOf($colleague, $tenant, 'sales');

    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenant->id]);
    $open = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 0]);
    $wonStage = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 1, 'is_won' => true]);

    $opportunity = App\Models\Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $open->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/opportunities/{$opportunity->id}/stage", ['pipeline_stage_id' => $wonStage->id])
        ->assertOk();

    Notification::assertSentTo($colleague, OpportunityWonNotification::class);
});

it('does not notify when the opportunity moves to a stage that is not won', function () {
    Notification::fake();

    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $colleague = User::factory()->create();
    memberOf($colleague, $tenant, 'sales');

    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenant->id]);
    $first = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 0]);
    $second = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 1]);

    $opportunity = App\Models\Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $first->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/opportunities/{$opportunity->id}/stage", ['pipeline_stage_id' => $second->id])
        ->assertOk();

    Notification::assertNothingSent();
});

it('notifies the assignee when a task is created for someone else', function () {
    Notification::fake();

    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $assignee = User::factory()->create();
    memberOf($assignee, $tenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tasks', [
            'title' => 'Preparar proposta',
            'column' => 'backlog',
            'priority' => 'high',
            'owner_id' => $assignee->id,
        ])
        ->assertCreated();

    Notification::assertSentTo($assignee, TaskAssignedNotification::class);
});

it('does not notify when a user assigns a task to themselves', function () {
    Notification::fake();

    ['token' => $token, 'user' => $user] = actingAsTenantUser('admin');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tasks', [
            'title' => 'Revisar pipeline',
            'column' => 'backlog',
            'priority' => 'low',
            'owner_id' => $user->id,
        ])
        ->assertCreated();

    Notification::assertNothingSent();
});

it('does not renotify when a task is edited without changing the assignee', function () {
    Notification::fake();

    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $assignee = User::factory()->create();
    memberOf($assignee, $tenant, 'sales');

    $task = Task::factory()->create(['tenant_id' => $tenant->id, 'owner_id' => $assignee->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Título novo'])
        ->assertOk();

    Notification::assertNothingSent();
});

it('notifies internal guests when an event is scheduled', function () {
    Notification::fake();

    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $guest = User::factory()->create();
    memberOf($guest, $tenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/events', [
            'title' => 'Reunião de kickoff',
            'kind' => 'meeting',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->addHour()->toIso8601String(),
            'guests' => [
                ['user_id' => $guest->id],
                ['name' => 'Convidado externo', 'email' => 'externo@example.com'],
            ],
        ])
        ->assertCreated();

    Notification::assertSentTo($guest, EventInviteNotification::class);
    Notification::assertSentTimes(EventInviteNotification::class, 1);
});

it('writes a real in-app notification row scoped to the tenant', function () {
    // Sem Notification::fake() aqui: o objetivo é provar que o canal
    // customizado grava a linha com tenant_id e destinatário corretos.
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $assignee = User::factory()->create();
    memberOf($assignee, $tenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tasks', [
            'title' => 'Ligar para o cliente',
            'column' => 'backlog',
            'priority' => 'medium',
            'owner_id' => $assignee->id,
        ])
        ->assertCreated();

    $notification = NotificationModel::withoutGlobalScope(TenantScope::class)
        ->where('notifiable_id', $assignee->id)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->tenant_id)->toBe($tenant->id)
        ->and($notification->type)->toBe(NotificationType::TaskAssigned->value)
        ->and($notification->data['task_title'])->toBe('Ligar para o cliente')
        ->and($notification->read_at)->toBeNull();
});
