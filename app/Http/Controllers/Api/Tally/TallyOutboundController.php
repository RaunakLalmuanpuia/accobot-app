<?php

namespace App\Http\Controllers\Api\Tally;

use App\Models\AuditEvent;
use App\Models\TallyAttendanceType;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyPayHead;
use App\Models\TallyCompany;
use App\Models\TallyStatutoryMaster;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyGodown;
use App\Models\TallyUnit;
use App\Models\TallyVoucher;
use App\Services\Tally\TallyOutboundFormatter;
use App\Services\Tally\TallyOutboundQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TallyOutboundController extends TallyBaseController
{
    public function __construct(
        private TallyOutboundFormatter $formatter,
        private TallyOutboundQueueService $queue,
    ) {}

    public function ledgerGroup(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyLedgerGroup::class);

        $groups = TallyLedgerGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatLedgerGroups($groups);
        $this->logOutbound('LedgerGroup', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function ledgerMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyLedger::class);

        $ledgers = TallyLedger::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatLedgers($ledgers);
        $this->logOutbound('Ledger', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function stockMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyStockItem::class);

        $items = TallyStockItem::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatStockItems($items);
        $this->logOutbound('StockItem', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function godownMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyGodown::class);

        $godowns = TallyGodown::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatGodowns($godowns);
        $this->logOutbound('Godown', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function unitMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyUnit::class);

        $units = TallyUnit::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatUnits($units);
        $this->logOutbound('Unit', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function stockGroup(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyStockGroup::class);

        $groups = TallyStockGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatStockGroups($groups);
        $this->logOutbound('StockGroup', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function stockCategory(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyStockCategory::class);

        $cats = TallyStockCategory::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatStockCategories($cats);
        $this->logOutbound('StockCategory', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function salesVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'Sales');
    }

    public function purchaseVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'Purchase');
    }

    public function debitNoteVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'DebitNote');
    }

    public function creditNoteVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'CreditNote');
    }

    public function receiptVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'Receipt');
    }

    public function paymentVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'Payment');
    }

    public function contraVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'Contra');
    }

    public function journalVoucher(Request $request): JsonResponse
    {
        return $this->voucherResponse($request, 'Journal');
    }

    public function statutoryMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyStatutoryMaster::class);

        $items = TallyStatutoryMaster::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatStatutoryMasters($items);
        $this->logOutbound('StatutoryMaster', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function employeeGroup(Request $request): JsonResponse
    {
        $conn   = $this->resolveAndVerify($request);
        $ids    = $this->queue->pendingIds($conn->tenant_id, TallyEmployeeGroup::class);

        $groups = TallyEmployeeGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatEmployeeGroups($groups);
        $this->logOutbound('EmployeeGroup', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function employee(Request $request): JsonResponse
    {
        $conn      = $this->resolveAndVerify($request);
        $ids       = $this->queue->pendingIds($conn->tenant_id, TallyEmployee::class);

        $employees = TallyEmployee::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatEmployees($employees);
        $this->logOutbound('Employee', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function payHead(Request $request): JsonResponse
    {
        $conn     = $this->resolveAndVerify($request);
        $ids      = $this->queue->pendingIds($conn->tenant_id, TallyPayHead::class);

        $payHeads = TallyPayHead::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatPayHeads($payHeads);
        $this->logOutbound('PayHead', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function attendanceType(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndVerify($request);
        $ids   = $this->queue->pendingIds($conn->tenant_id, TallyAttendanceType::class);

        $types = TallyAttendanceType::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatAttendanceTypes($types);
        $this->logOutbound('AttendanceType', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    public function salaryVoucher(Request $request): JsonResponse
    {
        return $this->payrollVoucherResponse($request, 'Payroll');
    }

    public function attendanceVoucher(Request $request): JsonResponse
    {
        return $this->payrollVoucherResponse($request, 'Attendance');
    }

    private function payrollVoucherResponse(Request $request, string $type): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyVoucher::class);

        $vouchers = TallyVoucher::withoutGlobalScope('tenant')
            ->with('employeeAllocations')
            ->where('tenant_id', $conn->tenant_id)
            ->where('voucher_type', $type)
            ->whereIn('id', $ids)
            ->get();

        $data = $type === 'Payroll'
            ? $this->formatter->formatSalaryVouchers($vouchers)
            : $this->formatter->formatAttendanceVouchers($vouchers);

        $this->logOutbound($type . 'Voucher', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    private function voucherResponse(Request $request, string $type): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyVoucher::class);

        $vouchers = TallyVoucher::withoutGlobalScope('tenant')
            ->with(['inventoryEntries', 'ledgerEntries'])
            ->where('tenant_id', $conn->tenant_id)
            ->where('voucher_type', $type)
            ->whereIn('id', $ids)
            ->get();

        $data = match ($type) {
            'Sales'      => $this->formatter->formatSalesVouchers($vouchers),
            'Purchase'   => $this->formatter->formatPurchaseVouchers($vouchers),
            'DebitNote'  => $this->formatter->formatDebitNoteVouchers($vouchers),
            'CreditNote' => $this->formatter->formatCreditNoteVouchers($vouchers),
            'Receipt'    => $this->formatter->formatReceiptVouchers($vouchers),
            'Payment'    => $this->formatter->formatPaymentVouchers($vouchers),
            'Contra'     => $this->formatter->formatContraVouchers($vouchers),
            'Journal'    => $this->formatter->formatJournalVouchers($vouchers),
        };

        $this->logOutbound($type . 'Voucher', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    private function logOutbound(string $entity, string $tenantId, array $data): void
    {
        if (empty($data)) {
            return;
        }
        Log::info('tally.outbound', [
            'entity'    => $entity,
            'tenant_id' => $tenantId,
            'count'     => count($data),
            'payload'   => $data,
        ]);
        AuditEvent::log(
            'tally.outbound.' . strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entity)),
            ['entity' => $entity, 'count' => count($data)],
            null,
            (string) $tenantId,
            'integration',
        );
    }

    public function companyMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds($conn->tenant_id, TallyCompany::class);

        $companies = TallyCompany::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        $data = $this->formatter->formatCompanyMasters($companies);
        $this->logOutbound('CompanyMaster', $conn->tenant_id, $data);
        return response()->json(['Data' => $data]);
    }

    private function resolveAndVerify(Request $request)
    {
        $conn      = $this->resolveConnection($request);
        $companyId = $request->query('companyId');

        if ($companyId && $conn->company_id !== $companyId) {
            abort(403, 'Company ID mismatch.');
        }

        return $conn;
    }
}
