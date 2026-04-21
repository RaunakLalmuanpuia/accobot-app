<?php

namespace App\Http\Controllers\Api\Tally;

use App\Services\Tally\TallyPayrollSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyInboundPayrollController extends TallyBaseController
{
    public function __construct(private TallyPayrollSync $sync) {}

    public function employeeGroups(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncEmployeeGroups($conn, $items);

        return response()->json($this->logResponse($log));
    }

    public function employees(Request $request): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('Data', []);
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncEmployees($conn, $items, $fullSync);

        return response()->json($this->logResponse($log));
    }

    public function payHeads(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncPayHeads($conn, $items);

        return response()->json($this->logResponse($log));
    }

    public function attendanceTypes(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncAttendanceTypes($conn, $items);

        return response()->json($this->logResponse($log));
    }

    private function logResponse($log): array
    {
        return [
            'status'  => $log->status,
            'created' => $log->records_created,
            'updated' => $log->records_updated,
            'deleted' => $log->records_deleted,
            'skipped' => $log->records_skipped,
            'failed'  => $log->records_failed,
        ];
    }
}
