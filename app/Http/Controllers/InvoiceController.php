<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download(Tenant $tenant, Invoice $invoice): Response
    {
        abort_if((string) $invoice->tenant_id !== (string) $tenant->id, 403);

        $invoice->load('client', 'items.product');

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }
}
