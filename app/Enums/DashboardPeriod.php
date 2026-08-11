<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Períodos do dashboard. Os valores replicam os do protótipo ("7d", "30d",
 * "90d", "ano") para que o front consuma o mesmo contrato sem camada de
 * tradução.
 *
 * O granule() é usado direto em date_trunc() no Postgres — por isso ele
 * NUNCA pode vir de string do usuário: a validação por enum é o que garante
 * que só chegue ali um valor da whitelist abaixo.
 */
enum DashboardPeriod: string
{
    case Last7Days = '7d';
    case Last30Days = '30d';
    case Last90Days = '90d';
    case Year = 'ano';

    public function startsAt(CarbonImmutable $reference): CarbonImmutable
    {
        return match ($this) {
            self::Last7Days => $reference->subDays(6)->startOfDay(),
            self::Last30Days => $reference->subDays(29)->startOfDay(),
            self::Last90Days => $reference->subDays(89)->startOfDay(),
            self::Year => $reference->startOfYear(),
        };
    }

    /**
     * Unidade de agregação da série temporal. Whitelist fechada — ver docblock
     * da classe.
     */
    public function granule(): string
    {
        return match ($this) {
            self::Last7Days, self::Last30Days => 'day',
            self::Last90Days => 'week',
            self::Year => 'month',
        };
    }
}
