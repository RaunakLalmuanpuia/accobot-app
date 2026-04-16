<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

/**
 * EmbeddingService — Generates vector embeddings via OpenAI (text-embedding-3-small).
 *
 * Used to embed clients and products for semantic similarity search with pgvector.
 * Dimensions: 1536 (text-embedding-3-small default).
 */
class EmbeddingService
{
    private int $dimensions = 1536;

    /**
     * Generate embedding vectors for multiple texts in one API call.
     *
     * @param  array<string>        $texts
     * @return array<array<float>>
     */
    public function embedMany(array $texts, ?string $tenantId = null): array
    {
        // Truncate long texts to stay within token limits
        $texts = array_map(fn ($text) => mb_substr($text, 0, 8000), $texts);

        Log::debug('EmbeddingService: generating embeddings', [
            'count'      => count($texts),
            'dimensions' => $this->dimensions,
        ]);

        $response = Embeddings::for($texts)
            ->dimensions($this->dimensions)
            ->generate(provider: 'openai');

        AiUsageLog::fromEmbeddingResponse(
            response:  $response,
            batchSize: count($texts),
            tenantId:  $tenantId,
        );

        return $response->embeddings;
    }

    /**
     * Embed a single text string.
     *
     * @return array<float>
     */
    public function embed(string $text, ?string $tenantId = null): array
    {
        return $this->embedMany([$text], $tenantId)[0];
    }

    /**
     * Embed a search query (same model, kept separate for clarity).
     *
     * @return array<float>
     */
    public function embedQuery(string $query, ?string $tenantId = null): array
    {
        return $this->embed($query, $tenantId);
    }
}
