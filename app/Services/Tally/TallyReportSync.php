<?php

namespace App\Services\Tally;

use App\Models\TallyConnection;
use App\Models\TallyReport;
use App\Models\TallySyncLog;

class TallyReportSync
{
    public function syncReport(TallyConnection $conn, string $type, array $payload): TallySyncLog
    {
        $log = TallySyncLog::create([
            'tenant_id'  => $conn->tenant_id,
            'entity'     => 'reports_' . $type,
            'direction'  => 'inbound',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        try {
            TallyReport::create([
                'tenant_id'    => $conn->tenant_id,
                'report_type'  => $type,
                'period_from'  => $payload['period_from'] ?? null,
                'period_to'    => $payload['period_to'],
                'data'         => $payload['data'] ?? [],
                'generated_at' => $payload['generated_at'] ?? null,
                'synced_at'    => now(),
            ]);

            $log->update([
                'status'          => 'success',
                'records_created' => 1,
                'completed_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $log->fresh();
    }
}
