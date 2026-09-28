<?php

namespace App\Models;

use App\Domain\Tickets\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'asset_id',
        'assigned_to',
        'reporter_name',
        'reporter_email',
        'reporter_phone',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'source',
        'reported_at',
        'assigned_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'response_due_at',
        'resolution_due_at',
        'resolution',
    ];

    /**
     * Campos calculados que se incluirán automáticamente
     * cuando el ticket se convierta a JSON.
     */
    protected $appends = [
        'response_sla_status',
        'resolution_sla_status',

        'response_sla_minutes_remaining',
        'resolution_sla_minutes_remaining',

        'response_sla_breached',
        'resolution_sla_breached',

        'response_sla_delay_minutes',
        'response_sla_margin_minutes',

        'resolution_sla_delay_minutes',
        'resolution_sla_margin_minutes',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'assigned_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'status' => TicketStatus::class,
            'resolution' => 'array',
        ];
    }

    /**
     * Activo relacionado con el ticket.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Técnico actualmente asignado al ticket.
     */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Historial completo del ticket.
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | SLA - Primera respuesta
    |--------------------------------------------------------------------------
    */

    public function getResponseSlaStatusAttribute(): string
    {
        if (!$this->response_due_at) {
            return 'not_configured';
        }

        // Ya hubo primera respuesta.
        if ($this->first_response_at) {
            return $this->first_response_at->lte($this->response_due_at)
                ? 'met'
                : 'breached';
        }

        // Todavía no existe primera respuesta.
        $minutesRemaining = now()->diffInMinutes(
            $this->response_due_at,
            false
        );

        if ($minutesRemaining < 0) {
            return 'breached';
        }

        if ($minutesRemaining <= 15) {
            return 'warning';
        }

        return 'on_time';
    }

    public function getResponseSlaMinutesRemainingAttribute(): ?int
    {
        if (!$this->response_due_at) {
            return null;
        }

        // Si ya hubo respuesta, el SLA dejó de estar corriendo.
        if ($this->first_response_at) {
            return 0;
        }

        return (int) now()->diffInMinutes(
            $this->response_due_at,
            false
        );
    }

    public function getResponseSlaBreachedAttribute(): bool
    {
        return $this->response_sla_status === 'breached';
    }

    /*
    |--------------------------------------------------------------------------
    | SLA - Resolución
    |--------------------------------------------------------------------------
    */

    public function getResolutionSlaStatusAttribute(): string
    {
        if (!$this->resolution_due_at) {
            return 'not_configured';
        }

        // El ticket ya fue resuelto.
        if ($this->resolved_at) {
            return $this->resolved_at->lte($this->resolution_due_at)
                ? 'met'
                : 'breached';
        }

        // Todavía no se ha resuelto.
        $minutesRemaining = now()->diffInMinutes(
            $this->resolution_due_at,
            false
        );

        if ($minutesRemaining < 0) {
            return 'breached';
        }

        /*
         * Advertencia cuando queda una hora o menos
         * para incumplir el SLA de resolución.
         */
        if ($minutesRemaining <= 60) {
            return 'warning';
        }

        return 'on_time';
    }

    public function getResolutionSlaMinutesRemainingAttribute(): ?int
    {
        if (!$this->resolution_due_at) {
            return null;
        }

        // Si ya fue resuelto, el SLA dejó de estar corriendo.
        if ($this->resolved_at) {
            return 0;
        }

        return (int) now()->diffInMinutes(
            $this->resolution_due_at,
            false
        );
    }

    public function getResolutionSlaBreachedAttribute(): bool
    {
        return $this->resolution_sla_status === 'breached';
    }
    /**
 * Minutos de retraso en la primera respuesta.
 * Devuelve 0 si el SLA fue cumplido o todavía no se ha incumplido.
 */
public function getResponseSlaDelayMinutesAttribute(): int
{
    if (!$this->response_due_at) {
        return 0;
    }

    $reference = $this->first_response_at ?? now();

    if ($reference->lessThanOrEqualTo($this->response_due_at)) {
        return 0;
    }

    return (int) $this->response_due_at->diffInMinutes($reference);
}


/**
 * Minutos de margen con los que se cumplió el SLA de primera respuesta.
 */
public function getResponseSlaMarginMinutesAttribute(): int
{
    if (!$this->response_due_at || !$this->first_response_at) {
        return 0;
    }

    if ($this->first_response_at->greaterThan($this->response_due_at)) {
        return 0;
    }

    return (int) $this->first_response_at->diffInMinutes(
        $this->response_due_at
    );
}


/**
 * Minutos de retraso en la resolución.
 * Si el ticket aún no ha sido resuelto, utiliza la hora actual.
 */
public function getResolutionSlaDelayMinutesAttribute(): int
{
    if (!$this->resolution_due_at) {
        return 0;
    }

    $reference = $this->resolved_at ?? now();

    if ($reference->lessThanOrEqualTo($this->resolution_due_at)) {
        return 0;
    }

    return (int) $this->resolution_due_at->diffInMinutes($reference);
}


/**
 * Minutos de margen con los que se cumplió el SLA de resolución.
 */
public function getResolutionSlaMarginMinutesAttribute(): int
{
    if (!$this->resolution_due_at || !$this->resolved_at) {
        return 0;
    }

    if ($this->resolved_at->greaterThan($this->resolution_due_at)) {
        return 0;
    }

    return (int) $this->resolved_at->diffInMinutes(
        $this->resolution_due_at
    );
}
}
