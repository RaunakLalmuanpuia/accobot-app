<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class AiUsageController extends Controller
{
    public function index(): Response
    {
        // ── Summary stats ──────────────────────────────────────────────────
        $stats = [
            'cost_today'   => round(AiUsageLog::whereDate('created_at', today())->sum('cost_usd'), 6),
            'cost_month'   => round(AiUsageLog::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->sum('cost_usd'), 6),
            'cost_total'   => round(AiUsageLog::sum('cost_usd'), 6),
            'calls_today'  => AiUsageLog::whereDate('created_at', today())->where('is_error', false)->count(),
            'calls_month'  => AiUsageLog::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->where('is_error', false)->count(),
            'errors_month' => AiUsageLog::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->where('is_error', true)->count(),
            'tokens_month' => AiUsageLog::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->sum('total_tokens'),
        ];

        // ── Daily cost + calls — last 30 days ──────────────────────────────
        $dailyRows = AiUsageLog::selectRaw("DATE(created_at) as date, ROUND(SUM(cost_usd)::numeric, 6) as cost, COUNT(*) FILTER (WHERE is_error = false) as calls")
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dailyLabels = [];
        $dailyCost   = [];
        $dailyCalls  = [];
        for ($i = 29; $i >= 0; $i--) {
            $d             = now()->subDays($i)->toDateString();
            $dailyLabels[] = now()->subDays($i)->format('d M');
            $dailyCost[]   = (float) ($dailyRows[$d]->cost  ?? 0);
            $dailyCalls[]  = (int)   ($dailyRows[$d]->calls ?? 0);
        }

        // ── By agent ──────────────────────────────────────────────────────
        $byAgent = AiUsageLog::selectRaw("agent, ROUND(SUM(cost_usd)::numeric, 6) as cost, COUNT(*) FILTER (WHERE is_error = false) as calls, SUM(total_tokens) as tokens")
            ->groupBy('agent')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => [
                'agent'  => $r->agent,
                'cost'   => (float) $r->cost,
                'calls'  => (int)   $r->calls,
                'tokens' => (int)   $r->tokens,
            ]);

        // ── By model ──────────────────────────────────────────────────────
        $byModel = AiUsageLog::selectRaw("model, ROUND(SUM(cost_usd)::numeric, 6) as cost, SUM(total_tokens) as tokens, COUNT(*) FILTER (WHERE is_error = false) as calls")
            ->whereNotNull('model')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($r) => [
                'model'  => $r->model,
                'cost'   => (float) $r->cost,
                'tokens' => (int)   $r->tokens,
                'calls'  => (int)   $r->calls,
            ]);

        // ── Per-tenant breakdown ───────────────────────────────────────────
        $tenantRows = AiUsageLog::selectRaw("
                tenant_id,
                agent,
                call_type,
                ROUND(SUM(cost_usd)::numeric, 6)            AS cost,
                COUNT(*) FILTER (WHERE is_error = false)    AS calls,
                COUNT(*) FILTER (WHERE is_error = true)     AS errors,
                SUM(total_tokens)                           AS tokens
            ")
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id', 'agent', 'call_type')
            ->orderByDesc('cost')
            ->get();

        $tenantNames = Tenant::whereIn('id', $tenantRows->pluck('tenant_id')->unique())
            ->pluck('name', 'id');

        $tenantBreakdown = $tenantRows
            ->groupBy('tenant_id')
            ->map(function ($rows, $tenantId) use ($tenantNames) {
                return [
                    'tenant_id'    => $tenantId,
                    'tenant_name'  => $tenantNames[$tenantId] ?? 'Unknown',
                    'total_cost'   => round($rows->sum('cost'), 6),
                    'total_calls'  => $rows->sum('calls'),
                    'total_errors' => $rows->sum('errors'),
                    'total_tokens' => $rows->sum('tokens'),
                    'by_agent'     => $rows->groupBy('agent')->map(function ($agentRows, $agent) {
                        return [
                            'agent'  => $agent,
                            'cost'   => round($agentRows->sum('cost'), 6),
                            'calls'  => $agentRows->sum('calls'),
                            'tokens' => $agentRows->sum('tokens'),
                        ];
                    })->sortByDesc('cost')->values(),
                ];
            })
            ->sortByDesc('total_cost')
            ->values();

        // ── Recent logs ────────────────────────────────────────────────────
        $recentLogs = AiUsageLog::with('user')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'agent'             => $r->agent,
                'model'             => $r->model,
                'call_type'         => $r->call_type,
                'prompt_tokens'     => $r->prompt_tokens,
                'completion_tokens' => $r->completion_tokens,
                'total_tokens'      => $r->total_tokens,
                'tool_steps'        => $r->tool_steps,
                'cost_usd'          => (float) $r->cost_usd,
                'is_error'          => (bool)  $r->is_error,
                'error_message'     => $r->error_message,
                'tenant_name'       => $tenantNames[$r->tenant_id] ?? null,
                'user_name'         => $r->user?->name,
                'created_at'        => $r->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/AiUsage', compact(
            'stats',
            'dailyLabels',
            'dailyCost',
            'dailyCalls',
            'byAgent',
            'byModel',
            'tenantBreakdown',
            'recentLogs',
        ));
    }
}
