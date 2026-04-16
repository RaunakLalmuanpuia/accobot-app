<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Throwable;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'context'    => 'array',
        'cost_usd'   => 'float',
        'is_error'   => 'boolean',
        'created_at' => 'datetime',
    ];

    // ── Pricing table (USD per 1M tokens) ─────────────────────────────────
    // Matched by longest prefix, so 'gpt-4o-mini' beats 'gpt-4o'.
    // Add new models as OpenAI releases them.

    // Prices verified from OpenRouter (openrouter.ai) — April 2026.
    // Ordered longest-prefix first so prefix matching picks the most specific entry.
    private const PRICING = [
        // GPT-5 family (verified Apr 2026)
        'gpt-5.4'                => ['prompt' =>  2.50, 'completion' => 15.00],
        'gpt-5.3'                => ['prompt' =>  2.50, 'completion' => 15.00],
        'gpt-5.2'                => ['prompt' =>  2.50, 'completion' => 15.00],
        'gpt-5.1'                => ['prompt' =>  1.25, 'completion' => 10.00],
        'gpt-5-mini'             => ['prompt' =>  1.25, 'completion' => 10.00],
        'gpt-5'                  => ['prompt' =>  1.25, 'completion' => 10.00],
        // GPT-4.1 family (verified Apr 2026)
        'gpt-4.1-nano'           => ['prompt' =>  0.10, 'completion' =>  0.40],
        'gpt-4.1-mini'           => ['prompt' =>  0.40, 'completion' =>  1.60],
        'gpt-4.1'                => ['prompt' =>  2.00, 'completion' =>  8.00],
        // GPT-4o family (verified Apr 2026)
        'gpt-4o-mini'            => ['prompt' =>  0.15, 'completion' =>  0.60],
        'gpt-4o'                 => ['prompt' =>  2.50, 'completion' => 10.00],
        // o-series reasoning models (verified Apr 2026)
        'o4-mini'                => ['prompt' =>  1.10, 'completion' =>  4.40],
        'o3-mini'                => ['prompt' =>  1.10, 'completion' =>  4.40],
        'o3'                     => ['prompt' =>  2.00, 'completion' =>  8.00],
        'o1-mini'                => ['prompt' =>  1.10, 'completion' =>  4.40],
        'o1'                     => ['prompt' => 15.00, 'completion' => 60.00],
        // Embeddings (verified Apr 2026)
        'text-embedding-3-small' => ['prompt' =>  0.02, 'completion' =>  0.00],
        'text-embedding-3-large' => ['prompt' =>  0.13, 'completion' =>  0.00],
        'text-embedding-ada-002' => ['prompt' =>  0.10, 'completion' =>  0.00],
    ];

    // ── Factory helpers ────────────────────────────────────────────────────

    public static function fromAgentResponse(
        AgentResponse $response,
        string        $agent,
        string        $callType = 'chat',
        ?string       $tenantId = null,
        ?int          $userId = null,
        int           $toolSteps = 0,
        ?array        $context = null,
    ): void {
        $model      = $response->meta->model ?? 'unknown';
        $prompt     = $response->usage->promptTokens;
        $completion = $response->usage->completionTokens;

        static::safeCreate([
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'agent'             => $agent,
            'model'             => $model,
            'provider'          => $response->meta->provider,
            'call_type'         => $callType,
            'prompt_tokens'     => $prompt,
            'completion_tokens' => $completion,
            'total_tokens'      => $prompt + $completion,
            'tool_steps'        => $toolSteps,
            'cost_usd'          => static::computeCost($model, $prompt, $completion),
            'is_error'          => false,
            'context'           => $context,
        ]);
    }

    public static function fromEmbeddingResponse(
        EmbeddingsResponse $response,
        int                $batchSize,
        ?string            $tenantId = null,
        ?int               $userId = null,
        ?array             $context = null,
    ): void {
        $model  = $response->meta->model ?? 'text-embedding-3-small';
        $tokens = $response->tokens;

        static::safeCreate([
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'agent'             => 'EmbeddingService',
            'model'             => $model,
            'provider'          => $response->meta->provider,
            'call_type'         => 'embedding',
            'prompt_tokens'     => $tokens,
            'completion_tokens' => 0,
            'total_tokens'      => $tokens,
            'tool_steps'        => 0,
            'cost_usd'          => static::computeCost($model, $tokens, 0),
            'is_error'          => false,
            'context'           => array_merge($context ?? [], ['batch_size' => $batchSize]),
        ]);
    }

    public static function fromError(
        Throwable $e,
        string    $agent,
        string    $callType,
        ?string   $tenantId = null,
        ?int      $userId = null,
        ?array    $context = null,
    ): void {
        static::safeCreate([
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'agent'             => $agent,
            'model'             => null,
            'provider'          => null,
            'call_type'         => $callType,
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
            'tool_steps'        => 0,
            'cost_usd'          => 0,
            'is_error'          => true,
            'error_message'     => $e->getMessage(),
            'context'           => $context,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private static function computeCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Exact match first
        $price = static::PRICING[$model] ?? null;

        // Prefix match — longest key that is a prefix of the model name wins
        if (! $price) {
            $bestLen = 0;
            foreach (static::PRICING as $key => $p) {
                if (str_starts_with($model, $key) && strlen($key) > $bestLen) {
                    $price   = $p;
                    $bestLen = strlen($key);
                }
            }
        }

        if (! $price) {
            Log::warning("AiUsageLog: no pricing for model '{$model}' — cost set to 0");
            return 0.0;
        }

        return round(
            ($promptTokens / 1_000_000) * $price['prompt'] +
            ($completionTokens / 1_000_000) * $price['completion'],
            8
        );
    }

    /**
     * Recalculate cost_usd for all existing rows (run once after deploying new pricing).
     */
    public static function recalculateAllCosts(): int
    {
        $updated = 0;
        static::whereNotNull('model')->chunkById(200, function ($rows) use (&$updated) {
            foreach ($rows as $row) {
                $cost = static::computeCost($row->model, $row->prompt_tokens, $row->completion_tokens);
                if ($cost != $row->cost_usd) {
                    $row->updateQuietly(['cost_usd' => $cost]);
                    $updated++;
                }
            }
        });
        return $updated;
    }

    /**
     * Write the log row, but never let a DB failure bubble up and break the AI response.
     */
    private static function safeCreate(array $data): void
    {
        try {
            static::create($data);
        } catch (Throwable $e) {
            Log::warning('AiUsageLog: failed to write row', [
                'error' => $e->getMessage(),
                'agent' => $data['agent'] ?? null,
            ]);
        }
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
