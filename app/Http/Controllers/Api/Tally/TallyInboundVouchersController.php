<?php

namespace App\Http\Controllers\Api\Tally;

use App\Services\Tally\TallyInboundSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyInboundVouchersController extends TallyBaseController
{
    public function __construct(private TallyInboundSync $sync) {}

    public function sales(Request $request): JsonResponse
    {
        return $this->handle($request, 'Sales');
    }

    public function creditNote(Request $request): JsonResponse
    {
        return $this->handle($request, 'CreditNote');
    }

    public function purchase(Request $request): JsonResponse
    {
        return $this->handle($request, 'Purchase');
    }

    public function debitNote(Request $request): JsonResponse
    {
        return $this->handle($request, 'DebitNote');
    }

    public function receipt(Request $request): JsonResponse
    {
        return $this->handle($request, 'Receipt');
    }

    public function payment(Request $request): JsonResponse
    {
        return $this->handle($request, 'Payment');
    }

    public function contra(Request $request): JsonResponse
    {
        return $this->handle($request, 'Contra');
    }

    public function journal(Request $request): JsonResponse
    {
        return $this->handle($request, 'Journal');
    }

    private function handle(Request $request, string $type): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('data', $request->input('Data', []));
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncVouchers($conn, $items, $type, $fullSync);

        return response()->json([
            'status'  => $log->status,
            'created' => $log->records_created,
            'updated' => $log->records_updated,
            'skipped' => $log->records_skipped,
            'failed'  => $log->records_failed,
        ]);
    }
}
