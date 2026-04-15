<?php

namespace App\Jobs;

use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $modelClass,
        public string $modelId,
    ) {}

    public function handle(EmbeddingService $embeddingService): void
    {
        $record = $this->modelClass::find($this->modelId);

        if (!$record) {
            return;
        }

        try {
            $vector = '[' . implode(',', $embeddingService->embed($record->toEmbeddingText())) . ']';

            DB::statement(
                "UPDATE {$record->getTable()} SET embedding = :vec::vector WHERE id = :id",
                ['vec' => $vector, 'id' => $record->id]
            );
        } catch (\Throwable $e) {
            Log::warning('GenerateEmbeddingJob: embedding failed', [
                'model' => $this->modelClass,
                'id'    => $this->modelId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
