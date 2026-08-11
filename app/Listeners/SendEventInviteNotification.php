<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MembershipStatus;
use App\Events\EventScheduled;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\EventInviteNotification;
use Illuminate\Support\Facades\Notification;

class SendEventInviteNotification
{
    public function handle(EventScheduled $event): void
    {
        $calendarEvent = $event->event;

        // Só convidados internos (com user_id): convidado externo tem apenas
        // nome/e-mail e não é usuário do produto — notificá-lo pelo sino não
        // faria sentido, e por e-mail seria outro fluxo (convite externo).
        $guestUserIds = $calendarEvent->guests()
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $calendarEvent->owner_id)
            ->pluck('user_id');

        if ($guestUserIds->isEmpty()) {
            return;
        }

        // Mesmo vindo de event_guests, os destinatários passam pelo filtro de
        // membership ativa no tenant do evento.
        $recipientIds = Membership::query()
            ->where('tenant_id', $calendarEvent->tenant_id)
            ->whereIn('user_id', $guestUserIds)
            ->where('status', MembershipStatus::Active)
            ->pluck('user_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        Notification::send(
            User::query()->whereIn('id', $recipientIds)->get(),
            new EventInviteNotification(
                tenantId: $calendarEvent->tenant_id,
                eventId: $calendarEvent->id,
                title: $calendarEvent->title,
                startsAt: $calendarEvent->starts_at->toIso8601String(),
                location: $calendarEvent->location,
                organizerName: $calendarEvent->owner?->name,
            ),
        );
    }
}
