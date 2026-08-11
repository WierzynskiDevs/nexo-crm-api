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

class LeadController extends Controller
{
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

    public function store(StoreLeadRequest $request): LeadResource
    {
        $lead = Lead::create($request->safe()->except('tags'));

        if ($request->filled('tags')) {
            $this->syncTags($lead, $request->array('tags'));
        }

        return new LeadResource($lead->load(['owner', 'tags']));
    }

    public function show(Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return new LeadResource($lead->load(['owner', 'tags']));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->safe()->except('tags'));

        if ($request->has('tags')) {
            $this->syncTags($lead, $request->array('tags'));
        }

        return new LeadResource($lead->load(['owner', 'tags']));
    }

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
