<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        $search     = $request->input('search');
        $eventType  = $request->input('event_type');
        $actorId    = $request->input('actor_user_id');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        $query = AuditEvent::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'ilike', "%{$search}%")
                  ->orWhereJsonContains('metadata', $search);
            });
        }

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        if ($actorId) {
            $query->where('actor_user_id', (int) $actorId);
        }

        if ($dateFrom) {
            $query->whereDate('occurred_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('occurred_at', '<=', $dateTo);
        }

        $events = $query->paginate(50)->withQueryString();

        $actorIds = $events->pluck('actor_user_id')->filter()->unique()->values();
        $actors   = User::whereIn('id', $actorIds)->pluck('name', 'id');

        $teamMembers = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenant->id))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $eventTypes = AuditEvent::where('tenant_id', $tenant->id)
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        return inertia('Settings/AuditLog', [
            'tenant'      => $tenant,
            'events'      => $events->through(fn (AuditEvent $e) => [
                'id'          => $e->id,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'event_type'  => $e->event_type,
                'actor_type'  => $e->actor_type,
                'actor_name'  => $e->actor_user_id ? ($actors[$e->actor_user_id] ?? "User #{$e->actor_user_id}") : null,
                'ip'          => $e->ip,
                'metadata'    => $e->metadata,
            ]),
            'filters'     => [
                'search'        => $search,
                'event_type'    => $eventType,
                'actor_user_id' => $actorId,
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
            ],
            'eventTypes'  => $eventTypes,
            'teamMembers' => $teamMembers,
        ]);
    }
}
