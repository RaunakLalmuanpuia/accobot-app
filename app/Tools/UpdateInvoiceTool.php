<?php

namespace App\Tools;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateInvoiceTool implements Tool
{
    private ?array $lastInvoice = null;

    public function description(): Stringable|string
    {
        return 'Edit an existing invoice. Can update the due date, status, notes, or currency. '
            . 'Can also add new line items, remove existing items by their ID, or update item details. '
            . 'Use GetInvoiceTool first to see the current invoice and item IDs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier'      => $schema->string()->description('Invoice number (e.g. INV-00001) or numeric ID')->required(),
            'due_date'        => $schema->string()->description('New due date in YYYY-MM-DD format. Pass null to leave unchanged.')->nullable()->required(),
            'status'          => $schema->string()->description('New status: draft, sent, paid, overdue, cancelled. Pass null to leave unchanged.')->nullable()->required(),
            'notes'           => $schema->string()->description('New notes or payment terms. Pass null to leave unchanged.')->nullable()->required(),
            'currency'        => $schema->string()->description('New currency code. Pass null to leave unchanged.')->nullable()->required(),
            'add_items'       => $schema->array()
                ->description('New line items to add. Pass null or empty array if not adding items.')
                ->items($schema->object([
                    'product_id'  => $schema->integer()->description('Product ID. Pass null for custom line item.')->nullable()->required(),
                    'description' => $schema->string()->description('Line item description. Pass null to use product name.')->nullable()->required(),
                    'quantity'    => $schema->number()->description('Quantity')->required(),
                    'unit_price'  => $schema->number()->description('Price per unit. Pass null to use product price.')->nullable()->required(),
                    'unit'        => $schema->string()->description('Unit of measure. Pass null to use product unit.')->nullable()->required(),
                    'tax_rate'    => $schema->number()->description('Tax rate percentage. Pass null to use product tax rate.')->nullable()->required(),
                ]))
                ->nullable()->required(),
            'remove_item_ids' => $schema->array()
                ->description('Array of invoice item IDs to remove. Pass null or empty array if not removing items.')
                ->items($schema->integer()->description('Invoice item ID'))
                ->nullable()->required(),
            'update_items'    => $schema->array()
                ->description('Existing line items to update. Pass null or empty array if not updating items.')
                ->items($schema->object([
                    'id'          => $schema->integer()->description('The invoice item ID to update')->required(),
                    'description' => $schema->string()->description('New description. Pass null to leave unchanged.')->nullable()->required(),
                    'quantity'    => $schema->number()->description('New quantity. Pass null to leave unchanged.')->nullable()->required(),
                    'unit_price'  => $schema->number()->description('New unit price. Pass null to leave unchanged.')->nullable()->required(),
                    'unit'        => $schema->string()->description('New unit. Pass null to leave unchanged.')->nullable()->required(),
                    'tax_rate'    => $schema->number()->description('New tax rate. Pass null to leave unchanged.')->nullable()->required(),
                ]))
                ->nullable()->required(),
        ];
    }

    public function getLastInvoice(): ?array { return $this->lastInvoice; }

    public function handle(Request $request): Stringable|string
    {
        $identifier  = (string) $request->string('identifier');
        $newDueDate  = $this->nullableString($request['due_date'] ?? null);
        $newStatus   = $this->nullableString($request['status'] ?? null);
        $newNotes    = $this->nullableString($request['notes'] ?? null);
        $newCurrency = $this->nullableString($request['currency'] ?? null);
        $addItems    = is_array($request['add_items'] ?? null) ? $request['add_items'] : [];
        $removeIds   = is_array($request['remove_item_ids'] ?? null) ? $request['remove_item_ids'] : [];
        $updateItems = is_array($request['update_items'] ?? null) ? $request['update_items'] : [];
        $tid         = request()->route('tenant')?->id;

        Log::info('UpdateInvoiceTool', ['identifier' => $identifier]);

        try {
            $invoice = is_numeric($identifier)
                ? Invoice::with('items.product', 'client')->where('tenant_id', $tid)->find((int) $identifier)
                : Invoice::with('items.product', 'client')->where('tenant_id', $tid)->where('invoice_number', $identifier)->first();

            if (! $invoice) {
                return "Invoice \"{$identifier}\" not found.";
            }

            DB::transaction(function () use ($invoice, $newDueDate, $newStatus, $newNotes, $newCurrency, $addItems, $removeIds, $updateItems) {
                $headerChanges = [];
                if ($newDueDate !== null)  $headerChanges['due_date'] = $newDueDate;
                if ($newStatus !== null && in_array($newStatus, ['draft', 'sent', 'paid', 'overdue', 'cancelled'], true))
                    $headerChanges['status'] = $newStatus;
                if ($newNotes !== null)    $headerChanges['notes']    = $newNotes;
                if ($newCurrency !== null) $headerChanges['currency'] = strtoupper($newCurrency);
                if ($headerChanges)        $invoice->update($headerChanges);

                if ($removeIds) {
                    InvoiceItem::where('invoice_id', $invoice->id)->whereIn('id', $removeIds)->delete();
                }

                foreach ($updateItems as $itemData) {
                    $item = InvoiceItem::where('invoice_id', $invoice->id)->find($itemData['id'] ?? null);
                    if (! $item) continue;
                    $changes = [];
                    foreach (['description', 'unit'] as $f) {
                        if (isset($itemData[$f]) && $itemData[$f] !== null && $itemData[$f] !== 'null')
                            $changes[$f] = $itemData[$f];
                    }
                    foreach (['quantity', 'unit_price', 'tax_rate'] as $f) {
                        if (isset($itemData[$f]) && $itemData[$f] !== null && $itemData[$f] !== 'null')
                            $changes[$f] = (float) $itemData[$f];
                    }
                    if ($changes) {
                        $item->fill($changes);
                        $lineTotal        = round($item->quantity * $item->unit_price, 2);
                        $item->tax_amount = round($lineTotal * ($item->tax_rate / 100), 2);
                        $item->total      = $lineTotal + $item->tax_amount;
                        $item->save();
                    }
                }

                foreach ($addItems as $itemData) {
                    $product   = isset($itemData['product_id']) ? Product::where('tenant_id', $invoice->tenant_id)->find($itemData['product_id']) : null;
                    $qty       = (float) ($itemData['quantity'] ?? 1);
                    $unitPrice = (float) ($itemData['unit_price'] ?? ($product?->unit_price ?? 0));
                    $taxRate   = (float) ($itemData['tax_rate'] ?? ($product?->tax_rate ?? 0));
                    $desc      = $itemData['description'] ?? ($product?->name ?? 'Service');
                    $unit      = $itemData['unit'] ?? ($product?->unit ?? 'unit');
                    $lineTotal = round($qty * $unitPrice, 2);
                    $lineTax   = round($lineTotal * ($taxRate / 100), 2);
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id, 'product_id' => $product?->id,
                        'description' => $desc, 'unit' => $unit, 'quantity' => $qty,
                        'unit_price' => $unitPrice, 'tax_rate' => $taxRate,
                        'tax_amount' => $lineTax, 'total' => $lineTotal + $lineTax,
                    ]);
                }

                $invoice->load('items');
                $subtotal  = $invoice->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price);
                $taxAmount = $invoice->items->sum(fn ($i) => (float) $i->tax_amount);
                $invoice->update([
                    'subtotal'   => round($subtotal, 2),
                    'tax_amount' => round($taxAmount, 2),
                    'total'      => round($subtotal + $taxAmount, 2),
                ]);
            });

            $invoice->load('items', 'client');
            $downloadUrl = route('invoices.download', ['tenant' => $tid, 'invoice' => $invoice->id]);

            $this->lastInvoice = [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client'         => $invoice->client->name,
                'due_date'       => $invoice->due_date,
                'status'         => $invoice->status,
                'subtotal'       => $invoice->subtotal,
                'tax_amount'     => $invoice->tax_amount,
                'total'          => $invoice->total,
                'currency'       => $invoice->currency,
                'download_url'   => $downloadUrl,
            ];

            $itemLines  = $invoice->items->map(fn ($i) => sprintf(
                '   - %s × %s %s @ ₹%s = ₹%s',
                $i->quantity, $i->description, $i->unit,
                number_format((float) $i->unit_price, 2),
                number_format((float) $i->total, 2)
            ))->implode("\n");
            $dueDateStr = $invoice->due_date?->format('M d, Y') ?? 'N/A';

            return <<<TEXT
Invoice updated successfully!

**{$invoice->invoice_number}**
Client: {$invoice->client->name}
Due Date: {$dueDateStr}
Status: {$invoice->status}

Line Items:
{$itemLines}

Subtotal: ₹{$invoice->subtotal}
Tax:      ₹{$invoice->tax_amount}
**Total:  ₹{$invoice->total}**

[Click here to download PDF]({$downloadUrl})
TEXT;

        } catch (\Exception $e) {
            Log::error('UpdateInvoiceTool error', ['error' => $e->getMessage()]);
            return "Error updating invoice: {$e->getMessage()}";
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === 'null' || trim((string) $value) === '') return null;
        return trim((string) $value);
    }
}
