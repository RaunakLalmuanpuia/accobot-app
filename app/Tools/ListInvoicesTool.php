<?php

namespace App\Tools;

use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListInvoicesTool implements Tool
{
    private array $lastResults = [];

    public function description(): Stringable|string
    {
        return 'List invoices. Can filter by status (draft, sent, paid, overdue, cancelled), '
            . 'client name, or date range. Supports pagination.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status'        => $schema->string()->description('Filter by status: draft, sent, paid, overdue, cancelled. Pass null for all.')->nullable()->required(),
            'client_filter' => $schema->string()->description('Filter by client name or company (partial match). Pass null for all.')->nullable()->required(),
            'from_date'     => $schema->string()->description('Filter invoices issued on or after this date (YYYY-MM-DD). Pass null for no lower bound.')->nullable()->required(),
            'to_date'       => $schema->string()->description('Filter invoices issued on or before this date (YYYY-MM-DD). Pass null for no upper bound.')->nullable()->required(),
            'limit'         => $schema->integer()->description('Number of invoices to return (default: 20, max: 100). Pass null for default.')->nullable()->required(),
            'page'          => $schema->integer()->description('Page number for pagination (default: 1). Pass null for default.')->nullable()->required(),
        ];
    }

    public function getLastResults(): array { return $this->lastResults; }

    public function handle(Request $request): Stringable|string
    {
        $status       = $this->nullableString($request['status'] ?? null);
        $clientFilter = $this->nullableString($request['client_filter'] ?? null);
        $fromDate     = $this->nullableString($request['from_date'] ?? null);
        $toDate       = $this->nullableString($request['to_date'] ?? null);
        $limit        = min((int) ($request['limit'] ?? 20) ?: 20, 100);
        $page         = max((int) ($request['page'] ?? 1) ?: 1, 1);
        $tid          = request()->route('tenant')?->id;

        Log::info('ListInvoicesTool', compact('status', 'clientFilter', 'fromDate', 'toDate', 'limit', 'page'));

        $validStatuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

        try {
            $query = Invoice::with('client')->where('tenant_id', $tid)->orderByDesc('issue_date')->orderByDesc('id');

            if ($status && in_array($status, $validStatuses, true)) $query->where('status', $status);

            if ($clientFilter) {
                $query->whereHas('client', fn ($q) => $q
                    ->where('name', 'ilike', "%{$clientFilter}%")
                    ->orWhere('company', 'ilike', "%{$clientFilter}%")
                );
            }

            if ($fromDate) $query->where('issue_date', '>=', $fromDate);
            if ($toDate)   $query->where('issue_date', '<=', $toDate);

            $total      = $query->count();
            $invoices   = $query->offset(($page - 1) * $limit)->limit($limit)->get();
            $totalPages = (int) ceil($total / $limit);

            if ($invoices->isEmpty()) {
                return 'No invoices found matching the given filters.';
            }

            $this->lastResults = $invoices->map(fn ($inv) => [
                'id'             => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'client'         => $inv->client->name,
                'issue_date'     => $inv->issue_date->format('Y-m-d'),
                'due_date'       => $inv->due_date,
                'status'         => $inv->status,
                'total'          => $inv->total,
                'currency'       => $inv->currency,
                'download_url'   => route('invoices.download', ['tenant' => $tid, 'invoice' => $inv->id]),
            ])->toArray();

            $header  = "| # | Invoice | Client | Date | Due Date | Total | Status | Download |\n";
            $header .= "|---|---------|--------|------|----------|-------|--------|----------|\n";

            $rows = $invoices->map(fn ($inv, $i) => sprintf(
                '| %d | **%s** | %s | %s | %s | ₹%s | `%s` | [PDF](%s) |',
                ($page - 1) * $limit + $i + 1,
                $inv->invoice_number, $inv->client->name,
                $inv->issue_date->format('M d, Y'), $inv->due_date?->format('M d, Y') ?? 'N/A',
                number_format((float) $inv->total, 2), $inv->status,
                route('invoices.download', ['tenant' => $tid, 'invoice' => $inv->id])
            ))->implode("\n");

            return "Showing {$invoices->count()} of {$total} invoice(s) (page {$page}/{$totalPages})\n\n{$header}{$rows}";

        } catch (\Exception $e) {
            Log::error('ListInvoicesTool error', ['error' => $e->getMessage()]);
            return "Error listing invoices: {$e->getMessage()}";
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === 'null' || trim((string) $value) === '') return null;
        return trim((string) $value);
    }
}
