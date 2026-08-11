<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\InviteController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\PipelineController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('select-tenant', [AuthController::class, 'selectTenant'])->middleware(['auth:api', 'throttle:auth']);
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
        Route::get('me', [AuthController::class, 'me'])->middleware(['auth:api', 'tenant']);

        Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:auth');
        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:auth');

        Route::post('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware(['auth:api', 'throttle:auth']);

        Route::post('invites/accept', [InviteController::class, 'accept'])->middleware('throttle:auth');
    });

    // SubstituteBindings vem depois de "tenant" de propósito — ver bootstrap/app.php.
    Route::middleware(['auth:api', 'tenant', SubstituteBindings::class])->group(function () {
        Route::apiResource('leads', LeadController::class);
        Route::apiResource('clients', ClientController::class);

        Route::apiResource('pipelines', PipelineController::class);
        Route::post('pipelines/{pipeline}/stages', [PipelineController::class, 'storeStage']);
        Route::patch('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'updateStage']);
        Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'destroyStage']);
        Route::post('pipelines/{pipeline}/stages/reorder', [PipelineController::class, 'reorderStages']);

        Route::apiResource('opportunities', OpportunityController::class);
        Route::patch('opportunities/{opportunity}/stage', [OpportunityController::class, 'moveStage']);

        Route::apiResource('tasks', TaskController::class);
        Route::patch('tasks/{task}/move', [TaskController::class, 'move']);
        Route::post('tasks/{task}/checklist-items', [TaskController::class, 'storeChecklistItem']);
        Route::patch('tasks/{task}/checklist-items/{checklistItem}', [TaskController::class, 'updateChecklistItem']);
        Route::delete('tasks/{task}/checklist-items/{checklistItem}', [TaskController::class, 'destroyChecklistItem']);

        Route::apiResource('events', EventController::class);
        Route::patch('events/{event}/cancel', [EventController::class, 'cancel']);
        Route::post('events/{event}/guests', [EventController::class, 'storeGuest']);
        Route::patch('events/{event}/guests/{guest}', [EventController::class, 'updateGuest']);
        Route::delete('events/{event}/guests/{guest}', [EventController::class, 'destroyGuest']);

        Route::apiResource('files', FileController::class)->except(['update']);
        Route::get('files/{file}/download-url', [FileController::class, 'downloadUrl']);

        Route::apiResource('members', MembershipController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('teams', TeamController::class);
        Route::post('teams/{team}/members', [TeamController::class, 'attachMember']);
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'detachMember']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });

    // Fora do grupo auth:api/tenant de propósito: acessado via link direto
    // (sem Bearer token), a assinatura temporária É a autorização.
    Route::get('files/{file}/stream', [FileController::class, 'stream'])
        ->middleware('signed')
        ->name('files.stream');
});
