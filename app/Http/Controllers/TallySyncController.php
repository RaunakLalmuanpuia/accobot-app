<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TallyConnection;
use App\Models\TallyEmployee;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyReport;
use App\Models\TallyStatutoryMaster;
use App\Models\TallySyncLog;
use App\Models\TallyGodown;
use App\Models\TallyUnit;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyCompany;
use App\Models\TallyVoucher;
use App\Services\ChatNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TallySyncController extends Controller
{
    public function index(Tenant $tenant)
    {
        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->first();

        // Latest log per entity for masters/vouchers tabs
        $latestLogs = TallySyncLog::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('entity')
            ->map(fn ($group) => $group->first())
            ->values();

        // All sync logs for the logs tab
        $allLogs = TallySyncLog::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        // Report snapshots
        $reports = TallyReport::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('synced_at')
            ->get(['id', 'report_type', 'period_from', 'period_to', 'generated_at', 'synced_at']);

        // Stats
        $stats = [
            'total_ledger_groups'     => TallyLedgerGroup::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_ledgers'           => TallyLedger::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_stock_groups'      => TallyStockGroup::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_stock_categories'  => TallyStockCategory::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_stock_items'       => TallyStockItem::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_godowns'           => TallyGodown::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_units'             => TallyUnit::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_vouchers'          => TallyVoucher::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_statutory_masters' => TallyStatutoryMaster::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_employees'         => TallyEmployee::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_companies'         => TallyCompany::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'last_synced_at'          => $connection?->last_synced_at,
        ];

        return Inertia::render('Tally/Sync', [
            'tenant'      => $tenant,
            'connection'  => $connection ? ['is_active' => $connection->is_active] : null,
            'latestLogs'  => $latestLogs,
            'allLogs'     => $allLogs,
            'reports'     => $reports,
            'stats'       => $stats,
        ]);
    }

    public function trigger(Request $request, Tenant $tenant)
    {
        $connection = TallyConnection::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        if (!$connection) {
            return back()->with('error', 'No active Tally connection found.');
        }

        // The connector pushes data to us — we cannot pull. Log a manual trigger note.
        TallySyncLog::create([
            'tenant_id'          => $tenant->id,
            'entity'             => 'manual_trigger',
            'direction'          => 'inbound',
            'status'             => 'success',
            'triggered_manually' => true,
            'started_at'         => now(),
            'completed_at'       => now(),
            'error_message'      => 'Manual sync reminder logged. Data flows from Tally connector — ensure connector is running.',
        ]);

        AuditEvent::log('tally.sync.triggered');

        ChatNotificationService::notify(
            tenantId:  $tenant->id,
            title:     'Tally Sync Started',
            body:      'A Tally data sync has been triggered.',
            eventType: 'tally.sync.started',
            data:      [],
        );

        return back()->with('success', 'Sync reminder logged. Tally connector pushes data automatically.');
    }
}
