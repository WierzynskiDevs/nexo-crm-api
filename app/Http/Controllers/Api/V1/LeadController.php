<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\Tag;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class LeadController extends Controller
{
    #[OA\Get(
        path: '/api/v1/leads',
        summary: 'Lista os leads do tenant',
        security: [['bearerAuth' => []]],
        tags: ['Leads'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'search', description: 'Busca por nome ou empresa', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['new', 'contacted', 'qualified', 'disqualified', 'converted'])),
            new OA\Parameter(name: 'source', in: 'query', schema: new OA\Schema(type: 'string', enum: ['inbound', 'outbound', 'referral', 'event', 'ads'])),
            new OA\Parameter(name: 'owner_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'sort', description: 'Formato `coluna:direcao`, ex.: `created_at:desc`', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/LeadCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Lead::class);

        $leads = Lead::query()
            ->with(['owner', 'tags'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($q) => $q->where('name', 'ilike', $term)->orWhere('company', 'ilike', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('owner_id'), fn ($query) => $query->where('owner_id', $request->string('owner_id')))
            ->when(
                $request->filled('sort'),
                function ($query) use ($request) {
                    [$column, $direction] = array_pad(explode(':', (string) $request->string('sort')), 2, 'asc');
                    $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
                },
                fn ($query) => $query->latest(),
            )
            ->paginate($request->integer('per_page', 15));

        return LeadResource::collection($leads);
    }

    #[OA\Post(
        path: '/api/v1/leads',
        summary: 'Cria um lead',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'source'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'company', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'source', type: 'string', enum: ['inbound', 'outbound', 'referral', 'event', 'ads']),
                    new OA\Property(property: 'status', type: 'string', enum: ['new', 'contacted', 'qualified', 'disqualified', 'converted']),
                    new OA\Property(property: 'priority', type: 'string', enum: ['high', 'medium', 'low']),
                    new OA\Property(property: 'score', type: 'integer', maximum: 100, minimum: 0),
                    new OA\Property(property: 'value_cents', description: 'Valor potencial em centavos', type: 'integer'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'due_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'owner_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'tags', description: 'Nomes de tags; as inexistentes são criadas no tenant', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        tags: ['Leads'],
        responses: [
            new OA\Response(response: 201, description: 'Lead criado', content: new OA\JsonContent(ref: '#/components/schemas/LeadEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreLeadRequest $request): LeadResource
    {
        $lead = Lead::create($request->safe()->except('tags'));

        if ($request->filled('tags')) {
            $this->syncTags($lead, $request->array('tags'));
        }

        return new LeadResource($lead->load(['owner', 'tags']));
    }

    #[OA\Get(
        path: '/api/v1/leads/{lead}',
        summary: 'Exibe um lead',
        security: [['bearerAuth' => []]],
        tags: ['Leads'],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/LeadEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return new LeadResource($lead->load(['owner', 'tags']));
    }

    #[OA\Put(
        path: '/api/v1/leads/{lead}',
        summary: 'Atualiza um lead',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'Campos aceitos são os mesmos da criação, todos opcionais. Enviar `tags` substitui o conjunto atual.',
            content: new OA\JsonContent(ref: '#/components/schemas/Lead'),
        ),
        tags: ['Leads'],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Lead atualizado', content: new OA\JsonContent(ref: '#/components/schemas/LeadEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->safe()->except('tags'));

        if ($request->has('tags')) {
            $this->syncTags($lead, $request->array('tags'));
        }

        return new LeadResource($lead->load(['owner', 'tags']));
    }

    #[OA\Delete(
        path: '/api/v1/leads/{lead}',
        summary: 'Exclui um lead (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Leads'],
        parameters: [new OA\Parameter(name: 'lead', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    private function syncTags(Lead $lead, array $tagNames): void
    {
        $tenantId = app(TenantContext::class)->id();

        $tagIds = collect($tagNames)
            ->filter()
            ->map(fn (string $name) => Tag::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
            )->id);

        $lead->tags()->sync($tagIds);
    }
}
