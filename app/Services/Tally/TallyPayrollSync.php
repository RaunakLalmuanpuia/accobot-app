<?php

namespace App\Services\Tally;

use App\Models\TallyAttendanceType;
use App\Models\TallyConnection;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyPayHead;
use App\Models\TallySyncLog;

class TallyPayrollSync
{
    use TallySyncHelpers;
    public function syncEmployeeGroups(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'employee_groups');

        try {
            foreach ($items as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['TallyId'] ?? $item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyEmployeeGroup::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_deleted++; }
                    continue;
                }

                $data = [
                    'tenant_id'           => $conn->tenant_id,
                    'tally_id'            => $tallyId,
                    'alter_id'            => $alterId,
                    'action'              => $action,
                    'name'                 => $item['Name'] ?? '',
                    'guid'                 => $item['Guid'] ?? null,
                    'under'                => $item['Under'] ?? $item['ParentName'] ?? null,
                    'cost_centre_category' => $item['CostCentreCatgeory'] ?? $item['CostCentreCategory'] ?? null,
                    'is_active'           => true,
                    'last_synced_at'      => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyEmployeeGroup::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncEmployees(TallyConnection $conn, array $items, bool $fullSync = false): TallySyncLog
    {
        $log = $this->startLog($conn, 'employees');

        try {
            $seenIds = [];

            foreach (array_chunk($items, 250) as $chunk) {
            foreach ($chunk as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['TallyId'] ?? $item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $seenIds[] = $tallyId;
                $alterId   = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing  = TallyEmployee::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_deleted++; }
                    continue;
                }

                $data = [
                    'tenant_id'           => $conn->tenant_id,
                    'tally_id'            => $tallyId,
                    'alter_id'            => $alterId,
                    'action'              => $action,
                    'name'              => $item['Name'] ?? '',
                    'employee_number'   => $item['EmployeeNumber'] ?? null,
                    'parent'            => $item['Parent'] ?? $item['GroupName'] ?? null,
                    'designation'       => $item['Designation'] ?? null,
                    'employee_function' => $item['Function'] ?? null,
                    'location'          => $item['Location'] ?? null,
                    'date_of_joining'   => $this->parseDate($item['JoiningDate'] ?? $item['DateOfJoining'] ?? null),
                    'date_of_leaving'   => $this->parseDate($item['ResignationDate'] ?? $item['DateOfLeaving'] ?? null),
                    'date_of_birth'     => $this->parseDate($item['DOB'] ?? $item['DateOfBirth'] ?? null),
                    'gender'            => $item['Gender'] ?? null,
                    'father_name'       => $item['FatherName'] ?? null,
                    'spouse_name'       => $item['SpouseName'] ?? null,
                    'aliases'           => isset($item['Aliases']) ? array_column($item['Aliases'], 'Alias') : null,
                    'is_active'           => true,
                    'last_synced_at'      => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyEmployee::create($data); $log->records_created++; }
            }
            }

            if ($fullSync && count($seenIds)) {
                TallyEmployee::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->whereNotIn('tally_id', $seenIds)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncPayHeads(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'pay_heads');

        try {
            foreach ($items as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['TallyId'] ?? $item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyPayHead::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_deleted++; }
                    continue;
                }

                $data = [
                    'tenant_id'          => $conn->tenant_id,
                    'tally_id'           => $tallyId,
                    'alter_id'           => $alterId,
                    'action'             => $action,
                    'name'               => $item['Name'] ?? '',
                    'pay_type'           => $item['PayType'] ?? $item['PayHeadType'] ?? null,
                    'income_type'        => $item['IncomeType'] ?? null,
                    'parent_group'       => $item['ParentGroup'] ?? $item['UnderGroup'] ?? null,
                    'calculation_type'   => $item['CalculationType'] ?? null,
                    'leave_type'         => $item['LeaveType'] ?? null,
                    'calculation_period' => $item['CalculationPeriod'] ?? $item['RatePeriod'] ?? null,
                    'is_active'        => true,
                    'last_synced_at'   => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyPayHead::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncAttendanceTypes(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'attendance_types');

        try {
            foreach ($items as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['TallyId'] ?? $item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyAttendanceType::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_deleted++; }
                    continue;
                }

                $data = [
                    'tenant_id'        => $conn->tenant_id,
                    'tally_id'         => $tallyId,
                    'alter_id'         => $alterId,
                    'action'           => $action,
                    'name'             => $item['Name'] ?? '',
                    'guid'             => $item['Guid'] ?? null,
                    'attendance_type'  => $item['AttendanceType'] ?? null,
                    'under'            => $item['Under'] ?? null,
                    'attendance_period'=> $item['AttendancePeriod'] ?? $item['UnitOfMeasure'] ?? null,
                    'is_active'       => true,
                    'last_synced_at'  => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyAttendanceType::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

}
