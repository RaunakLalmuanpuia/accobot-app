<?php

namespace App\Services\Tally;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\TallyConnection;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallySyncLog;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyStatutoryMaster;
use App\Models\TallyVoucher;
use App\Models\TallyVoucherInventoryEntry;
use App\Models\TallyVoucherLedgerEntry;
use App\Models\Vendor;

class TallyInboundSync
{
    public function syncLedgerGroups(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'ledger_groups');

        try {
            foreach ($items as $raw) {
                $item = $this->strip($raw);
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);

                $existing = TallyLedgerGroup::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)
                    ->first();

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
                    'under_id'         => isset($item['UnderID']) ? (int) $item['UnderID'] : null,
                    'under_name'       => $item['UnderName'] ?? null,
                    'nature_of_group'  => $item['NatureOfGroup'] ?? null,
                    'is_revenue'       => $this->parseBool($item['IsRevenue'] ?? null),
                    'affects_gross'    => $this->parseBool($item['AffectsGross'] ?? null),
                    'is_addable'       => $this->parseBool($item['IsAddable'] ?? null),
                    'is_active'        => true,
                    'last_synced_at'   => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyLedgerGroup::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncLedgers(TallyConnection $conn, array $items, bool $fullSync = false): TallySyncLog
    {
        $log = $this->startLog($conn, 'ledgers');

        try {
            $seenIds = [];

            foreach (array_chunk($items, 250) as $chunk) {
            foreach ($chunk as $raw) {
                $item = $this->strip($raw);
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $seenIds[] = $tallyId;
                $alterId = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);

                $existing = TallyLedger::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)
                    ->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $groupName  = $item['GroupName'] ?? null;
                $parentGroup = $item['ParentGroup'] ?? null;
                $category   = $this->deriveCategory($groupName, $parentGroup);

                $data = [
                    'tenant_id'                  => $conn->tenant_id,
                    'tally_id'                   => $tallyId,
                    'alter_id'                   => $alterId,
                    'action'                     => $action,
                    'ledger_name'                => $item['LedgerName'] ?? ($item['Name'] ?? ''),
                    'group_name'                 => $groupName,
                    'parent_group'               => $parentGroup,
                    'ledger_category'            => $category,
                    'is_bill_wise_on'            => $this->parseBool($item['IsBillWiseOn'] ?? null),
                    'inventory_affected'         => $this->parseBool($item['InventoryAffected'] ?? null),
                    'is_cost_centre_applicable'  => $this->parseBool($item['IsCostCentreApplicable'] ?? null),
                    'gstin_number'               => $item['GSTINNumber'] ?? null,
                    'pan_number'                 => $item['PANNumber'] ?? null,
                    'tan_number'                 => $item['TANNumber'] ?? null,
                    'gst_type'                   => $item['GSTType'] ?? null,
                    'is_rcm_applicable'          => $this->parseBool($item['IsRCMApplicable'] ?? null),
                    'mailing_name'               => $item['MailingName'] ?? null,
                    'mobile_number'              => $item['MobileNumber'] ?? null,
                    'contact_person'             => $item['ContactPerson'] ?? null,
                    'contact_person_email'       => $item['ContactPersonEmail'] ?? null,
                    'contact_person_email_cc'    => $item['ContactPersonEmailCC'] ?? null,
                    'contact_person_fax'         => $item['ContactPersonFax'] ?? null,
                    'contact_person_website'     => $item['ContactPersonWebsite'] ?? null,
                    'contact_person_mobile'      => $item['ContactPersonMobile'] ?? null,
                    'addresses'                  => $item['Addresses'] ?? null,
                    'state_name'                 => $item['StateName'] ?? null,
                    'country_name'               => $item['CountryName'] ?? null,
                    'pin_code'                   => $item['PinCode'] ?? null,
                    'credit_period'              => isset($item['CreditPeriod']) ? (int) $item['CreditPeriod'] : null,
                    'credit_limit'               => isset($item['CreditLimit']) ? (float) $item['CreditLimit'] : null,
                    'opening_balance'            => isset($item['OpeningBalance']) ? (float) $item['OpeningBalance'] : null,
                    'opening_balance_type'       => $item['OpeningBalanceType'] ?? null,
                    'bank_details'               => $item['BankDetails'] ?? null,
                    'aliases'                    => $item['Aliases'] ?? null,
                    'description'                => $item['Description'] ?? null,
                    'notes'                      => $item['Notes'] ?? null,
                    'is_active'                  => true,
                    'last_synced_at'             => now(),
                ];

                $ledger = null;
                if ($existing) {
                    $existing->update($data);
                    $ledger = $existing->fresh();
                    $log->records_updated++;
                } else {
                    $ledger = TallyLedger::create($data);
                    $log->records_created++;
                }

                $this->autoMapLedger($ledger, $conn->tenant_id);
            }
            } // end chunk

            if ($fullSync && count($seenIds)) {
                TallyLedger::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->whereNotIn('tally_id', $seenIds)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncStockGroups(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'stock_groups');

        try {
            foreach ($items as $raw) {
                $item = $this->strip($raw);
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyStockGroup::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'            => $conn->tenant_id,
                    'tally_id'             => $tallyId,
                    'alter_id'             => $alterId,
                    'action'               => $action,
                    'name'                 => $item['Name'] ?? '',
                    'parent_id'            => isset($item['ParentID']) ? (int) $item['ParentID'] : null,
                    'parent_name'          => $item['ParentName'] ?? null,
                    'nature_of_group'      => $item['NatureOfGroup'] ?? null,
                    'should_add_quantities'=> $this->parseBool($item['ShouldAddQuantities'] ?? null) ?? false,
                    'is_active'            => true,
                    'last_synced_at'       => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyStockGroup::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncStockCategories(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'stock_categories');

        try {
            foreach ($items as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyStockCategory::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'    => $conn->tenant_id,
                    'tally_id'     => $tallyId,
                    'alter_id'     => $alterId,
                    'action'       => $action,
                    'name'         => $item['Name'] ?? '',
                    'parent_name'  => $item['ParentName'] ?? null,
                    'is_active'    => true,
                    'last_synced_at' => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyStockCategory::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncStockItems(TallyConnection $conn, array $items, bool $fullSync = false): TallySyncLog
    {
        $log = $this->startLog($conn, 'stock_items');

        try {
            $seenIds = [];

            foreach (array_chunk($items, 250) as $chunk) {
            foreach ($chunk as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $seenIds[] = $tallyId;
                $alterId   = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing  = TallyStockItem::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false]); $log->records_updated++; }
                    continue;
                }

                $data = [
                    'tenant_id'               => $conn->tenant_id,
                    'tally_id'                => $tallyId,
                    'alter_id'                => $alterId,
                    'action'                  => $action,
                    'name'                    => $item['Name'] ?? '',
                    'description'             => $item['Description'] ?? null,
                    'remarks'                 => $item['Remarks'] ?? null,
                    'aliases'                 => $item['Aliases'] ?? null,
                    'stock_group_id'          => isset($item['StockGroupID']) ? (int) $item['StockGroupID'] : null,
                    'stock_group_name'        => $item['StockGroupName'] ?? null,
                    'stock_category_id'       => isset($item['StockCategoryID']) ? (int) $item['StockCategoryID'] : null,
                    'category_name'           => $item['CategoryName'] ?? null,
                    'unit_id'                 => isset($item['UnitID']) ? (int) $item['UnitID'] : null,
                    'unit_name'               => $item['UnitName'] ?? null,
                    'alternate_unit'          => $item['AlternateUnit'] ?? null,
                    'conversion'              => isset($item['Conversion']) ? (float) $item['Conversion'] : null,
                    'denominator'             => isset($item['Denominator']) ? (int) $item['Denominator'] : 1,
                    'is_gst_applicable'       => $this->parseBool($item['IsGSTApplicable'] ?? null) ?? false,
                    'taxability'              => $item['Taxability'] ?? null,
                    'calculation_type'        => $item['CalculationType'] ?? null,
                    'igst_rate'               => (float) ($item['IGSTRate'] ?? 0),
                    'sgst_rate'               => (float) ($item['SGSTRate'] ?? 0),
                    'cgst_rate'               => (float) ($item['CGSTRate'] ?? 0),
                    'cess_rate'               => (float) ($item['CessRate'] ?? 0),
                    'hsn_code'                => $item['HSNCode'] ?? null,
                    'mrp_rate'                => isset($item['MRPRate']) ? (float) $item['MRPRate'] : null,
                    'standard_cost'           => isset($item['StandardCost']) ? (float) $item['StandardCost'] : null,
                    'standard_price'          => isset($item['StandardPrice']) ? (float) $item['StandardPrice'] : null,
                    'opening_balance'         => (float) ($item['OpeningBalance'] ?? 0),
                    'opening_rate'            => (float) ($item['OpeningRate'] ?? 0),
                    'opening_value'           => (float) ($item['OpeningValue'] ?? 0),
                    'closing_balance'         => (float) ($item['ClosingBalance'] ?? 0),
                    'closing_rate'            => (float) ($item['ClosingRate'] ?? 0),
                    'closing_value'           => (float) ($item['ClosingValue'] ?? 0),
                    'costing_method'          => $item['CostingMethod'] ?? null,
                    'is_batch_applicable'     => $this->parseBool($item['IsBatchApplicable'] ?? null) ?? false,
                    'is_expiry_date_applicable' => $this->parseBool($item['IsExpiryDateApplicable'] ?? null) ?? false,
                    'reorder_level'           => isset($item['ReorderLevel']) ? (float) $item['ReorderLevel'] : null,
                    'reorder_quantity'        => isset($item['ReorderQuantity']) ? (float) $item['ReorderQuantity'] : null,
                    'maximum_quantity'        => isset($item['MaximumQuantity']) ? (float) $item['MaximumQuantity'] : null,
                    'batch_allocations'       => $item['BatchAllocations'] ?? null,
                    'is_active'               => true,
                    'last_synced_at'          => now(),
                ];

                $stockItem = null;
                if ($existing) {
                    $existing->update($data);
                    $stockItem = $existing->fresh();
                    $log->records_updated++;
                } else {
                    $stockItem = TallyStockItem::create($data);
                    $log->records_created++;
                }

                $this->autoMapStockItem($stockItem, $conn->tenant_id);
            }
            } // end chunk

            if ($fullSync && count($seenIds)) {
                TallyStockItem::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->whereNotIn('tally_id', $seenIds)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncVouchers(TallyConnection $conn, array $items, string $type, bool $fullSync = false): TallySyncLog
    {
        $entity = 'vouchers_' . strtolower(str_replace(' ', '_', $type));
        $log    = $this->startLog($conn, $entity);

        try {
            $seenIds = [];

            foreach (array_chunk($items, 250) as $chunk) {
            foreach ($chunk as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['MasterID'] ?? $item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $seenIds[] = $tallyId;
                $alterId   = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing  = TallyVoucher::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('tally_id', $tallyId)->first();

                if ($existing && $existing->alter_id === $alterId) { $log->records_skipped++; continue; }

                $action = $item['Action'] ?? 'Create';
                if ($action === 'Delete') {
                    if ($existing) { $existing->update(['is_active' => false, 'is_deleted' => true]); $log->records_updated++; }
                    continue;
                }

                // Resolve party ledger FK
                $partyLedgerId = null;
                if (!empty($item['PartyName'])) {
                    $partyLedger = TallyLedger::withoutGlobalScope('tenant')
                        ->where('tenant_id', $conn->tenant_id)
                        ->where('ledger_name', $item['PartyName'])
                        ->value('id');
                    $partyLedgerId = $partyLedger;
                }

                $voucherData = [
                    'tenant_id'               => $conn->tenant_id,
                    'tally_id'                => $tallyId,
                    'alter_id'                => $alterId,
                    'action'                  => $action,
                    'voucher_type'            => $type,
                    'voucher_number'          => $item['VoucherNumber'] ?? null,
                    'voucher_date'            => $this->parseDate($item['VoucherDate'] ?? null),
                    'reference'               => $item['Reference'] ?? null,
                    'reference_date'          => $item['ReferenceDate'] ?? null,
                    'party_name'              => $item['PartyName'] ?? null,
                    'party_tally_ledger_id'   => $partyLedgerId,
                    'voucher_total'           => isset($item['VoucherTotal']) ? (float) $item['VoucherTotal'] : null,
                    'is_invoice'              => $this->parseBool($item['IsInvoice'] ?? null) ?? false,
                    'is_deleted'              => false,
                    'place_of_supply'         => $item['PlaceOfSupply'] ?? null,
                    'delivery_note_no'        => $item['DeliveryNoteNo'] ?? null,
                    'delivery_note_date'      => $item['DeliveryNoteDate'] ?? null,
                    'dispatch_doc_no'         => $item['DispatchDocNo'] ?? null,
                    'dispatch_through'        => $item['DispatchThrough'] ?? null,
                    'destination'             => $item['Destination'] ?? null,
                    'carrier_name'            => $item['CarrierName'] ?? null,
                    'lr_no'                   => $item['LRNo'] ?? null,
                    'lr_date'                 => $item['LRDate'] ?? null,
                    'motor_vehicle_no'        => $item['MotorVehicleNo'] ?? null,
                    'order_no'                => $item['OrderNo'] ?? null,
                    'order_date'              => $item['OrderDate'] ?? null,
                    'terms_of_payment'        => $item['TermsOfPayment'] ?? null,
                    'terms_of_delivery'       => $item['TermsOfDelivery'] ?? null,
                    'other_references'        => $item['OtherReferences'] ?? null,
                    'buyer_name'              => $item['BuyerName'] ?? null,
                    'buyer_alias'             => $item['BuyerAlias'] ?? null,
                    'buyer_gstin'             => $item['BuyerGSTIN'] ?? null,
                    'buyer_pin_code'          => $item['BuyerPinCode'] ?? null,
                    'buyer_state'             => $item['BuyerState'] ?? null,
                    'buyer_country'           => $item['BuyerCountry'] ?? null,
                    'buyer_gst_registration_type' => $item['BuyerGSTRegistrationType'] ?? null,
                    'buyer_email'             => $item['BuyerEmail'] ?? null,
                    'buyer_mobile'            => $item['BuyerMobile'] ?? null,
                    'buyer_address'           => $item['BuyerAddress'] ?? null,
                    'consignee_name'          => $item['ConsigneeName'] ?? null,
                    'consignee_gstin'         => $item['ConsigneeGSTIN'] ?? null,
                    'consignee_tally_group'   => $item['ConsigneeTallyGroup'] ?? null,
                    'consignee_pin_code'      => $item['ConsigneePinCode'] ?? null,
                    'consignee_state'         => $item['ConsigneeState'] ?? null,
                    'consignee_country'       => $item['ConsigneeCountry'] ?? null,
                    'consignee_gst_registration_type' => $item['ConsigneeGSTRegistrationType'] ?? null,
                    'irn'                     => $item['IRN'] ?? null,
                    'acknowledgement_no'      => $item['AcknowledgementNo'] ?? null,
                    'acknowledgement_date'    => $item['AcknowledgementDate'] ?? null,
                    'qr_code'                 => $item['QRCode'] ?? null,
                    'narration'               => $item['Narration'] ?? null,
                    'cost_centre'             => $item['CostCentre'] ?? null,
                    'is_active'               => true,
                    'last_synced_at'          => now(),
                ];

                // Wrap voucher upsert + child entries atomically
                $voucher = null;
                try {
                    DB::transaction(function () use ($existing, $voucherData, $item, $conn, &$log, &$voucher) {
                        if ($existing) {
                            $existing->update($voucherData);
                            $voucher = $existing->fresh();
                            $log->records_updated++;
                        } else {
                            $voucher = TallyVoucher::create($voucherData);
                            $log->records_created++;
                        }

                        // Re-insert child entries
                        TallyVoucherInventoryEntry::withoutGlobalScope('tenant')->where('tally_voucher_id', $voucher->id)->delete();
                        TallyVoucherLedgerEntry::withoutGlobalScope('tenant')->where('tally_voucher_id', $voucher->id)->delete();

                        foreach ($item['InventoryEntries'] ?? [] as $ie) {
                            $ie = $this->strip($ie);
                            $stockItemId = null;
                            if (!empty($ie['StockItemName'])) {
                                $stockItem = TallyStockItem::withoutGlobalScope('tenant')
                                    ->where('tenant_id', $conn->tenant_id)
                                    ->where('name', $ie['StockItemName'])
                                    ->first();

                                if (!$stockItem) {
                                    $product = Product::withoutGlobalScope('tenant')
                                        ->updateOrCreate(
                                            ['name' => $ie['StockItemName'], 'tenant_id' => $conn->tenant_id],
                                            [
                                                'unit'       => $ie['Unit'] ?? 'pcs',
                                                'unit_price' => (float) ($ie['Rate'] ?? 0),
                                                'tax_rate'   => (float) ($ie['IGSTRate'] ?? 0),
                                                'is_active'  => true,
                                                'tenant_id'  => $conn->tenant_id,
                                            ]
                                        );
                                    $stockItemId = null; // no TallyStockItem row yet; will link when masters sync
                                } else {
                                    $stockItemId = $stockItem->id;
                                }
                            }
                            TallyVoucherInventoryEntry::create([
                                'tenant_id'          => $conn->tenant_id,
                                'tally_voucher_id'   => $voucher->id,
                                'tally_stock_item_id'=> $stockItemId,
                                'stock_item_name'    => $ie['StockItemName'] ?? null,
                                'item_code'          => $ie['ItemCode'] ?? null,
                                'group_name'         => $ie['GroupName'] ?? null,
                                'hsn_code'           => $ie['HSNCode'] ?? null,
                                'unit'               => $ie['Unit'] ?? null,
                                'igst_rate'          => isset($ie['IGSTRate']) ? (float) $ie['IGSTRate'] : null,
                                'cess_rate'          => isset($ie['CessRate']) ? (float) $ie['CessRate'] : null,
                                'is_deemed_positive' => $this->parseBool($ie['IsDeemedPositive'] ?? null) ?? false,
                                'actual_qty'         => (float) ($ie['ActualQty'] ?? 0),
                                'billed_qty'         => (float) ($ie['BilledQty'] ?? 0),
                                'rate'               => (float) ($ie['Rate'] ?? 0),
                                'discount_percent'   => (float) ($ie['DiscountPercent'] ?? 0),
                                'amount'             => (float) ($ie['Amount'] ?? 0),
                                'tax_amount'         => (float) ($ie['TaxAmount'] ?? 0),
                                'mrp'                => isset($ie['MRP']) ? (float) $ie['MRP'] : null,
                                'sales_ledger'       => $ie['SalesLedger'] ?? null,
                                'godown_name'        => $ie['GodownName'] ?? null,
                                'batch_name'         => $ie['BatchName'] ?? null,
                                'batch_allocations'  => $ie['BatchAllocations'] ?? null,
                                'accounting_allocations' => $ie['AccountingAllocations'] ?? null,
                            ]);
                        }

                        foreach ($item['LedgerEntries'] ?? [] as $le) {
                            $le = $this->strip($le);
                            $ledgerId = null;
                            if (!empty($le['LedgerName'])) {
                                $ledgerId = TallyLedger::withoutGlobalScope('tenant')
                                    ->where('tenant_id', $conn->tenant_id)
                                    ->where('ledger_name', $le['LedgerName'])
                                    ->value('id');
                            }
                            TallyVoucherLedgerEntry::create([
                                'tenant_id'          => $conn->tenant_id,
                                'tally_voucher_id'   => $voucher->id,
                                'tally_ledger_id'    => $ledgerId,
                                'ledger_name'        => $le['LedgerName'] ?? null,
                                'ledger_group'       => $le['LedgerGroup'] ?? null,
                                'ledger_amount'      => (float) ($le['LedgerAmount'] ?? 0),
                                'is_deemed_positive' => $this->parseBool($le['IsDeemedPositive'] ?? null) ?? false,
                                'is_party_ledger'    => $this->parseBool($le['IsPartyLedger'] ?? null) ?? false,
                                'igst_rate'          => $le['IGSTRate'] ?? null,
                                'hsn_code'           => $le['HSNCode'] ?? null,
                                'cess_rate'          => $le['CessRate'] ?? null,
                                'bills_allocation'   => $le['BillsAllocation'] ?? null,
                            ]);
                        }
                    });
                } catch (\Throwable $e) {
                    $log->records_failed++;
                    Log::warning("Tally voucher sync failed for tally_id={$tallyId}: " . $e->getMessage());
                    continue;
                }

                // Auto-map Sales vouchers to Invoice — outside transaction, best-effort
                if ($voucher && $type === 'Sales') {
                    $this->autoMapSalesVoucher($voucher, $conn->tenant_id);
                }
            }
            } // end chunk

            if ($fullSync && count($seenIds)) {
                TallyVoucher::withoutGlobalScope('tenant')
                    ->where('tenant_id', $conn->tenant_id)
                    ->where('voucher_type', $type)
                    ->whereNotIn('tally_id', $seenIds)
                    ->update(['is_active' => false]);
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    public function syncStatutoryMasters(TallyConnection $conn, array $items): TallySyncLog
    {
        $log = $this->startLog($conn, 'statutory_masters');

        try {
            foreach ($items as $raw) {
                $item    = $this->strip($raw);
                $tallyId = (int) ($item['ID'] ?? $item['Id'] ?? 0);
                if (!$tallyId) { $log->records_failed++; continue; }

                $alterId  = (int) ($item['AlterID'] ?? $item['AlterId'] ?? 0);
                $existing = TallyStatutoryMaster::withoutGlobalScope('tenant')
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
                    'statutory_type'      => $item['StatutoryType'] ?? null,
                    'registration_number' => $item['RegistrationNumber'] ?? $item['GSTIN'] ?? null,
                    'state_code'          => $item['StateCode'] ?? null,
                    'registration_type'   => $item['RegistrationType'] ?? null,
                    'pan'                 => $item['PAN'] ?? null,
                    'tan'                 => $item['TAN'] ?? null,
                    'applicable_from'     => $this->parseDate($item['ApplicableFrom'] ?? null),
                    'details'             => array_diff_key($item, array_flip([
                        'ID', 'Id', 'AlterID', 'AlterId', 'Action', 'Name',
                        'StatutoryType', 'RegistrationNumber', 'GSTIN',
                        'StateCode', 'RegistrationType', 'PAN', 'TAN', 'ApplicableFrom',
                    ])),
                    'is_active'           => true,
                    'last_synced_at'      => now(),
                ];

                if ($existing) { $existing->update($data); $log->records_updated++; }
                else { TallyStatutoryMaster::create($data); $log->records_created++; }
            }
        } catch (\Throwable $e) {
            return $this->failLog($log, $e->getMessage());
        }

        return $this->completeLog($log, $conn);
    }

    // ── Private ────────────────────────────────────────────────────────────────

    use TallySyncHelpers;

    private function parseBool(mixed $v): ?bool
    {
        if (is_null($v))    return null;
        if (is_bool($v))    return $v;
        if ($v === 'Yes' || $v === 'true' || $v === '1') return true;
        if ($v === 'No'  || $v === 'false'|| $v === '0') return false;
        return null;
    }

    private function deriveCategory(?string $groupName, ?string $parentGroup): string
    {
        $combined = strtolower(($groupName ?? '') . ' ' . ($parentGroup ?? ''));

        if (str_contains($combined, 'debtor'))                                          return 'customer';
        if (str_contains($combined, 'creditor') || str_contains($combined, 'supplier')) return 'vendor';
        if (str_contains($combined, 'bank') || str_contains($combined, 'cash-in-hand')
            || str_contains($combined, 'cash'))                                         return 'bank';
        // tax before income — "duties & taxes" must not be caught by income
        if (str_contains($combined, 'duties & taxes') || str_contains($combined, 'gst')
            || str_contains($combined, 'tds') || str_contains($combined, 'tcs'))        return 'tax';
        if (str_contains($combined, 'sales') || str_contains($combined, 'income'))      return 'income';
        if (str_contains($combined, 'expense'))                                         return 'expense';
        if (str_contains($combined, 'fixed assets') || str_contains($combined, 'current assets')
            || str_contains($combined, 'investments'))                                  return 'asset';
        if (str_contains($combined, 'loans') || str_contains($combined, 'capital')
            || str_contains($combined, 'current liabilities') || str_contains($combined, 'provisions')) return 'liability';

        return 'other';
    }

    private function autoMapLedger(TallyLedger $ledger, string $tenantId): void
    {
        $canonicalName = $ledger->mailing_name ?? $ledger->ledger_name;
        $fields = [
            'name'      => $canonicalName,
            'email'     => $ledger->contact_person_email,
            'phone'     => $ledger->mobile_number ?? $ledger->contact_person_mobile,
            'company'   => $ledger->ledger_name,
            'tax_id'    => $ledger->gstin_number ?? $ledger->pan_number,
            'tenant_id' => $tenantId,
        ];

        if ($ledger->ledger_category === 'customer') {
            $client = Client::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('tally_ledger_id', $ledger->id)
                ->first();

            if (!$client) {
                // Claim placeholder created from a voucher if names match
                $client = Client::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('tally_ledger_id')
                    ->where('name', $canonicalName)
                    ->first();
            }

            if ($client) {
                $client->update(array_merge($fields, ['tally_ledger_id' => $ledger->id]));
            } else {
                $client = Client::create(array_merge($fields, ['tally_ledger_id' => $ledger->id]));
            }

            $ledger->update(['mapped_client_id' => $client->id]);
        } elseif ($ledger->ledger_category === 'vendor') {
            $vendor = Vendor::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('tally_ledger_id', $ledger->id)
                ->first();

            if (!$vendor) {
                $vendor = Vendor::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('tally_ledger_id')
                    ->where('name', $canonicalName)
                    ->first();
            }

            if ($vendor) {
                $vendor->update(array_merge($fields, ['tally_ledger_id' => $ledger->id]));
            } else {
                $vendor = Vendor::create(array_merge($fields, ['tally_ledger_id' => $ledger->id]));
            }

            $ledger->update(['mapped_vendor_id' => $vendor->id]);
        }
    }

    private function autoMapStockItem(TallyStockItem $item, string $tenantId): void
    {
        $fields = [
            'name'        => $item->name,
            'description' => $item->description,
            'unit'        => $item->unit_name ?? 'pcs',
            'unit_price'  => $item->standard_price ?? $item->standard_cost ?? 0,
            'tax_rate'    => $item->igst_rate ?? 0,
            'is_active'   => true,
            'tenant_id'   => $tenantId,
        ];

        $product = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('tally_stock_item_id', $item->id)
            ->first();

        if (!$product) {
            // Claim placeholder created from a voucher inventory entry if names match
            $product = Product::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->whereNull('tally_stock_item_id')
                ->where('name', $item->name)
                ->first();
        }

        if ($product) {
            $product->update(array_merge($fields, ['tally_stock_item_id' => $item->id]));
        } else {
            $product = Product::create(array_merge($fields, ['tally_stock_item_id' => $item->id]));
        }
        $item->update(['mapped_product_id' => $product->id]);
    }

    private function autoMapSalesVoucher(TallyVoucher $voucher, string $tenantId): void
    {
        if (!$voucher->voucher_date || !$voucher->voucher_total) return;

        // Resolve client via mapped party ledger
        $clientId = null;
        if ($voucher->party_tally_ledger_id) {
            $clientId = Client::withoutGlobalScope('tenant')
                ->where('tally_ledger_id', $voucher->party_tally_ledger_id)
                ->where('tenant_id', $tenantId)
                ->value('id');
        }

        if (!$clientId) {
            // Party ledger not yet synced — create a placeholder Client from voucher buyer fields.
            // When the ledger syncs later, autoMapLedger() will updateOrCreate on tally_ledger_id and link up.
            $partyName = $voucher->buyer_name ?? $voucher->party_name;
            if (!$partyName) return;

            $client = Client::withoutGlobalScope('tenant')
                ->updateOrCreate(
                    ['name' => $partyName, 'tenant_id' => $tenantId],
                    [
                        'email'     => $voucher->buyer_email,
                        'phone'     => $voucher->buyer_mobile,
                        'company'   => $voucher->party_name,
                        'tax_id'    => $voucher->buyer_gstin,
                        'address'   => $voucher->buyer_address,
                        'tenant_id' => $tenantId,
                    ]
                );
            $clientId = $client->id;
        }

        $invoice = Invoice::withoutGlobalScope('tenant')
            ->updateOrCreate(
                ['tally_voucher_id' => $voucher->id, 'tenant_id' => $tenantId],
                [
                    'tenant_id'      => $tenantId,
                    'client_id'      => $clientId,
                    'invoice_number' => $voucher->voucher_number ?? \App\Models\Invoice::generateNumber($tenantId),
                    'issue_date'     => $voucher->voucher_date,
                    'due_date'       => $voucher->voucher_date,
                    'status'         => 'unpaid',
                    'subtotal'       => $voucher->voucher_total,
                    'tax_amount'     => 0,
                    'total'          => $voucher->voucher_total,
                    'currency'       => 'INR',
                    'notes'          => $voucher->narration,
                    'amount_paid'    => 0,
                    'amount_due'     => $voucher->voucher_total,
                ]
            );

        $voucher->update(['mapped_invoice_id' => $invoice->id]);
    }
}
