<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OpportunityStatus;
use App\Events\OpportunityWon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunities\MoveOpportunityStageRequest;
use App\Http\Requests\Opportunities\StoreOpportunityRequest;
use App\Http\Requests\Opportunities\UpdateOpportunityRequest;
use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\StageTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OpportunityController extends Controller
{
    #[OA\Get(
        path: '/api/v1/opportunities',
        summary: 'Lista as oportunidades do tenant',
        security: [['bearerAuth' => []]],
        tags: ['Oportunidades'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'pipeline_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'pipeline_stage_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'won', 'lost'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/OpportunityCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Opportunity::class);

        $opportunities = Opportunity::query()
            ->with('owner')
            ->when($request->filled('pipeline_id'), fn ($query) => $query->where('pipeline_id', $request->string('pipeline_id')))
            ->when($request->filled('pipeline_stage_id'), fn ($query) => $query->where('pipeline_stage_id', $request->string('pipeline_stage_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return OpportunityResource::collection($opportunities);
    }

    #[OA\Post(
        path: '/api/v1/opportunities',
        summary: 'Cria uma oportunidade',
        description: 'Registra também a transição inicial de etapa. Os ids referenciados são validados contra o tenant corrente.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'pipeline_id', 'pipeline_stage_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'pipeline_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'pipeline_stage_id', description: 'Precisa pertencer ao pipeline informado', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'lead_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'client_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'owner_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'value_cents', type: 'integer'),
                    new OA\Property(property: 'probability', type: 'integer', maximum: 100, minimum: 0),
                    new OA\Property(property: 'expected_close_date', type: 'string', format: 'date', nullable: true),
                ],
            ),
        ),
        tags: ['Oportunidades'],
        responses: [
            new OA\Response(response: 201, description: 'Oportunidade criada', content: new OA\JsonContent(ref: '#/components/schemas/OpportunityEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreOpportunityRequest $request): OpportunityResource
    {
        $opportunity = DB::transaction(function () use ($request) {
            // Definido explicitamente (não só via default da coluna): o
            // default do banco não é refletido de volta no objeto Eloquent
            // recém-criado, e o valor é usado logo abaixo e na resposta.
            $opportunity = Opportunity::create([
                ...$request->validated(),
                'status' => OpportunityStatus::Open,
            ]);

            StageTransition::create([
                'opportunity_id' => $opportunity->id,
                'from_stage_id' => null,
                'to_stage_id' => $opportunity->pipeline_stage_id,
                'moved_by_user_id' => Auth::guard('api')->id(),
                'moved_at' => now(),
            ]);

            return $opportunity;
        });

        return new OpportunityResource($opportunity->load('owner'));
    }

    #[OA\Get(
        path: '/api/v1/opportunities/{opportunity}',
        summary: 'Exibe uma oportunidade',
        security: [['bearerAuth' => []]],
        tags: ['Oportunidades'],
        parameters: [new OA\Parameter(name: 'opportunity', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/OpportunityEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);

        return new OpportunityResource($opportunity->load('owner', 'stageTransitions'));
    }

    #[OA\Put(
        path: '/api/v1/opportunities/{opportunity}',
        summary: 'Atualiza uma oportunidade',
        description: 'Para mover de etapa use `PATCH /opportunities/{opportunity}/stage`, que também registra a transição.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/Opportunity')),
        tags: ['Oportunidades'],
        parameters: [new OA\Parameter(name: 'opportunity', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Oportunidade atualizada', content: new OA\JsonContent(ref: '#/components/schemas/OpportunityEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $opportunity->update($request->validated());

        return new OpportunityResource($opportunity->load('owner'));
    }

    #[OA\Delete(
        path: '/api/v1/opportunities/{opportunity}',
        summary: 'Exclui uma oportunidade (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Oportunidades'],
        parameters: [new OA\Parameter(name: 'opportunity', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);

        $opportunity->delete();

        return response()->json(null, 204);
    }

    #[OA\Patch(
        path: '/api/v1/opportunities/{opportunity}/stage',
        summary: 'Move a oportunidade de etapa',
        description: <<<'MD'
            Além de mover, registra a transição e ajusta o status conforme a etapa
            de destino: etapa de ganho marca `won` e preenche `closed_at`; etapa de
            perda marca `lost` e grava o `lost_reason`; qualquer outra volta o
            status para `open` e limpa o fechamento.

            Mover para uma etapa de ganho dispara a notificação de oportunidade
            ganha para os demais membros do tenant.

            A etapa de destino precisa pertencer ao mesmo pipeline da oportunidade.
            MD,
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pipeline_stage_id'],
                properties: [
                    new OA\Property(property: 'pipeline_stage_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'lost_reason', description: 'Considerado apenas quando a etapa de destino é de perda', type: 'string', nullable: true),
                ],
            ),
        ),
        tags: ['Oportunidades'],
        parameters: [new OA\Parameter(name: 'opportunity', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Oportunidade movida', content: new OA\JsonContent(ref: '#/components/schemas/OpportunityEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function moveStage(MoveOpportunityStageRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $won = DB::transaction(function () use ($request, $opportunity) {
            $fromStageId = $opportunity->pipeline_stage_id;
            $toStage = PipelineStage::query()->findOrFail($request->string('pipeline_stage_id'));

            $opportunity->pipeline_stage_id = $toStage->id;

            if ($toStage->is_won) {
                $opportunity->status = OpportunityStatus::Won;
                $opportunity->closed_at = now();
                $opportunity->lost_reason = null;
            } elseif ($toStage->is_lost) {
                $opportunity->status = OpportunityStatus::Lost;
                $opportunity->closed_at = now();
                $opportunity->lost_reason = $request->string('lost_reason')->toString() ?: null;
            } else {
                $opportunity->status = OpportunityStatus::Open;
                $opportunity->closed_at = null;
                $opportunity->lost_reason = null;
            }

            $opportunity->save();

            StageTransition::create([
                'opportunity_id' => $opportunity->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStage->id,
                'moved_by_user_id' => Auth::guard('api')->id(),
                'moved_at' => now(),
            ]);

            return $toStage->is_won;
        });

        // Fora da transação de propósito: o efeito colateral só faz sentido
        // depois do commit — notificar sobre uma venda que sofreu rollback
        // seria pior do que não notificar.
        if ($won) {
            OpportunityWon::dispatch($opportunity);
        }

        return new OpportunityResource($opportunity->load('owner'));
    }
}
