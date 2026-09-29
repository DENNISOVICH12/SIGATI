<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Ticket;
use App\Models\TicketEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const OPEN_STATUSES = ['new', 'assigned', 'in_progress'];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('tickets.view') || $user->can('assets.view'), 403);

        $isEngineer = $user->hasRole('engineer');
        $tickets = Ticket::query();

        if (! $isEngineer) {
            $tickets->where('assigned_to', $user->id);
        }

        $ticketCounts = (clone $tickets)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(['new', 'assigned', 'in_progress', 'resolved', 'closed'])
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($ticketCounts[$status] ?? 0)])
            ->all();

        $unassigned = Ticket::query()
            ->whereNull('assigned_to')
            ->where('status', 'new')
            ->count();

        $openTickets = (clone $tickets)->whereIn('status', self::OPEN_STATUSES)->count();
        $slaBreached = $this->applyBreachedSla((clone $tickets)->whereIn('status', self::OPEN_STATUSES))->count();
        $resolvedPendingClosure = (clone $tickets)->where('status', 'resolved')->count();

        $assets = null;
        if ($user->can('assets.view')) {
            $assetCounts = Asset::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $assets = [
                'total' => (int) $assetCounts->sum(),
                'by_status' => collect(['operational', 'pending_review', 'faulty', 'maintenance'])
                    ->mapWithKeys(fn (string $status): array => [$status => (int) ($assetCounts[$status] ?? 0)])
                    ->all(),
                'problematic' => (int) collect(['pending_review', 'faulty', 'maintenance'])
                    ->sum(fn (string $status): int => (int) ($assetCounts[$status] ?? 0)),
            ];
        }

        return response()->json([
            'generated_at' => now()->toISOString(),
            'scope' => $isEngineer ? 'global' : 'assigned',
            'assets' => $assets,
            'tickets' => [
                'total' => (int) array_sum($byStatus),
                'open' => $openTickets,
                'by_status' => $byStatus,
                'unassigned' => $unassigned,
                'sla_breached' => $slaBreached,
                'resolved_pending_closure' => $resolvedPendingClosure,
            ],
            'attention' => [
                'unassigned' => $unassigned,
                'sla_breached' => $slaBreached,
                'resolved_pending_closure' => $isEngineer ? $resolvedPendingClosure : 0,
                'problematic_assets' => $assets['problematic'] ?? 0,
            ],
            'recent_activity' => $this->recentActivity($isEngineer, $user->id),
        ]);
    }

    private function applyBreachedSla(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $response): void {
                $response->whereNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->where('response_due_at', '<', now());
            })->orWhere(function (Builder $response): void {
                $response->whereNotNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->whereColumn('first_response_at', '>', 'response_due_at');
            })->orWhere(function (Builder $resolution): void {
                $resolution->whereNull('resolved_at')
                    ->whereNotNull('resolution_due_at')
                    ->where('resolution_due_at', '<', now());
            });
        });
    }

    private function recentActivity(bool $isEngineer, int $userId): array
    {
        $ticketEvents = TicketEvent::query()
            ->with(['ticket:id,code,title,assigned_to', 'user:id,name'])
            ->when(! $isEngineer, fn (Builder $query) => $query->whereHas(
                'ticket',
                fn (Builder $ticket) => $ticket->where('assigned_to', $userId)
            ))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (TicketEvent $event): array => [
                'id' => 'ticket-'.$event->id,
                'resource_type' => 'ticket',
                'resource_id' => $event->ticket_id,
                'resource_code' => $event->ticket?->code,
                'event_type' => $event->event_type,
                'description' => $event->description,
                'reason' => $event->reason,
                'old_status' => $event->old_status,
                'new_status' => $event->new_status,
                'actor' => $event->user?->name,
                'created_at' => $event->created_at?->toISOString(),
            ]);

        $assetEvents = collect();
        if ($isEngineer) {
            $assetEvents = AssetHistory::query()
                ->with(['asset:id,code,name', 'user:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AssetHistory $event): array => [
                    'id' => 'asset-'.$event->id,
                    'resource_type' => 'asset',
                    'resource_id' => $event->asset_id,
                    'resource_code' => $event->asset?->code,
                    'event_type' => $event->action,
                    'description' => $event->description,
                    'reason' => $event->reason,
                    'old_status' => data_get($event->old_values, 'status'),
                    'new_status' => data_get($event->new_values, 'status'),
                    'actor' => $event->user?->name,
                    'created_at' => $event->created_at?->toISOString(),
                ]);
        }

        return $ticketEvents
            ->concat($assetEvents)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->all();
    }
}
