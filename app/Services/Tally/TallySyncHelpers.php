<?php

namespace App\Services\Tally;

use App\Models\TallyConnection;
use App\Models\TallySyncLog;

trait TallySyncHelpers
{
    private function startLog(TallyConnection $conn, string $entity): TallySyncLog
    {
        return TallySyncLog::create([
            'tenant_id'  => $conn->tenant_id,
            'entity'     => $entity,
            'direction'  => 'inbound',
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }

    private function completeLog(TallySyncLog $log, TallyConnection $conn): TallySyncLog
    {
        $log->update([
            'status'          => 'success',
            'completed_at'    => now(),
            'records_created' => $log->records_created ?? 0,
            'records_updated' => $log->records_updated ?? 0,
            'records_skipped' => $log->records_skipped ?? 0,
            'records_failed'  => $log->records_failed  ?? 0,
        ]);
        $conn->update(['last_synced_at' => now()]);
        return $log->fresh();
    }

    private function failLog(TallySyncLog $log, string $message): TallySyncLog
    {
        $log->update([
            'status'        => 'failed',
            'error_message' => $message,
            'completed_at'  => now(),
        ]);
        return $log->fresh();
    }

    private function strip(array $item): array
    {
        return array_map(function ($v) {
            if (is_string($v)) return ltrim($v, "\u{0004}");
            if (is_array($v))  return $this->strip($v);
            return $v;
        }, $item);
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v) return null;
        try {
            return \Carbon\Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
