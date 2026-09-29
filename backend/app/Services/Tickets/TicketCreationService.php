<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketCreationService
{
    public function create(array $data, ?User $actor = null): Ticket
    {
        return DB::transaction(function () use ($data, $actor): Ticket {
            $reportedAt = now();
            $priority = $data['priority'] ?? 'medium';
            $sla = match ($priority) {
                'critical' => ['response' => 15, 'resolution' => 120],
                'high' => ['response' => 30, 'resolution' => 240],
                'low' => ['response' => 120, 'resolution' => 1440],
                default => ['response' => 60, 'resolution' => 480],
            };

            do {
                $code = 'TCK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            } while (Ticket::query()->where('code', $code)->exists());

            $ticket = Ticket::create([
                'code' => $code,
                'asset_id' => $data['asset_id'] ?? null,
                'assigned_to' => null,
                'reporter_name' => $data['reporter_name'],
                'reporter_email' => $data['reporter_email'] ?? null,
                'reporter_phone' => $data['reporter_phone'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'priority' => $priority,
                'status' => 'new',
                'source' => $data['source'] ?? 'internal',
                'reported_at' => $reportedAt,
                'response_due_at' => $reportedAt->copy()->addMinutes($sla['response']),
                'resolution_due_at' => $reportedAt->copy()->addMinutes($sla['resolution']),
            ]);

            $ticket->events()->create([
                'user_id' => $actor?->id,
                'event_type' => 'created',
                'old_status' => null,
                'new_status' => 'new',
                'old_assigned_to' => null,
                'new_assigned_to' => null,
                'description' => $actor ? 'Ticket creado.' : 'Ticket recibido desde la identificación QR.',
                'metadata' => [
                    'source' => $ticket->source,
                    'priority' => $ticket->priority,
                    'asset_id' => $ticket->asset_id,
                    'response_due_at' => $ticket->response_due_at?->toISOString(),
                    'resolution_due_at' => $ticket->resolution_due_at?->toISOString(),
                ],
            ]);

            return $ticket->refresh();
        });
    }
}
