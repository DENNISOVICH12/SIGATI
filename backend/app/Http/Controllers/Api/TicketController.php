<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Ticket;
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
             * Al menos uno de los SLA pendientes dispone
             * de más de 15 minutos antes de vencer.
             */
            case 'on_time':

                $onTimeLimit = $now
                    ->copy()
                    ->addMinutes(15);

                $query->where(function ($q) use (
                    $onTimeLimit
                ) {

                    /*
                     * Primera respuesta todavía en tiempo.
                     */
                    $q->where(function ($response) use (
                        $onTimeLimit
                    ) {

                        $response
                            ->whereNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->where(
                                'response_due_at',
                                '>',
                                $onTimeLimit
                            );
                    })

                    /*
                     * Resolución todavía en tiempo.
                     */
                    ->orWhere(function ($resolution) use (
                        $onTimeLimit
                    ) {

                        $resolution
                            ->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->where(
                                'resolution_due_at',
                                '>',
                                $onTimeLimit
                            );
                    });
                });

                break;

            /*
             * ==================================================
             * SLA GENERAL EN ADVERTENCIA
             * ==================================================
             *
             * Al menos uno de los SLA pendientes vence
             * dentro de los próximos 15 minutos.
             */
            case 'warning':

                $warningLimit = $now
                    ->copy()
                    ->addMinutes(15);

                $query->where(function ($q) use (
                    $now,
                    $warningLimit
                ) {

                    /*
                     * Primera respuesta próxima a vencer.
                     */
                    $q->where(function ($response) use (
                        $now,
                        $warningLimit
                    ) {

                        $response
                            ->whereNull('first_response_at')
                            ->whereNotNull('response_due_at')
                            ->whereBetween(
                                'response_due_at',
                                [
                                    $now,
                                    $warningLimit,
                                ]
                            );
                    })

                    /*
                     * Resolución próxima a vencer.
                     */
                    ->orWhere(function ($resolution) use (
                        $now,
                        $warningLimit
                    ) {

                        $resolution
                            ->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->whereBetween(
                                'resolution_due_at',
                                [
                                    $now,
                                    $warningLimit,
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
                    ->addMinutes(15);

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
                    ->addMinutes(15);

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
             * y quedan más de 15 minutos.
             */
            case 'on_time':

                $onTimeLimit = $now
                    ->copy()
                    ->addMinutes(15);

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
             * y faltan entre 0 y 15 minutos.
             */
            case 'warning':

                $warningLimit = $now
                    ->copy()
                    ->addMinutes(15);

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

    /*
     * Límite utilizado para determinar cuándo un SLA
     * activo entra en estado de advertencia.
     *
     * Actualmente:
     * 0 a 15 minutos = warning
     * más de 15 minutos = on_time
     */
    $warningLimit = $now
        ->copy()
        ->addMinutes(15);

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
            $warningLimit
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
                $warningLimit,
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
            $warningLimit
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
                $warningLimit,
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
            $warningLimit
        ) {

            $query
                ->where(function ($response) use (
                    $now,
                    $warningLimit
                ) {

                    $response
                        ->whereNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->whereBetween(
                            'response_due_at',
                            [
                                $now,
                                $warningLimit,
                            ]
                        );
                })
                ->orWhere(function ($resolution) use (
                    $now,
                    $warningLimit
                ) {

                    $resolution
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->whereBetween(
                            'resolution_due_at',
                            [
                                $now,
                                $warningLimit,
                            ]
                        );
                });
        })
        ->count();

    /*
     * Tickets que actualmente tienen al menos
     * un SLA pendiente y todavía disponen de
     * más de 15 minutos.
     */
    $slaOnTime = Ticket::query()
        ->where(function ($query) use ($warningLimit) {

            $query
                ->where(function ($response) use ($warningLimit) {

                    $response
                        ->whereNull('first_response_at')
                        ->whereNotNull('response_due_at')
                        ->where(
                            'response_due_at',
                            '>',
                            $warningLimit
                        );
                })
                ->orWhere(function ($resolution) use ($warningLimit) {

                    $resolution
                        ->whereNull('resolved_at')
                        ->whereNotNull('resolution_due_at')
                        ->where(
                            'resolution_due_at',
                            '>',
                            $warningLimit
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
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tickets.create'), 403);

        $validated = $request->validate([
            'asset_id' => [
                'nullable',
                'integer',
                'exists:assets,id',
            ],

            'reporter_name' => [
                'required',
                'string',
                'max:150',
            ],

            'reporter_email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'reporter_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'description' => [
                'required',
                'string',
                'max:5000',
            ],

            'category' => [
                'required',
                'string',
                'max:80',
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

            'source' => [
                'nullable',
                Rule::in([
                    'internal',
                    'qr',
                    'phone',
                    'email',
                    'manual',
                ]),
            ],
        ]);

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
public function claim(Request $request, Ticket $ticket): JsonResponse
{
    abort_unless($request->user()->can('tickets.claim'), 403);

    $claimedTicket = DB::transaction(function () use ($ticket, $request) {

        /*
         * Volvemos a consultar el ticket aplicando bloqueo de fila.
         *
         * Esto evita que dos técnicos puedan tomar el mismo
         * ticket simultáneamente.
         */
        $lockedTicket = Ticket::query()
            ->whereKey($ticket->id)
            ->lockForUpdate()
            ->firstOrFail();

        /*
         * Solo se puede tomar un ticket que actualmente
         * no tenga técnico asignado.
         */
        if ($lockedTicket->assigned_to !== null) {
            abort(409, 'Este ticket ya fue tomado por otro técnico.');
        }

        /*
         * No permitimos tomar tickets que ya hayan finalizado.
         */
        if (in_array($lockedTicket->status, [
            'resolved',
            'closed',
        ], true)) {
            abort(409, 'Este ticket ya no está disponible para ser tomado.');
        }

        $oldStatus = $lockedTicket->status;
        $now = now();

        $lockedTicket->update([
            'assigned_to' => $request->user()->id,
            'status' => 'assigned',
            'assigned_at' => $now,

            /*
             * La primera vez que alguien toma el ticket
             * registramos la primera respuesta.
             *
             * Si posteriormente se libera y otro técnico
             * lo toma, este valor NO se sobrescribe.
             */
            'first_response_at' => $lockedTicket->first_response_at ?? $now,
        ]);

        /*
         * Registramos la acción en el historial.
         */
        $lockedTicket->events()->create([
            'user_id' => $request->user()->id,

            'event_type' => 'claimed',

            'old_status' => $oldStatus,
            'new_status' => 'assigned',

            'old_assigned_to' => null,
            'new_assigned_to' => $request->user()->id,

            'description' => 'El técnico tomó el servicio.',

            'metadata' => [
                'assigned_at' => $now->toISOString(),
            ],
        ]);

        return $lockedTicket;
    });

    $claimedTicket->load([
        'asset:id,code,name,category,area_id,location_id,status',
        'asset.area:id,name,code',
        'asset.location:id,area_id,name,code',
        'assignedTechnician:id,name,email',

        'events.user:id,name,email',
        'events.oldAssignedTechnician:id,name,email',
        'events.newAssignedTechnician:id,name,email',
    ]);

    return response()->json([
        'message' => 'Servicio tomado correctamente.',
        'ticket' => $claimedTicket,
    ]);
}
/**
 * Permitir que un técnico desista de un servicio
 * que actualmente tiene asignado.
 */
public function release(Request $request, Ticket $ticket): JsonResponse
{
    abort_unless($request->user()->can('tickets.claim'), 403);

    $validated = $request->validate([
        'reason' => [
            'required',
            'string',
            'max:1000',
        ],
    ]);

    $releasedTicket = DB::transaction(function () use (
        $ticket,
        $request,
        $validated
    ) {
        /*
         * Bloqueamos la fila para evitar cambios simultáneos
         * mientras se libera el ticket.
         */
        $lockedTicket = Ticket::query()
            ->whereKey($ticket->id)
            ->lockForUpdate()
            ->firstOrFail();

        /*
         * El ticket debe tener actualmente un técnico.
         */
        if ($lockedTicket->assigned_to === null) {
            abort(409, 'Este ticket ya se encuentra disponible.');
        }

        /*
         * Un técnico solamente puede desistir de un servicio
         * que esté asignado a él mismo.
         */
        if ((int) $lockedTicket->assigned_to !== (int) $request->user()->id) {
            abort(
                403,
                'No puedes desistir de un servicio asignado a otro técnico.'
            );
        }

        /*
         * No se pueden liberar tickets que ya terminaron.
         */
        if (in_array($lockedTicket->status, [
            'resolved',
            'closed',
        ], true)) {
            abort(
                409,
                'No se puede desistir de un ticket que ya fue finalizado.'
            );
        }

        $oldStatus = $lockedTicket->status;
        $oldAssignedTo = $lockedTicket->assigned_to;
        $now = now();

        /*
         * Dejamos nuevamente disponible el ticket.
         *
         * first_response_at NO se borra porque representa
         * cuándo ocurrió la primera atención real.
         *
         * assigned_at sí se limpia porque actualmente
         * ya no existe un técnico asignado.
         */
        $lockedTicket->update([
            'assigned_to' => null,
            'status' => 'new',
            'assigned_at' => null,
        ]);

        /*
         * Registramos quién desistió, cuándo y por qué.
         */
        $lockedTicket->events()->create([
            'user_id' => $request->user()->id,

            'event_type' => 'released',

            'old_status' => $oldStatus,
            'new_status' => 'new',

            'old_assigned_to' => $oldAssignedTo,
            'new_assigned_to' => null,

            'description' => 'El técnico desistió del servicio.',

            'reason' => $validated['reason'],

            'metadata' => [
                'released_at' => $now->toISOString(),
            ],
        ]);

        return $lockedTicket;
    });

    $releasedTicket->load([
        'asset:id,code,name,category,area_id,location_id,status',
        'asset.area:id,name,code',
        'asset.location:id,area_id,name,code',
        'assignedTechnician:id,name,email',

        'events.user:id,name,email',
        'events.oldAssignedTechnician:id,name,email',
        'events.newAssignedTechnician:id,name,email',
    ]);

    return response()->json([
        'message' => 'Servicio liberado correctamente.',
        'ticket' => $releasedTicket,
    ]);
}
/**
 * Asignar o reasignar un ticket a un técnico específico.
 *
 * Esta operación está destinada principalmente al ingeniero,
 * quien debe contar con el permiso tickets.assign.
 */
public function assign(Request $request, Ticket $ticket): JsonResponse
{
    abort_unless($request->user()->can('tickets.assign'), 403);

    $validated = $request->validate([
        'technician_id' => [
            'required',
            'integer',
            'exists:users,id',
        ],

        'reason' => [
            'required',
            'string',
            'max:1000',
        ],
    ]);

    $assignedTicket = DB::transaction(function () use (
        $ticket,
        $request,
        $validated
    ) {
        /*
         * Bloqueamos el ticket mientras se realiza la asignación.
         *
         * Esto evita conflictos si un técnico intenta tomar
         * el servicio exactamente al mismo tiempo.
         */
        $lockedTicket = Ticket::query()
            ->whereKey($ticket->id)
            ->lockForUpdate()
            ->firstOrFail();

        /*
         * No permitimos modificar tickets ya finalizados.
         */
        if (in_array($lockedTicket->status, [
            'resolved',
            'closed',
        ], true)) {
            abort(
                409,
                'No se puede asignar un técnico a un ticket finalizado.'
            );
        }

        /*
         * Obtenemos el usuario seleccionado.
         */
        $technician = \App\Models\User::query()
            ->findOrFail($validated['technician_id']);

        /*
         * Verificamos que realmente tenga el rol technician.
         *
         * No basta con que el ID del usuario exista.
         */
        if (!$technician->hasRole('technician')) {
            abort(
                422,
                'El usuario seleccionado no tiene el rol de técnico.'
            );
        }

        /*
         * Evitamos una reasignación innecesaria al mismo técnico.
         */
        if (
            $lockedTicket->assigned_to !== null &&
            (int) $lockedTicket->assigned_to === (int) $technician->id
        ) {
            abort(
                409,
                'Este ticket ya se encuentra asignado al técnico seleccionado.'
            );
        }

        $oldStatus = $lockedTicket->status;
        $oldAssignedTo = $lockedTicket->assigned_to;

        $now = now();

        /*
         * Asignamos el servicio.
         *
         * first_response_at solamente se establece si todavía
         * no existe una primera respuesta registrada.
         */
        $lockedTicket->update([
            'assigned_to' => $technician->id,
            'status' => 'assigned',
            'assigned_at' => $now,
            'first_response_at' => $lockedTicket->first_response_at ?? $now,
        ]);

        /*
         * Determinamos si se trata de una asignación inicial
         * o de una reasignación.
         */
        $eventType = $oldAssignedTo === null
            ? 'assigned'
            : 'reassigned';

        $description = $oldAssignedTo === null
            ? 'El ingeniero asignó el servicio a un técnico.'
            : 'El ingeniero reasignó el servicio a otro técnico.';

        /*
         * Registramos toda la trazabilidad.
         */
        $lockedTicket->events()->create([
            'user_id' => $request->user()->id,

            'event_type' => $eventType,

            'old_status' => $oldStatus,
            'new_status' => 'assigned',

            'old_assigned_to' => $oldAssignedTo,
            'new_assigned_to' => $technician->id,

            'description' => $description,

            'reason' => $validated['reason'],

            'metadata' => [
                'assigned_at' => $now->toISOString(),
                'assigned_by' => $request->user()->id,
                'technician_id' => $technician->id,
            ],
        ]);

        return $lockedTicket;
    });

    /*
     * Cargamos toda la información necesaria para devolver
     * inmediatamente el ticket actualizado al frontend.
     */
    $assignedTicket->load([
        'asset:id,code,name,category,area_id,location_id,status',
        'asset.area:id,name,code',
        'asset.location:id,area_id,name,code',

        'assignedTechnician:id,name,email',

        'events.user:id,name,email',
        'events.oldAssignedTechnician:id,name,email',
        'events.newAssignedTechnician:id,name,email',
    ]);

    return response()->json([
        'message' => 'Técnico asignado correctamente.',
        'ticket' => $assignedTicket,
    ]);
}
/**
 * Iniciar la atención de un ticket.
 *
 * Solo puede hacerlo el técnico actualmente asignado
 * o un usuario con capacidad administrativa sobre tickets.
 */
public function start(Request $request, Ticket $ticket): JsonResponse
{
    abort_unless(
        $request->user()->can('tickets.update'),
        403
    );

    if ($ticket->status !== 'assigned') {
        return response()->json([
            'message' => 'Solo se puede iniciar la atención de un ticket asignado.',
        ], 422);
    }

    if (!$ticket->assigned_to) {
        return response()->json([
            'message' => 'El ticket no tiene un técnico asignado.',
        ], 422);
    }

    $user = $request->user();

    $isAssignedTechnician = (int) $ticket->assigned_to === (int) $user->id;
    $canAdministrate = $user->can('tickets.assign');

    if (!$isAssignedTechnician && !$canAdministrate) {
        return response()->json([
            'message' => 'Este servicio está asignado a otro técnico.',
        ], 403);
    }

    $validated = $request->validate([
        'note' => [
            'nullable',
            'string',
            'max:2000',
        ],
    ]);

    $oldStatus = $ticket->status;

    $ticket->update([
        'status' => 'in_progress',
        'first_response_at' => $ticket->first_response_at ?? now(),
    ]);

    $ticket->events()->create([
        'user_id' => $user->id,
        'event_type' => 'started',
        'old_status' => $oldStatus,
        'new_status' => 'in_progress',
        'old_assigned_to' => $ticket->assigned_to,
        'new_assigned_to' => $ticket->assigned_to,
        'description' => 'Se inició la atención del servicio.',
        'reason' => $validated['note'] ?? null,
        'metadata' => [
            'started_at' => now()->toISOString(),
        ],
    ]);

    return response()->json([
        'message' => 'Atención del servicio iniciada correctamente.',
        'ticket' => $ticket->fresh([
            'asset.area',
            'asset.location',
            'assignedTechnician:id,name,email',
            'events.user:id,name,email',
            'events.oldAssignedTechnician:id,name,email',
            'events.newAssignedTechnician:id,name,email',
        ]),
    ]);
}
/**
 * Resolver un ticket.
 *
 * Solo el técnico que tiene asignado el servicio puede resolverlo.
 * El ticket debe estar actualmente en progreso.
 */
public function resolve(Request $request, Ticket $ticket): JsonResponse
{
    abort_unless($request->user()->can('tickets.update'), 403);

    $validated = $request->validate([
        'diagnosis' => [
            'required',
            'string',
            'max:3000',
        ],

        'solution' => [
            'required',
            'string',
            'max:5000',
        ],

        'observations' => [
            'nullable',
            'string',
            'max:3000',
        ],
    ]);

    // Solo se puede resolver un ticket que esté siendo atendido.
    if ($ticket->status !== 'in_progress') {
        return response()->json([
            'message' => 'Solo se puede resolver un servicio que esté en proceso.',
        ], 422);
    }

    // El ticket debe tener un técnico asignado.
    if (!$ticket->assigned_to) {
        return response()->json([
            'message' => 'El servicio no tiene un técnico asignado.',
        ], 422);
    }

    // Solo el técnico asignado puede resolver el servicio.
    if ((int) $ticket->assigned_to !== (int) $request->user()->id) {
        return response()->json([
            'message' => 'Solo el técnico asignado puede resolver este servicio.',
        ], 403);
    }

    $oldStatus = $ticket->status;

    $resolution = [
        'diagnosis' => $validated['diagnosis'],
        'solution' => $validated['solution'],
        'observations' => $validated['observations'] ?? null,
    ];

    $ticket->update([
        'status' => 'resolved',
        'resolved_at' => now(),
        'resolution' => json_encode(
            $resolution,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
    ]);

    $ticket->events()->create([
        'user_id' => $request->user()->id,
        'event_type' => 'resolved',
        'old_status' => $oldStatus,
        'new_status' => 'resolved',
        'old_assigned_to' => $ticket->assigned_to,
        'new_assigned_to' => $ticket->assigned_to,
        'description' => 'El técnico resolvió el servicio.',
        'reason' => $validated['solution'],
        'metadata' => [
            'diagnosis' => $validated['diagnosis'],
            'solution' => $validated['solution'],
            'observations' => $validated['observations'] ?? null,
            'resolved_at' => $ticket->resolved_at?->toISOString(),
        ],
    ]);

    return response()->json([
        'message' => 'Servicio resuelto correctamente.',
        'ticket' => $ticket->fresh([
            'asset.area',
            'asset.location',
            'assignedTechnician:id,name,email',
            'events.user:id,name,email',
            'events.oldAssignedTechnician:id,name,email',
            'events.newAssignedTechnician:id,name,email',
        ]),
    ]);
}
/**
 * Cerrar definitivamente un ticket resuelto.
 *
 * Solo un usuario con el permiso tickets.close puede realizar
 * el cierre administrativo del servicio.
 */
public function close(Request $request, Ticket $ticket): JsonResponse
{
    $user = $request->user();

    if (! $user->can('tickets.close')) {
        return response()->json([
            'message' => 'No tienes permiso para cerrar definitivamente este servicio.',
            'error' => 'forbidden',
        ], 403);
    }

    $validated = $request->validate([
        'note' => [
            'required',
            'string',
            'max:2000',
        ],
    ]);

    // Solo se pueden cerrar servicios previamente resueltos.
    if ($ticket->status !== 'resolved') {
        return response()->json([
            'message' => 'Solo se puede cerrar un servicio que se encuentre resuelto.',
        ], 422);
    }

    $oldStatus = $ticket->status;

    $ticket->update([
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    $ticket->events()->create([
        'user_id' => $request->user()->id,
        'event_type' => 'closed',

        'old_status' => $oldStatus,
        'new_status' => 'closed',

        'old_assigned_to' => $ticket->assigned_to,
        'new_assigned_to' => $ticket->assigned_to,

        'description' => 'El servicio fue cerrado definitivamente.',
        'reason' => $validated['note'],

        'metadata' => [
            'closed_at' => $ticket->closed_at?->toISOString(),
            'closed_by' => $request->user()->id,
        ],
    ]);

    return response()->json([
        'message' => 'Servicio cerrado correctamente.',
        'ticket' => $ticket->fresh([
            'asset.area',
            'asset.location',
            'assignedTechnician:id,name,email',

            'events.user:id,name,email',
            'events.oldAssignedTechnician:id,name,email',
            'events.newAssignedTechnician:id,name,email',
        ]),
    ]);
}
}