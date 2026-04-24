<?php

namespace App\Http\Controllers;

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
use App\Models\Tenant;
use App\Services\Tally\TallyOutboundFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TallyMasterCrudController extends Controller
{
    public function __construct(private TallyOutboundFormatter $formatter) {}

    // ── Ledger Groups ──────────────────────────────────────────────────────────

    public function ledgerGroupStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'under_name'      => 'nullable|string|max:255',
            'nature_of_group' => 'nullable|string|max:255',
        ]);

        $record = TallyLedgerGroup::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Ledger group created and queued for Tally sync.');
    }

    public function ledgerGroupUpdate(Request $request, Tenant $tenant, TallyLedgerGroup $ledgerGroup)
    {
        abort_unless($ledgerGroup->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'under_name'      => 'nullable|string|max:255',
            'nature_of_group' => 'nullable|string|max:255',
        ]);

        $ledgerGroup->update($data);

        if (! $ledgerGroup->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($ledgerGroup);
        return back()->with('success', 'Ledger group updated and queued for Tally sync.');
    }

    public function ledgerGroupDestroy(Tenant $tenant, TallyLedgerGroup $ledgerGroup)
    {
        abort_unless($ledgerGroup->tenant_id === $tenant->id, 404);

        if (! $ledgerGroup->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyLedgerGroup::class, $ledgerGroup->id);
            $ledgerGroup->delete();
            return back()->with('success', 'Ledger group deleted (was never synced to Tally).');
        }

        $ledgerGroup->update(['is_active' => false]);
        $this->logPayload($ledgerGroup);
        return back()->with('success', 'Ledger group marked inactive and queued for deletion in Tally.');
    }

    // ── Ledgers ────────────────────────────────────────────────────────────────

    public function ledgerStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'ledger_name'                       => 'required|string|max:255',
            'group_name'                        => 'nullable|string|max:255',
            'gstin_number'                      => 'nullable|string|max:50',
            'pan_number'                        => 'nullable|string|max:20',
            'gst_type'                          => 'nullable|string|max:50',
            'state_name'                        => 'nullable|string|max:100',
            'mobile_number'                     => 'nullable|string|max:20',
            'credit_limit'                      => 'nullable|numeric|min:0',
            'opening_balance'                   => 'nullable|numeric',
            'opening_balance_type'              => 'nullable|in:Dr,Cr',
            'bank_details'                      => 'nullable|array',
            'bank_details.*.BankName'           => 'nullable|string|max:255',
            'bank_details.*.IFSCode'            => 'nullable|string|max:50',
            'bank_details.*.AccountNumber'      => 'nullable|string|max:50',
            'bank_details.*.PaymentFavouring'   => 'nullable|string|max:255',
            'bank_details.*.TransactionName'    => 'nullable|string|max:255',
            'bank_details.*.TransactionType'    => 'nullable|string|max:100',
        ]);

        $record = TallyLedger::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Ledger created and queued for Tally sync.');
    }

    public function ledgerUpdate(Request $request, Tenant $tenant, TallyLedger $ledger)
    {
        abort_unless($ledger->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'ledger_name'                       => 'required|string|max:255',
            'group_name'                        => 'nullable|string|max:255',
            'gstin_number'                      => 'nullable|string|max:50',
            'pan_number'                        => 'nullable|string|max:20',
            'gst_type'                          => 'nullable|string|max:50',
            'state_name'                        => 'nullable|string|max:100',
            'mobile_number'                     => 'nullable|string|max:20',
            'credit_limit'                      => 'nullable|numeric|min:0',
            'opening_balance'                   => 'nullable|numeric',
            'opening_balance_type'              => 'nullable|in:Dr,Cr',
            'bank_details'                      => 'nullable|array',
            'bank_details.*.BankName'           => 'nullable|string|max:255',
            'bank_details.*.IFSCode'            => 'nullable|string|max:50',
            'bank_details.*.AccountNumber'      => 'nullable|string|max:50',
            'bank_details.*.PaymentFavouring'   => 'nullable|string|max:255',
            'bank_details.*.TransactionName'    => 'nullable|string|max:255',
            'bank_details.*.TransactionType'    => 'nullable|string|max:100',
        ]);

        $ledger->update($data);

        if (! $ledger->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($ledger);
        return back()->with('success', 'Ledger updated and queued for Tally sync.');
    }

    public function ledgerDestroy(Tenant $tenant, TallyLedger $ledger)
    {
        abort_unless($ledger->tenant_id === $tenant->id, 404);

        if (! $ledger->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyLedger::class, $ledger->id);
            $ledger->delete();
            return back()->with('success', 'Ledger deleted (was never synced to Tally).');
        }

        $ledger->update(['is_active' => false]);
        $this->logPayload($ledger);
        return back()->with('success', 'Ledger marked inactive and queued for deletion in Tally.');
    }

    // ── Stock Groups ───────────────────────────────────────────────────────────

    public function stockGroupStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'parent' => 'nullable|string|max:255',
        ]);

        $record = TallyStockGroup::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Stock group created and queued for Tally sync.');
    }

    public function stockGroupUpdate(Request $request, Tenant $tenant, TallyStockGroup $stockGroup)
    {
        abort_unless($stockGroup->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'parent' => 'nullable|string|max:255',
        ]);

        $stockGroup->update($data);

        if (! $stockGroup->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($stockGroup);
        return back()->with('success', 'Stock group updated and queued for Tally sync.');
    }

    public function stockGroupDestroy(Tenant $tenant, TallyStockGroup $stockGroup)
    {
        abort_unless($stockGroup->tenant_id === $tenant->id, 404);

        if (! $stockGroup->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyStockGroup::class, $stockGroup->id);
            $stockGroup->delete();
            return back()->with('success', 'Stock group deleted (was never synced to Tally).');
        }

        $stockGroup->update(['is_active' => false]);
        $this->logPayload($stockGroup);
        return back()->with('success', 'Stock group marked inactive and queued for deletion in Tally.');
    }

    // ── Stock Categories ───────────────────────────────────────────────────────

    public function stockCategoryStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'parent' => 'nullable|string|max:255',
        ]);

        $record = TallyStockCategory::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Stock category created and queued for Tally sync.');
    }

    public function stockCategoryUpdate(Request $request, Tenant $tenant, TallyStockCategory $stockCategory)
    {
        abort_unless($stockCategory->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'parent' => 'nullable|string|max:255',
        ]);

        $stockCategory->update($data);

        if (! $stockCategory->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($stockCategory);
        return back()->with('success', 'Stock category updated and queued for Tally sync.');
    }

    public function stockCategoryDestroy(Tenant $tenant, TallyStockCategory $stockCategory)
    {
        abort_unless($stockCategory->tenant_id === $tenant->id, 404);

        if (! $stockCategory->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyStockCategory::class, $stockCategory->id);
            $stockCategory->delete();
            return back()->with('success', 'Stock category deleted (was never synced to Tally).');
        }

        $stockCategory->update(['is_active' => false]);
        $this->logPayload($stockCategory);
        return back()->with('success', 'Stock category marked inactive and queued for deletion in Tally.');
    }

    // ── Stock Items ────────────────────────────────────────────────────────────

    public function stockItemStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'stock_group_name' => 'nullable|string|max:255',
            'category_name'    => 'nullable|string|max:255',
            'unit_name'        => 'nullable|string|max:50',
            'hsn_code'         => 'nullable|string|max:20',
            'igst_rate'        => 'nullable|numeric|min:0|max:100',
            'sgst_rate'        => 'nullable|numeric|min:0|max:100',
            'cgst_rate'        => 'nullable|numeric|min:0|max:100',
            'cess_rate'        => 'nullable|numeric|min:0|max:100',
            'opening_balance'  => 'nullable|numeric|min:0',
        ]);

        $record = TallyStockItem::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Stock item created and queued for Tally sync.');
    }

    public function stockItemUpdate(Request $request, Tenant $tenant, TallyStockItem $stockItem)
    {
        abort_unless($stockItem->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'stock_group_name' => 'nullable|string|max:255',
            'category_name'    => 'nullable|string|max:255',
            'unit_name'        => 'nullable|string|max:50',
            'hsn_code'         => 'nullable|string|max:20',
            'igst_rate'        => 'nullable|numeric|min:0|max:100',
            'sgst_rate'        => 'nullable|numeric|min:0|max:100',
            'cgst_rate'        => 'nullable|numeric|min:0|max:100',
            'cess_rate'        => 'nullable|numeric|min:0|max:100',
            'opening_balance'  => 'nullable|numeric|min:0',
        ]);

        $stockItem->update($data);

        if (! $stockItem->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($stockItem);
        return back()->with('success', 'Stock item updated and queued for Tally sync.');
    }

    public function stockItemDestroy(Tenant $tenant, TallyStockItem $stockItem)
    {
        abort_unless($stockItem->tenant_id === $tenant->id, 404);

        if (! $stockItem->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyStockItem::class, $stockItem->id);
            $stockItem->delete();
            return back()->with('success', 'Stock item deleted (was never synced to Tally).');
        }

        $stockItem->update(['is_active' => false]);
        $this->logPayload($stockItem);
        return back()->with('success', 'Stock item marked inactive and queued for deletion in Tally.');
    }

    // ── Statutory Masters ──────────────────────────────────────────────────────

    public function statutoryMasterStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'statutory_type'      => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'state_code'          => 'nullable|string|max:10',
            'registration_type'   => 'nullable|string|max:50',
            'pan'                 => 'nullable|string|max:20',
            'tan'                 => 'nullable|string|max:20',
            'applicable_from'     => 'nullable|date',
        ]);

        $record = TallyStatutoryMaster::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Statutory master created and queued for Tally sync.');
    }

    public function statutoryMasterUpdate(Request $request, Tenant $tenant, TallyStatutoryMaster $statutoryMaster)
    {
        abort_unless($statutoryMaster->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'statutory_type'      => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',
            'state_code'          => 'nullable|string|max:10',
            'registration_type'   => 'nullable|string|max:50',
            'pan'                 => 'nullable|string|max:20',
            'tan'                 => 'nullable|string|max:20',
            'applicable_from'     => 'nullable|date',
        ]);

        $statutoryMaster->update($data);

        if (! $statutoryMaster->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($statutoryMaster);
        return back()->with('success', 'Statutory master updated and queued for Tally sync.');
    }

    public function statutoryMasterDestroy(Tenant $tenant, TallyStatutoryMaster $statutoryMaster)
    {
        abort_unless($statutoryMaster->tenant_id === $tenant->id, 404);

        if (! $statutoryMaster->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyStatutoryMaster::class, $statutoryMaster->id);
            $statutoryMaster->delete();
            return back()->with('success', 'Statutory master deleted (was never synced to Tally).');
        }

        $statutoryMaster->update(['is_active' => false]);
        $this->logPayload($statutoryMaster);
        return back()->with('success', 'Statutory master marked inactive and queued for deletion in Tally.');
    }

    // ── Employee Groups ────────────────────────────────────────────────────────

    public function employeeGroupStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'under'                => 'nullable|string|max:255',
            'cost_centre_category' => 'nullable|string|max:255',
        ]);

        $record = TallyEmployeeGroup::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Employee group created and queued for Tally sync.');
    }

    public function employeeGroupUpdate(Request $request, Tenant $tenant, TallyEmployeeGroup $employeeGroup)
    {
        abort_unless($employeeGroup->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'under'                => 'nullable|string|max:255',
            'cost_centre_category' => 'nullable|string|max:255',
        ]);

        $employeeGroup->update($data);

        if (! $employeeGroup->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($employeeGroup);
        return back()->with('success', 'Employee group updated and queued for Tally sync.');
    }

    public function employeeGroupDestroy(Tenant $tenant, TallyEmployeeGroup $employeeGroup)
    {
        abort_unless($employeeGroup->tenant_id === $tenant->id, 404);

        if (! $employeeGroup->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyEmployeeGroup::class, $employeeGroup->id);
            $employeeGroup->delete();
            return back()->with('success', 'Employee group deleted (was never synced to Tally).');
        }

        $employeeGroup->update(['is_active' => false]);
        $this->logPayload($employeeGroup);
        return back()->with('success', 'Employee group marked inactive and queued for deletion in Tally.');
    }

    // ── Employees ──────────────────────────────────────────────────────────────

    public function employeeStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'employee_number' => 'nullable|string|max:50',
            'parent'          => 'nullable|string|max:255',
            'designation'     => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:100',
            'gender'          => 'nullable|in:Male,Female,Other',
            'date_of_joining' => 'nullable|date',
            'date_of_birth'   => 'nullable|date',
        ]);

        $record = TallyEmployee::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Employee created and queued for Tally sync.');
    }

    public function employeeUpdate(Request $request, Tenant $tenant, TallyEmployee $employee)
    {
        abort_unless($employee->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'employee_number' => 'nullable|string|max:50',
            'parent'          => 'nullable|string|max:255',
            'designation'     => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:100',
            'gender'          => 'nullable|in:Male,Female,Other',
            'date_of_joining' => 'nullable|date',
            'date_of_birth'   => 'nullable|date',
        ]);

        $employee->update($data);

        if (! $employee->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($employee);
        return back()->with('success', 'Employee updated and queued for Tally sync.');
    }

    public function employeeDestroy(Tenant $tenant, TallyEmployee $employee)
    {
        abort_unless($employee->tenant_id === $tenant->id, 404);

        if (! $employee->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyEmployee::class, $employee->id);
            $employee->delete();
            return back()->with('success', 'Employee deleted (was never synced to Tally).');
        }

        $employee->update(['is_active' => false]);
        $this->logPayload($employee);
        return back()->with('success', 'Employee marked inactive and queued for deletion in Tally.');
    }

    // ── Pay Heads ──────────────────────────────────────────────────────────────

    public function payHeadStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'pay_type'           => 'nullable|string|max:100',
            'income_type'        => 'nullable|string|max:100',
            'parent_group'       => 'nullable|string|max:255',
            'calculation_type'   => 'nullable|string|max:100',
            'calculation_period' => 'nullable|string|max:50',
        ]);

        $record = TallyPayHead::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Pay head created and queued for Tally sync.');
    }

    public function payHeadUpdate(Request $request, Tenant $tenant, TallyPayHead $payHead)
    {
        abort_unless($payHead->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'pay_type'           => 'nullable|string|max:100',
            'income_type'        => 'nullable|string|max:100',
            'parent_group'       => 'nullable|string|max:255',
            'calculation_type'   => 'nullable|string|max:100',
            'calculation_period' => 'nullable|string|max:50',
        ]);

        $payHead->update($data);

        if (! $payHead->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($payHead);
        return back()->with('success', 'Pay head updated and queued for Tally sync.');
    }

    public function payHeadDestroy(Tenant $tenant, TallyPayHead $payHead)
    {
        abort_unless($payHead->tenant_id === $tenant->id, 404);

        if (! $payHead->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyPayHead::class, $payHead->id);
            $payHead->delete();
            return back()->with('success', 'Pay head deleted (was never synced to Tally).');
        }

        $payHead->update(['is_active' => false]);
        $this->logPayload($payHead);
        return back()->with('success', 'Pay head marked inactive and queued for deletion in Tally.');
    }

    // ── Attendance Types ───────────────────────────────────────────────────────

    public function attendanceTypeStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'attendance_type'   => 'nullable|string|max:100',
            'attendance_period' => 'nullable|string|max:50',
        ]);

        $record = TallyAttendanceType::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]));

        $this->logPayload($record);
        return back()->with('success', 'Attendance type created and queued for Tally sync.');
    }

    public function attendanceTypeUpdate(Request $request, Tenant $tenant, TallyAttendanceType $attendanceType)
    {
        abort_unless($attendanceType->tenant_id === $tenant->id, 404);

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'attendance_type'   => 'nullable|string|max:100',
            'attendance_period' => 'nullable|string|max:50',
        ]);

        $attendanceType->update($data);

        if (! $attendanceType->wasChanged()) {
            return back()->with('info', 'No changes detected.');
        }

        $this->logPayload($attendanceType);
        return back()->with('success', 'Attendance type updated and queued for Tally sync.');
    }

    public function attendanceTypeDestroy(Tenant $tenant, TallyAttendanceType $attendanceType)
    {
        abort_unless($attendanceType->tenant_id === $tenant->id, 404);

        if (! $attendanceType->tally_id) {
            $this->purgeFromQueue($tenant->id, TallyAttendanceType::class, $attendanceType->id);
            $attendanceType->delete();
            return back()->with('success', 'Attendance type deleted (was never synced to Tally).');
        }

        $attendanceType->update(['is_active' => false]);
        $this->logPayload($attendanceType);
        return back()->with('success', 'Attendance type marked inactive and queued for deletion in Tally.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function purgeFromQueue(int $tenantId, string $entityType, int $entityId): void
    {
        DB::table('tally_outbound_queue')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();
    }

    private function logPayload(Model $record): void
    {
        $record->refresh();
        $collection = collect([$record]);

        $payload = match (true) {
            $record instanceof TallyLedgerGroup     => $this->formatter->formatLedgerGroups($collection),
            $record instanceof TallyLedger          => $this->formatter->formatLedgers($collection),
            $record instanceof TallyStockGroup      => $this->formatter->formatStockGroups($collection),
            $record instanceof TallyStockCategory   => $this->formatter->formatStockCategories($collection),
            $record instanceof TallyStockItem       => $this->formatter->formatStockItems($collection),
            $record instanceof TallyStatutoryMaster => $this->formatter->formatStatutoryMasters($collection),
            $record instanceof TallyEmployeeGroup   => $this->formatter->formatEmployeeGroups($collection),
            $record instanceof TallyEmployee        => $this->formatter->formatEmployees($collection),
            $record instanceof TallyPayHead         => $this->formatter->formatPayHeads($collection),
            $record instanceof TallyAttendanceType  => $this->formatter->formatAttendanceTypes($collection),
            default                                 => [],
        };

        Log::info('tally.outbound_preview', [
            'entity'    => class_basename($record),
            'action'    => $record->action,
            'tenant_id' => $record->tenant_id,
            'payload'   => $payload,
        ]);
    }
}
