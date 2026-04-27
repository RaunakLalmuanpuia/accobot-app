<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\ChatNotificationService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Tenant $tenant)
    {
        return inertia('Invoices/Index', [
            'tenant'   => $tenant,
            'invoices' => Invoice::where('tenant_id', $tenant->id)
                ->with([
                    'client:id,name',
                    'items:id,invoice_id,product_id,description,unit,quantity,unit_price,tax_rate,total',
                    'items.product:id,name',
                ])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get([
                    'id', 'invoice_number', 'client_id', 'issue_date', 'due_date',
                    'status', 'subtotal', 'tax_amount', 'total',
                    'amount_paid', 'amount_due', 'currency', 'notes',
                ]),
        ]);
    }

    public function create(Tenant $tenant)
    {
        return inertia('Invoices/Form', [
            'tenant'   => $tenant,
            'clients'  => Client::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'unit', 'unit_price', 'tax_rate']),
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'issue_date'      => 'required|date',
            'due_date'        => 'nullable|date|after_or_equal:issue_date',
            'status'          => 'required|in:draft,sent,paid,partial,overdue,cancelled',
            'currency'        => 'nullable|string|size:3',
            'amount_paid'     => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.unit'        => 'nullable|string|max:50',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
            'items.*.product_id'  => 'nullable|exists:products,id',
        ]);

        abort_if(
            Client::where('id', $request->client_id)->where('tenant_id', $tenant->id)->doesntExist(),
            403
        );

        [$subtotal, $taxAmount, $total, $itemsData] = $this->calcTotals($request->items);

        $amountPaid = (float) ($request->amount_paid ?? 0);

        $invoice = $tenant->invoices()->create([
            'invoice_number' => Invoice::generateNumber($tenant->id),
            'client_id'      => $request->client_id,
            'issue_date'     => $request->issue_date,
            'due_date'       => $request->due_date,
            'status'         => $request->status ?? 'draft',
            'currency'       => $request->currency ?? 'INR',
            'notes'          => $request->notes,
            'subtotal'       => $subtotal,
            'tax_amount'     => $taxAmount,
            'total'          => $total,
            'amount_paid'    => $amountPaid,
            'amount_due'     => max(0, $total - $amountPaid),
        ]);

        $invoice->items()->createMany($itemsData);

        AuditEvent::log('invoice.created', [
            'id'             => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id'      => $invoice->client_id,
            'total'          => $invoice->total,
            'status'         => $invoice->status,
        ]);

        ChatNotificationService::notify(
            tenantId:  $tenant->id,
            title:     'Invoice Created',
            body:      "Invoice {$invoice->invoice_number} has been created.",
            eventType: 'invoice.created',
            data:      ['invoice_id' => $invoice->id],
        );

        return redirect()->route('invoices.index', ['tenant' => $tenant->id]);
    }

    public function edit(Tenant $tenant, Invoice $invoice)
    {
        abort_if((string) $invoice->tenant_id !== (string) $tenant->id, 403);

        $invoice->load('items');

        return inertia('Invoices/Form', [
            'tenant'   => $tenant,
            'invoice'  => $invoice,
            'clients'  => Client::where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'unit', 'unit_price', 'tax_rate']),
        ]);
    }

    public function update(Request $request, Tenant $tenant, Invoice $invoice)
    {
        abort_if((string) $invoice->tenant_id !== (string) $tenant->id, 403);

        $request->validate([
            'client_id'           => 'required|exists:clients,id',
            'issue_date'          => 'required|date',
            'due_date'            => 'nullable|date|after_or_equal:issue_date',
            'status'              => 'required|in:draft,sent,paid,partial,overdue,cancelled',
            'currency'            => 'nullable|string|size:3',
            'amount_paid'         => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.unit'        => 'nullable|string|max:50',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.tax_rate'    => 'nullable|numeric|min:0|max:100',
            'items.*.product_id'  => 'nullable|exists:products,id',
        ]);

        abort_if(
            Client::where('id', $request->client_id)->where('tenant_id', $tenant->id)->doesntExist(),
            403
        );

        [$subtotal, $taxAmount, $total, $itemsData] = $this->calcTotals($request->items);

        $amountPaid = (float) ($request->amount_paid ?? $invoice->amount_paid ?? 0);

        $invoice->update([
            'client_id'   => $request->client_id,
            'issue_date'  => $request->issue_date,
            'due_date'    => $request->due_date,
            'status'      => $request->status,
            'currency'    => $request->currency ?? 'INR',
            'notes'       => $request->notes,
            'subtotal'    => $subtotal,
            'tax_amount'  => $taxAmount,
            'total'       => $total,
            'amount_paid' => $amountPaid,
            'amount_due'  => max(0, $total - $amountPaid),
        ]);

        $invoice->items()->delete();
        $invoice->items()->createMany($itemsData);

        AuditEvent::log('invoice.updated', [
            'id'             => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id'      => $invoice->client_id,
            'total'          => $invoice->total,
            'status'         => $invoice->status,
        ]);

        if ($invoice->wasChanged('status') && $invoice->status === 'paid') {
            ChatNotificationService::notify(
                tenantId:  $tenant->id,
                title:     'Invoice Paid',
                body:      "Invoice {$invoice->invoice_number} has been marked as paid.",
                eventType: 'invoice.paid',
                data:      ['invoice_id' => $invoice->id],
            );
        }

        return redirect()->route('invoices.index', ['tenant' => $tenant->id]);
    }

    public function destroy(Tenant $tenant, Invoice $invoice)
    {
        abort_if((string) $invoice->tenant_id !== (string) $tenant->id, 403);

        AuditEvent::log('invoice.deleted', [
            'id'             => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total'          => $invoice->total,
        ]);

        $invoice->items()->delete();
        $invoice->delete();

        return back();
    }

    public function download(Tenant $tenant, Invoice $invoice)
    {
        abort_if((string) $invoice->tenant_id !== (string) $tenant->id, 403);

        $invoice->load('client', 'items.product');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $invoice, 'tenant' => $tenant])
            ->setPaper('a4', 'portrait');

        $filename = str_replace(['/', '\\'], '-', $invoice->invoice_number);
        return $pdf->download("{$filename}.pdf");
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function calcTotals(array $items): array
    {
        $subtotal  = 0;
        $taxAmount = 0;
        $itemsData = [];

        foreach ($items as $item) {
            $qty      = (float) $item['quantity'];
            $price    = (float) $item['unit_price'];
            $taxRate  = (float) ($item['tax_rate'] ?? 0);
            $itemSub  = round($qty * $price, 2);
            $itemTax  = round($itemSub * $taxRate / 100, 2);
            $itemTotal = round($itemSub + $itemTax, 2);

            $subtotal  += $itemSub;
            $taxAmount += $itemTax;

            $itemsData[] = [
                'product_id'  => $item['product_id'] ?? null,
                'description' => $item['description'],
                'unit'        => $item['unit'] ?? 'unit',
                'quantity'    => $qty,
                'unit_price'  => $price,
                'tax_rate'    => $taxRate,
                'tax_amount'  => $itemTax,
                'total'       => $itemTotal,
            ];
        }

        $total = round($subtotal + $taxAmount, 2);

        return [$subtotal, $taxAmount, $total, $itemsData];
    }
}
