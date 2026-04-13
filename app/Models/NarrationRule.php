<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NarrationRule extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'match_type', 'match_value', 'transaction_type',
        'amount_min', 'amount_max', 'narration_head_id',
        'narration_sub_head_id', 'note_template', 'party_name', 'priority', 'is_active',
        'source', 'match_count', 'last_matched_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'priority'        => 'integer',
        'last_matched_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function narrationHead(): BelongsTo
    {
        return $this->belongsTo(NarrationHead::class);
    }

    public function narrationSubHead(): BelongsTo
    {
        return $this->belongsTo(NarrationSubHead::class);
    }

    // ── Match Logic ────────────────────────────────────────────────────────

    /**
     * Find the highest-priority matching rule for the current tenant.
     *
     * In a normal HTTP request, BelongsToTenant global scope handles tenant isolation
     * automatically. Pass $tenantId explicitly (e.g. from seeders or CLI commands) to
     * bypass the scope and query a specific tenant's rules directly.
     */
    public static function findBestMatch(string $narration, string $type, float $amount, ?string $tenantId = null): ?self
    {
        $query = $tenantId
            ? self::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            : self::query();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($type) {
                $q->where('transaction_type', $type)->orWhere('transaction_type', 'both');
            })
            ->orderBy('priority', 'asc')
            ->get()
            ->first(fn ($rule) => $rule->matches($narration, $type, $amount));
    }

    public function matches(string $narration, string $type, float $amount = 0): bool
    {
        if ($this->amount_min && $amount < $this->amount_min) return false;
        if ($this->amount_max && $amount > $this->amount_max) return false;

        $subject = strtolower($narration);
        $search  = strtolower($this->match_value);

        return match ($this->match_type) {
            'contains'    => str_contains($subject, $search),
            'starts_with' => str_starts_with($subject, $search),
            'ends_with'   => str_ends_with($subject, $search),
            'exact'       => $subject === $search,
            'regex'       => (bool) preg_match($this->match_value, $narration),
            default       => false,
        };
    }

    /**
     * Extract party name from the narration.
     *
     * For regex rules: looks for a named capture group (?P<party>...).
     * For all other rules: returns the static party_name stored on the rule.
     */
    public function extractParty(string $narration): ?string
    {
        if ($this->match_type === 'regex') {
            if (preg_match($this->match_value, $narration, $matches) && !empty($matches['party'])) {
                return Str::title(strtolower(trim($matches['party'])));
            }
        }

        return $this->party_name ?: null;
    }

    /**
     * Generate the narration note from the template.
     *
     * Supported placeholders: {match}, {raw}, {amount}, {date}, {party}
     * {party} resolves to extractParty() result, falling back to the match_value.
     */
    public function generateNote(string $rawNarration, float $amount, $date = null, ?string $extractedParty = null): string
    {
        $template = $this->note_template ?? '{match} Transaction';
        $dateObj  = $date ?? now();

        $party = $extractedParty
            ?? $this->party_name
            ?? Str::title($this->match_value);

        $replacements = [
            '{match}'  => Str::title($this->match_value),
            '{raw}'    => $rawNarration,
            '{amount}' => number_format($amount, 2),
            '{date}'   => $dateObj instanceof \DateTime ? $dateObj->format('d-M-Y') : $dateObj,
            '{party}'  => $party,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
