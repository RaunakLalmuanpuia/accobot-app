<?php

namespace App\Http\Controllers\Api\Tally;

use App\Services\Tally\TallyInboundSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyInboundVouchersController extends TallyBaseController
{
    public function __construct(private TallyInboundSync $sync) {}

    public function sales(Request $request): JsonResponse      { return $this->handle($request, 'Sales'); }
    public function creditNote(Request $request): JsonResponse { return $this->handle($request, 'CreditNote'); }
    public function purchase(Request $request): JsonResponse   { return $this->handle($request, 'Purchase'); }
    public function debitNote(Request $request): JsonResponse  { return $this->handle($request, 'DebitNote'); }
    public function receipt(Request $request): JsonResponse    { return $this->handle($request, 'Receipt'); }
    public function payment(Request $request): JsonResponse    { return $this->handle($request, 'Payment'); }
    public function contra(Request $request): JsonResponse     { return $this->handle($request, 'Contra'); }
    public function journal(Request $request): JsonResponse    { return $this->handle($request, 'Journal'); }
    public function salary(Request $request): JsonResponse     { return $this->handlePayroll($request, 'Payroll'); }
    public function attendance(Request $request): JsonResponse { return $this->handlePayroll($request, 'Attendance'); }

    public function voucher(Request $request): JsonResponse
    {
        $type  = $request->input('VoucherBaseType', $request->input('VoucherType', $request->input('voucher_type', '')));
        $valid = ['Sales', 'CreditNote', 'Purchase', 'DebitNote', 'Receipt', 'Payment', 'Contra', 'Journal'];

        if (!in_array($type, $valid, true)) {
            return response()->json(['error' => 'Invalid or missing VoucherBaseType. Must be one of: ' . implode(', ', $valid)], 422);
        }

        return $this->handle($request, $type);
    }

    private function handle(Request $request, string $type): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('data', $request->input('Data', []));
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncVouchers($conn, $items, $type, $fullSync);
        $data     = $this->logResponse($log);
        $this->logAudit($conn, 'voucher_' . strtolower($type), $data);
        return response()->json($data);
    }

    private function handlePayroll(Request $request, string $type): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('data', $request->input('Data', []));
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncPayrollVouchers($conn, $items, $type, $fullSync);
        $data     = $this->logResponse($log);
        $this->logAudit($conn, 'voucher_' . strtolower($type), $data);
        return response()->json($data);
    }
}
