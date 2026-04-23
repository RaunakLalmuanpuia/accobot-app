<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TallyLedger;
use App\Models\TallyStockItem;
use App\Models\TallyVoucher;
use App\Models\Vendor;
use App\Services\Tally\TallyInboundSync;
use App\Services\Tally\TallyOutboundQueueService;
use Illuminate\Database\Eloquent\Model;

class TallyAccobotObserver
{
    public function __construct(private TallyOutboundQueueService $queue) {}

    public function created(Model $model): void
    {
        if (TallyInboundSync::$syncing) {
            return;
        }

        $this->ensureStubAndQueue($model);
    }

    public function updated(Model $model): void
    {
        if (TallyInboundSync::$syncing) {
            return;
        }

        $this->ensureStubAndQueue($model);
    }

    private function ensureStubAndQueue(Model $model): void
    {
        match (true) {
            $model instanceof Client  => $this->handleClient($model),
            $model instanceof Vendor  => $this->handleVendor($model),
            $model instanceof Product => $this->handleProduct($model),
            $model instanceof Invoice => $this->handleInvoice($model),
            default                   => null,
        };
    }

    private function handleClient(Client $client): void
    {
        if ($client->tally_ledger_id) {
            $this->queue->queue((int) $client->tenant_id, TallyLedger::class, (int) $client->tally_ledger_id);
            return;
        }

        $ledger = TallyLedger::create([
            'tenant_id'        => $client->tenant_id,
            'ledger_name'      => $client->name,
            'group_name'       => 'Sundry Debtors',
            'mapped_client_id' => $client->id,
            'is_active'        => true,
            'action'           => 'Create',
        ]);

        $client->updateQuietly(['tally_ledger_id' => $ledger->id]);
        // TallyModelObserver fires on $ledger->create() and queues it
    }

    private function handleVendor(Vendor $vendor): void
    {
        if ($vendor->tally_ledger_id) {
            $this->queue->queue((int) $vendor->tenant_id, TallyLedger::class, (int) $vendor->tally_ledger_id);
            return;
        }

        $ledger = TallyLedger::create([
            'tenant_id'        => $vendor->tenant_id,
            'ledger_name'      => $vendor->name,
            'group_name'       => 'Sundry Creditors',
            'mapped_vendor_id' => $vendor->id,
            'is_active'        => true,
            'action'           => 'Create',
        ]);

        $vendor->updateQuietly(['tally_ledger_id' => $ledger->id]);
    }

    private function handleProduct(Product $product): void
    {
        if ($product->tally_stock_item_id) {
            $this->queue->queue((int) $product->tenant_id, TallyStockItem::class, (int) $product->tally_stock_item_id);
            return;
        }

        $item = TallyStockItem::create([
            'tenant_id'         => $product->tenant_id,
            'name'              => $product->name,
            'unit_name'         => $product->unit,
            'is_active'         => true,
            'action'            => 'Create',
            'mapped_product_id' => $product->id,
        ]);

        $product->updateQuietly(['tally_stock_item_id' => $item->id]);
    }

    private function handleInvoice(Invoice $invoice): void
    {
        if ($invoice->tally_voucher_id) {
            $this->queue->queue((int) $invoice->tenant_id, TallyVoucher::class, (int) $invoice->tally_voucher_id);
            return;
        }

        $voucher = TallyVoucher::create([
            'tenant_id'         => $invoice->tenant_id,
            'voucher_type'      => 'Sales',
            'voucher_number'    => $invoice->invoice_number,
            'voucher_date'      => $invoice->issue_date,
            'party_name'        => $invoice->client?->name,
            'voucher_total'     => $invoice->total,
            'is_invoice'        => true,
            'is_active'         => true,
            'action'            => 'Create',
            'mapped_invoice_id' => $invoice->id,
        ]);

        $invoice->updateQuietly(['tally_voucher_id' => $voucher->id]);
    }
}
