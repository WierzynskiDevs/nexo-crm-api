<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Schemas dos recursos devolvidos pela API, espelhando os App\Http\Resources.
 *
 * Campos de relacionamento (`owner`, `tags`, `stages`, ...) só aparecem na
 * resposta quando o endpoint carrega o relacionamento — por isso estão
 * marcados como nullable aqui.
 *
 * Todo valor monetário é inteiro em centavos.
 */
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'Marina Alves'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Tenant',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'Acme Holdings'),
        new OA\Property(property: 'slug', type: 'string', example: 'acme-holdings'),
        new OA\Property(property: 'status', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Session',
    description: 'Sessão autenticada completa. O refresh token não aparece aqui: ele vai no cookie httpOnly `refresh_token`, restrito a `/api/v1/auth`.',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'tenant', ref: '#/components/schemas/Tenant'),
        new OA\Property(property: 'role', type: 'string', enum: ['super_admin', 'admin', 'manager', 'sales', 'support']),
        new OA\Property(property: 'access_token', type: 'string'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TenantSelectionChallenge',
    description: 'Devolvido quando o usuário pertence a mais de uma empresa ativa: o access token ainda não tem claim de tenant e só serve para chamar `/auth/select-tenant`.',
    properties: [
        new OA\Property(property: 'requires_tenant_selection', type: 'boolean', example: true),
        new OA\Property(property: 'access_token', type: 'string'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'tenants', type: 'array', items: new OA\Items(ref: '#/components/schemas/Tenant')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CurrentSession',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'tenant', ref: '#/components/schemas/Tenant', nullable: true),
        new OA\Property(property: 'role', type: 'string', nullable: true),
        new OA\Property(property: 'permissions', description: 'Slugs das permissões do papel no tenant corrente', type: 'array', items: new OA\Items(type: 'string', example: 'leads.criar')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Lead',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'company', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'source', type: 'string', enum: ['inbound', 'outbound', 'referral', 'event', 'ads']),
        new OA\Property(property: 'status', type: 'string', enum: ['new', 'contacted', 'qualified', 'disqualified', 'converted']),
        new OA\Property(property: 'priority', type: 'string', enum: ['high', 'medium', 'low']),
        new OA\Property(property: 'score', type: 'integer', maximum: 100, minimum: 0),
        new OA\Property(property: 'value_cents', description: 'Valor potencial em centavos', type: 'integer'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'due_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Client',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'converted_from_lead_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'contact_name', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'mrr_cents', description: 'Receita recorrente mensal em centavos', type: 'integer'),
        new OA\Property(property: 'health', type: 'string', enum: ['healthy', 'attention', 'risk']),
        new OA\Property(property: 'segment', type: 'string', enum: ['enterprise', 'mid_market', 'smb']),
        new OA\Property(property: 'client_since', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'archived_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PipelineStage',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'Qualificação'),
        new OA\Property(property: 'position', description: 'Ordem da etapa no funil, começando em 0', type: 'integer'),
        new OA\Property(property: 'is_won', description: 'Mover para cá marca a oportunidade como ganha', type: 'boolean'),
        new OA\Property(property: 'is_lost', description: 'Mover para cá marca a oportunidade como perdida', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Pipeline',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string', example: 'Comercial'),
        new OA\Property(property: 'is_default', type: 'boolean'),
        new OA\Property(property: 'stages', type: 'array', items: new OA\Items(ref: '#/components/schemas/PipelineStage'), nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Opportunity',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'pipeline_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'pipeline_stage_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'lead_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'client_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'value_cents', type: 'integer'),
        new OA\Property(property: 'probability', type: 'integer', maximum: 100, minimum: 0),
        new OA\Property(property: 'expected_close_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'won', 'lost']),
        new OA\Property(property: 'lost_reason', type: 'string', nullable: true),
        new OA\Property(property: 'closed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TaskChecklistItem',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'is_done', type: 'boolean'),
        new OA\Property(property: 'position', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Task',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'column', type: 'string', enum: ['backlog', 'in_progress', 'review', 'done']),
        new OA\Property(property: 'priority', type: 'string', enum: ['high', 'medium', 'low']),
        new OA\Property(property: 'tag', type: 'string', nullable: true),
        new OA\Property(property: 'position', type: 'integer'),
        new OA\Property(property: 'due_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'checklist_items', type: 'array', items: new OA\Items(ref: '#/components/schemas/TaskChecklistItem'), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EventGuest',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'user_id', description: 'Preenchido em convidado interno; nulo em convidado externo', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'name', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'response_status', type: 'string', enum: ['pending', 'accepted', 'declined']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Event',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'workspace_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'kind', type: 'string', enum: ['meeting', 'demo', 'call', 'internal', 'client']),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'location', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'related_type', type: 'string', enum: ['lead', 'client', 'opportunity'], nullable: true),
        new OA\Property(property: 'related_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'canceled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'guests', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventGuest'), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'File',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'fileable_type', type: 'string', enum: ['lead', 'client', 'opportunity', 'task'], nullable: true),
        new OA\Property(property: 'fileable_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'original_name', type: 'string'),
        new OA\Property(property: 'mime_type', type: 'string'),
        new OA\Property(property: 'size_bytes', type: 'integer'),
        new OA\Property(property: 'uploaded_by', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Permission',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'slug', type: 'string', example: 'leads.criar'),
        new OA\Property(property: 'module', type: 'string', example: 'Leads'),
        new OA\Property(property: 'action', type: 'string', example: 'criar'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Role',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'slug', type: 'string', enum: ['super_admin', 'admin', 'manager', 'sales', 'support']),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(ref: '#/components/schemas/Permission'), nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Membership',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['invited', 'active', 'inactive']),
        new OA\Property(property: 'invited_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'joined_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'role', ref: '#/components/schemas/Role', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Team',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'goal_amount_cents', type: 'integer', nullable: true),
        new OA\Property(property: 'pipeline_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'lead', ref: '#/components/schemas/User', description: 'Usuário responsável pela equipe', nullable: true),
        new OA\Property(property: 'members', type: 'array', items: new OA\Items(ref: '#/components/schemas/User'), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AuditLog',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'action', type: 'string', example: 'member.invited'),
        new OA\Property(property: 'auditable_type', type: 'string', nullable: true),
        new OA\Property(property: 'auditable_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'old_values', type: 'object', nullable: true),
        new OA\Property(property: 'new_values', type: 'object', nullable: true),
        new OA\Property(property: 'ip_address', type: 'string', nullable: true),
        new OA\Property(property: 'actor', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Notification',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', enum: ['opportunity.won', 'task.assigned', 'event.invited']),
        new OA\Property(property: 'data', description: 'Payload específico do tipo — o cliente usa o campo type para interpretá-lo', type: 'object'),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Dashboard',
    properties: [
        new OA\Property(property: 'period', type: 'string', enum: ['7d', '30d', '90d', 'ano']),
        new OA\Property(
            property: 'range',
            properties: [
                new OA\Property(property: 'from', type: 'string', format: 'date-time'),
                new OA\Property(property: 'to', type: 'string', format: 'date-time'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'kpis',
            description: 'Cada KPI traz o valor da janela, o da janela anterior de mesma duração e o delta percentual (null quando não havia base de comparação)',
            properties: [
                new OA\Property(property: 'revenue_cents', ref: '#/components/schemas/DashboardKpi'),
                new OA\Property(property: 'clients', ref: '#/components/schemas/DashboardKpi'),
                new OA\Property(property: 'average_ticket_cents', ref: '#/components/schemas/DashboardKpi'),
                new OA\Property(property: 'leads', ref: '#/components/schemas/DashboardKpi'),
                new OA\Property(property: 'opportunities', ref: '#/components/schemas/DashboardKpi'),
                new OA\Property(property: 'tasks', ref: '#/components/schemas/DashboardKpi'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'conversion',
            properties: [
                new OA\Property(property: 'rate', description: 'Oportunidades ganhas ÷ leads criados, em %', type: 'number', format: 'float'),
                new OA\Property(property: 'delta_points', description: 'Variação em pontos percentuais', type: 'number', format: 'float'),
                new OA\Property(property: 'won_opportunities', type: 'integer'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'sales_series',
            description: 'Receita ganha por bucket de tempo (dia, semana ou mês conforme o período), com buckets vazios preenchidos',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'bucket', type: 'string', format: 'date'),
                    new OA\Property(property: 'revenue_cents', type: 'integer'),
                    new OA\Property(property: 'won_opportunities', type: 'integer'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(
            property: 'funnel',
            description: 'Oportunidades criadas na janela, por etapa atual do pipeline',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'stage_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'stage', type: 'string'),
                    new OA\Property(property: 'position', type: 'integer'),
                    new OA\Property(property: 'count', type: 'integer'),
                    new OA\Property(property: 'value_cents', type: 'integer'),
                ],
                type: 'object',
            ),
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DashboardKpi',
    properties: [
        new OA\Property(property: 'value', type: 'number'),
        new OA\Property(property: 'previous', type: 'number'),
        new OA\Property(property: 'delta_percent', type: 'number', format: 'float', nullable: true),
    ],
    type: 'object',
)]
final class ResourceSchemas {}
