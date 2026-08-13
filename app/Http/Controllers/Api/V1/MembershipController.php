<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\InviteMemberRequest;
use App\Http\Requests\Members\UpdateMembershipRequest;
use App\Http\Resources\MembershipResource;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\MembershipInviteNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class MembershipController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    #[OA\Get(
        path: '/api/v1/members',
        summary: 'Lista os membros da empresa',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['invited', 'active', 'inactive'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/MembershipCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Membership::class);

        $memberships = Membership::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->with(['user', 'role'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return MembershipResource::collection($memberships);
    }

    #[OA\Post(
        path: '/api/v1/members',
        summary: 'Convida um membro para a empresa',
        description: 'Cria o usuário se ainda não existir e envia o convite por e-mail. A membership nasce como `invited` e vira `active` quando o convite é aceito. Registrado em auditoria.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'role_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'role_id', description: 'Id de um papel do catálogo (`GET /roles`)', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        tags: ['Governança'],
        responses: [
            new OA\Response(response: 201, description: 'Convite enviado', content: new OA\JsonContent(ref: '#/components/schemas/MembershipEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function store(InviteMemberRequest $request): MembershipResource
    {
        $tenant = app(TenantContext::class)->get();

        $membership = DB::transaction(function () use ($request, $tenant) {
            $user = User::query()->firstOrCreate(
                ['email' => $request->string('email')],
                ['name' => $request->string('name'), 'password' => Str::random(40)],
            );

            $membership = Membership::query()->firstOrNew([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            // Reconvidar quem já é membro ativo rebaixaria a pessoa para
            // "invited" (derrubando o acesso dela) e trocaria seu papel sem
            // passar pela Policy de update nem gerar auditoria de mudança de
            // papel — um caminho de edição por quem só tem "usuarios.criar".
            abort_if(
                $membership->exists && $membership->status === MembershipStatus::Active,
                422,
                'Este usuário já é membro ativo da empresa.',
            );

            $membership->fill([
                'role_id' => $request->input('role_id'),
                'status' => MembershipStatus::Invited,
                'invited_at' => now(),
            ])->save();

            $token = Password::createToken($user);
            $user->notify(new MembershipInviteNotification($tenant, $token));

            return $membership;
        });

        $this->auditLogger->log('member.invited', $membership, request: $request);

        return new MembershipResource($membership->load(['user', 'role']));
    }

    #[OA\Patch(
        path: '/api/v1/members/{member}',
        summary: 'Altera o papel ou o status de um membro',
        description: 'A mudança é registrada em auditoria, com os valores anterior e novo.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'role_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'status', type: 'string', enum: ['invited', 'active', 'inactive']),
        ], type: 'object')),
        tags: ['Governança'],
        parameters: [new OA\Parameter(name: 'member', description: 'Id da membership (não do usuário)', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Membro atualizado', content: new OA\JsonContent(ref: '#/components/schemas/MembershipEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateMembershipRequest $request, Membership $member): MembershipResource
    {
        // Mesma guarda do destroy(): ninguém altera o próprio papel nem se
        // desativa. Sem isso, qualquer Admin com "usuarios.editar" se
        // auto-promoveria — e um status "inactive" em si mesmo produziria
        // lockout imediato (ResolveTenantContext exige membership ativa).
        abort_if(
            $member->user_id === Auth::guard('api')->id(),
            422,
            'Você não pode alterar seu próprio papel ou status.',
        );

        $before = $member->only(['role_id', 'status']);

        $member->update($request->validated());

        $this->auditLogger->log('member.updated', $member, $before, $member->only(['role_id', 'status']), $request);

        return new MembershipResource($member->load(['user', 'role']));
    }

    #[OA\Delete(
        path: '/api/v1/members/{member}',
        summary: 'Remove um membro da empresa',
        description: 'Um usuário não pode remover a própria membership — isso responde 422, para ninguém se trancar para fora da própria empresa.',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        parameters: [new OA\Parameter(name: 'member', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, description: 'Tentativa de remover a própria membership', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
    public function destroy(Request $request, Membership $member): JsonResponse
    {
        $this->authorize('delete', $member);

        abort_if($member->user_id === Auth::guard('api')->id(), 422, 'Você não pode remover sua própria membership.');

        $this->auditLogger->log('member.removed', $member, $member->only(['user_id', 'role_id', 'status']), request: $request);

        $member->delete();

        return response()->json(null, 204);
    }
}
