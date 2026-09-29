<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tickets\TicketSla;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
 * Listar tickets.
 *
 * Permite filtrar por:
 * - estado
 * - prioridad
 * - técnico asignado
 * - estado del SLA
 * - búsqueda general
 */
public function index(Request $request): JsonResponse
{
    abort_unless(
        $request->user()->can('tickets.view'),
        403
    );

    /*
     * Validamos los filtros recibidos.
     */
    $validated = $request->validate([
        'status' => [
            'nullable',
            Rule::in([
                'new',
                'assigned',
                'in_progress',
                'resolved',
                'closed',
            ]),
        ],

        'priority' => [
            'nullable',
            Rule::in([
                'low',
                'medium',
                'high',
                'critical',
            ]),
        ],

        'assigned_to' => [
            'nullable',
            'integer',
            'exists:users,id',
        ],

        /*
         * Permite consultar únicamente tickets
         * que todavía no tienen técnico asignado.
         *
         * Ejemplo:
         * ?unassigned=1
         */
        'unassigned' => [
            'nullable',
            'boolean',
        ],

        /*
         * SLA general.
         *
         * Evalúa conjuntamente:
         * - primera respuesta
         * - resolución
         */
        'sla' => [
            'nullable',
            Rule::in([
                'on_time',
                'warning',
                'breached',
                'met',
            ]),
        ],

        /*
         * SLA específico de primera respuesta.
         *
         * Ejemplo:
         * ?response_sla=breached
         */
        'response_sla' => [
            'nullable',
            Rule::in([
                'on_time',
                'warning',
                'breached',
                'met',
            ]),
        ],

        /*
         * SLA específico de resolución.
         *
         * Ejemplo:
         * ?resolution_sla=met
         */
        'resolution_sla' => [
            'nullable',
            Rule::in([
                'on_time',
                'warning',
                'breached',
                'met',
            ]),
        ],

        'search' => [
            'nullable',
            'string',
            'max:150',
        ],

        'per_page' => [
            'nullable',
            'integer',
            'min:1',
            'max:100',
        ],
    ]);

    /*
     * Consulta base.
     */
    $query = Ticket::query()
        ->with([
            'asset:id,code,name,category,area_id,location_id,status',
            'asset.area:id,name,code',
            'asset.location:id,area_id,name,code',
            'assignedTechnician:id,name,email',
        ]);

    /*
     * ==========================================================
     * FILTRO POR ESTADO
     * ==========================================================
     */
    if (!empty($validated['status'])) {
        $query->where(
            'status',
            $validated['status']
        );
    }

    /*
     * ==========================================================
     * FILTRO POR PRIORIDAD
     * ==========================================================
     */
    if (!empty($validated['priority'])) {
        $query->where(
            'priority',
            $validated['priority']
        );
    }

    /*
     * ==========================================================
     * FILTRO POR TÉCNICO ASIGNADO
     * ==========================================================
     */
    if (!empty($validated['assigned_to'])) {
        $query->where(
            'assigned_to',
            $validated['assigned_to']
        );
    }

    /*
     * ==========================================================
     * FILTRO DE TICKETS SIN ASIGNAR
     * ==========================================================
     *
     * Ejemplo:
     * ?unassigned=1
     */
    if (!empty($validated['unassigned'])) {
        $query->whereNull('assigned_to');
    }

    /*
     * ==========================================================
     * BÚSQUEDA GENERAL
     * ==========================================================
     *
     * Permite buscar por:
     * - código
     * - título
     * - descripción
     * - nombre del reportante
     * - correo del reportante
     */
    if (!empty($validated['search'])) {

        $search = trim(
            $validated['search']
        );

        $query->where(function ($q) use ($search) {

            $q->where(
                'code',
                'ilike',
                "%{$search}%"
            )
            ->orWhere(
                'title',
                'ilike',
                "%{$search}%"
            )
            ->orWhere(
                'description',
                'ilike',
                "%{$search}%"
            )
            ->orWhere(
                'reporter_name',
                'ilike',
                "%{$search}%"
            )
            ->orWhere(
                'reporter_email',
                'ilike',
                "%{$search}%"
            );
        });
    }

    /*
     * ==========================================================
     * SLA GENERAL
     * ==========================================================
     *
     * Evalúa conjuntamente:
     *
     * - SLA de primera respuesta.
     * - SLA de resolución.
     *
     * IMPORTANTE:
     *
     * Un SLA incumplido continúa siendo incumplido aunque
     * posteriormente se haya respondido o resuelto el ticket.
     *
     * Esto permite conservar correctamente el historial
     * para métricas, reportes y auditoría.
     */
    if (!empty($validated['sla'])) {

        $now = now();

        switch ($validated['sla']) {

            /*
             * ==================================================
             * SLA GENERAL INCUMPLIDO
             * ==================================================
             *
             * Se considera breached cuando:
             *
             * 1. Primera respuesta pendiente y vencida.
             * 2. Primera respuesta realizada fuera del plazo.
             * 3. Resolución pendiente y vencida.
             * 4. Resolución realizada fuera del plazo.
             */
            case 'breached':

                $query->where(function ($q) use ($now) {

                    /*
                     * Primera respuesta pendiente y vencida.
                     */
                    $q->where(function ($response) use ($now) {

                        $response
                            ->whereNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->where(
                                'response_due_at',
                                '<',
                                $now
                            );
                    })

                    /*
                     * Primera respuesta realizada tarde.
                     */
                    ->orWhere(function ($response) {

                        $response
                            ->whereNotNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->whereColumn(
                                'first_response_at',
                                '>',
                                'response_due_at'
                            );
                    })

                    /*
                     * Resolución pendiente y vencida.
                     */
                    ->orWhere(function ($resolution) use ($now) {

                        $resolution
                            ->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->where(
                                'resolution_due_at',
                                '<',
                                $now
                            );
                    })

                    /*
                     * Resolución realizada tarde.
                     */
                    ->orWhere(function ($resolution) {

                        $resolution
                            ->whereNotNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->whereColumn(
                                'resolved_at',
                                '>',
                                'resolution_due_at'
                            );
                    });
                });

                break;

            /*
             * ==================================================
             * SLA GENERAL CUMPLIDO
             * ==================================================
             *
             * Se devuelve el ticket cuando al menos uno de sus
             * SLA finalizados fue cumplido dentro del plazo.
             *
             * Para análisis exactos de cada SLA se recomienda:
             *
             * ?response_sla=met
             * ?resolution_sla=met
             */
            case 'met':

                $query->where(function ($q) {

                    /*
                     * Primera respuesta cumplida.
                     */
                    $q->where(function ($response) {

                        $response
                            ->whereNotNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->whereColumn(
                                'first_response_at',
                                '<=',
                                'response_due_at'
                            );
                    })

                    /*
                     * Resolución cumplida.
                     */
                    ->orWhere(function ($resolution) {

                        $resolution
                            ->whereNotNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->whereColumn(
                                'resolved_at',
                                '<=',
                                'resolution_due_at'
                            );
                    });
                });

                break;

            /*
             * ==================================================
             * SLA GENERAL EN TIEMPO
             * ==================================================
             *
             * Al menos uno de los SLA pendientes permanece
             * fuera de su ventana de advertencia.
             */
            case 'on_time':

                $responseOnTimeLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESPONSE_WARNING_MINUTES);
                $resolutionOnTimeLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESOLUTION_WARNING_MINUTES);

                $query->where(function ($q) use (
                    $responseOnTimeLimit,
                    $resolutionOnTimeLimit
                ) {

                    /*
                     * Primera respuesta todavía en tiempo.
                     */
                    $q->where(function ($response) use (
                        $responseOnTimeLimit
                    ) {

                        $response
                            ->whereNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->where(
                                'response_due_at',
                                '>',
                                $responseOnTimeLimit
                            );
                    })

                    /*
                     * Resolución todavía en tiempo.
                     */
                    ->orWhere(function ($resolution) use (
                        $resolutionOnTimeLimit
                    ) {

                        $resolution
                            ->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->where(
                                'resolution_due_at',
                                '>',
                                $resolutionOnTimeLimit
                            );
                    });
                });

                break;

            /*
             * ==================================================
             * SLA GENERAL EN ADVERTENCIA
             * ==================================================
             *
             * Al menos uno de los SLA pendientes está dentro
             * de su ventana de advertencia.
             */
            case 'warning':

                $responseWarningLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESPONSE_WARNING_MINUTES);
                $resolutionWarningLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESOLUTION_WARNING_MINUTES);

                $query->where(function ($q) use (
                    $now,
                    $responseWarningLimit,
                    $resolutionWarningLimit
                ) {

                    /*
                     * Primera respuesta próxima a vencer.
                     */
                    $q->where(function ($response) use (
                        $now,
                        $responseWarningLimit
                    ) {

                        $response
                            ->whereNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->whereBetween(
                                'response_due_at',
                                [
                                    $now,
                                    $responseWarningLimit,
                                ]
                            );
                    })

                    /*
                     * Resolución próxima a vencer.
                     */
                    ->orWhere(function ($resolution) use (
                        $now,
                        $resolutionWarningLimit
                    ) {

                        $resolution
                            ->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->whereBetween(
                                'resolution_due_at',
                                [
                                    $now,
                                    $resolutionWarningLimit,
                                ]
                            );
                    });
                });

                break;
        }
    }

    /*
     * ==========================================================
     * SLA DE PRIMERA RESPUESTA
     * ==========================================================
     *
     * Ejemplos:
     *
     * ?response_sla=on_time
     * ?response_sla=warning
     * ?response_sla=breached
     * ?response_sla=met
     */
    if (!empty($validated['response_sla'])) {

        $now = now();

        switch ($validated['response_sla']) {

            /*
             * ==================================================
             * PRIMERA RESPUESTA INCUMPLIDA
             * ==================================================
             *
             * Incluye dos escenarios:
             *
             * 1. Todavía no hubo respuesta y el plazo venció.
             * 2. Sí hubo respuesta, pero se realizó tarde.
             */
            case 'breached':

                $query->where(function ($q) use ($now) {

                    /*
                     * Caso 1:
                     * No ha respondido y ya venció.
                     */
                    $q->where(function ($pending) use ($now) {

                        $pending
                            ->whereNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->where(
                                'response_due_at',
                                '<',
                                $now
                            );
                    })

                    /*
                     * Caso 2:
                     * Respondió después del límite.
                     */
                    ->orWhere(function ($late) {

                        $late
                            ->whereNotNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->whereColumn(
                                'first_response_at',
                                '>',
                                'response_due_at'
                            );
                    });
                });

                break;

            /*
             * ==================================================
             * PRIMERA RESPUESTA CUMPLIDA
             * ==================================================
             *
             * Hubo respuesta antes o exactamente
             * en la fecha límite.
             */
            case 'met':

                $query
                    ->whereNotNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->whereColumn(
                        'first_response_at',
                        '<=',
                        'response_due_at'
                    );

                break;

            /*
             * ==================================================
             * PRIMERA RESPUESTA EN TIEMPO
             * ==================================================
             *
             * Todavía no hay respuesta y quedan
             * más de 15 minutos.
             */
            case 'on_time':

                $onTimeLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESPONSE_WARNING_MINUTES);

                $query
                    ->whereNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->where(
                        'response_due_at',
                        '>',
                        $onTimeLimit
                    );

                break;

            /*
             * ==================================================
             * PRIMERA RESPUESTA EN ADVERTENCIA
             * ==================================================
             *
             * Todavía no hay respuesta y faltan
             * entre 0 y 15 minutos para vencer.
             */
            case 'warning':

                $warningLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESPONSE_WARNING_MINUTES);

                $query
                    ->whereNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->whereBetween(
                        'response_due_at',
                        [
                            $now,
                            $warningLimit,
                        ]
                    );

                break;
        }
    }

    /*
     * ==========================================================
     * SLA DE RESOLUCIÓN
     * ==========================================================
     *
     * Ejemplos:
     *
     * ?resolution_sla=on_time
     * ?resolution_sla=warning
     * ?resolution_sla=breached
     * ?resolution_sla=met
     */
    if (!empty($validated['resolution_sla'])) {

        $now = now();

        switch ($validated['resolution_sla']) {

            /*
             * ==================================================
             * RESOLUCIÓN INCUMPLIDA
             * ==================================================
             *
             * Incluye dos escenarios:
             *
             * 1. El ticket sigue sin resolver y el plazo venció.
             * 2. El ticket fue resuelto después del plazo.
             */
            case 'breached':

                $query->where(function ($q) use ($now) {

                    /*
                     * Caso 1:
                     * Sigue pendiente y ya venció.
                     */
                    $q->where(function ($pending) use ($now) {

                        $pending
                            ->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->where(
                                'resolution_due_at',
                                '<',
                                $now
                            );
                    })

                    /*
                     * Caso 2:
                     * Fue resuelto después del límite.
                     */
                    ->orWhere(function ($late) {

                        $late
                            ->whereNotNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->whereColumn(
                                'resolved_at',
                                '>',
                                'resolution_due_at'
                            );
                    });
                });

                break;

            /*
             * ==================================================
             * RESOLUCIÓN CUMPLIDA
             * ==================================================
             *
             * El ticket fue resuelto antes o exactamente
             * en la fecha límite.
             */
            case 'met':

                $query
                    ->whereNotNull('resolved_at')
                    ->whereNotNull('resolution_due_at')
                    ->whereColumn(
                        'resolved_at',
                        '<=',
                        'resolution_due_at'
                    );

                break;

            /*
             * ==================================================
             * RESOLUCIÓN EN TIEMPO
             * ==================================================
             *
             * El ticket todavía no está resuelto
             * y permanece fuera de la ventana de advertencia.
             */
            case 'on_time':

                $onTimeLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESOLUTION_WARNING_MINUTES);

                $query
                    ->whereNull('resolved_at')
                    ->whereNotNull('resolution_due_at')
                    ->where(
                        'resolution_due_at',
                        '>',
                        $onTimeLimit
                    );

                break;

            /*
             * ==================================================
             * RESOLUCIÓN EN ADVERTENCIA
             * ==================================================
             *
             * El ticket todavía no está resuelto
             * y está dentro de la ventana de advertencia.
             */
            case 'warning':

                $warningLimit = $now
                    ->copy()
                    ->addMinutes(TicketSla::RESOLUTION_WARNING_MINUTES);

                $query
                    ->whereNull('resolved_at')
                    ->whereNotNull('resolution_due_at')
                    ->whereBetween(
                        'resolution_due_at',
                        [
                            $now,
                            $warningLimit,
                        ]
                    );

                break;
        }
    }

    /*
     * ==========================================================
     * ORDEN Y PAGINACIÓN
     * ==========================================================
     *
     * 1. Tickets más recientes primero.
     * 2. ID como criterio secundario.
     */
    $tickets = $query
        ->orderByDesc('reported_at')
        ->orderByDesc('id')
        ->paginate(
            $validated['per_page'] ?? 20
        )
        ->withQueryString();

    return response()->json($tickets);
}

/**
 * Obtener estadísticas generales de tickets.
 *
 * Este endpoint está pensado principalmente para alimentar
 * el dashboard de SIGATI sin necesidad de descargar todos
 * los tickets al frontend y realizar los cálculos allí.
 *
 * Incluye:
 * - total de tickets
 * - cantidad por estado
 * - tickets sin asignar
 * - SLA de primera respuesta
 * - SLA de resolución
 * - resumen general de SLA
 */
public function stats(Request $request): JsonResponse
{
    abort_unless(
        $request->user()->can('tickets.view'),
        403
    );

    $now = now();

    $responseWarningLimit = $now
        ->copy()
        ->addMinutes(TicketSla::RESPONSE_WARNING_MINUTES);
    $resolutionWarningLimit = $now
        ->copy()
        ->addMinutes(TicketSla::RESOLUTION_WARNING_MINUTES);

    /*
     * ==========================================================
     * ESTADÍSTICAS GENERALES
     * ==========================================================
     */
    $total = Ticket::query()->count();

    $new = Ticket::query()
        ->where('status', 'new')
        ->count();

    $assigned = Ticket::query()
        ->where('status', 'assigned')
        ->count();

    $inProgress = Ticket::query()
        ->where('status', 'in_progress')
        ->count();

    $resolved = Ticket::query()
        ->where('status', 'resolved')
        ->count();

    $closed = Ticket::query()
        ->where('status', 'closed')
        ->count();

    $unassigned = Ticket::query()
        ->whereNull('assigned_to')
        ->whereNotIn('status', [
            'resolved',
            'closed',
        ])
        ->count();

    /*
     * ==========================================================
     * SLA DE PRIMERA RESPUESTA
     * ==========================================================
     */

    /*
     * Primera respuesta todavía pendiente,
     * pero con más de 15 minutos disponibles.
     */
    $responseOnTime = Ticket::query()
        ->whereNull('first_response_at')
        ->whereNotNull('response_due_at')
        ->where(
            'response_due_at',
            '>',
            $responseWarningLimit
        )
        ->count();

    /*
     * Primera respuesta pendiente y próxima a vencer.
     */
    $responseWarning = Ticket::query()
        ->whereNull('first_response_at')
        ->whereNotNull('response_due_at')
        ->whereBetween(
            'response_due_at',
            [
                $now,
                $responseWarningLimit,
            ]
        )
        ->count();

    /*
     * Primera respuesta incumplida.
     *
     * Incluye:
     * 1. Sigue pendiente y ya venció.
     * 2. Se respondió, pero después del límite.
     */
    $responseBreached = Ticket::query()
        ->where(function ($query) use ($now) {

            $query
                ->where(function ($pending) use ($now) {

                    $pending
                        ->whereNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->where(
                            'response_due_at',
                            '<',
                            $now
                        );
                })
                ->orWhere(function ($late) {

                    $late
                        ->whereNotNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->whereColumn(
                            'first_response_at',
                            '>',
                            'response_due_at'
                        );
                });
        })
        ->count();

    /*
     * Primera respuesta cumplida dentro del plazo.
     */
    $responseMet = Ticket::query()
        ->whereNotNull('first_response_at')
        ->whereNotNull('response_due_at')
        ->whereColumn(
            'first_response_at',
            '<=',
            'response_due_at'
        )
        ->count();

    /*
     * ==========================================================
     * SLA DE RESOLUCIÓN
     * ==========================================================
     */

    /*
     * Resolución pendiente y todavía con más
     * de 15 minutos disponibles.
     */
    $resolutionOnTime = Ticket::query()
        ->whereNull('resolved_at')
        ->whereNotNull('resolution_due_at')
        ->where(
            'resolution_due_at',
            '>',
            $resolutionWarningLimit
        )
        ->count();

    /*
     * Resolución pendiente y próxima a vencer.
     */
    $resolutionWarning = Ticket::query()
        ->whereNull('resolved_at')
        ->whereNotNull('resolution_due_at')
        ->whereBetween(
            'resolution_due_at',
            [
                $now,
                $resolutionWarningLimit,
            ]
        )
        ->count();

    /*
     * Resolución incumplida.
     *
     * Incluye:
     * 1. Sigue pendiente y el plazo ya venció.
     * 2. Fue resuelto después del límite.
     */
    $resolutionBreached = Ticket::query()
        ->where(function ($query) use ($now) {

            $query
                ->where(function ($pending) use ($now) {

                    $pending
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->where(
                            'resolution_due_at',
                            '<',
                            $now
                        );
                })
                ->orWhere(function ($late) {

                    $late
                        ->whereNotNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->whereColumn(
                            'resolved_at',
                            '>',
                            'resolution_due_at'
                        );
                });
        })
        ->count();

    /*
     * Resolución cumplida dentro del plazo.
     */
    $resolutionMet = Ticket::query()
        ->whereNotNull('resolved_at')
        ->whereNotNull('resolution_due_at')
        ->whereColumn(
            'resolved_at',
            '<=',
            'resolution_due_at'
        )
        ->count();

    /*
     * ==========================================================
     * SLA GENERAL
     * ==========================================================
     *
     * Un ticket se considera con SLA general incumplido
     * cuando incumplió al menos uno de los dos SLA:
     *
     * - primera respuesta
     * - resolución
     *
     * Usamos una única consulta para evitar contar dos veces
     * un ticket que haya incumplido ambos.
     */
    $slaBreached = Ticket::query()
        ->where(function ($query) use ($now) {

            /*
             * Primera respuesta pendiente y vencida.
             */
            $query->where(function ($response) use ($now) {

                $response
                    ->whereNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->where(
                        'response_due_at',
                        '<',
                        $now
                    );
            })

            /*
             * Primera respuesta realizada tarde.
             */
            ->orWhere(function ($response) {

                $response
                    ->whereNotNull('first_response_at')
                    ->whereNotNull('response_due_at')
                    ->whereColumn(
                        'first_response_at',
                        '>',
                        'response_due_at'
                    );
            })

            /*
             * Resolución pendiente y vencida.
             */
            ->orWhere(function ($resolution) use ($now) {

                $resolution
                    ->whereNull('resolved_at')
                    ->whereNotNull('resolution_due_at')
                    ->where(
                        'resolution_due_at',
                        '<',
                        $now
                    );
            })

            /*
             * Resolución realizada tarde.
             */
            ->orWhere(function ($resolution) {

                $resolution
                    ->whereNotNull('resolved_at')
                    ->whereNotNull('resolution_due_at')
                    ->whereColumn(
                        'resolved_at',
                        '>',
                        'resolution_due_at'
                    );
            });
        })
        ->count();

    /*
     * Tickets que actualmente tienen al menos
     * un SLA en estado de advertencia.
     */
    $slaWarning = Ticket::query()
        ->where(function ($query) use (
            $now,
            $responseWarningLimit,
            $resolutionWarningLimit
        ) {

            $query
                ->where(function ($response) use (
                    $now,
                    $responseWarningLimit
                ) {

                    $response
                        ->whereNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->whereBetween(
                            'response_due_at',
                            [
                                $now,
                                $responseWarningLimit,
                            ]
                        );
                })
                ->orWhere(function ($resolution) use (
                    $now,
                    $resolutionWarningLimit
                ) {

                    $resolution
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->whereBetween(
                            'resolution_due_at',
                            [
                                $now,
                                $resolutionWarningLimit,
                            ]
                        );
                });
        })
        ->count();

    /*
     * Tickets que actualmente tienen al menos
     * un SLA pendiente fuera de su ventana de advertencia.
     */
    $slaOnTime = Ticket::query()
        ->where(function ($query) use (
            $responseWarningLimit,
            $resolutionWarningLimit
        ) {

            $query
                ->where(function ($response) use ($responseWarningLimit) {

                    $response
                        ->whereNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->where(
                            'response_due_at',
                            '>',
                            $responseWarningLimit
                        );
                })
                ->orWhere(function ($resolution) use ($resolutionWarningLimit) {

                    $resolution
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->where(
                            'resolution_due_at',
                            '>',
                            $resolutionWarningLimit
                        );
                });
        })
        ->count();

    /*
     * ==========================================================
     * RESPUESTA
     * ==========================================================
     */
    return response()->json([
        'generated_at' => $now->toISOString(),

        'tickets' => [
            'total' => $total,

            'by_status' => [
                'new' => $new,
                'assigned' => $assigned,
                'in_progress' => $inProgress,
                'resolved' => $resolved,
                'closed' => $closed,
            ],

            'unassigned' => $unassigned,
        ],

        'response_sla' => [
            'on_time' => $responseOnTime,
            'warning' => $responseWarning,
            'breached' => $responseBreached,
            'met' => $responseMet,
        ],

        'resolution_sla' => [
            'on_time' => $resolutionOnTime,
            'warning' => $resolutionWarning,
            'breached' => $resolutionBreached,
            'met' => $resolutionMet,
        ],

        'sla' => [
            'on_time' => $slaOnTime,
            'warning' => $slaWarning,
            'breached' => $slaBreached,
        ],
    ]);
}
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()->can('tickets.view'), 403);

        $ticket->load([
            'asset:id,code,name,category,area_id,location_id,status',
            'asset.area:id,name,code',
            'asset.location:id,area_id,name,code',
            'assignedTechnician:id,name,email',
            'events.user:id,name,email',
            'events.oldAssignedTechnician:id,name,email',
            'events.newAssignedTechnician:id,name,email',
        ]);

        return response()->json([
            'ticket' => $ticket,
        ]);
    }

    /**
     * Crear un nuevo ticket.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('tickets.create'), 403);

        $validated = $request->validated();

        $ticket = DB::transaction(function () use ($validated, $request) {

            /*
             * Si el ticket está relacionado con un activo,
             * comprobamos que el activo siga existiendo.
             */
            if (!empty($validated['asset_id'])) {
                Asset::query()
                    ->whereKey($validated['asset_id'])
                    ->firstOrFail();
            }

            $reportedAt = now();

            /*
             * SLA inicial.
             *
             * Estos tiempos son provisionales para el desarrollo.
             * Posteriormente serán configurables y deberán ser
             * validados con el hospital.
             */
            $sla = match ($validated['priority'] ?? 'medium') {
                'critical' => [
                    'response' => 15,
                    'resolution' => 120,
                ],

                'high' => [
                    'response' => 30,
                    'resolution' => 240,
                ],

                'medium' => [
                    'response' => 60,
                    'resolution' => 480,
                ],

                'low' => [
                    'response' => 120,
                    'resolution' => 1440,
                ],
            };

            /*
             * Generamos un código no secuencial para evitar
             * depender del ID de base de datos como código visible.
             */
            do {
                $code = 'TCK-' .
                    now()->format('Ymd') .
                    '-' .
                    strtoupper(Str::random(6));
            } while (
                Ticket::query()
                    ->where('code', $code)
                    ->exists()
            );

            $ticket = Ticket::create([
                'code' => $code,

                'asset_id' => $validated['asset_id'] ?? null,
                'assigned_to' => null,

                'reporter_name' => $validated['reporter_name'],
                'reporter_email' => $validated['reporter_email'] ?? null,
                'reporter_phone' => $validated['reporter_phone'] ?? null,

                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],

                'priority' => $validated['priority'] ?? 'medium',
                'status' => 'new',

                'source' => $validated['source'] ?? 'internal',

                'reported_at' => $reportedAt,

                'response_due_at' => $reportedAt
                    ->copy()
                    ->addMinutes($sla['response']),

                'resolution_due_at' => $reportedAt
                    ->copy()
                    ->addMinutes($sla['resolution']),
            ]);

            /*
             * Primer evento del historial.
             */
            $ticket->events()->create([
                'user_id' => $request->user()->id,

                'event_type' => 'created',

                'old_status' => null,
                'new_status' => 'new',

                'old_assigned_to' => null,
                'new_assigned_to' => null,

                'description' => 'Ticket creado.',

                'metadata' => [
                    'source' => $ticket->source,
                    'priority' => $ticket->priority,
                    'asset_id' => $ticket->asset_id,
                    'response_due_at' => $ticket->response_due_at?->toISOString(),
                    'resolution_due_at' => $ticket->resolution_due_at?->toISOString(),
                ],
            ]);

            return $ticket;
        });

        $ticket->load([
            'asset:id,code,name,category,area_id,location_id,status',
            'asset.area:id,name,code',
            'asset.location:id,area_id,name,code',
            'assignedTechnician:id,name,email',
            'events.user:id,name,email',
        ]);

        return response()->json([
            'message' => 'Ticket creado correctamente.',
            'ticket' => $ticket,
        ], 201);
    }
    /**
     * Permitir que un técnico tome un ticket disponible.
     */
    public function claim(Request $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->can('tickets.claim') && $request->user()->hasRole('technician'), 403);

        return $this->workflowResponse(
            $workflow->claim($ticket->id, $request->user()),
            'Servicio tomado correctamente.'
        );
    }
    /**
     * Permitir que un técnico desista de un servicio
     * que actualmente tiene asignado.
     */
    public function release(Request $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->can('tickets.claim') && $request->user()->hasRole('technician'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u'],
        ]);

        return $this->workflowResponse(
            $workflow->release($ticket->id, $request->user(), trim($validated['reason'])),
            'Servicio liberado correctamente.'
        );
    }
    /**
     * Asignar o reasignar un ticket a un técnico específico.
     *
     * Esta operación está destinada principalmente al ingeniero,
     * quien debe contar con el permiso tickets.assign.
     */
    public function assign(Request $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->can('tickets.assign'), 403);

        $validated = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000', 'not_regex:/^\s*$/u'],
        ]);
        $technician = User::query()->findOrFail($validated['technician_id']);
        $reason = isset($validated['reason']) ? trim($validated['reason']) : null;

        return $this->workflowResponse(
            $workflow->assign($ticket->id, $request->user(), $technician, $reason),
            'Técnico asignado correctamente.'
        );
    }
    /**
     * Iniciar la atención de un ticket.
     *
     * Solo puede hacerlo el técnico actualmente asignado
     * o un usuario con capacidad administrativa sobre tickets.
     */
    public function start(Request $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->can('tickets.update') && $request->user()->hasRole('technician'), 403);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000', 'not_regex:/^\s*$/u'],
        ]);
        $note = isset($validated['note']) ? trim($validated['note']) : null;

        return $this->workflowResponse(
            $workflow->start($ticket->id, $request->user(), $note),
            'Atención del servicio iniciada correctamente.'
        );
    }
    /**
     * Resolver un ticket.
     *
     * Solo el técnico que tiene asignado el servicio puede resolverlo.
     * El ticket debe estar actualmente en progreso.
     */
    public function resolve(Request $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->can('tickets.update') && $request->user()->hasRole('technician'), 403);

        $validated = $request->validate([
            'diagnosis' => ['required', 'string', 'max:3000', 'not_regex:/^\s*$/u'],
            'solution' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'],
            'notes' => ['nullable', 'string', 'max:3000', 'not_regex:/^\s*$/u'],
            'observations' => ['nullable', 'string', 'max:3000', 'not_regex:/^\s*$/u'],
        ]);
        $notes = $validated['notes'] ?? $validated['observations'] ?? null;

        return $this->workflowResponse(
            $workflow->resolve($ticket->id, $request->user(), [
                'diagnosis' => trim($validated['diagnosis']),
                'solution' => trim($validated['solution']),
                'notes' => $notes === null ? null : trim($notes),
            ]),
            'Servicio resuelto correctamente.'
        );
    }
    /**
     * Cerrar definitivamente un ticket resuelto.
     *
     * Solo un usuario con el permiso tickets.close puede realizar
     * el cierre administrativo del servicio.
     */
    public function close(Request $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        abort_unless($request->user()->can('tickets.close') && $request->user()->hasRole('engineer'), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000', 'not_regex:/^\s*$/u'],
        ]);

        return $this->workflowResponse(
            $workflow->close($ticket->id, $request->user(), trim($validated['reason'])),
            'Servicio cerrado correctamente.'
        );
    }


    private function workflowResponse(Ticket $ticket, string $message): JsonResponse
    {
        $ticket->load([
            'asset:id,code,name,category,area_id,location_id,status',
            'asset.area:id,name,code',
            'asset.location:id,area_id,name,code',
            'assignedTechnician:id,name,email',
            'events.user:id,name,email',
            'events.oldAssignedTechnician:id,name,email',
            'events.newAssignedTechnician:id,name,email',
        ]);

        return response()->json([
            'message' => $message,
            'ticket' => $ticket,
        ]);
    }
}
