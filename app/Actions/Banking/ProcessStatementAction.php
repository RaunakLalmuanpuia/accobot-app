<?php

namespace App\Actions\Banking;

use App\Models\BankTransaction;
use App\Models\Tenant;
use App\Services\Banking\NarrationPipelineService;
use App\Services\Banking\StatementParserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProcessStatementAction
{
    public function __construct(
        private StatementParserService   $parser,
        private NarrationPipelineService $pipeline,
    ) {}

    /**
     * Parse and ingest a bank statement file.
     */
    public function execute(UploadedFile $file, Tenant $tenant, ?string $bankAccountName = null): array
    {
        $dtos = $this->parser->parse($file, $tenant);

        if ($dtos->isEmpty()) {
            return [
                'batch_id'     => null,
                'total'        => 0,
                'imported'     => 0,
                'duplicates'   => 0,
                'failed'       => 0,
                'transactions' => [],
                'message'      => 'No transactions found in the uploaded file.',
            ];
        }

        $batchId = (string) Str::uuid();
        $results = [
            'batch_id'     => $batchId,
            'total'        => $dtos->count(),
            'imported'     => 0,
            'duplicates'   => 0,
            'failed'       => 0,
            'transactions' => [],
        ];

        foreach ($dtos as $dto) {
            try {
                $transaction = DB::transaction(function () use ($dto, $tenant, $bankAccountName, $batchId) {
                    $t = $this->pipeline->process($dto, $tenant, 'statement', $bankAccountName ?? '');
                    $t->update([
                        'import_batch_id' => $batchId,
                        'import_source'   => 'statement',
                    ]);
                    return $t;
                });

                $transaction->is_duplicate ? $results['duplicates']++ : $results['imported']++;
                $results['transactions'][] = $this->summarise($transaction);

            } catch (Throwable $e) {
                $results['failed']++;
                $results['transactions'][] = [
                    'status'        => 'failed',
                    'raw_narration' => $dto->rawNarration,
                    'amount'        => $dto->amount,
                    'date'          => $dto->transactionDate->toDateString(),
                    'error'         => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function summarise(BankTransaction $t): array
    {
        return [
            'status'                => $t->is_duplicate ? 'duplicate' : 'imported',
            'id'                    => $t->id,
            'transaction_date'      => $t->transaction_date->toDateString(),
            'type'                  => $t->type,
            'amount'                => $t->amount,
            'raw_narration'         => $t->raw_narration,
            'narration_head_id'     => $t->narration_head_id,
            'narration_sub_head_id' => $t->narration_sub_head_id,
            'narration_note'        => $t->narration_note,
            'narration_source'      => $t->narration_source,
            'ai_confidence'         => $t->ai_confidence,
            'review_status'         => $t->review_status,
            'is_duplicate'          => $t->is_duplicate,
        ];
    }
}
