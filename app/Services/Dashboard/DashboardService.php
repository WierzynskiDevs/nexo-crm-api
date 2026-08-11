<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\DashboardPeriod;
use App\Enums\OpportunityStatus;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Agregações reais do dashboard, substituindo o mock "dashboardByPeriod" do
 * protótipo.
 *
 * Todas as queries daqui passam pela TenantScope dos models de domínio — não
 * há nenhum where('tenant_id', ...) manual, justamente para não abrir um
 * caminho paralelo ao mecanismo central de isolamento.
 *
 * Convenções das métricas (todas dentro da janela do período pedido):
 * - receita = soma de value_cents das oportunidades ganhas, pela data de
 *   fechamento (closed_at), não pela de criação;
 * - leads/oportunidades/tarefas = registros criados na janela;
 * - clientes = total acumulado de clientes não arquivados até o fim da
 *   janela (é um estoque, não um fluxo) — por isso o delta compara o
 *   acumulado no fim contra o acumulado no início;
 * - conversão = oportunidades ganhas ÷ leads criados na janela.
 *
 * Os timestamps são comparados em UTC (como estão gravados); a conversão para
 * o fuso do usuário é responsabilidade da borda de apresentação.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(DashboardPeriod $period, ?string $pipelineId = null): array
    {
        $now = CarbonImmutable::now();
        $from = $period->startsAt($now);

        // Janela anterior de mesma duração, imediatamente antes da atual, para
        // os deltas percentuais.
        $length = $from->diffInSeconds($now);
        $previousFrom = $from->subSeconds($length);

        $current = $this->metrics($from, $now);
        $previous = $this->metrics($previousFrom, $from);

        return [
            'period' => $period->value,
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $now->toIso8601String(),
            ],
            'kpis' => [
                'revenue_cents' => $this->kpi($current['revenue_cents'], $previous['revenue_cents']),
                'clients' => $this->kpi($current['clients'], $previous['clients']),
                'average_ticket_cents' => $this->kpi($current['average_ticket_cents'], $previous['average_ticket_cents']),
                'leads' => $this->kpi($current['leads'], $previous['leads']),
                'opportunities' => $this->kpi($current['opportunities'], $previous['opportunities']),
                'tasks' => $this->kpi($current['tasks'], $previous['tasks']),
            ],
            'conversion' => [
                'rate' => $current['conversion_rate'],
                'delta_points' => round($current['conversion_rate'] - $previous['conversion_rate'], 2),
                'won_opportunities' => $current['won_opportunities'],
            ],
            'sales_series' => $this->salesSeries($period, $from, $now),
            'funnel' => $this->funnel($from, $now, $pipelineId),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function metrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $revenueCents = (int) Opportunity::query()
            ->where('status', OpportunityStatus::Won)
            ->whereBetween('closed_at', [$from, $to])
            ->sum('value_cents');

        $wonCount = Opportunity::query()
            ->where('status', OpportunityStatus::Won)
            ->whereBetween('closed_at', [$from, $to])
            ->count();

        $leads = Lead::query()->whereBetween('created_at', [$from, $to])->count();

        return [
            'revenue_cents' => $revenueCents,
            'won_opportunities' => $wonCount,
            'average_ticket_cents' => $wonCount > 0 ? intdiv($revenueCents, $wonCount) : 0,
            'clients' => Client::query()->whereNull('archived_at')->where('created_at', '<=', $to)->count(),
            'leads' => $leads,
            'opportunities' => Opportunity::query()->whereBetween('created_at', [$from, $to])->count(),
            'tasks' => Task::query()->whereBetween('created_at', [$from, $to])->count(),
            'conversion_rate' => $leads > 0 ? round($wonCount / $leads * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array{value: int|float, previous: int|float, delta_percent: float|null}
     */
    private function kpi(int|float $current, int|float $previous): array
    {
        return [
            'value' => $current,
            'previous' => $previous,
            // null (e não 0) quando não havia base de comparação: "cresceu
            // 100%" sobre zero seria uma leitura inventada.
            'delta_percent' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
        ];
    }

    /**
     * Receita ganha por bucket de tempo, já com os buckets vazios preenchidos
     * para o gráfico não "pular" períodos sem venda.
     *
     * @return array<int, array{bucket: string, revenue_cents: int, won_opportunities: int}>
     */
    private function salesSeries(DashboardPeriod $period, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $granule = $period->granule();

        $rows = Opportunity::query()
            ->where('status', OpportunityStatus::Won)
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw("date_trunc('{$granule}', closed_at) as bucket")
            ->selectRaw('sum(value_cents) as revenue_cents')
            ->selectRaw('count(*) as won_opportunities')
            ->groupByRaw("date_trunc('{$granule}', closed_at)")
            ->orderByRaw("date_trunc('{$granule}', closed_at)")
            ->get()
            ->keyBy(fn ($row) => CarbonImmutable::parse($row->bucket)->toDateString());

        return $this->buckets($granule, $from, $to)
            ->map(function (CarbonImmutable $bucket) use ($rows) {
                $row = $rows->get($bucket->toDateString());

                return [
                    'bucket' => $bucket->toDateString(),
                    'revenue_cents' => (int) ($row->revenue_cents ?? 0),
                    'won_opportunities' => (int) ($row->won_opportunities ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function buckets(string $granule, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $start = match ($granule) {
            'day' => $from->startOfDay(),
            'week' => $from->startOfWeek(),
            default => $from->startOfMonth(),
        };

        $buckets = collect();
        for ($cursor = $start; $cursor <= $to; $cursor = $this->advance($cursor, $granule)) {
            $buckets->push($cursor);
        }

        return $buckets;
    }

    private function advance(CarbonImmutable $cursor, string $granule): CarbonImmutable
    {
        return match ($granule) {
            'day' => $cursor->addDay(),
            'week' => $cursor->addWeek(),
            default => $cursor->addMonth(),
        };
    }

    /**
     * Funil por etapa do pipeline: quantas oportunidades criadas na janela
     * estão hoje em cada etapa, e quanto elas somam.
     *
     * @return array<int, array{stage_id: string, stage: string, position: int, count: int, value_cents: int}>
     */
    private function funnel(CarbonImmutable $from, CarbonImmutable $to, ?string $pipelineId): array
    {
        // Pipeline vem por dentro da TenantScope: um pipeline_id de outro
        // tenant simplesmente não é encontrado aqui (além da validação no
        // Form Request).
        $pipeline = Pipeline::query()
            ->with('stages')
            ->when($pipelineId, fn ($query) => $query->whereKey($pipelineId))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        if ($pipeline === null) {
            return [];
        }

        $totals = Opportunity::query()
            ->where('pipeline_id', $pipeline->id)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('pipeline_stage_id')
            ->selectRaw('pipeline_stage_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(value_cents), 0) as value_cents')
            ->get()
            ->keyBy('pipeline_stage_id');

        return $pipeline->stages
            ->map(function ($stage) use ($totals) {
                $row = $totals->get($stage->id);

                return [
                    'stage_id' => $stage->id,
                    'stage' => $stage->name,
                    'position' => $stage->position,
                    'count' => (int) ($row->total ?? 0),
                    'value_cents' => (int) ($row->value_cents ?? 0),
                ];
            })
            ->values()
            ->all();
    }
}
