<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AttachTeamMemberRequest;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class TeamController extends Controller
{
    #[OA\Get(
        path: '/api/v1/teams',
        summary: 'Lista as equipes do tenant',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TeamCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        return TeamResource::collection(Team::query()->with(['leadUser', 'members'])->latest()->get());
    }

    #[OA\Post(
        path: '/api/v1/teams',
        summary: 'Cria uma equipe',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'goal_amount_cents', description: 'Meta da equipe em centavos', type: 'integer', nullable: true),
                    new OA\Property(property: 'pipeline_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'lead_user_id', description: 'Responsável pela equipe', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'members', description: 'Ids de usuários que já entram na equipe', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ],
            ),
        ),
        tags: ['Governança'],
        responses: [
            new OA\Response(response: 201, description: 'Equipe criada', content: new OA\JsonContent(ref: '#/components/schemas/TeamEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(StoreTeamRequest $request): TeamResource
    {
        $team = DB::transaction(function () use ($request) {
            $team = Team::create($request->safe()->except('member_ids'));

            if ($request->filled('member_ids')) {
                $team->members()->sync($request->array('member_ids'));
            }

            return $team;
        });

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    #[OA\Get(
        path: '/api/v1/teams/{team}',
        summary: 'Exibe uma equipe com seus membros',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        parameters: [new OA\Parameter(name: 'team', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/TeamEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    #[OA\Put(
        path: '/api/v1/teams/{team}',
        summary: 'Atualiza uma equipe',
        description: 'Não mexe na lista de membros — use os endpoints de membros da equipe.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'goal_amount_cents', type: 'integer', nullable: true),
            new OA\Property(property: 'pipeline_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'lead_user_id', type: 'string', format: 'uuid', nullable: true),
        ], type: 'object')),
        tags: ['Governança'],
        parameters: [new OA\Parameter(name: 'team', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Equipe atualizada', content: new OA\JsonContent(ref: '#/components/schemas/TeamEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    #[OA\Delete(
        path: '/api/v1/teams/{team}',
        summary: 'Exclui uma equipe',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        parameters: [new OA\Parameter(name: 'team', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/teams/{team}/members',
        summary: 'Adiciona um usuário à equipe',
        description: 'Idempotente: adicionar quem já está na equipe não duplica o vínculo.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['user_id'], properties: [
                new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
            ]),
        ),
        tags: ['Governança'],
        parameters: [new OA\Parameter(name: 'team', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Equipe com a lista atualizada', content: new OA\JsonContent(ref: '#/components/schemas/TeamEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function attachMember(AttachTeamMemberRequest $request, Team $team): TeamResource
    {
        $team->members()->syncWithoutDetaching([$request->input('user_id')]);

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    #[OA\Delete(
        path: '/api/v1/teams/{team}/members/{user}',
        summary: 'Remove um usuário da equipe',
        description: 'Desfaz apenas o vínculo com a equipe: o usuário continua membro da empresa.',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        parameters: [
            new OA\Parameter(name: 'team', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function detachMember(Team $team, string $user): JsonResponse
    {
        $this->authorize('update', $team);

        $team->members()->detach($user);

        return response()->json(null, 204);
    }
}
