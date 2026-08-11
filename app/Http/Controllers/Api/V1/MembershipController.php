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

class MembershipController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

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

    public function update(UpdateMembershipRequest $request, Membership $member): MembershipResource
    {
        $before = $member->only(['role_id', 'status']);

        $member->update($request->validated());

        $this->auditLogger->log('member.updated', $member, $before, $member->only(['role_id', 'status']), $request);

        return new MembershipResource($member->load(['user', 'role']));
    }

    public function destroy(Request $request, Membership $member): JsonResponse
    {
        $this->authorize('delete', $member);

        abort_if($member->user_id === Auth::guard('api')->id(), 422, 'Você não pode remover sua própria membership.');

        $this->auditLogger->log('member.removed', $member, $member->only(['user_id', 'role_id', 'status']), request: $request);

        $member->delete();

        return response()->json(null, 204);
    }
}
