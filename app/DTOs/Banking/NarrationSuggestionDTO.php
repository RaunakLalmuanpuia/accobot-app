<?php

namespace App\DTOs\Banking;

readonly class NarrationSuggestionDTO
{
    public function __construct(
        public ?int    $narrationHeadId,
        public ?int    $narrationSubHeadId,
        public ?string $narrationNote,
        public ?string $partyName,
        public string  $source,        // 'rule_based' | 'ai_suggested' | 'manual'
        public float   $confidence,
        public array   $aiSuggestions,
        public ?int    $appliedRuleId,
        public array   $aiMetadata,
    ) {}
}
