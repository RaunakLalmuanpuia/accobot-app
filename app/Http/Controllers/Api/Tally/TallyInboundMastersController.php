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
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncLedgerGroups($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'ledger_groups', $data);
        return response()->json($data);
    }

    public function ledgers(Request $request): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('Data', []);
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncLedgers($conn, $items, $fullSync);
        $data     = $this->logResponse($log);
        $this->logAudit($conn, 'ledgers', $data);
        return response()->json($data);
    }

    public function stockItems(Request $request): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('Data', []);
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncStockItems($conn, $items, $fullSync);
        $data     = $this->logResponse($log);
        $this->logAudit($conn, 'stock_items', $data);
        return response()->json($data);
    }

    public function stockGroups(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncStockGroups($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'stock_groups', $data);
        return response()->json($data);
    }

    public function stockCategories(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncStockCategories($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'stock_categories', $data);
        return response()->json($data);
    }

    public function godowns(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncGodowns($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'godowns', $data);
        return response()->json($data);
    }

    public function units(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncUnits($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'units', $data);
        return response()->json($data);
    }

    public function statutory(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncStatutoryMasters($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'statutory_masters', $data);
        return response()->json($data);
    }

    public function company(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncCompanyMaster($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'company_master', $data);
        return response()->json($data);
    }
}
