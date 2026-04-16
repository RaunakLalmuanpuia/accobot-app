<?php

namespace App\Services\Banking;

use App\Agents\Narration\NarrationSuggestionAgent;
use App\DTOs\Banking\NarrationSuggestionDTO;
use App\Models\AiUsageLog;
use App\Models\NarrationHead;

class NarrationAiService
{
    /**
     * Ask the AI to categorize a transaction using the given tenant's catalog.
     */
    public function suggest(
        string $rawNarration,
        string $type,
        float  $amount,
        string $date,
        string $tenantId,
    ): NarrationSuggestionDTO {

        $catalog     = $this->buildCatalog($type, $tenantId);
        $catalogJson = json_encode($catalog, JSON_PRETTY_PRINT);
        $amountStr   = number_format($amount, 2);

        $prompt = <<<PROMPT
        Transaction Details:
        - Raw Narration : {$rawNarration}
        - Type          : {$type}
        - Amount        : ₹{$amountStr}
        - Date          : {$date}

        Available Narration Catalog (Head → Sub-heads):
        {$catalogJson}

        Categorize this transaction using only the heads and sub-heads listed above.
        PROMPT;

        try {
            $response = NarrationSuggestionAgent::make()->prompt($prompt);
        } catch (\Throwable $e) {
            AiUsageLog::fromError(
                e:        $e,
                agent:    'NarrationSuggestionAgent',
                callType: 'structured',
                tenantId: $tenantId,
            );
            throw $e;
        }

        AiUsageLog::fromAgentResponse(
            response: $response,
            agent:    'NarrationSuggestionAgent',
            callType: 'structured',
            tenantId: $tenantId,
        );

        [$headId, $subHeadId] = $this->resolveIds(
            $response['narration_head_name'] ?? '',
            $response['narration_sub_head_name'] ?? '',
            $type,
            $tenantId,
        );

        return new NarrationSuggestionDTO(
            narrationHeadId:    $headId,
            narrationSubHeadId: $subHeadId,
            narrationNote:      $response['narration_note'] ?? null,
            partyName:          $response['party_name'] ?: null,
            source:             'ai_suggested',
            confidence:         (float) ($response['confidence'] ?? 0.5),
            aiSuggestions:      $response['alternatives'] ?? [],
            appliedRuleId:      null,
            aiMetadata:         [
                'reasoning'     => $response['reasoning'] ?? null,
                'head_name'     => $response['narration_head_name'] ?? null,
                'sub_head_name' => $response['narration_sub_head_name'] ?? null,
            ],
        );
    }

    // ── Private Helpers ────────────────────────────────────────────────────

    private function buildCatalog(string $type, string $tenantId): array
    {
        return NarrationHead::with('activeSubHeads')
            ->where('tenant_id', $tenantId)
            ->active()
            ->forTransactionType($type)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($head) => [
                'head'      => $head->name,
                'sub_heads' => $head->activeSubHeads->pluck('name')->toArray(),
            ])
            ->toArray();
    }

    private function resolveIds(string $headName, string $subHeadName, string $type, string $tenantId): array
    {
        $head = NarrationHead::where('tenant_id', $tenantId)
            ->active()
            ->forTransactionType($type)
            ->where('name', $headName)
            ->first();

        if (!$head) {
            return [null, null];
        }

        $subHead = $head->activeSubHeads()->where('name', $subHeadName)->first();

        return [$head->id, $subHead?->id];
    }
}
