<?php

namespace App\Observers;

use App\Services\Tally\TallyInboundSync;
use App\Services\Tally\TallyOutboundQueueService;
use Illuminate\Database\Eloquent\Model;

class TallyModelObserver
{
    public function __construct(private TallyOutboundQueueService $queue) {}

    public function created(Model $model): void
    {
        $this->enqueue($model);
    }

    public function updated(Model $model): void
    {
        $this->enqueue($model);
    }

    private function enqueue(Model $model): void
    {
        if (TallyInboundSync::$syncing) {
            return;
        }

        if (isset($model->is_active) && !$model->is_active) {
            if (!$model->tally_id) {
                // Never reached Tally — nothing to delete there
                return;
            }
            $correctAction = 'Delete';
        } else {
            $correctAction = $model->tally_id ? 'Update' : 'Create';
        }

        if ($model->action !== $correctAction) {
            $model->updateQuietly(['action' => $correctAction]);
        }

        $this->queue->queue(
            (int) $model->tenant_id,
            $model::class,
            (int) $model->id,
        );
    }
}
