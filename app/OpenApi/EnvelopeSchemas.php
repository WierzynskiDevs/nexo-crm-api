<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Envelopes de resposta.
 *
 * A API sempre devolve o recurso sob `data`; coleções paginadas trazem
 * também `links` e `meta`. Ter um schema pronto por recurso evita repetir
 * essa estrutura em cada endpoint.
 */
#[OA\Schema(schema: 'SessionEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Session')], type: 'object')]
#[OA\Schema(schema: 'TenantSelectionEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TenantSelectionChallenge')], type: 'object')]
#[OA\Schema(schema: 'CurrentSessionEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CurrentSession')], type: 'object')]
#[OA\Schema(schema: 'LeadEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Lead')], type: 'object')]
#[OA\Schema(schema: 'LeadCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Lead')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'ClientEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Client')], type: 'object')]
#[OA\Schema(schema: 'ClientCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Client')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'PipelineEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Pipeline')], type: 'object')]
#[OA\Schema(schema: 'PipelineCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Pipeline')),
], type: 'object')]
#[OA\Schema(schema: 'PipelineStageEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PipelineStage')], type: 'object')]
#[OA\Schema(schema: 'OpportunityEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Opportunity')], type: 'object')]
#[OA\Schema(schema: 'OpportunityCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Opportunity')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'TaskEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')], type: 'object')]
#[OA\Schema(schema: 'TaskCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Task')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'TaskChecklistItemEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TaskChecklistItem')], type: 'object')]
#[OA\Schema(schema: 'EventEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')], type: 'object')]
#[OA\Schema(schema: 'EventCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Event')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'EventGuestEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventGuest')], type: 'object')]
#[OA\Schema(schema: 'FileEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/File')], type: 'object')]
#[OA\Schema(schema: 'FileCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/File')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'MembershipEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Membership')], type: 'object')]
#[OA\Schema(schema: 'MembershipCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Membership')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'TeamEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Team')], type: 'object')]
#[OA\Schema(schema: 'TeamCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Team')),
], type: 'object')]
#[OA\Schema(schema: 'RoleCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Role')),
], type: 'object')]
#[OA\Schema(schema: 'AuditLogCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLog')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'NotificationEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Notification')], type: 'object')]
#[OA\Schema(schema: 'NotificationCollection', properties: [
    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Notification')),
    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
], type: 'object')]
#[OA\Schema(schema: 'DashboardEnvelope', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Dashboard')], type: 'object')]
final class EnvelopeSchemas {}
