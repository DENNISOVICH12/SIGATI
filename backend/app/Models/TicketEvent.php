<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'event_type',
        'old_status',
        'new_status',
        'old_assigned_to',
        'new_assigned_to',
        'description',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Ticket al que pertenece el evento.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Usuario que realizó la acción.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Técnico que estaba asignado antes del evento.
     */
    public function oldAssignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_assigned_to');
    }

    /**
     * Técnico que quedó asignado después del evento.
     */
    public function newAssignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_assigned_to');
    }
}