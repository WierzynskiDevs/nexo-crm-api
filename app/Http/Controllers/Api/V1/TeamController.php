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

class TeamController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Team::class);

        return TeamResource::collection(Team::query()->with(['leadUser', 'members'])->latest()->get());
    }

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

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return response()->json(null, 204);
    }

    public function attachMember(AttachTeamMemberRequest $request, Team $team): TeamResource
    {
        $team->members()->syncWithoutDetaching([$request->input('user_id')]);

        return new TeamResource($team->load(['leadUser', 'members']));
    }

    public function detachMember(Team $team, string $user): JsonResponse
    {
        $this->authorize('update', $team);

        $team->members()->detach($user);

        return response()->json(null, 204);
    }
}
