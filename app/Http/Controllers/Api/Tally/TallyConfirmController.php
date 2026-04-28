<?php

namespace App\Http\Controllers\Api\Tally;

use App\Models\AuditEvent;
use App\Models\TallyAttendanceType;
use App\Models\TallyCompany;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyPayHead;
use App\Models\TallyStatutoryMaster;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyUnit;
use App\Models\TallyVoucher;
use App\Services\Tally\TallyOutboundQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyConfirmController extends TallyBaseController
{
    public function __construct(private TallyOutboundQueueService $queue) {}

    public function unitMaster(Request $request): JsonResponse
    {
        return $this->handle($request, TallyUnit::class);
    }

    public function ledgerMaster(Request $request): JsonResponse
    {
        return $this->handle($request, TallyLedger::class);
    }

    public function stockMaster(Request $request): JsonResponse
    {
        return $this->handle($request, TallyStockItem::class);
    }

    public function ledgerGroup(Request $request): JsonResponse
    {
        return $this->handle($request, TallyLedgerGroup::class);
    }

    public function stockGroup(Request $request): JsonResponse
    {
        return $this->handle($request, TallyStockGroup::class);
    }

    public function stockCategory(Request $request): JsonResponse
    {
        return $this->handle($request, TallyStockCategory::class);
    }

    public function salesVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function purchaseVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function debitNoteVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function creditNoteVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function receiptVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function paymentVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function contraVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function journalVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function statutoryMaster(Request $request): JsonResponse
    {
        return $this->handle($request, TallyStatutoryMaster::class);
    }

    public function companyMaster(Request $request): JsonResponse
    {
        return $this->handle($request, TallyCompany::class);
    }

    public function employeeGroup(Request $request): JsonResponse
    {
        return $this->handle($request, TallyEmployeeGroup::class);
    }

    public function employee(Request $request): JsonResponse
    {
        return $this->handle($request, TallyEmployee::class);
    }

    public function payHead(Request $request): JsonResponse
    {
        return $this->handle($request, TallyPayHead::class);
    }

    public function attendanceType(Request $request): JsonResponse
    {
        return $this->handle($request, TallyAttendanceType::class);
    }

    public function salaryVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    public function attendanceVoucher(Request $request): JsonResponse
    {
        return $this->handle($request, TallyVoucher::class);
    }

    private function handle(Request $request, string $modelClass): JsonResponse
    {
        $conn    = $this->resolveConnection($request);
        $items   = $request->input('Data', []);
        $updated = 0;

        foreach ($items as $item) {
            $id      = $item['AccobotId'] ?? $item['Id'] ?? null;
            $tallyId = $item['TallyId'] ?? $item['TallyID'] ?? null;
            $synced  = $item['IsSynced'] ?? false;

            if (!$id || !$tallyId) continue;

            $record = $modelClass::withoutGlobalScope('tenant')
                ->where('tenant_id', $conn->tenant_id)
                ->find($id);

            if (!$record) continue;

            $record->updateQuietly(['tally_id' => (int) $tallyId]);
            $updated++;

            $this->queue->markConfirmed($conn->tenant_id, $modelClass, (int) $id);

            $this->markMappedSynced($record, $synced);
        }

        if ($updated > 0) {
            AuditEvent::log('tally.outbound.confirmed', [
                'model'   => class_basename($modelClass),
                'updated' => $updated,
            ], null, (string) $conn->tenant_id, 'integration');
        }

        return response()->json(['status' => 'ok', 'updated' => $updated]);
    }

    private function markMappedSynced(mixed $record, mixed $synced): void
    {
        if (!$synced) return;

        $now = now();

        if ($record instanceof TallyLedger) {
            $record->mappedClient?->updateQuietly(['tally_synced_at' => $now]);
            $record->mappedVendor?->updateQuietly(['tally_synced_at' => $now]);
        }

        if ($record instanceof TallyStockItem) {
            $record->mappedProduct?->updateQuietly(['tally_synced_at' => $now]);
        }

        if ($record instanceof TallyVoucher) {
            $record->mappedInvoice?->updateQuietly(['tally_synced_at' => $now]);
        }
    }
}
