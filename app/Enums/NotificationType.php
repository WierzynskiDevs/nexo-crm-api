<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de notificação in-app. Grava na coluna "type" da tabela
 * notifications — o front usa esse valor para escolher ícone e destino do
 * clique, então os valores são contrato de API (não renomear sem migration).
 */
enum NotificationType: string
{
    case OpportunityWon = 'opportunity.won';
    case TaskAssigned = 'task.assigned';
    case EventInvited = 'event.invited';
}
