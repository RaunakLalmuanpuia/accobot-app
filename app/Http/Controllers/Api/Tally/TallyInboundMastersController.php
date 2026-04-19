<?php

namespace App\Http\Controllers\Api\Tally;

use App\Services\Tally\TallyInboundSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyInboundMastersController extends TallyBaseController
{
    public function __construct(private TallyInboundSync $sync) {}

    public function ledgerGroups(Request $request): JsonResponse
    {
        $conn  = $this->resolveConnection($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncLedgerGroups($conn, $items);

        return response()->json($this->logResponse($log));
    }

    public function ledgers(Request $request): JsonResponse
    {
        $conn      = $this->resolveConnection($request);
        $items     = $request->input('Data', []);
        $fullSync  = (bool) $request->input('full_sync', false);
        $log       = $this->sync->syncLedgers($conn, $items, $fullSync);

        return response()->json($this->logResponse($log));
    }

    public function stockItems(Request $request): JsonResponse
    {
        $conn     = $this->resolveConnection($request);
        $items    = $request->input('Data', []);
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncStockItems($conn, $items, $fullSync);

        return response()->json($this->logResponse($log));
    }

    public function stockGroups(Request $request): JsonResponse
    {
        $conn  = $this->resolveConnection($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncStockGroups($conn, $items);

        return response()->json($this->logResponse($log));
    }

    public function stockCategories(Request $request): JsonResponse
    {
        $conn  = $this->resolveConnection($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncStockCategories($conn, $items);

        return response()->json($this->logResponse($log));
    }

    private function logResponse($log): array
    {
        return [
            'status'  => $log->status,
            'created' => $log->records_created,
            'updated' => $log->records_updated,
            'skipped' => $log->records_skipped,
            'failed'  => $log->records_failed,
        ];
    }
}
