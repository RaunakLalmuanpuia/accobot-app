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
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyEmployeeGroup::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'      => $conn->tenant_id,
                    'tally_id'       => $tallyId,
                    'alter_id'       => $alterId,
                    'action'         => $action,
                    'name'           => $item['Name'] ?? '',
                    'parent_name'    => $item['ParentName'] ?? null,
                    'is_active'      => true,
                    'last_synced_at' => now(),
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
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $seenIds[] = $tallyId;
                $alterId   = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing  = TallyEmployee::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'           => $conn->tenant_id,
                    'tally_id'            => $tallyId,
                    'alter_id'            => $alterId,
                    'action'              => $action,
                    'name'                => $item['Name'] ?? '',
                    'employee_number'     => $item['EmployeeNumber'] ?? null,
                    'group_name'          => $item['GroupName'] ?? null,
                    'designation'         => $item['Designation'] ?? null,
                    'employee_function'   => $item['Function'] ?? null,
                    'department'          => $item['Department'] ?? null,
                    'date_of_joining'     => $this->parseDate($item['DateOfJoining'] ?? null),
                    'date_of_leaving'     => $this->parseDate($item['DateOfLeaving'] ?? null),
                    'date_of_birth'       => $this->parseDate($item['DateOfBirth'] ?? null),
                    'gender'              => $item['Gender'] ?? null,
                    'pan'                 => $item['PAN'] ?? null,
                    'aadhar'              => $item['AadharNumber'] ?? $item['Aadhar'] ?? null,
                    'pf_number'           => $item['PFNumber'] ?? null,
                    'uan_number'          => $item['UANNumber'] ?? null,
                    'esi_number'          => $item['ESINumber'] ?? null,
                    'bank_name'           => $item['BankName'] ?? null,
                    'bank_account_number' => $item['BankAccountNumber'] ?? null,
                    'bank_ifsc'           => $item['BankIFSC'] ?? null,
                    'addresses'           => $item['Addresses'] ?? null,
                    'salary_details'      => $item['SalaryDetails'] ?? null,
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
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyPayHead::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'        => $conn->tenant_id,
                    'tally_id'         => $tallyId,
                    'alter_id'         => $alterId,
                    'action'           => $action,
                    'name'             => $item['Name'] ?? '',
                    'pay_head_type'    => $item['PayHeadType'] ?? null,
                    'pay_slip_name'    => $item['PaySlipName'] ?? null,
                    'under_group'      => $item['UnderGroup'] ?? null,
                    'ledger_name'      => $item['LedgerName'] ?? null,
                    'calculation_type' => $item['CalculationType'] ?? null,
                    'rate'             => isset($item['Rate']) ? (float) $item['Rate'] : null,
                    'rate_period'      => $item['RatePeriod'] ?? null,
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
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyAttendanceType::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'       => $conn->tenant_id,
                    'tally_id'        => $tallyId,
                    'alter_id'        => $alterId,
                    'action'          => $action,
                    'name'            => $item['Name'] ?? '',
                    'attendance_type' => $item['AttendanceType'] ?? null,
                    'unit_of_measure' => $item['UnitOfMeasure'] ?? null,
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
