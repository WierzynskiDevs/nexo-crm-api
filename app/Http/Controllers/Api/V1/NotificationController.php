<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Caixa de notificações do usuário autenticado (sino da UI).
 *
 * A TenantScope isola entre tenants; o filtro por notifiable abaixo é o que
 * isola usuários DENTRO do mesmo tenant — sem ele, qualquer membro leria a
 * caixa dos colegas.
 */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->ownedQuery()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'data' => ['unread' => $this->ownedQuery()->whereNull('read_at')->count()],
        ]);
    }

    public function markAsRead(Notification $notification): NotificationResource
    {
        $this->authorize('update', $notification);

        // Idempotente: reler não desloca o read_at original.
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return new NotificationResource($notification);
    }

    public function markAllAsRead(): JsonResponse
    {
        $this->ownedQuery()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(null, 204);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json(null, 204);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Notification>
     */
    private function ownedQuery()
    {
        $user = Auth::guard('api')->user();

        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }
}
