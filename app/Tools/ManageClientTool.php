<?php

namespace App\Tools;

use App\Models\Client;
use App\Services\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageClientTool implements Tool
{
    private array $lastResults = [];
    private ?array $lastClient = null;

    public function __construct(private EmbeddingService $embeddingService) {}

    public function description(): Stringable|string
    {
        return 'List, search, create, or update clients. '
            . 'Use action=search before creating to avoid duplicates. '
            . 'Only name is required when creating.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action'    => $schema->string()->description('What to do: "list", "search", "create", or "update".')->required(),
            'query'     => $schema->string()->description('Search term for action=search. Pass null otherwise.')->nullable()->required(),
            'filter'    => $schema->string()->description('Text filter for action=list. Pass null for all.')->nullable()->required(),
            'limit'     => $schema->integer()->description('Results to return (default: 20 for list, 5 for search). Pass null for default.')->nullable()->required(),
            'page'      => $schema->integer()->description('Page number for action=list (default: 1). Pass null for default.')->nullable()->required(),
            'client_id' => $schema->integer()->description('Client ID — required for action=update. Pass null otherwise.')->nullable()->required(),
            'name'      => $schema->string()->description('Client name — required for action=create. Pass null to leave unchanged on update.')->nullable()->required(),
            'email'     => $schema->string()->description('Email address. Pass null to skip or leave unchanged.')->nullable()->required(),
            'phone'     => $schema->string()->description('Phone number. Pass null to skip or leave unchanged.')->nullable()->required(),
            'company'   => $schema->string()->description('Company name. Pass null to skip or leave unchanged.')->nullable()->required(),
            'address'   => $schema->string()->description('Address. Pass null to skip or leave unchanged.')->nullable()->required(),
            'notes'     => $schema->string()->description('Notes. Pass null to skip or leave unchanged.')->nullable()->required(),
        ];
    }

    public function getLastResults(): array { return $this->lastResults; }
    public function getLastClient(): ?array { return $this->lastClient; }

    private function tenantId(): ?string
    {
        return request()->route('tenant')?->id;
    }

    public function handle(Request $request): Stringable|string
    {
        $action = trim((string) ($request['action'] ?? ''));
        Log::info('ManageClientTool', ['action' => $action]);

        try {
            return match ($action) {
                'list'   => $this->list($request),
                'search' => $this->search($request),
                'create' => $this->create($request),
                'update' => $this->update($request),
                default  => "Unknown action \"{$action}\". Use list, search, create, or update.",
            };
        } catch (\Exception $e) {
            Log::error('ManageClientTool error', ['action' => $action, 'error' => $e->getMessage()]);
            return "Error: {$e->getMessage()}";
        }
    }

    private function list(Request $request): string
    {
        $filter = $this->nullableString($request['filter'] ?? null);
        $limit  = min((int) ($request['limit'] ?? 20) ?: 20, 100);
        $page   = max((int) ($request['page'] ?? 1) ?: 1, 1);

        $query = Client::query()->orderBy('name');

        if ($filter) {
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$filter}%")
                ->orWhere('company', 'ilike', "%{$filter}%")
                ->orWhere('email', 'ilike', "%{$filter}%")
            );
        }

        $total      = $query->count();
        $clients    = $query->offset(($page - 1) * $limit)->limit($limit)->get();
        $totalPages = (int) ceil($total / $limit);

        if ($clients->isEmpty()) {
            return $filter ? "No clients found matching \"{$filter}\"." : 'No clients in the system yet.';
        }

        $this->lastResults = $clients->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'company' => $c->company,
            'email' => $c->email, 'phone' => $c->phone,
        ])->toArray();

        $header  = "| # | Name | Company | Email | Phone |\n";
        $header .= "|---|------|---------|-------|-------|\n";

        $rows = $clients->map(fn ($c, $i) => sprintf(
            '| %d | **%s** | %s | %s | %s |',
            ($page - 1) * $limit + $i + 1,
            $c->name, $c->company ?? '—', $c->email ?? '—', $c->phone ?? '—'
        ))->implode("\n");

        return "Showing {$clients->count()} of {$total} client(s) (page {$page}/{$totalPages})\n\n{$header}{$rows}";
    }

    private function search(Request $request): string
    {
        $query  = trim((string) ($request['query'] ?? ''));
        $limit  = (int) ($request['limit'] ?? 5) ?: 5;
        $tid    = $this->tenantId();

        if (empty($query)) {
            return 'A search query is required.';
        }

        $queryEmbedding = $this->embeddingService->embedQuery($query);
        $vectorLiteral  = '[' . implode(',', $queryEmbedding) . ']';

        $rows = DB::select(
            "SELECT id, name, email, phone, address, company, tax_id, notes,
                    1 - (embedding <=> :vec::vector) AS similarity
             FROM clients
             WHERE embedding IS NOT NULL AND tenant_id = :tid
             ORDER BY embedding <=> :vec2::vector LIMIT :limit",
            ['vec' => $vectorLiteral, 'vec2' => $vectorLiteral, 'tid' => $tid, 'limit' => $limit]
        );

        if (empty($rows)) {
            $rows = DB::select(
                "SELECT id, name, email, phone, address, company, tax_id, notes, 0 AS similarity
                 FROM clients
                 WHERE tenant_id = :tid
                   AND (name ILIKE :q OR company ILIKE :q2 OR email ILIKE :q3) LIMIT :limit",
                ['tid' => $tid, 'q' => "%{$query}%", 'q2' => "%{$query}%", 'q3' => "%{$query}%", 'limit' => $limit]
            );
        }

        if (empty($rows)) {
            return "No clients found matching \"{$query}\".";
        }

        $this->lastResults = [];
        $lines = [];

        foreach ($rows as $i => $row) {
            $score = round((float) $row->similarity * 100, 1);
            $this->lastResults[] = [
                'id' => $row->id, 'name' => $row->name, 'email' => $row->email,
                'phone' => $row->phone, 'company' => $row->company,
                'address' => $row->address, 'tax_id' => $row->tax_id, 'similarity' => $score,
            ];
            $lines[] = sprintf(
                "%d. **%s** (client_id: %d)\n   🏢 %s | 📧 %s | 📞 %s | 🎯 Match: %s%%",
                $i + 1, $row->name, $row->id,
                $row->company ?? 'N/A', $row->email ?? 'N/A', $row->phone ?? 'N/A', $score
            );
        }

        return 'Found ' . count($lines) . " client(s):\n\n" . implode("\n\n", $lines);
    }

    private function create(Request $request): string
    {
        $name    = $this->nullableString($request['name'] ?? null);
        $email   = $this->nullableString($request['email'] ?? null);
        $phone   = $this->nullableString($request['phone'] ?? null);
        $company = $this->nullableString($request['company'] ?? null);
        $address = $this->nullableString($request['address'] ?? null);
        $tid     = $this->tenantId();

        if (! $name) {
            return 'Client name is required.';
        }

        $existing = Client::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->orWhere(fn ($q) => $q->whereNotNull('email')->where('email', $email ?? ''))
            ->first();

        if ($existing) {
            $this->lastClient = [
                'id' => $existing->id, 'name' => $existing->name, 'email' => $existing->email,
                'phone' => $existing->phone, 'company' => $existing->company, 'address' => $existing->address,
            ];
            return "Client already exists: **{$existing->name}** (client_id: {$existing->id}). Using existing record.";
        }

        $client = Client::create([
            'tenant_id' => $tid,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'company'   => $company,
            'address'   => $address,
        ]);

        try {
            $vector = '[' . implode(',', $this->embeddingService->embed($client->toEmbeddingText())) . ']';
            DB::statement('UPDATE clients SET embedding = :vec::vector WHERE id = :id', ['vec' => $vector, 'id' => $client->id]);
        } catch (\Exception $e) {
            Log::warning('ManageClientTool: embedding failed', ['error' => $e->getMessage()]);
        }

        $this->lastClient = [
            'id' => $client->id, 'name' => $client->name, 'email' => $client->email,
            'phone' => $client->phone, 'company' => $client->company, 'address' => $client->address,
        ];

        $details = array_filter([
            $client->company ? "Company: {$client->company}" : null,
            $client->email   ? "Email: {$client->email}"     : null,
            $client->phone   ? "Phone: {$client->phone}"     : null,
        ]);
        $detailLine = $details ? ' (' . implode(', ', $details) . ')' : '';

        return "Client created successfully! **{$client->name}**{$detailLine} (client_id: {$client->id})";
    }

    private function update(Request $request): string
    {
        $clientId = $request['client_id'] ?? null;
        if (! $clientId || $clientId === 'null') {
            return 'client_id is required for update.';
        }

        $client = Client::find((int) $clientId);
        if (! $client) {
            return 'Client not found.';
        }

        $changes = [];
        foreach (['name', 'email', 'phone', 'company', 'address', 'notes'] as $field) {
            $val = $this->nullableString($request[$field] ?? null);
            if ($val !== null) {
                $changes[$field] = $val;
            }
        }

        if (empty($changes)) {
            return 'No changes provided — client was not updated.';
        }

        $client->update($changes);

        try {
            $client->refresh();
            $vector = '[' . implode(',', $this->embeddingService->embed($client->toEmbeddingText())) . ']';
            DB::statement('UPDATE clients SET embedding = :vec::vector WHERE id = :id', ['vec' => $vector, 'id' => $client->id]);
        } catch (\Exception $e) {
            Log::warning('ManageClientTool: re-embedding failed', ['error' => $e->getMessage()]);
        }

        return "Client **{$client->name}** updated. Changed: " . implode(', ', array_keys($changes)) . '.';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === 'null' || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }
}
