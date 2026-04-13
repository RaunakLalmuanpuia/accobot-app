<?php

namespace App\Actions\Banking;

use App\Models\BankTransaction;
use App\Models\Tenant;
use App\Services\Banking\NarrationPipelineService;
use Illuminate\Support\Facades\DB;

class IngestSmsTransactionAction
{
    public function __construct(private NarrationPipelineService $pipeline) {}

    public function execute(string $rawSms, Tenant $tenant, ?string $bankAccountName = null): BankTransaction
    {
        return DB::transaction(fn () => $this->pipeline->processFromSms($rawSms, $tenant, $bankAccountName ?? ''));
    }
}
