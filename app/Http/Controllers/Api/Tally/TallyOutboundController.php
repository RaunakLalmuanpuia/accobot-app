<?php

namespace App\Http\Controllers\Api\Tally;

use App\Models\TallyAttendanceType;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyPayHead;
use App\Models\TallyStatutoryMaster;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyVoucher;
use App\Services\Tally\TallyOutboundFormatter;
use App\Services\Tally\TallyOutboundQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyOutboundController extends TallyBaseController
{
    public function __construct(
        private TallyOutboundFormatter $formatter,
        private TallyOutboundQueueService $queue,
    ) {}

    public function ledgerGroup(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyLedgerGroup::class);

        $groups = TallyLedgerGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatLedgerGroups($groups)]);
    }

    public function ledgerMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyLedger::class);

        $ledgers = TallyLedger::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatLedgers($ledgers)]);
    }

    public function stockMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyStockItem::class);

        $items = TallyStockItem::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatStockItems($items)]);
    }

    public function stockGroup(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyStockGroup::class);

        $groups = TallyStockGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatStockGroups($groups)]);
    }

    public function stockCategory(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyStockCategory::class);

        $cats = TallyStockCategory::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatStockCategories($cats)]);
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
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyStatutoryMaster::class);

        $items = TallyStatutoryMaster::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatStatutoryMasters($items)]);
    }

    public function employeeGroup(Request $request): JsonResponse
    {
        $conn   = $this->resolveAndVerify($request);
        $ids    = $this->queue->pendingIds((int) $conn->tenant_id, TallyEmployeeGroup::class);

        $groups = TallyEmployeeGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatEmployeeGroups($groups)]);
    }

    public function employee(Request $request): JsonResponse
    {
        $conn      = $this->resolveAndVerify($request);
        $ids       = $this->queue->pendingIds((int) $conn->tenant_id, TallyEmployee::class);

        $employees = TallyEmployee::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatEmployees($employees)]);
    }

    public function payHead(Request $request): JsonResponse
    {
        $conn     = $this->resolveAndVerify($request);
        $ids      = $this->queue->pendingIds((int) $conn->tenant_id, TallyPayHead::class);

        $payHeads = TallyPayHead::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatPayHeads($payHeads)]);
    }

    public function attendanceType(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndVerify($request);
        $ids   = $this->queue->pendingIds((int) $conn->tenant_id, TallyAttendanceType::class);

        $types = TallyAttendanceType::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->whereIn('id', $ids)
            ->get();

        return response()->json(['Data' => $this->formatter->formatAttendanceTypes($types)]);
    }

    private function voucherResponse(Request $request, string $type): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);
        $ids  = $this->queue->pendingIds((int) $conn->tenant_id, TallyVoucher::class);

        $vouchers = TallyVoucher::withoutGlobalScope('tenant')
            ->with(['inventoryEntries', 'ledgerEntries'])
            ->where('tenant_id', $conn->tenant_id)
            ->where('voucher_type', $type)
            ->whereIn('id', $ids)
            ->get();

        $formatted = match ($type) {
            'Sales'      => $this->formatter->formatSalesVouchers($vouchers),
            'Purchase'   => $this->formatter->formatPurchaseVouchers($vouchers),
            'DebitNote'  => $this->formatter->formatDebitNoteVouchers($vouchers),
            'CreditNote' => $this->formatter->formatCreditNoteVouchers($vouchers),
            'Receipt'    => $this->formatter->formatReceiptVouchers($vouchers),
            'Payment'    => $this->formatter->formatPaymentVouchers($vouchers),
            'Contra'     => $this->formatter->formatContraVouchers($vouchers),
            'Journal'    => $this->formatter->formatJournalVouchers($vouchers),
        };

        return response()->json(['Data' => $formatted]);
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
