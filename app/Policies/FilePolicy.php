<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class FilePolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('arquivos.visualizar');
    }

    public function view(User $user, File $file): bool
    {
        return $this->belongsToCurrentTenant($file) && $user->can('arquivos.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('arquivos.criar');
    }

    public function delete(User $user, File $file): bool
    {
        return $this->belongsToCurrentTenant($file) && $user->can('arquivos.excluir');
    }
}
