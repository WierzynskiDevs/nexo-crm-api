<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowDashboardRequest;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

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
