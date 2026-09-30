<?php

namespace App\Services\Tickets;

use App\Domain\Tickets\TicketStatus;
use App\Domain\Tickets\TicketWorkflowException;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketWorkflowService
{
    public function claim(int $ticketId, User $actor): Ticket
    {
        return $this->transaction($ticketId, function (Ticket $ticket) use ($actor): void {
            $this->transition($ticket, TicketStatus::New, TicketStatus::Assigned);

            if ($ticket->assigned_to !== null) {
                throw new TicketWorkflowException('Este ticket ya fue tomado por otro técnico.');
            }

            $now = now();
            $ticket->update([
                'assigned_to' => $actor->id,
                'status' => TicketStatus::Assigned,
                'assigned_at' => $now,
                'first_response_at' => $ticket->first_response_at ?? $now,
            ]);
            $this->event($ticket, $actor, 'claimed', TicketStatus::New, TicketStatus::Assigned, null, $actor->id, null, [
                'assigned_at' => $now->toISOString(),
            ]);
        });
    }

    public function release(int $ticketId, User $actor, string $reason): Ticket
    {
        return $this->transaction($ticketId, function (Ticket $ticket) use ($actor, $reason): void {
            $this->transition($ticket, TicketStatus::Assigned, TicketStatus::New);

            if ((int) $ticket->assigned_to !== (int) $actor->id) {
                throw new TicketWorkflowException('Solo el técnico asignado puede liberar este servicio.', 403);
            }

            $oldAssignedTo = $ticket->assigned_to;
            $ticket->update([
                'assigned_to' => null,
                'status' => TicketStatus::New,
                'assigned_at' => null,
            ]);
            $this->event($ticket, $actor, 'released', TicketStatus::Assigned, TicketStatus::New, $oldAssignedTo, null, $reason, [
                'released_at' => now()->toISOString(),
            ]);
        });
    }

    public function assign(int $ticketId, User $actor, User $technician, ?string $reason): Ticket
    {
        return $this->transaction($ticketId, function (Ticket $ticket) use ($actor, $technician, $reason): void {
            $technician = User::query()->lockForUpdate()->findOrFail($technician->id);
            $status = $ticket->status;
            if (! in_array($status, [TicketStatus::New, TicketStatus::Assigned, TicketStatus::InProgress], true)) {
                throw new TicketWorkflowException('No se puede asignar un técnico en el estado actual del ticket.');
            }
            if (! $technician->active || ! $technician->hasRole('technician')) {
                throw new TicketWorkflowException('El usuario seleccionado no es un técnico activo.', 422);
            }
            if ((int) $ticket->assigned_to === (int) $technician->id) {
                throw new TicketWorkflowException('Este ticket ya se encuentra asignado al técnico seleccionado.');
            }
            if ($status === TicketStatus::InProgress && $reason === null) {
                throw new TicketWorkflowException('La reasignación de un ticket en progreso requiere un motivo.', 422);
            }

            $oldAssignedTo = $ticket->assigned_to;
            $newStatus = $status === TicketStatus::New ? TicketStatus::Assigned : $status;
            $now = now();
            $ticket->update([
                'assigned_to' => $technician->id,
                'status' => $newStatus,
                'assigned_at' => $now,
                'first_response_at' => $ticket->first_response_at ?? $now,
            ]);
            $this->event(
                $ticket,
                $actor,
                $oldAssignedTo === null ? 'assigned' : 'reassigned',
                $status,
                $newStatus,
                $oldAssignedTo,
                $technician->id,
                $reason,
                ['assigned_at' => $now->toISOString(), 'assigned_by' => $actor->id]
            );
        });
    }

    public function start(int $ticketId, User $actor, ?string $note): Ticket
    {
        return $this->transaction($ticketId, function (Ticket $ticket) use ($actor, $note): void {
            $this->transition($ticket, TicketStatus::Assigned, TicketStatus::InProgress);
            $this->ensureAssignedTo($ticket, $actor);
            $now = now();
            $ticket->update([
                'status' => TicketStatus::InProgress,
                'first_response_at' => $ticket->first_response_at ?? $now,
            ]);
            $this->event($ticket, $actor, 'started', TicketStatus::Assigned, TicketStatus::InProgress, $actor->id, $actor->id, $note, [
                'started_at' => $now->toISOString(),
            ]);
        });
    }

    public function resolve(int $ticketId, User $actor, array $resolution): Ticket
    {
        return DB::transaction(function () use ($ticketId, $actor, $resolution): Ticket {
            return $this->resolveInCurrentTransaction($ticketId, $actor, $resolution);
        });
    }

    /**
     * Resolve a ticket as part of a transaction already owned by an orchestrator.
     *
     * This method acquires the ticket lock itself and always applies the same
     * state-machine primitive used by the public standalone flow.
     */
    public function resolveInCurrentTransaction(int $ticketId, User $actor, array $resolution): Ticket
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('La resolución compuesta requiere una transacción activa.');
        }

        $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticketId);
        $this->applyResolution($ticket, $actor, $resolution);

        return $ticket->refresh();
    }

    public function close(int $ticketId, User $actor, string $reason): Ticket
    {
        return $this->transaction($ticketId, function (Ticket $ticket) use ($actor, $reason): void {
            $this->transition($ticket, TicketStatus::Resolved, TicketStatus::Closed);
            $now = now();
            $ticket->update(['status' => TicketStatus::Closed, 'closed_at' => $now]);
            $this->event($ticket, $actor, 'closed', TicketStatus::Resolved, TicketStatus::Closed, $ticket->assigned_to, $ticket->assigned_to, $reason, [
                'closed_at' => $now->toISOString(),
                'closed_by' => $actor->id,
            ]);
        });
    }

    private function transaction(int $ticketId, callable $operation): Ticket
    {
        return DB::transaction(function () use ($ticketId, $operation): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticketId);
            $operation($ticket);

            return $ticket->refresh();
        });
    }

    private function transition(Ticket $ticket, TicketStatus $from, TicketStatus $to): void
    {
        if ($ticket->status !== $from || ! $from->canTransitionTo($to)) {
            throw new TicketWorkflowException("La transición de {$ticket->status->value} a {$to->value} no está permitida.");
        }
    }

    private function ensureAssignedTo(Ticket $ticket, User $actor): void
    {
        if ((int) $ticket->assigned_to !== (int) $actor->id) {
            throw new TicketWorkflowException('Solo el técnico asignado puede realizar esta operación.', 403);
        }
    }

    private function applyResolution(Ticket $ticket, User $actor, array $resolution): void
    {
        $this->transition($ticket, TicketStatus::InProgress, TicketStatus::Resolved);
        $this->ensureAssignedTo($ticket, $actor);
        $now = now();
        $ticket->update([
            'status' => TicketStatus::Resolved,
            'resolved_at' => $now,
            'resolution' => $resolution,
        ]);
        $this->event($ticket, $actor, 'resolved', TicketStatus::InProgress, TicketStatus::Resolved, $actor->id, $actor->id, null, [
            ...$resolution,
            'resolved_at' => $now->toISOString(),
        ]);
    }

    private function event(Ticket $ticket, User $actor, string $type, TicketStatus $oldStatus, TicketStatus $newStatus, ?int $oldAssignedTo, ?int $newAssignedTo, ?string $reason, array $metadata): void
    {
        $ticket->events()->create([
            'user_id' => $actor->id,
            'event_type' => $type,
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'old_assigned_to' => $oldAssignedTo,
            'new_assigned_to' => $newAssignedTo,
            'description' => match ($type) {
                'claimed' => 'El técnico tomó el servicio.',
                'released' => 'El técnico liberó el servicio.',
                'assigned' => 'El ingeniero asignó el servicio a un técnico.',
                'reassigned' => 'El ingeniero reasignó el servicio a otro técnico.',
                'started' => 'Se inició la atención del servicio.',
                'resolved' => 'El técnico resolvió el servicio.',
                'closed' => 'El servicio fue cerrado definitivamente.',
            },
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
