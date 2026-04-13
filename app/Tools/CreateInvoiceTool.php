<?php

namespace App\Tools;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateInvoiceTool implements Tool
{
    private ?array $lastInvoice = null;

    public function description(): Stringable|string
    {
        return 'Create an invoice for a client. '
            . 'Requires the client ID and a list of line items (each with product_id or description, quantity, and unit_price). '
            . 'Always search for the client and products first to get their IDs.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The ID of the client to bill')->required(),
            'items'     => $schema->array()
                ->description('Array of line items to add to the invoice.')
                ->items($schema->object([
                    'product_id'  => $schema->integer()->description('ID of the product from inventory. Pass null if adding a custom line item.')->nullable()->required(),
                    'description' => $schema->string()->description('Line item description. Pass null to use the product name.')->nullable()->required(),
                    'quantity'    => $schema->number()->description('Quantity of the item.')->required(),
                    'unit_price'  => $schema->number()->description('Price per unit. Pass null to use the product\'s price.')->nullable()->required(),
                    'unit'        => $schema->string()->description('Unit of measure. Pass null to use the product\'s unit.')->nullable()->required(),
                    'tax_rate'    => $schema->number()->description('Tax rate percentage. Pass null to use the product\'s tax rate.')->nullable()->required(),
                ]))
                ->required(),
            'due_date' => $schema->string()->description('Due date in YYYY-MM-DD format. Pass null to default to 30 days from today.')->nullable()->required(),
            'notes'    => $schema->string()->description('Optional notes or payment terms. Pass null if none.')->nullable()->required(),
            'currency' => $schema->string()->description('Currency code, e.g. INR, USD, EUR. Pass null to default to INR.')->nullable()->required(),
        ];
    }

    public function getLastInvoice(): ?array
    {
        return $this->lastInvoice;
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request->integer('client_id');
        $itemsRaw = is_array($request['items'] ?? []) ? $request['items'] : [];
        $rawDate  = $request['due_date'] ?? null;
        $dueDate  = ($rawDate && $rawDate !== 'null') ? $rawDate : now()->addDays(30)->format('Y-m-d');
        $rawNotes = $request['notes'] ?? null;
        $notes    = ($rawNotes && $rawNotes !== 'null') ? $rawNotes : null;
        $rawCurr  = $request['currency'] ?? null;
        $currency = strtoupper(($rawCurr && $rawCurr !== 'null') ? $rawCurr : 'INR');
        $tid      = request()->route('tenant')?->id;

        Log::info('CreateInvoiceTool', ['client_id' => $clientId, 'items' => count($itemsRaw)]);

        try {
            $client = Client::find($clientId);
            if (! $client) {
                return "Client with ID {$clientId} not found. Please search for the client first.";
            }

            if (empty($itemsRaw)) {
                return 'Cannot create an invoice with no line items.';
            }

            $invoice = DB::transaction(function () use ($tid, $client, $itemsRaw, $dueDate, $notes, $currency) {
                $invoice = Invoice::create([
                    'tenant_id'      => $tid,
                    'invoice_number' => Invoice::generateNumber(),
                    'client_id'      => $client->id,
                    'issue_date'     => today(),
                    'due_date'       => $dueDate,
                    'status'         => 'draft',
                    'currency'       => $currency,
                    'notes'          => $notes,
                    'subtotal'       => 0,
                    'tax_amount'     => 0,
                    'total'          => 0,
                ]);

                $subtotal  = 0;
                $taxAmount = 0;

                foreach ($itemsRaw as $item) {
                    $product   = isset($item['product_id']) ? Product::find($item['product_id']) : null;
                    $qty       = (float) ($item['quantity'] ?? 1);
                    $unitPrice = (float) ($item['unit_price'] ?? ($product?->unit_price ?? 0));
                    $taxRate   = (float) ($item['tax_rate'] ?? ($product?->tax_rate ?? 0));
                    $desc      = $item['description'] ?? ($product?->name ?? 'Service');
                    $unit      = $item['unit'] ?? ($product?->unit ?? 'unit');
                    $lineTotal = round($qty * $unitPrice, 2);
                    $lineTax   = round($lineTotal * ($taxRate / 100), 2);

                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'product_id'  => $product?->id,
                        'description' => $desc,
                        'unit'        => $unit,
                        'quantity'    => $qty,
                        'unit_price'  => $unitPrice,
                        'tax_rate'    => $taxRate,
                        'tax_amount'  => $lineTax,
                        'total'       => $lineTotal + $lineTax,
                    ]);

                    $subtotal  += $lineTotal;
                    $taxAmount += $lineTax;
                }

                $invoice->update([
                    'subtotal'   => round($subtotal, 2),
                    'tax_amount' => round($taxAmount, 2),
                    'total'      => round($subtotal + $taxAmount, 2),
                ]);

                return $invoice->load('items.product');
            });

            $downloadUrl = route('invoices.download', ['tenant' => $tid, 'invoice' => $invoice->id]);

            $this->lastInvoice = [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client'         => $client->name,
                'issue_date'     => $invoice->issue_date->format('Y-m-d'),
                'due_date'       => $invoice->due_date,
                'status'         => $invoice->status,
                'subtotal'       => $invoice->subtotal,
                'tax_amount'     => $invoice->tax_amount,
                'total'          => $invoice->total,
                'currency'       => $invoice->currency,
                'download_url'   => $downloadUrl,
                'items'          => $invoice->items->map(fn ($i) => [
                    'description' => $i->description,
                    'quantity'    => $i->quantity,
                    'unit_price'  => $i->unit_price,
                    'total'       => $i->total,
                ])->toArray(),
            ];

            $itemLines  = $invoice->items->map(fn ($i) => sprintf(
                '   - %s × %s %s @ ₹%s = ₹%s',
                $i->quantity, $i->description, $i->unit,
                number_format((float) $i->unit_price, 2),
                number_format((float) $i->total, 2)
            ))->implode("\n");
            $dueDateStr = $invoice->due_date?->format('M d, Y') ?? 'N/A';

            return <<<TEXT
Invoice created successfully!

**{$invoice->invoice_number}**
Client: {$client->name}
Issue Date: {$invoice->issue_date->format('M d, Y')}
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
            Log::error('CreateInvoiceTool error', ['error' => $e->getMessage()]);
            return "Error creating invoice: {$e->getMessage()}";
        }
    }
}
