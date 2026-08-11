<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class TaskPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('tarefas.visualizar');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->belongsToCurrentTenant($task) && $user->can('tarefas.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('tarefas.criar');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->belongsToCurrentTenant($task) && $user->can('tarefas.editar');
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->belongsToCurrentTenant($task) && $user->can('tarefas.excluir');
    }
}
