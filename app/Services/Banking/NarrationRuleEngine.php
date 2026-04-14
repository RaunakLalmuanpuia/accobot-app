<?php

namespace App\Services\Banking;

use App\DTOs\Banking\NarrationSuggestionDTO;
use App\Models\NarrationRule;

class NarrationRuleEngine
{
    /**
     * Try to match the narration against the current tenant's rules.
     *
     * Matching strategy (first match wins):
     *   1. $partyName  — AI-extracted or parsed counterparty name, most reliable signal
     *   2. $narration  — raw text fallback; catches keyword rules like "salary", "emi",
     *                    and regex rules on structured statement descriptions
     *
     * Pass $tenantId explicitly when there is no HTTP request context (e.g. seeders, CLI).
     * In normal HTTP requests BelongsToTenant global scope handles tenant isolation.
     */
    public function match(
        string  $narration,
        string  $type,
        float   $amount,
        ?string $partyName = null,
        ?string $tenantId  = null,
    ): ?NarrationSuggestionDTO {

        $rule = null;

        // Tier A: match on party name — precise, catches learned rules like "suntech solutions"
        if ($partyName) {
            $rule = NarrationRule::findBestMatch($partyName, $type, $amount, $tenantId);
        }

        // Tier B: match on raw narration — catches keyword rules and structured CSV formats
        $rule ??= NarrationRule::findBestMatch($narration, $type, $amount, $tenantId);

        if (!$rule) {
            return null;
        }

        $rule->increment('match_count');
        $rule->update(['last_matched_at' => now()]);

        // Resolve final party name:
        // regex extractParty() on the narration → rule's static party_name → AI-extracted $partyName
        $party = $rule->extractParty($narration) ?? $partyName;

        return new NarrationSuggestionDTO(
            narrationHeadId:    $rule->narration_head_id,
            narrationSubHeadId: $rule->narration_sub_head_id,
            narrationNote:      $rule->generateNote($narration, $amount, extractedParty: $party),
            partyName:          $party,
            source:             'rule_based',
            confidence:         1.00,
            aiSuggestions:      [],
            appliedRuleId:      $rule->id,
            aiMetadata:         [],
        );
    }
}
