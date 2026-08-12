<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\TaskAssigned;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\StoreChecklistItemRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateChecklistItemRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskChecklistItemResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class TaskController extends Controller
{
    #[OA\Get(
        path: '/api/v1/tasks',
        summary: 'Lista as tarefas do tenant',
        description: 'Ordenadas por `position`, para alimentar o quadro kanban.',
        security: [['bearerAuth' => []]],
        tags: ['Tarefas'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'column', in: 'query', schema: new OA\Schema(type: 'string', enum: ['backlog', 'in_progress', 'review', 'done'])),
            new OA\Parameter(name: 'owner_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/TaskCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->with(['owner', 'checklistItems'])
            ->when($request->filled('column'), fn ($query) => $query->where('column', $request->string('column')))
            ->when($request->filled('owner_id'), fn ($query) => $query->where('owner_id', $request->string('owner_id')))
            ->orderBy('position')
            ->paginate($request->integer('per_page', 50));

        return TaskResource::collection($tasks);
    }

    #[OA\Post(
        path: '/api/v1/tasks',
        summary: 'Cria uma tarefa',
        description: 'Informar `owner_id` diferente de quem está criando notifica o responsável (sino e e-mail).',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'column', 'priority'],
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'column', type: 'string', enum: ['backlog', 'in_progress', 'review', 'done']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['high', 'medium', 'low']),
                    new OA\Property(property: 'tag', type: 'string', nullable: true),
                    new OA\Property(property: 'due_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'owner_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'checklist', description: 'Títulos dos itens de checklist, na ordem', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        tags: ['Tarefas'],
        responses: [
            new OA\Response(response: 201, description: 'Tarefa criada', content: new OA\JsonContent(ref: '#/components/schemas/TaskEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = Task::create($request->safe()->except('checklist'));

        foreach (array_values($request->array('checklist')) as $position => $title) {
            TaskChecklistItem::create([
                'task_id' => $task->id,
                'title' => $title,
                'position' => $position,
            ]);
        }

        if ($task->owner_id !== null) {
            TaskAssigned::dispatch($task, $task->owner_id);
        }

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    #[OA\Get(
        path: '/api/v1/tasks/{task}',
        summary: 'Exibe uma tarefa com seu checklist',
        security: [['bearerAuth' => []]],
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TaskEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    #[OA\Put(
        path: '/api/v1/tasks/{task}',
        summary: 'Atualiza uma tarefa',
        description: 'Trocar o `owner_id` notifica o novo responsável. Editar outros campos não reenvia notificação.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/Task')),
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Tarefa atualizada', content: new OA\JsonContent(ref: '#/components/schemas/TaskEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $previousOwnerId = $task->owner_id;

        $task->update($request->validated());

        // Só quando o responsável realmente mudou: um PATCH de título não
        // deve reenviar "nova tarefa atribuída a você".
        if ($task->owner_id !== null && $task->owner_id !== $previousOwnerId) {
            TaskAssigned::dispatch($task, $task->owner_id);
        }

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    #[OA\Delete(
        path: '/api/v1/tasks/{task}',
        summary: 'Exclui uma tarefa (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    #[OA\Patch(
        path: '/api/v1/tasks/{task}/move',
        summary: 'Move a tarefa entre colunas do quadro',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['column'],
                properties: [
                    new OA\Property(property: 'column', type: 'string', enum: ['backlog', 'in_progress', 'review', 'done']),
                    new OA\Property(property: 'position', description: 'Posição dentro da coluna de destino', type: 'integer'),
                ],
            ),
        ),
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Tarefa movida', content: new OA\JsonContent(ref: '#/components/schemas/TaskEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function move(MoveTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    #[OA\Post(
        path: '/api/v1/tasks/{task}/checklist-items',
        summary: 'Adiciona um item ao checklist da tarefa',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['title'], properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'is_done', type: 'boolean'),
            ]),
        ),
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 201, description: 'Item criado', content: new OA\JsonContent(ref: '#/components/schemas/TaskChecklistItemEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function storeChecklistItem(StoreChecklistItemRequest $request, Task $task): TaskChecklistItemResource
    {
        $nextPosition = $task->checklistItems()->max('position') + 1;

        $item = TaskChecklistItem::create([
            ...$request->validated(),
            'task_id' => $task->id,
            'position' => $nextPosition,
        ]);

        return new TaskChecklistItemResource($item);
    }

    #[OA\Patch(
        path: '/api/v1/tasks/{task}/checklist-items/{checklistItem}',
        summary: 'Atualiza um item do checklist',
        description: 'O item precisa pertencer à tarefa da URL; caso contrário responde 404.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'is_done', type: 'boolean'),
            new OA\Property(property: 'position', type: 'integer'),
        ], type: 'object')),
        tags: ['Tarefas'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'checklistItem', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item atualizado', content: new OA\JsonContent(ref: '#/components/schemas/TaskChecklistItemEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function updateChecklistItem(UpdateChecklistItemRequest $request, Task $task, TaskChecklistItem $checklistItem): TaskChecklistItemResource
    {
        abort_unless($checklistItem->task_id === $task->id, 404);

        $checklistItem->update($request->validated());

        return new TaskChecklistItemResource($checklistItem);
    }

    #[OA\Delete(
        path: '/api/v1/tasks/{task}/checklist-items/{checklistItem}',
        summary: 'Remove um item do checklist',
        security: [['bearerAuth' => []]],
        tags: ['Tarefas'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'checklistItem', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroyChecklistItem(Task $task, TaskChecklistItem $checklistItem): JsonResponse
    {
        $this->authorize('update', $task);
        abort_unless($checklistItem->task_id === $task->id, 404);

        $checklistItem->delete();

        return response()->json(null, 204);
    }
}
