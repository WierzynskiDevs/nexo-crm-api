<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique(); // ex: leads.view, leads.create
            $table->string('module'); // Leads, Clientes, Pipeline, Tarefas, Arquivos, Usuários, Empresas, Logs, Configurações
            $table->string('action'); // visualizar, criar, editar, excluir, exportar, configurar
            $table->timestamps();

            $table->unique(['module', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
