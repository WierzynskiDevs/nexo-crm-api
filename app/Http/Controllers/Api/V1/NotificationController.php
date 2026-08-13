<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Caixa de notificações do usuário autenticado (sino da UI).
 *
 * A TenantScope isola entre tenants; o filtro por notifiable abaixo é o que
 * isola usuários DENTRO do mesmo tenant — sem ele, qualquer membro leria a
 * caixa dos colegas.
 */
class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'Lista as notificações do usuário autenticado',
        description: 'Devolve apenas as notificações endereçadas a quem está autenticado — nunca as de outro usuário, mesmo do mesmo tenant.',
        security: [['bearerAuth' => []]],
        tags: ['Notificações'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'unread', description: 'Quando true, devolve apenas as não lidas', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/NotificationCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->ownedQuery()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    #[OA\Get(
        path: '/api/v1/notifications/unread-count',
        summary: 'Contador de notificações não lidas',
        description: 'Usado pelo badge do sino.',
        security: [['bearerAuth' => []]],
        tags: ['Notificações'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [new OA\Property(property: 'unread', type: 'integer', example: 3)], type: 'object'),
                ], type: 'object'),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'data' => ['unread' => $this->ownedQuery()->whereNull('read_at')->count()],
        ]);
    }

    #[OA\Patch(
        path: '/api/v1/notifications/{notification}/read',
        summary: 'Marca uma notificação como lida',
        description: 'Idempotente: reler não desloca o `read_at` original.',
        security: [['bearerAuth' => []]],
        tags: ['Notificações'],
        parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/NotificationEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, description: 'A notificação é de outro usuário', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function markAsRead(Notification $notification): NotificationResource
    {
        $this->authorize('update', $notification);

        // Idempotente: reler não desloca o read_at original.
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return new NotificationResource($notification);
    }

    #[OA\Post(
        path: '/api/v1/notifications/read-all',
        summary: 'Marca todas as não lidas como lidas',
        security: [['bearerAuth' => []]],
        tags: ['Notificações'],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function markAllAsRead(): JsonResponse
    {
        $this->ownedQuery()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: '/api/v1/notifications/{notification}',
        summary: 'Remove uma notificação da caixa',
        security: [['bearerAuth' => []]],
        tags: ['Notificações'],
        parameters: [new OA\Parameter(name: 'notification', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, description: 'A notificação é de outro usuário', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json(null, 204);
    }

    /**
     * @return Builder<Notification>
     */
    private function ownedQuery()
    {
        $user = Auth::guard('api')->user();

        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }
}
