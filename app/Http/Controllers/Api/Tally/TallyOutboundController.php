<?php

namespace App\Http\Controllers\Api\Tally;

use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyVoucher;
use App\Services\Tally\TallyOutboundFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyOutboundController extends TallyBaseController
{
    public function __construct(private TallyOutboundFormatter $formatter) {}

    public function ledgerGroup(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);

        $groups = TallyLedgerGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->where('is_active', true)
            ->get();

        return response()->json(['Data' => $this->formatter->formatLedgerGroups($groups)]);
    }

    public function ledgerMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);

        $ledgers = TallyLedger::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->where('is_active', true)
            ->get();

        return response()->json(['Data' => $this->formatter->formatLedgers($ledgers)]);
    }

    public function stockMaster(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);

        $items = TallyStockItem::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->where('is_active', true)
            ->get();

        return response()->json(['Data' => $this->formatter->formatStockItems($items)]);
    }

    public function stockGroup(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);

        $groups = TallyStockGroup::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->where('is_active', true)
            ->get();

        return response()->json(['Data' => $this->formatter->formatStockGroups($groups)]);
    }

    public function stockCategory(Request $request): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);

        $cats = TallyStockCategory::withoutGlobalScope('tenant')
            ->where('tenant_id', $conn->tenant_id)
            ->where('is_active', true)
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

    private function voucherResponse(Request $request, string $type): JsonResponse
    {
        $conn = $this->resolveAndVerify($request);

        $vouchers = TallyVoucher::withoutGlobalScope('tenant')
            ->with(['inventoryEntries', 'ledgerEntries'])
            ->where('tenant_id', $conn->tenant_id)
            ->where('voucher_type', $type)
            ->where('is_active', true)
            ->get();

        $formatted = match ($type) {
            'Sales'      => $this->formatter->formatSalesVouchers($vouchers),
            'Purchase'   => $this->formatter->formatPurchaseVouchers($vouchers),
            'DebitNote'  => $this->formatter->formatDebitNoteVouchers($vouchers),
            'CreditNote' => $this->formatter->formatCreditNoteVouchers($vouchers),
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
