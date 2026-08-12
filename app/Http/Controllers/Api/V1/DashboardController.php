<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowDashboardRequest;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    #[OA\Get(
        path: '/api/v1/dashboard',
        summary: 'Indicadores agregados do tenant',
        description: 'Agregações reais do período pedido, com comparação contra a janela anterior de mesma duração.',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        parameters: [
            new OA\Parameter(
                name: 'period',
                description: 'Janela de análise. Define também o agrupamento da série: dia (7d, 30d), semana (90d) ou mês (ano).',
                in: 'query',
                schema: new OA\Schema(type: 'string', default: '30d', enum: ['7d', '30d', '90d', 'ano']),
            ),
            new OA\Parameter(
                name: 'pipeline_id',
                description: 'Pipeline usado no funil. Omitido, usa o pipeline padrão do tenant.',
                in: 'query',
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/DashboardEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ],
    )]
    public function index(ShowDashboardRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->build(
                $request->period(),
                $request->input('pipeline_id'),
            ),
        ]);
    }
}
