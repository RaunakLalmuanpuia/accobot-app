<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan command to generate OpenAI embeddings for clients and products.
 *
 * Usage:
 *   php artisan embed:data              # Only records without embeddings
 *   php artisan embed:data --all        # Re-embed everything
 *   php artisan embed:data --type=clients
 *   php artisan embed:data --type=products
 *   php artisan embed:data --limit=50
 */
class EmbedData extends Command
{
    protected $signature = 'embed:data
                            {--all    : Re-generate embeddings for all records}
                            {--type=  : Which model to embed: clients, vendors, products (default: all)}
                            {--limit= : Max records to process (0 = no limit)}
                            {--batch= : Batch size for API calls (default: 20)}';

    protected $description = 'Generate OpenAI embeddings for clients, vendors, and products and store them in PostgreSQL (pgvector)';

    public function handle(EmbeddingService $embeddingService): int
    {
        $type      = $this->option('type') ?: 'all';
        $limit     = (int) ($this->option('limit') ?: 0);
        $batchSize = (int) ($this->option('batch') ?: 20);
        $reEmbed   = (bool) $this->option('all');

        $this->info('Starting OpenAI embedding generation...');

        $totalProcessed = 0;
        $totalErrors    = 0;

        if ($type === 'all' || $type === 'clients') {
            [$processed, $errors] = $this->embedModel(
                Client::class, 'clients', $embeddingService, $batchSize, $limit, $reEmbed
            );
            $totalProcessed += $processed;
            $totalErrors    += $errors;
        }

        if ($type === 'all' || $type === 'vendors') {
            [$processed, $errors] = $this->embedModel(
                Vendor::class, 'vendors', $embeddingService, $batchSize, $limit, $reEmbed
            );
            $totalProcessed += $processed;
            $totalErrors    += $errors;
        }

        if ($type === 'all' || $type === 'products') {
            [$processed, $errors] = $this->embedModel(
                Product::class, 'products', $embeddingService, $batchSize, $limit, $reEmbed
            );
            $totalProcessed += $processed;
            $totalErrors    += $errors;
        }

        $this->newLine();
        $this->info("Done! Processed: {$totalProcessed} | Errors: {$totalErrors}");

        if ($totalErrors > 0) {
            $this->warn('Some records failed. Re-run to retry failed ones.');
        }

        return self::SUCCESS;
    }

    private function embedModel(
        string $modelClass,
        string $label,
        EmbeddingService $embeddingService,
        int $batchSize,
        int $limit,
        bool $reEmbed
    ): array {
        $this->info("\nEmbedding {$label}...");

        $query = $modelClass::query();

        if (! $reEmbed) {
            $query->whereNull('embedding');
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->line("  No {$label} need embedding. Use --all to re-generate.");
            return [0, 0];
        }

        $this->line("  Found {$total} {$label} to process.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $errors    = 0;

        $query->chunk($batchSize, function ($records) use (
            $embeddingService, $bar, &$processed, &$errors, $label
        ) {
            $texts = $records->map(fn ($r) => $r->toEmbeddingText())->all();

            try {
                $embeddings = $embeddingService->embedMany($texts);

                foreach ($records as $index => $record) {
                    $vector = '[' . implode(',', $embeddings[$index]) . ']';

                    // Use raw SQL to update the pgvector column
                    DB::statement(
                        "UPDATE {$record->getTable()} SET embedding = :vec::vector WHERE id = :id",
                        ['vec' => $vector, 'id' => $record->id]
                    );

                    $processed++;
                    $bar->advance();
                }
            } catch (\Exception $e) {
                $errors += count($records);
                $this->newLine();
                $this->error("  Batch error: {$e->getMessage()}");
                $bar->advance(count($records));
            }

            usleep(200_000); // 200ms pause between batches for rate limiting
        });

        $bar->finish();
        return [$processed, $errors];
    }
}
