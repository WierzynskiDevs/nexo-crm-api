<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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

class TaskController extends Controller
{
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

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    public function move(MoveTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->load(['owner', 'checklistItems']));
    }

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

    public function updateChecklistItem(UpdateChecklistItemRequest $request, Task $task, TaskChecklistItem $checklistItem): TaskChecklistItemResource
    {
        $checklistItem->update($request->validated());

        return new TaskChecklistItemResource($checklistItem);
    }

    public function destroyChecklistItem(Task $task, TaskChecklistItem $checklistItem): JsonResponse
    {
        $this->authorize('update', $task);

        $checklistItem->delete();

        return response()->json(null, 204);
    }
}
