<?php

namespace App\Services\Banking;

use App\DTOs\Banking\NarrationSuggestionDTO;
use App\Models\NarrationRule;

class NarrationRuleEngine
{
    /**
     * Try to match the narration against the current tenant's rules.
     * BelongsToTenant on NarrationRule scopes the query automatically.
     */
    public function match(string $narration, string $type, float $amount): ?NarrationSuggestionDTO
    {
        $rule = NarrationRule::findBestMatch($narration, $type, $amount);

        if (!$rule) {
            return null;
        }

        $rule->update(['match_count' => $rule->match_count + 1, 'last_matched_at' => now()]);

        return new NarrationSuggestionDTO(
            narrationHeadId:    $rule->narration_head_id,
            narrationSubHeadId: $rule->narration_sub_head_id,
            narrationNote:      $rule->generateNote($narration, $amount),
            partyName:          null,
            source:             'rule_based',
            confidence:         1.00,
            aiSuggestions:      [],
            appliedRuleId:      $rule->id,
            aiMetadata:         [],
        );
    }
}
