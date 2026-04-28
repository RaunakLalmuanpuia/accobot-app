<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\TallyVoucher;
use App\Models\TallyVoucherInventoryEntry;
use App\Models\TallyVoucherLedgerEntry;
use App\Models\Tenant;
use App\Services\Tally\TallyOutboundFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TallyVoucherCrudController extends Controller
{
    public function __construct(private TallyOutboundFormatter $formatter) {}

    private function validationRules(): array
    {
        return [
            // Core
            'voucher_type'    => 'required|string|max:50',
            'voucher_number'  => 'nullable|string|max:100',
            'voucher_date'    => 'required|date',
            'party_name'      => 'nullable|string|max:255',
            'voucher_total'   => 'nullable|numeric',
            'narration'       => 'nullable|string',
            'is_invoice'      => 'boolean',
            'reference'       => 'nullable|string|max:100',
            'reference_date'  => 'nullable|string|max:20',
            'place_of_supply' => 'nullable|string|max:100',
            'cost_centre'     => 'nullable|string|max:255',

            // Dispatch / shipping
            'delivery_note_no'   => 'nullable|string|max:100',
            'delivery_note_date' => 'nullable|string|max:20',
            'dispatch_doc_no'    => 'nullable|string|max:100',
            'dispatch_through'   => 'nullable|string|max:255',
            'destination'        => 'nullable|string|max:255',
            'carrier_name'       => 'nullable|string|max:255',
            'lr_no'              => 'nullable|string|max:100',
            'lr_date'            => 'nullable|string|max:20',
            'motor_vehicle_no'   => 'nullable|string|max:100',

            // Order
            'order_no'          => 'nullable|string|max:100',
            'order_date'        => 'nullable|string|max:20',
            'terms_of_payment'  => 'nullable|string|max:255',
            'terms_of_delivery' => 'nullable|string|max:255',
            'other_references'  => 'nullable|string|max:255',

            // Buyer
            'buyer_name'                  => 'nullable|string|max:255',
            'buyer_alias'                 => 'nullable|string|max:255',
            'buyer_gstin'                 => 'nullable|string|max:20',
            'buyer_pin_code'              => 'nullable|string|max:10',
            'buyer_state'                 => 'nullable|string|max:100',
            'buyer_country'               => 'nullable|string|max:100',
            'buyer_gst_registration_type' => 'nullable|string|max:100',
            'buyer_email'                 => 'nullable|email|max:255',
            'buyer_mobile'                => 'nullable|string|max:20',
            'buyer_address'               => 'nullable|string|max:500',

            // Consignee
            'consignee_name'                  => 'nullable|string|max:255',
            'consignee_gstin'                 => 'nullable|string|max:20',
            'consignee_tally_group'           => 'nullable|string|max:255',
            'consignee_pin_code'              => 'nullable|string|max:10',
            'consignee_state'                 => 'nullable|string|max:100',
            'consignee_country'               => 'nullable|string|max:100',
            'consignee_gst_registration_type' => 'nullable|string|max:100',

            // Ledger entries
            'ledger_entries'                      => 'nullable|array',
            'ledger_entries.*.ledger_name'        => 'required|string|max:255',
            'ledger_entries.*.ledger_group'       => 'nullable|string|max:255',
            'ledger_entries.*.ledger_amount'      => 'required|numeric',
            'ledger_entries.*.is_deemed_positive' => 'boolean',
            'ledger_entries.*.is_party_ledger'    => 'boolean',
            'ledger_entries.*.igst_rate'                          => 'nullable|string|max:20',
            'ledger_entries.*.hsn_code'                           => 'nullable|string|max:20',
            'ledger_entries.*.cess_rate'                          => 'nullable|string|max:20',
            'ledger_entries.*.bills_allocation'                   => 'nullable|array',
            'ledger_entries.*.bills_allocation.*.AgstType'        => 'nullable|string|max:50',
            'ledger_entries.*.bills_allocation.*.Reference'       => 'nullable|string|max:255',
            'ledger_entries.*.bills_allocation.*.CreditPeriod'    => 'nullable|string|max:50',
            'ledger_entries.*.bills_allocation.*.Amount'          => 'nullable|numeric',

            // Inventory entries
            'inventory_entries'                       => 'nullable|array',
            'inventory_entries.*.stock_item_name'     => 'required|string|max:255',
            'inventory_entries.*.item_code'           => 'nullable|string|max:100',
            'inventory_entries.*.group_name'          => 'nullable|string|max:255',
            'inventory_entries.*.hsn_code'            => 'nullable|string|max:20',
            'inventory_entries.*.unit'                => 'nullable|string|max:50',
            'inventory_entries.*.billed_qty'          => 'nullable|numeric|min:0',
            'inventory_entries.*.actual_qty'          => 'nullable|numeric|min:0',
            'inventory_entries.*.rate'                => 'nullable|numeric|min:0',
            'inventory_entries.*.igst_rate'           => 'nullable|numeric|min:0|max:100',
            'inventory_entries.*.cess_rate'           => 'nullable|numeric|min:0|max:100',
            'inventory_entries.*.discount_percent'    => 'nullable|numeric|min:0|max:100',
            'inventory_entries.*.amount'              => 'nullable|numeric',
            'inventory_entries.*.tax_amount'          => 'nullable|numeric',
            'inventory_entries.*.mrp'                 => 'nullable|numeric|min:0',
            'inventory_entries.*.sales_ledger'        => 'nullable|string|max:255',
            'inventory_entries.*.godown_name'         => 'nullable|string|max:255',
            'inventory_entries.*.batch_name'          => 'nullable|string|max:255',
            'inventory_entries.*.is_deemed_positive'  => 'boolean',
        ];
    }

    public function voucherStore(Request $request, Tenant $tenant)
    {
        $data = $request->validate($this->validationRules());

        $voucher = null;

        DB::transaction(function () use ($data, $tenant, &$voucher) {
            $voucher = TallyVoucher::create(array_merge(
                collect($data)->except(['ledger_entries', 'inventory_entries'])->toArray(),
                ['tenant_id' => $tenant->id, 'is_active' => true]
            ));

            foreach ($data['ledger_entries'] ?? [] as $le) {
                TallyVoucherLedgerEntry::create(array_merge($le, [
                    'tenant_id'        => $tenant->id,
                    'tally_voucher_id' => $voucher->id,
                ]));
            }

            foreach ($data['inventory_entries'] ?? [] as $ie) {
                TallyVoucherInventoryEntry::create(array_merge($ie, [
                    'tenant_id'        => $tenant->id,
                    'tally_voucher_id' => $voucher->id,
                ]));
            }
        });

        AuditEvent::log('tally.voucher.created', [
            'id'           => $voucher->id,
            'voucher_type' => $voucher->voucher_type,
            'voucher_date' => $voucher->voucher_date,
            'party_name'   => $voucher->party_name,
            'total'        => $voucher->voucher_total,
        ]);
        $this->logPayload($voucher);
        return back()->with('success', 'Voucher created and queued for Tally sync.');
    }

    public function voucherUpdate(Request $request, Tenant $tenant, TallyVoucher $voucher)
    {
        abort_unless($voucher->tenant_id === $tenant->id, 404);

        $data = $request->validate($this->validationRules());

        DB::transaction(function () use ($data, $tenant, $voucher) {
            $voucher->update(
                collect($data)->except(['ledger_entries', 'inventory_entries'])->toArray()
            );

            $voucher->ledgerEntries()->delete();
            foreach ($data['ledger_entries'] ?? [] as $le) {
                TallyVoucherLedgerEntry::create(array_merge($le, [
                    'tenant_id'        => $tenant->id,
                    'tally_voucher_id' => $voucher->id,
                ]));
            }

            $voucher->inventoryEntries()->delete();
            foreach ($data['inventory_entries'] ?? [] as $ie) {
                TallyVoucherInventoryEntry::create(array_merge($ie, [
                    'tenant_id'        => $tenant->id,
                    'tally_voucher_id' => $voucher->id,
                ]));
            }
        });

        AuditEvent::log('tally.voucher.updated', [
            'id'           => $voucher->id,
            'voucher_type' => $voucher->voucher_type,
        ]);
        $this->logPayload($voucher);
        return back()->with('success', 'Voucher updated and queued for Tally sync.');
    }

    public function voucherDestroy(Tenant $tenant, TallyVoucher $voucher)
    {
        abort_unless($voucher->tenant_id === $tenant->id, 404);

        if (! $voucher->tally_id) {
            DB::table('tally_outbound_queue')
                ->where('tenant_id', $tenant->id)
                ->where('entity_type', TallyVoucher::class)
                ->where('entity_id', $voucher->id)
                ->delete();

            $voucher->ledgerEntries()->delete();
            $voucher->inventoryEntries()->delete();
            $voucher->employeeAllocations()->delete();
            $voucher->delete();
            AuditEvent::log('tally.voucher.deleted', ['id' => $voucher->id, 'voucher_type' => $voucher->voucher_type]);
            return back()->with('success', 'Voucher deleted (was never synced to Tally).');
        }

        $voucher->update(['is_active' => false]);
        AuditEvent::log('tally.voucher.deleted', ['id' => $voucher->id, 'voucher_type' => $voucher->voucher_type, 'deactivated' => true]);
        $this->logPayload($voucher);
        return back()->with('success', 'Voucher marked inactive and queued for deletion in Tally.');
    }

    private function logPayload(TallyVoucher $voucher): void
    {
        $voucher->refresh()->load(['ledgerEntries', 'inventoryEntries', 'employeeAllocations']);
        $collection = collect([$voucher]);

        $payload = match ($voucher->voucher_type) {
            'Sales'       => $this->formatter->formatSalesVouchers($collection),
            'Purchase'    => $this->formatter->formatPurchaseVouchers($collection),
            'Receipt'     => $this->formatter->formatReceiptVouchers($collection),
            'Payment'     => $this->formatter->formatPaymentVouchers($collection),
            'Contra'      => $this->formatter->formatContraVouchers($collection),
            'Journal'     => $this->formatter->formatJournalVouchers($collection),
            'CreditNote'  => $this->formatter->formatCreditNoteVouchers($collection),
            'DebitNote'   => $this->formatter->formatDebitNoteVouchers($collection),
            'Payroll'     => $this->formatter->formatSalaryVouchers($collection),
            'Attendance'  => $this->formatter->formatAttendanceVouchers($collection),
            default       => [],
        };

        Log::info('tally.outbound_preview', [
            'entity'    => 'TallyVoucher',
            'action'    => $voucher->action,
            'tenant_id' => $voucher->tenant_id,
            'payload'   => $payload,
        ]);
    }
}
