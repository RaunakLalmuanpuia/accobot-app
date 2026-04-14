<?php

namespace App\Services\Banking;

use App\Agents\Narration\EmailParserAgent;
use App\Agents\Narration\SmsParserAgent;
use App\DTOs\Banking\ParsedTransactionDTO;
use App\Models\BankTransaction;
use App\Models\Tenant;

class NarrationPipelineService
{
    /**
     * Source trust hierarchy — higher index = more authoritative.
     * A statement is the bank's official record; SMS is the least reliable.
     */
    private const SOURCE_PRIORITY = ['sms' => 1, 'email' => 2, 'statement' => 3];

    public function __construct(
        private NarrationRuleEngine $ruleEngine,
        private NarrationAiService  $aiService,
    ) {}

    // ── Entry points ───────────────────────────────────────────────────────

    public function processFromSms(string $rawSms, Tenant $tenant, string $bankAccountName = ''): BankTransaction
    {
        $response = SmsParserAgent::make()->prompt("Parse this bank SMS:\n\n{$rawSms}");

        $dto = ParsedTransactionDTO::fromArray([
            'raw_narration'    => $rawSms,
            'type'             => $response['type'],
            'amount'           => $response['amount'],
            'bank_reference'   => $response['bank_reference'] ?? '',
            'party_name'       => $response['party_name'] ?? null,
            'transaction_date' => $response['transaction_date'],
            'balance_after'    => $response['balance_after'] ?? null,
            'bank_name'        => $response['bank_name'] ?? $bankAccountName ?: null,
        ]);

        return $this->process($dto, $tenant, 'sms', $bankAccountName);
    }

    public function processFromEmail(string $rawEmail, Tenant $tenant, string $bankAccountName = ''): BankTransaction
    {
        $response = EmailParserAgent::make()->prompt("Parse this bank alert email:\n\n{$rawEmail}");

        $dto = ParsedTransactionDTO::fromArray([
            'raw_narration'    => $rawEmail,
            'type'             => $response['type'],
            'amount'           => $response['amount'],
            'bank_reference'   => $response['bank_reference'] ?? '',
            'party_name'       => $response['party_name'] ?? null,
            'transaction_date' => $response['transaction_date'],
            'balance_after'    => $response['balance_after'] ?? null,
            'bank_name'        => $response['bank_name'] ?? $bankAccountName ?: null,
        ]);

        return $this->process($dto, $tenant, 'email', $bankAccountName);
    }

    /**
     * Run the full narration pipeline on an already-parsed DTO.
     * Called directly by the statement upload flow and internally by the two methods above.
     */
    public function process(
        ParsedTransactionDTO $dto,
        Tenant               $tenant,
        string               $importSource = 'statement',
        string               $bankAccountName = '',
    ): BankTransaction {

        // ── Dedup check ────────────────────────────────────────────────────
        $hash = BankTransaction::makeDedupHash(
            $dto->transactionDate->toDateString(),
            $dto->amount,
            $dto->type,
            $dto->bankReference
        );

        $existing = BankTransaction::where('tenant_id', $tenant->id)->where('dedup_hash', $hash)->first();

        // ── Source priority upgrade ────────────────────────────────────────
        if ($existing && $this->incomingOutranks($importSource, $existing->import_source)) {
            return $this->upgradeSource($existing, $dto, $importSource);
        }

        $isDuplicate = $existing !== null;

        // ── Tier 1: Rule engine ────────────────────────────────────────────
        // Pass the AI-parsed party name so the engine can match learned rules
        // (e.g. "suntech solutions") before falling back to the raw narration text.
        $suggestion = $this->ruleEngine->match(
            narration: $dto->rawNarration,
            type:      $dto->type,
            amount:    $dto->amount,
            partyName: $dto->partyName,
            tenantId:  $tenant->id,
        );

        // ── Tier 2: AI fallback ────────────────────────────────────────────
        if (!$suggestion) {
            $suggestion = $this->aiService->suggest(
                $dto->rawNarration,
                $dto->type,
                $dto->amount,
                $dto->transactionDate->toDateString(),
                $tenant->id,
            );
        }

        // ── Persist ────────────────────────────────────────────────────────
        $transaction = BankTransaction::create([
            'tenant_id'             => $tenant->id,
            'bank_account_name'     => $bankAccountName ?: ($dto->bankName ?? ''),
            'transaction_date'      => $dto->transactionDate,
            'bank_reference'        => $dto->bankReference,
            'raw_narration'         => $dto->rawNarration,
            'type'                  => $dto->type,
            'amount'                => $dto->amount,
            'balance_after'         => $dto->balanceAfter,
            'narration_head_id'     => $suggestion->narrationHeadId,
            'narration_sub_head_id' => $suggestion->narrationSubHeadId,
            'narration_note'        => $suggestion->narrationNote,
            'party_name'            => $suggestion->partyName ?? $dto->partyName,
            'narration_source'      => $suggestion->source,
            'ai_confidence'         => $suggestion->confidence,
            'ai_suggestions'        => $suggestion->aiSuggestions,
            'ai_metadata'           => $suggestion->aiMetadata,
            'review_status'         => 'pending',
            'applied_rule_id'       => $suggestion->appliedRuleId,
            'dedup_hash'            => $hash,
            'is_duplicate'          => $isDuplicate,
            'import_source'         => $importSource,
        ]);

        return $transaction;
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function incomingOutranks(string $incoming, string $existing): bool
    {
        $incomingRank = self::SOURCE_PRIORITY[$incoming] ?? 0;
        $existingRank = self::SOURCE_PRIORITY[$existing] ?? 0;

        return $incomingRank > $existingRank;
    }

    private function upgradeSource(BankTransaction $existing, ParsedTransactionDTO $dto, string $newSource): BankTransaction
    {
        $existing->update([
            'raw_narration'  => $dto->rawNarration,
            'import_source'  => $newSource,
            'party_name'     => $dto->partyName    ?? $existing->party_name,
            'balance_after'  => $dto->balanceAfter ?? $existing->balance_after,
            'bank_reference' => $dto->bankReference ?: $existing->bank_reference,
        ]);

        return $existing->fresh();
    }
}
