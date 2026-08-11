<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class LeadPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('leads.visualizar');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->belongsToCurrentTenant($lead) && $user->can('leads.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('leads.criar');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->belongsToCurrentTenant($lead) && $user->can('leads.editar');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->belongsToCurrentTenant($lead) && $user->can('leads.excluir');
    }
}
