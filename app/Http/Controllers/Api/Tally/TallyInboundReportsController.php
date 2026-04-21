<?php

namespace App\Http\Controllers\Api\Tally;

use App\Services\Tally\TallyReportSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyInboundReportsController extends TallyBaseController
{
    public function __construct(private TallyReportSync $sync) {}

    public function balanceSheet(Request $request): JsonResponse
    {
        return $this->handle($request, 'balance_sheet');
    }

    public function profitLoss(Request $request): JsonResponse
    {
        return $this->handle($request, 'profit_loss');
    }

    public function cashFlow(Request $request): JsonResponse
    {
        return $this->handle($request, 'cash_flow');
    }

    public function ratioAnalysis(Request $request): JsonResponse
    {
        return $this->handle($request, 'ratio_analysis');
    }

    private function handle(Request $request, string $type): JsonResponse
    {
        $conn    = $this->resolveAndLog($request);
        $payload = $request->all();
        $log     = $this->sync->syncReport($conn, $type, $payload);

        return response()->json(['status' => $log->status]);
    }
}
