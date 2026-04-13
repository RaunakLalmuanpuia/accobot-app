<?php

namespace App\Tools;

use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetInvoiceTool implements Tool
{
    private ?array $lastInvoice = null;

    public function getLastInvoice(): ?array { return $this->lastInvoice; }

    public function description(): Stringable|string
    {
        return 'Get full details of an invoice by its invoice number (e.g. INV-00001) or numeric ID. '
            . 'Use this when the user asks about a specific invoice.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()->description('Invoice number (e.g. INV-00001) or numeric ID')->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $identifier = (string) $request->string('identifier');
        $tid        = request()->route('tenant')?->id;

        Log::info('GetInvoiceTool', ['identifier' => $identifier]);

        try {
            $invoice = is_numeric($identifier)
                ? Invoice::with('client', 'items.product')->find((int) $identifier)
                : Invoice::with('client', 'items.product')->where('invoice_number', $identifier)->first();

            if (! $invoice) {
                return "Invoice \"{$identifier}\" not found.";
            }

            $downloadUrl = route('invoices.download', ['tenant' => $tid, 'invoice' => $invoice->id]);

            $this->lastInvoice = [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client'         => $invoice->client->name,
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

            $itemLines  = $invoice->items->map(fn ($item) => sprintf(
                '   - %s × %s %s @ ₹%s (tax %s%%) = ₹%s',
                $item->quantity, $item->description, $item->unit,
                number_format((float) $item->unit_price, 2),
                $item->tax_rate,
                number_format((float) $item->total, 2)
            ))->implode("\n");
            $dueDateStr = $invoice->due_date?->format('M d, Y') ?? 'N/A';

            return <<<TEXT
## {$invoice->invoice_number}

**Client:** {$invoice->client->name}
**Issue Date:** {$invoice->issue_date->format('M d, Y')}
**Due Date:** {$dueDateStr}
**Status:** {$invoice->status}
**Currency:** {$invoice->currency}

### Line Items
{$itemLines}

### Totals
Subtotal:  ₹{$invoice->subtotal}
Tax:       ₹{$invoice->tax_amount}
**Total:   ₹{$invoice->total}**

### Notes
{$invoice->notes}

[Click here to download PDF]({$downloadUrl})
TEXT;

        } catch (\Exception $e) {
            Log::error('GetInvoiceTool error', ['error' => $e->getMessage()]);
            return "Error fetching invoice: {$e->getMessage()}";
        }
    }
}
