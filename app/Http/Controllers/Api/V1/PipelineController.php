<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pipelines\ReorderPipelineStagesRequest;
use App\Http\Requests\Pipelines\StorePipelineRequest;
use App\Http\Requests\Pipelines\StorePipelineStageRequest;
use App\Http\Requests\Pipelines\UpdatePipelineRequest;
use App\Http\Requests\Pipelines\UpdatePipelineStageRequest;
use App\Http\Resources\PipelineResource;
use App\Http\Resources\PipelineStageResource;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PipelineController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pipeline::class);

        return PipelineResource::collection(Pipeline::query()->with('stages')->orderBy('name')->get());
    }

    public function store(StorePipelineRequest $request): PipelineResource
    {
        $pipeline = DB::transaction(function () use ($request) {
            $pipeline = Pipeline::create($request->safe()->except('stages'));

            $stageNames = $request->array('stages') ?: ['Novo', 'Em andamento', 'Fechado', 'Perdido'];
            foreach (array_values($stageNames) as $position => $name) {
                PipelineStage::create([
                    'pipeline_id' => $pipeline->id,
                    'name' => $name,
                    'position' => $position,
                    'is_won' => $position === count($stageNames) - 2,
                    'is_lost' => $position === count($stageNames) - 1,
                ]);
            }

            return $pipeline;
        });

        return new PipelineResource($pipeline->load('stages'));
    }

    public function show(Pipeline $pipeline): PipelineResource
    {
        $this->authorize('view', $pipeline);

        return new PipelineResource($pipeline->load('stages'));
    }

    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): PipelineResource
    {
        $pipeline->update($request->validated());

        return new PipelineResource($pipeline->load('stages'));
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('delete', $pipeline);

        $pipeline->delete();

        return response()->json(null, 204);
    }

    public function storeStage(StorePipelineStageRequest $request, Pipeline $pipeline): PipelineStageResource
    {
        $nextPosition = $pipeline->stages()->max('position') + 1;

        $stage = PipelineStage::create([
            ...$request->validated(),
            'pipeline_id' => $pipeline->id,
            'position' => $nextPosition,
        ]);

        return new PipelineStageResource($stage);
    }

    public function updateStage(UpdatePipelineStageRequest $request, Pipeline $pipeline, PipelineStage $stage): PipelineStageResource
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->update($request->validated());

        return new PipelineStageResource($stage);
    }

    public function destroyStage(Pipeline $pipeline, PipelineStage $stage): JsonResponse
    {
        $this->authorize('update', $pipeline);
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->delete();

        return response()->json(null, 204);
    }

    public function reorderStages(ReorderPipelineStagesRequest $request, Pipeline $pipeline): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $pipeline) {
            $stageIds = $request->array('stage_ids');

            // Duas fases: a unique constraint (pipeline_id, position) é
            // verificada por statement, não no fim da transação — atualizar
            // direto para a posição final pode colidir com a posição atual
            // de outra stage no meio da sequência. Um passo intermediário
            // com valores negativos (fora do intervalo válido) elimina
            // qualquer colisão possível antes de aplicar as posições finais.
            foreach ($stageIds as $index => $stageId) {
                PipelineStage::query()
                    ->where('id', $stageId)
                    ->where('pipeline_id', $pipeline->id)
                    ->update(['position' => -($index + 1)]);
            }

            foreach ($stageIds as $position => $stageId) {
                PipelineStage::query()
                    ->where('id', $stageId)
                    ->where('pipeline_id', $pipeline->id)
                    ->update(['position' => $position]);
            }
        });

        return PipelineStageResource::collection($pipeline->stages()->get());
    }
}
