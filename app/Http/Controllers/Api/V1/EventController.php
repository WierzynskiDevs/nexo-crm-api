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
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    #[OA\Get(
        path: '/api/v1/events',
        summary: 'Lista os eventos da agenda',
        description: 'Ordenados por início. Eventos cancelados são omitidos, salvo `include_canceled=true`.',
        security: [['bearerAuth' => []]],
        tags: ['Agenda'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'from', description: 'Início da janela; traz eventos que terminam a partir desta data', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'to', description: 'Fim da janela; traz eventos que começam até esta data', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'kind', in: 'query', schema: new OA\Schema(type: 'string', enum: ['meeting', 'demo', 'call', 'internal', 'client'])),
            new OA\Parameter(name: 'owner_id', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'include_canceled', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/EventCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
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

    #[OA\Post(
        path: '/api/v1/events',
        summary: 'Agenda um evento',
        description: <<<'MD'
            Convidados podem ser internos (`user_id`) ou externos (`name` e `email`).
            Apenas os internos, que sejam membros ativos do tenant, recebem
            notificação — externos ficam registrados sem aviso automático.

            `related_type`/`related_id` vinculam o evento a um lead, cliente ou
            oportunidade do mesmo tenant.
            MD,
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'kind', 'starts_at', 'ends_at'],
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'kind', type: 'string', enum: ['meeting', 'demo', 'call', 'internal', 'client']),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'ends_at', description: 'Precisa ser posterior a starts_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'location', type: 'string', nullable: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'owner_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'related_type', type: 'string', enum: ['lead', 'client', 'opportunity'], nullable: true),
                    new OA\Property(property: 'related_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(
                        property: 'guests',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'user_id', description: 'Convidado interno', type: 'string', format: 'uuid', nullable: true),
                            new OA\Property(property: 'name', description: 'Convidado externo', type: 'string', nullable: true),
                            new OA\Property(property: 'email', description: 'Convidado externo', type: 'string', format: 'email', nullable: true),
                        ], type: 'object'),
                    ),
                ],
            ),
        ),
        tags: ['Agenda'],
        responses: [
            new OA\Response(response: 201, description: 'Evento criado', content: new OA\JsonContent(ref: '#/components/schemas/EventEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
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

    #[OA\Get(
        path: '/api/v1/events/{event}',
        summary: 'Exibe um evento com seus convidados',
        security: [['bearerAuth' => []]],
        tags: ['Agenda'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/EventEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(Event $event): EventResource
    {
        $this->authorize('view', $event);

        return new EventResource($event->load(['owner', 'guests']));
    }

    #[OA\Put(
        path: '/api/v1/events/{event}',
        summary: 'Atualiza um evento',
        description: 'Não mexe na lista de convidados — use os endpoints de convidados.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/Event')),
        tags: ['Agenda'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Evento atualizado', content: new OA\JsonContent(ref: '#/components/schemas/EventEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $event->update($request->validated());

        return new EventResource($event->load(['owner', 'guests']));
    }

    #[OA\Delete(
        path: '/api/v1/events/{event}',
        summary: 'Exclui um evento (soft delete)',
        description: 'Para manter o histórico na agenda, prefira `PATCH /events/{event}/cancel`.',
        security: [['bearerAuth' => []]],
        tags: ['Agenda'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(null, 204);
    }

    #[OA\Patch(
        path: '/api/v1/events/{event}/cancel',
        summary: 'Cancela um evento sem excluí-lo',
        description: 'Preenche `canceled_at`. O evento some da listagem padrão, mas continua acessível com `include_canceled=true`.',
        security: [['bearerAuth' => []]],
        tags: ['Agenda'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Evento cancelado', content: new OA\JsonContent(ref: '#/components/schemas/EventEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function cancel(Event $event): EventResource
    {
        $this->authorize('update', $event);

        $event->update(['canceled_at' => now()]);

        return new EventResource($event->load(['owner', 'guests']));
    }

    #[OA\Post(
        path: '/api/v1/events/{event}/guests',
        summary: 'Adiciona um convidado ao evento',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                description: 'Informe `user_id` para convidado interno, ou `name`/`email` para externo.',
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                ],
            ),
        ),
        tags: ['Agenda'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 201, description: 'Convidado adicionado', content: new OA\JsonContent(ref: '#/components/schemas/EventGuestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function storeGuest(StoreEventGuestRequest $request, Event $event): EventGuestResource
    {
        $guest = EventGuest::create([
            ...$request->validated(),
            'event_id' => $event->id,
        ]);

        return new EventGuestResource($guest->load('user'));
    }

    #[OA\Patch(
        path: '/api/v1/events/{event}/guests/{guest}',
        summary: 'Atualiza a resposta de um convidado',
        description: 'O convidado precisa pertencer ao evento da URL; caso contrário responde 404. Como `event_guests` não tem `tenant_id` próprio, essa checagem é o que impede alcançar convidado de outro tenant.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'response_status', type: 'string', enum: ['pending', 'accepted', 'declined']),
        ], type: 'object')),
        tags: ['Agenda'],
        parameters: [
            new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'guest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Convidado atualizado', content: new OA\JsonContent(ref: '#/components/schemas/EventGuestEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function updateGuest(UpdateEventGuestRequest $request, Event $event, EventGuest $guest): EventGuestResource
    {
        abort_unless($guest->event_id === $event->id, 404);

        $guest->update($request->validated());

        return new EventGuestResource($guest->load('user'));
    }

    #[OA\Delete(
        path: '/api/v1/events/{event}/guests/{guest}',
        summary: 'Remove um convidado do evento',
        security: [['bearerAuth' => []]],
        tags: ['Agenda'],
        parameters: [
            new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'guest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroyGuest(Event $event, EventGuest $guest): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($guest->event_id === $event->id, 404);

        $guest->delete();

        return response()->json(null, 204);
    }
}
