<?php

namespace App\Tools;

use App\Models\BankTransaction;
use App\Models\NarrationHead;
use App\Models\NarrationSubHead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * NarrateTransactionTool — List transactions, get narration heads, and save narrations.
 *
 * Actions:
 *   list      — list/filter bank transactions
 *   get_heads — get available narration heads and sub-heads for a transaction type
 *   save      — save confirmed narration to a transaction
 */
class NarrateTransactionTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'List bank transactions, get narration heads/sub-heads, and save narrations. '
            . 'Use action=list to find transactions, action=get_heads to see available categories, '
            . 'and action=save to store the confirmed narration.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('What to do: "list", "get_heads", or "save".')
                ->required(),

            // list
            'search' => $schema->string()
                ->description('Keyword to search in raw narration (e.g. "Amazon", "salary"). For action=list. Pass null for no filter.')
                ->nullable()
                ->required(),
            'review_status' => $schema->string()
                ->description('Filter by status: pending, reviewed, flagged. For action=list. Pass null for all.')
                ->nullable()
                ->required(),
            'type' => $schema->string()
                ->description('Transaction type: "credit" or "debit". Used by action=list (filter) and action=get_heads (required). Pass null for no filter on list.')
                ->nullable()
                ->required(),
            'from_date' => $schema->string()
                ->description('Filter transactions on or after this date (YYYY-MM-DD). For action=list. Pass null for no lower bound.')
                ->nullable()
                ->required(),
            'to_date' => $schema->string()
                ->description('Filter transactions on or before this date (YYYY-MM-DD). For action=list. Pass null for no upper bound.')
                ->nullable()
                ->required(),
            'limit' => $schema->integer()
                ->description('Number of results to return (default: 20, max: 50). For action=list. Pass null for default.')
                ->nullable()
                ->required(),
            'page' => $schema->integer()
                ->description('Page number for action=list (default: 1). Pass null for default.')
                ->nullable()
                ->required(),

            // save
            'transaction_id' => $schema->integer()
                ->description('ID of the bank transaction to narrate (txn_id from action=list). For action=save.')
                ->nullable()
                ->required(),
            'narration_head_id' => $schema->integer()
                ->description('Head ID to assign (head_id from action=get_heads). Required if narration_sub_head_id is null. For action=save.')
                ->nullable()
                ->required(),
            'narration_sub_head_id' => $schema->integer()
                ->description('Sub-head ID to assign (sub_head_id from action=get_heads). Pass null to narrate at head level only. For action=save.')
                ->nullable()
                ->required(),
            'party_name' => $schema->string()
                ->description('Vendor or party name extracted from the raw narration. For action=save. Pass null if not applicable.')
                ->nullable()
                ->required(),
            'narration_note' => $schema->string()
                ->description('Short narration note for the CA. For action=save.')
                ->nullable()
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $action = trim((string) ($request['action'] ?? ''));

        Log::info('NarrateTransactionTool', ['action' => $action]);

        try {
            return match ($action) {
                'list'      => $this->list($request),
                'get_heads' => $this->getHeads($request),
                'save'      => $this->save($request),
                default     => "Unknown action \"{$action}\". Use list, get_heads, or save.",
            };
        } catch (\Exception $e) {
            Log::error('NarrateTransactionTool error', ['action' => $action, 'error' => $e->getMessage()]);
            return "Error: {$e->getMessage()}";
        }
    }

    // ── List ──────────────────────────────────────────────────────────────────

    private function list(Request $request): string
    {
        $search       = $this->nullableString($request['search'] ?? null);
        $reviewStatus = $this->nullableString($request['review_status'] ?? null);
        $type         = $this->nullableString($request['type'] ?? null);
        $fromDate     = $this->nullableString($request['from_date'] ?? null);
        $toDate       = $this->nullableString($request['to_date'] ?? null);
        $limit        = min((int) ($request['limit'] ?? 20) ?: 20, 50);
        $page         = max((int) ($request['page'] ?? 1) ?: 1, 1);

        $query = BankTransaction::with(['narrationHead', 'narrationSubHead'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($search)       $query->where('raw_narration', 'ilike', "%{$search}%");
        if ($reviewStatus && in_array($reviewStatus, ['pending', 'reviewed', 'flagged'], true))
                           $query->where('review_status', $reviewStatus);
        if ($type && in_array($type, ['credit', 'debit'], true))
                           $query->where('type', $type);
        if ($fromDate)     $query->where('transaction_date', '>=', $fromDate);
        if ($toDate)       $query->where('transaction_date', '<=', $toDate);

        $total      = $query->count();
        $txns       = $query->offset(($page - 1) * $limit)->limit($limit)->get();
        $totalPages = (int) ceil($total / $limit);

        if ($txns->isEmpty()) {
            return $search ? "No transactions found matching \"{$search}\"." : 'No transactions found.';
        }

        $header  = "| # | Date | Type | Amount | Raw Narration | Status | Narration | Txn ID |\n";
        $header .= "|---|------|------|--------|---------------|--------|-----------|--------|\n";

        $rows = $txns->map(fn ($t, $i) => sprintf(
            '| %d | %s | %s | ₹%s | %s | %s | %s | txn_id:%d |',
            ($page - 1) * $limit + $i + 1,
            $t->transaction_date->format('d M Y'),
            strtoupper($t->type),
            number_format((float) $t->amount, 2),
            $t->raw_narration,
            $t->review_status,
            $t->narrationHead
                ? ($t->narrationSubHead
                    ? "{$t->narrationHead->name} › {$t->narrationSubHead->name}"
                    : $t->narrationHead->name)
                : '—',
            $t->id
        ))->implode("\n");

        return "Showing {$txns->count()} of {$total} transaction(s) (page {$page}/{$totalPages})\n\n{$header}{$rows}";
    }

    // ── Get Heads ─────────────────────────────────────────────────────────────

    private function getHeads(Request $request): string
    {
        $type = strtolower(trim((string) ($request['type'] ?? '')));

        if (! in_array($type, ['credit', 'debit'], true)) {
            return 'type must be "credit" or "debit" for action=get_heads.';
        }

        $heads = NarrationHead::with('activeSubHeads')
            ->where('is_active', true)
            ->whereIn('type', [$type, 'both'])
            ->orderBy('sort_order')
            ->get();

        if ($heads->isEmpty()) {
            return "No narration heads found for type: {$type}.";
        }

        $lines = $heads->map(function ($head) {
            $subLines = $head->activeSubHeads->map(fn ($sub) =>
                "   - sub_head_id:{$sub->id} **{$sub->name}**"
                . ($sub->ledger_code ? " [{$sub->ledger_code}]" : '')
                . ($sub->requires_party ? ' *(party name required)*' : '')
            )->implode("\n");

            return "**{$head->name}** (head_id:{$head->id})\n{$subLines}";
        })->implode("\n\n");

        return "Available narration heads for **{$type}** transactions:\n\n{$lines}";
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    private function save(Request $request): string
    {
        $transactionId      = (int) ($request['transaction_id'] ?? 0);
        $narrationSubHeadId = $this->nullableInt($request['narration_sub_head_id'] ?? null);
        $partyName          = $this->nullableString($request['party_name'] ?? null);
        $narrationNote      = trim((string) ($request['narration_note'] ?? ''));

        $transaction = BankTransaction::find($transactionId);
        if (! $transaction) {
            return 'Transaction not found.';
        }

        $subHead = $narrationSubHeadId
            ? NarrationSubHead::with('narrationHead')->find($narrationSubHeadId)
            : null;

        if ($narrationSubHeadId && ! $subHead) {
            return 'Narration sub-head not found.';
        }

        $headId = $subHead ? $subHead->narration_head_id : null;

        if (! $headId) {
            $rawHeadId = $request['narration_head_id'] ?? null;
            if ($rawHeadId && $rawHeadId !== 'null') {
                $headId = (int) $rawHeadId;
            }
        }

        $transaction->update([
            'narration_head_id'     => $headId,
            'narration_sub_head_id' => $subHead?->id,
            'narration_source'      => 'ai_suggested',
            'narration_note'        => $narrationNote,
            'party_name'            => $partyName,
            'review_status'         => 'reviewed',
        ]);

        $head = $subHead?->narrationHead ?? ($headId ? NarrationHead::find($headId) : null);

        return sprintf(
            "Narration saved for transaction on **%s** (₹%s %s).\n- **Head:** %s\n- **Sub-Head:** %s%s\n- **Note:** %s",
            $transaction->transaction_date->format('d M Y'),
            number_format((float) $transaction->amount, 2),
            strtoupper($transaction->type),
            $head?->name ?? '—',
            $subHead?->name ?? '—',
            $partyName ? "\n- **Party:** {$partyName}" : '',
            $narrationNote
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === 'null' || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === 'null' || $value === '') {
            return null;
        }
        return (int) $value;
    }
}
