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
use OpenApi\Attributes as OA;

class PipelineController extends Controller
{
    #[OA\Get(
        path: '/api/v1/pipelines',
        summary: 'Lista os pipelines do tenant com suas etapas',
        description: 'Não é paginado: a quantidade de funis por tenant é pequena e a UI precisa de todos de uma vez.',
        security: [['bearerAuth' => []]],
        tags: ['Pipelines'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/PipelineCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pipeline::class);

        return PipelineResource::collection(Pipeline::query()->with('stages')->orderBy('name')->get());
    }

    #[OA\Post(
        path: '/api/v1/pipelines',
        summary: 'Cria um pipeline',
        description: 'Sem `stages`, cria o conjunto padrão (Novo, Em andamento, Fechado, Perdido). A penúltima etapa vira a de ganho e a última a de perda.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Comercial'),
                    new OA\Property(property: 'is_default', type: 'boolean'),
                    new OA\Property(property: 'stages', description: 'Nomes das etapas, na ordem', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        tags: ['Pipelines'],
        responses: [
            new OA\Response(response: 201, description: 'Pipeline criado', content: new OA\JsonContent(ref: '#/components/schemas/PipelineEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
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

    #[OA\Get(
        path: '/api/v1/pipelines/{pipeline}',
        summary: 'Exibe um pipeline com suas etapas',
        security: [['bearerAuth' => []]],
        tags: ['Pipelines'],
        parameters: [new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/PipelineEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Pipeline $pipeline): PipelineResource
    {
        $this->authorize('view', $pipeline);

        return new PipelineResource($pipeline->load('stages'));
    }

    #[OA\Put(
        path: '/api/v1/pipelines/{pipeline}',
        summary: 'Atualiza um pipeline',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'is_default', type: 'boolean'),
        ], type: 'object')),
        tags: ['Pipelines'],
        parameters: [new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Pipeline atualizado', content: new OA\JsonContent(ref: '#/components/schemas/PipelineEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): PipelineResource
    {
        $pipeline->update($request->validated());

        return new PipelineResource($pipeline->load('stages'));
    }

    #[OA\Delete(
        path: '/api/v1/pipelines/{pipeline}',
        summary: 'Exclui um pipeline (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Pipelines'],
        parameters: [new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $this->authorize('delete', $pipeline);

        $pipeline->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/pipelines/{pipeline}/stages',
        summary: 'Adiciona uma etapa ao pipeline',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'is_won', type: 'boolean'),
                    new OA\Property(property: 'is_lost', type: 'boolean'),
                ],
            ),
        ),
        tags: ['Pipelines'],
        parameters: [new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 201, description: 'Etapa criada', content: new OA\JsonContent(ref: '#/components/schemas/PipelineStageEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
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

    #[OA\Patch(
        path: '/api/v1/pipelines/{pipeline}/stages/{stage}',
        summary: 'Atualiza uma etapa',
        description: 'A etapa precisa pertencer ao pipeline da URL; caso contrário responde 404.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'is_won', type: 'boolean'),
            new OA\Property(property: 'is_lost', type: 'boolean'),
        ], type: 'object')),
        tags: ['Pipelines'],
        parameters: [
            new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'stage', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Etapa atualizada', content: new OA\JsonContent(ref: '#/components/schemas/PipelineStageEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function updateStage(UpdatePipelineStageRequest $request, Pipeline $pipeline, PipelineStage $stage): PipelineStageResource
    {
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->update($request->validated());

        return new PipelineStageResource($stage);
    }

    #[OA\Delete(
        path: '/api/v1/pipelines/{pipeline}/stages/{stage}',
        summary: 'Remove uma etapa',
        security: [['bearerAuth' => []]],
        tags: ['Pipelines'],
        parameters: [
            new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'stage', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroyStage(Pipeline $pipeline, PipelineStage $stage): JsonResponse
    {
        $this->authorize('update', $pipeline);
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        $stage->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/pipelines/{pipeline}/stages/reorder',
        summary: 'Reordena as etapas do pipeline',
        description: 'Recebe os ids das etapas na ordem desejada. A posição é única por pipeline no banco, então a gravação é feita em duas fases para não colidir no meio da transação.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['stages'],
                properties: [
                    new OA\Property(property: 'stages', description: 'Ids das etapas na ordem final', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ],
            ),
        ),
        tags: ['Pipelines'],
        parameters: [new OA\Parameter(name: 'pipeline', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Etapas na nova ordem',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PipelineStage')),
                ], type: 'object'),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
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
