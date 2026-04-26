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
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'employee_groups', $data);
        return response()->json($data);
    }

    public function employees(Request $request): JsonResponse
    {
        $conn     = $this->resolveAndLog($request);
        $items    = $request->input('Data', []);
        $fullSync = (bool) $request->input('full_sync', false);
        $log      = $this->sync->syncEmployees($conn, $items, $fullSync);
        $data     = $this->logResponse($log);
        $this->logAudit($conn, 'employees', $data);
        return response()->json($data);
    }

    public function payHeads(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncPayHeads($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'pay_heads', $data);
        return response()->json($data);
    }

    public function attendanceTypes(Request $request): JsonResponse
    {
        $conn  = $this->resolveAndLog($request);
        $items = $request->input('Data', []);
        $log   = $this->sync->syncAttendanceTypes($conn, $items);
        $data  = $this->logResponse($log);
        $this->logAudit($conn, 'attendance_types', $data);
        return response()->json($data);
    }
}
