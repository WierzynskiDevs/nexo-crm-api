<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\EventScheduled;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventGuestRequest;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventGuestRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventGuestResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventGuest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $events = Event::query()
            ->with(['owner', 'guests'])
            ->when($request->filled('from'), fn ($query) => $query->where('ends_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('starts_at', '<=', $request->date('to')))
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')))
            ->when($request->filled('owner_id'), fn ($query) => $query->where('owner_id', $request->string('owner_id')))
            ->when(! $request->boolean('include_canceled'), fn ($query) => $query->whereNull('canceled_at'))
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 50));

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): EventResource
    {
        $event = DB::transaction(function () use ($request) {
            $event = Event::create($request->safe()->except('guests'));

            foreach ($request->array('guests') as $guest) {
                EventGuest::create([
                    'event_id' => $event->id,
                    'user_id' => $guest['user_id'] ?? null,
                    'name' => $guest['name'] ?? null,
                    'email' => $guest['email'] ?? null,
                ]);
            }

            return $event;
        });

        // Fora da transação: notificar convidados de um evento que sofreu
        // rollback seria pior do que não notificar.
        EventScheduled::dispatch($event);

        return new EventResource($event->load(['owner', 'guests']));
    }

    public function show(Event $event): EventResource
    {
        $this->authorize('view', $event);

        return new EventResource($event->load(['owner', 'guests']));
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $event->update($request->validated());

        return new EventResource($event->load(['owner', 'guests']));
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(null, 204);
    }

    public function cancel(Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event->update(['canceled_at' => now()]);

        return new EventResource($event->load(['owner', 'guests']));
    }

    public function storeGuest(StoreEventGuestRequest $request, Event $event): EventGuestResource
    {
        $guest = EventGuest::create([
            ...$request->validated(),
            'event_id' => $event->id,
        ]);

        return new EventGuestResource($guest->load('user'));
    }

    public function updateGuest(UpdateEventGuestRequest $request, Event $event, EventGuest $guest): EventGuestResource
    {
        abort_unless($guest->event_id === $event->id, 404);

        $guest->update($request->validated());

        return new EventGuestResource($guest->load('user'));
    }

    public function destroyGuest(Event $event, EventGuest $guest): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($guest->event_id === $event->id, 404);

        $guest->delete();

        return response()->json(null, 204);
    }
}
