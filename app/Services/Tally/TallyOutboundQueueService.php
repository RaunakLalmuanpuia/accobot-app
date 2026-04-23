<?php

namespace App\Services\Tally;

use App\Models\TallyOutboundQueue;
use Illuminate\Support\Facades\DB;

class TallyOutboundQueueService
{
    public function queue(int $tenantId, string $entityType, int $entityId): void
    {
        DB::table('tally_outbound_queue')->upsert(
            [
                'tenant_id'   => $tenantId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'status'      => 'pending',
                'queued_at'   => now(),
                'confirmed_at' => null,
            ],
            ['tenant_id', 'entity_type', 'entity_id'],
            ['status' => 'pending', 'queued_at' => now(), 'confirmed_at' => null]
        );
    }

    public function markConfirmed(int $tenantId, string $entityType, int $entityId): void
    {
        TallyOutboundQueue::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    /** Returns array of entity IDs that have a pending queue entry for the given type. */
    public function pendingIds(int $tenantId, string $entityType): array
    {
        return TallyOutboundQueue::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('status', 'pending')
            ->pluck('entity_id')
            ->all();
    }
}
