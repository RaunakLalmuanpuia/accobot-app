<?php

namespace App\Http\Controllers\Api\Tally;

use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyConfirmController extends TallyBaseController
{
    public function ledgerMaster(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyLedger::class);
    }

    public function stockMaster(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyStockItem::class);
    }

    public function ledgerGroup(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyLedgerGroup::class);
    }

    public function stockGroup(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyStockGroup::class);
    }

    public function stockCategory(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyStockCategory::class);
    }

    public function salesVoucher(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyVoucher::class);
    }

    public function purchaseVoucher(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyVoucher::class);
    }

    public function debitNoteVoucher(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyVoucher::class);
    }

    public function creditNoteVoucher(Request $request, string $companyId): JsonResponse
    {
        return $this->handle($request, $companyId, TallyVoucher::class);
    }

    private function handle(Request $request, string $companyId, string $modelClass): JsonResponse
    {
        $conn = $this->resolveConnectionByCompanyId($request, $companyId);

        $items    = $request->input('Data', []);
        $updated  = 0;

        foreach ($items as $item) {
            $id      = $item['Id'] ?? $item['ID'] ?? null;
            $tallyId = $item['TallyId'] ?? $item['TallyID'] ?? null;
            $synced  = $item['IsSynced'] ?? false;

            if (!$id || !$tallyId) continue;

            $record = $modelClass::withoutGlobalScope('tenant')
                ->where('tenant_id', $conn->tenant_id)
                ->find($id);

            if (!$record) continue;

            $record->update(['tally_id' => (int) $tallyId]);
            $updated++;

            // Mark mapped Accobot model as synced where applicable
            $this->markMappedSynced($record, $synced);
        }

        return response()->json(['status' => 'ok', 'updated' => $updated]);
    }

    private function markMappedSynced(mixed $record, mixed $synced): void
    {
        if (!$synced) return;

        if ($record instanceof TallyLedger) {
            $record->mappedClient?->touch();
            $record->mappedVendor?->touch();
        }

        if ($record instanceof TallyStockItem) {
            $record->mappedProduct?->touch();
        }

        if ($record instanceof TallyVoucher) {
            $record->mappedInvoice?->touch();
        }
    }
}
