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

class OpportunityController extends Controller
{
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

    public function show(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);

        return new OpportunityResource($opportunity->load('owner', 'stageTransitions'));
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $opportunity->update($request->validated());

        return new OpportunityResource($opportunity->load('owner'));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);

        $opportunity->delete();

        return response()->json(null, 204);
    }

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
