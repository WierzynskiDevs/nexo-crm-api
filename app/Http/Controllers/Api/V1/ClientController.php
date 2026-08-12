<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ClientController extends Controller
{
    #[OA\Get(
        path: '/api/v1/clients',
        summary: 'Lista os clientes do tenant',
        security: [['bearerAuth' => []]],
        tags: ['Clientes'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'search', description: 'Busca por nome da conta ou do contato', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'health', in: 'query', schema: new OA\Schema(type: 'string', enum: ['healthy', 'attention', 'risk'])),
            new OA\Parameter(name: 'segment', in: 'query', schema: new OA\Schema(type: 'string', enum: ['enterprise', 'mid_market', 'smb'])),
            new OA\Parameter(name: 'archived', description: 'Quando true, lista apenas arquivados; caso contrário, apenas ativos', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/ClientCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->with('owner')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($q) => $q->where('name', 'ilike', $term)->orWhere('contact_name', 'ilike', $term));
            })
            ->when($request->filled('health'), fn ($query) => $query->where('health', $request->string('health')))
            ->when($request->filled('segment'), fn ($query) => $query->where('segment', $request->string('segment')))
            ->when($request->boolean('archived'), fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ClientResource::collection($clients);
    }

    #[OA\Post(
        path: '/api/v1/clients',
        summary: 'Cria um cliente',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'contact_name', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'mrr_cents', description: 'Receita recorrente mensal em centavos', type: 'integer'),
                    new OA\Property(property: 'health', type: 'string', enum: ['healthy', 'attention', 'risk']),
                    new OA\Property(property: 'segment', type: 'string', enum: ['enterprise', 'mid_market', 'smb']),
                    new OA\Property(property: 'client_since', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'converted_from_lead_id', description: 'Lead de origem; precisa pertencer ao mesmo tenant', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'owner_id', type: 'string', format: 'uuid', nullable: true),
                ],
            ),
        ),
        tags: ['Clientes'],
        responses: [
            new OA\Response(response: 201, description: 'Cliente criado', content: new OA\JsonContent(ref: '#/components/schemas/ClientEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreClientRequest $request): ClientResource
    {
        $client = Client::create($request->validated());

        return new ClientResource($client->load('owner'));
    }

    #[OA\Get(
        path: '/api/v1/clients/{client}',
        summary: 'Exibe um cliente',
        security: [['bearerAuth' => []]],
        tags: ['Clientes'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ClientEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);

        return new ClientResource($client->load('owner'));
    }

    #[OA\Put(
        path: '/api/v1/clients/{client}',
        summary: 'Atualiza um cliente',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'Mesmos campos da criação, todos opcionais. `archived_at` arquiva/desarquiva a conta.',
            content: new OA\JsonContent(ref: '#/components/schemas/Client'),
        ),
        tags: ['Clientes'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Cliente atualizado', content: new OA\JsonContent(ref: '#/components/schemas/ClientEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client->load('owner'));
    }

    #[OA\Delete(
        path: '/api/v1/clients/{client}',
        summary: 'Exclui um cliente (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Clientes'],
        parameters: [new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->json(null, 204);
    }
}
