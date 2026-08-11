<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MembershipStatus;
use App\Events\OpportunityWon;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\OpportunityWonNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class SendOpportunityWonNotification
{
    public function handle(OpportunityWon $event): void
    {
        $opportunity = $event->opportunity;
        $actor = Auth::guard('api')->user();

        // "Oportunidade ganha notifica todo o workspace" (referência do
        // protótipo): todos os membros ativos do tenant, menos quem moveu.
        $recipientIds = Membership::query()
            ->where('tenant_id', $opportunity->tenant_id)
            ->where('status', MembershipStatus::Active)
            ->when($actor, fn ($query) => $query->where('user_id', '!=', $actor->id))
            ->pluck('user_id');

        if ($recipientIds->isEmpty()) {
            return;
        }

        Notification::send(
            User::query()->whereIn('id', $recipientIds)->get(),
            new OpportunityWonNotification(
                tenantId: $opportunity->tenant_id,
                opportunityId: $opportunity->id,
                opportunityName: $opportunity->name,
                valueCents: (int) $opportunity->value_cents,
                wonByName: $actor?->name,
            ),
        );
    }
}
