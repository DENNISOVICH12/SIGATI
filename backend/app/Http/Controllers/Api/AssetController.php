<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Asset;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    /**
     * Listar activos con soporte para búsqueda y filtros combinados.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('assets.view'), 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['operational', 'pending_review', 'faulty', 'maintenance'])],
            'category' => ['nullable', 'string', 'max:80'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        /*
         * Si se envían tanto area_id como location_id,
         * validamos que la ubicación pertenezca efectivamente al área indicada.
         */
        if (!empty($validated['area_id']) && !empty($validated['location_id'])) {
            $locationBelongsToArea = Location::query()
                ->whereKey($validated['location_id'])
                ->where('area_id', $validated['area_id'])
                ->exists();

            if (!$locationBelongsToArea) {
                throw ValidationException::withMessages([
                    'location_id' => ['La ubicación seleccionada no pertenece al área indicada.'],
                ]);
            }
        }

        $query = Asset::query()
            ->with([
                'area:id,name,code',
                'location:id,area_id,name,code',
            ]);

        // Búsqueda insensible a mayúsculas/minúsculas en PostgreSQL
        if (!empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search) {
                $term = "%{$search}%";
                $q->where('code', 'ilike', $term)
                  ->orWhere('name', 'ilike', $term)
                  ->orWhere('category', 'ilike', $term)
                  ->orWhere('brand', 'ilike', $term)
                  ->orWhere('model', 'ilike', $term)
                  ->orWhere('serial_number', 'ilike', $term)
                  ->orWhere('responsible_name', 'ilike', $term)
                  ->orWhere('hostname', 'ilike', $term)
                  ->orWhere('ip_address', 'ilike', $term);
            });
        }

        // Filtro por estado operativo
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Filtro por categoría
        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        // Filtro por área
        if (!empty($validated['area_id'])) {
            $query->where('area_id', $validated['area_id']);
        }

        // Filtro por ubicación
        if (!empty($validated['location_id'])) {
            $query->where('location_id', $validated['location_id']);
        }

        $assets = $query->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return response()->json($assets);
    }

    /**
     * Obtener listado de categorías únicas existentes de activos.
     */
    public function categories(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('assets.view'), 403);

        $categories = Asset::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Consultar un activo.
     */
    public function show(Request $request, Asset $asset): JsonResponse
    {
        abort_unless($request->user()->can('assets.view'), 403);

        $asset->load([
            'area:id,name,code',
            'location:id,area_id,name,code',
        ]);

        return response()->json([
            'asset' => $asset,
        ]);
    }

    /**
     * Crear un activo.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('assets.create'), 403);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:assets,code',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'category' => [
                'required',
                'string',
                'max:80',
            ],

            'brand' => [
                'nullable',
                'string',
                'max:100',
            ],

            'model' => [
                'nullable',
                'string',
                'max:100',
            ],

            'serial_number' => [
                'nullable',
                'string',
                'max:120',
                'unique:assets,serial_number',
            ],

            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')->where('active', true),
            ],

            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('active', true),
            ],

            'responsible_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'hostname' => [
                'nullable',
                'string',
                'max:120',
            ],

            'ip_address' => [
                'nullable',
                'ip',
            ],

            'mac_address' => [
                'nullable',
                'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'operational',
                    'pending_review',
                    'faulty',
                    'maintenance',
                ]),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $validated['responsible_name'] = self::normalizeResponsibleName(
            $validated['responsible_name'] ?? null
        );

        /*
         * Si se proporciona una ubicación, comprobamos que
         * realmente pertenezca al área seleccionada.
         */
        if (!empty($validated['location_id'])) {
            $locationBelongsToArea = Location::query()
                ->whereKey($validated['location_id'])
                ->where('area_id', $validated['area_id'])
                ->exists();

            if (!$locationBelongsToArea) {
                return response()->json([
                    'message' => 'La ubicación seleccionada no pertenece al área indicada.',
                ], 422);
            }
        }

        $asset = DB::transaction(function () use ($validated, $request) {

            $asset = Asset::create($validated);

            $asset->load([
                'area:id,name,code',
                'location:id,area_id,name,code',
            ]);

            /*
             * Registrar la creación del activo.
             */
            $asset->history()->create([
                'user_id' => $request->user()->id,
                'action' => 'created',
                'description' => 'Activo registrado en SIGATI.',
                'reason' => null,

                'old_values' => null,

                'new_values' => [
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'category' => $asset->category,

                    'area_id' => $asset->area_id,
                    'area_name' => $asset->area?->name,

                    'location_id' => $asset->location_id,
                    'location_name' => $asset->location?->name,

                    'responsible_name' => $asset->responsible_name,
                    'status' => $asset->status,
                ],
            ]);

            return $asset;
        });

        return response()->json([
            'message' => 'Activo creado correctamente.',
            'asset' => $asset,
        ], 201);
    }

    /**
     * Actualizar información general del activo.
     *
     * No permite trasladar el activo ni cambiar su estado.
     */
    public function update(Request $request, Asset $asset): JsonResponse
    {
        abort_unless($request->user()->can('assets.update'), 403);

        if ($request->exists('responsible_name')) {
            throw ValidationException::withMessages([
                'responsible_name' => ['El responsable solo puede modificarse mediante un traslado.'],
            ]);
        }

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('assets', 'code')->ignore($asset->id),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'category' => [
                'sometimes',
                'required',
                'string',
                'max:80',
            ],

            'brand' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'model' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'serial_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                Rule::unique('assets', 'serial_number')->ignore($asset->id),
            ],

            'hostname' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],

            'ip_address' => [
                'sometimes',
                'nullable',
                'ip',
            ],

            'mac_address' => [
                'sometimes',
                'nullable',
                'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $asset = DB::transaction(function () use ($asset, $validated, $request) {

            /*
             * Guardamos únicamente los valores que fueron enviados
             * y que realmente podrían cambiar.
             */
            $oldValues = [];

            foreach (array_keys($validated) as $field) {
                $oldValues[$field] = $asset->{$field};
            }

            $asset->update($validated);
            $asset->refresh();

            /*
             * Eloquent permite saber qué campos realmente cambiaron.
             */
            $changes = $asset->getChanges();

            unset(
                $changes['updated_at'],
                $changes['created_at']
            );

            /*
             * Solo creamos historial si realmente hubo cambios.
             */
            if (!empty($changes)) {

                $changedOldValues = [];

                foreach (array_keys($changes) as $field) {
                    if (array_key_exists($field, $oldValues)) {
                        $changedOldValues[$field] = $oldValues[$field];
                    }
                }

                $asset->history()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'updated',
                    'description' => 'Información general del activo actualizada.',
                    'reason' => null,
                    'old_values' => $changedOldValues,
                    'new_values' => $changes,
                ]);
            }

            $asset->load([
                'area:id,name,code',
                'location:id,area_id,name,code',
            ]);

            return $asset;
        });

        return response()->json([
            'message' => 'Activo actualizado correctamente.',
            'asset' => $asset,
        ]);
    }

    /**
     * Trasladar un activo a otra área o ubicación.
     */
    public function transfer(Request $request, Asset $asset): JsonResponse
    {
        abort_unless($request->user()->can('assets.transfer'), 403);

        $validated = $request->validate([
            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')->where('active', true),
            ],

            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('active', true),
            ],

            'responsible_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $assetId = $asset->id;

        $asset = DB::transaction(function () use ($assetId, $validated, $request) {
            $asset = Asset::query()->lockForUpdate()->findOrFail($assetId);

            $activeArea = Area::query()
                ->whereKey($validated['area_id'])
                ->where('active', true)
                ->first(['id']);

            if (!$activeArea) {
                throw ValidationException::withMessages([
                    'area_id' => ['El área seleccionada no existe o está inactiva.'],
                ]);
            }

            if (!empty($validated['location_id'])) {
                $location = Location::query()
                    ->whereKey($validated['location_id'])
                    ->where('area_id', $validated['area_id'])
                    ->where('active', true)
                    ->first(['id']);

                if (!$location) {
                    throw ValidationException::withMessages([
                        'location_id' => ['La ubicación seleccionada no pertenece al área indicada o está inactiva.'],
                    ]);
                }
            }

            $newAreaId = (int) $validated['area_id'];
            $newLocationId = isset($validated['location_id'])
                ? (int) $validated['location_id']
                : null;
            $newResponsibleName = self::normalizeResponsibleName(
                $validated['responsible_name'] ?? null
            );

            $currentAreaId = $asset->area_id !== null ? (int) $asset->area_id : null;
            $currentLocationId = $asset->location_id !== null ? (int) $asset->location_id : null;
            $currentResponsibleName = self::normalizeResponsibleName($asset->responsible_name);

            if (
                $currentAreaId === $newAreaId &&
                $currentLocationId === $newLocationId &&
                $currentResponsibleName === $newResponsibleName
            ) {
                throw ValidationException::withMessages([
                    'transfer' => ['Debes modificar el área, la ubicación o el funcionario responsable para realizar el traslado.'],
                ]);
            }

            /*
             * Cargamos ubicación actual antes del traslado.
             */
            $asset->load([
                'area:id,name,code',
                'location:id,area_id,name,code',
            ]);

            $oldValues = [
                'area_id' => $asset->area_id,
                'area_name' => $asset->area?->name,

                'location_id' => $asset->location_id,
                'location_name' => $asset->location?->name,

                'responsible_name' => $asset->responsible_name,
            ];

            /*
             * Realizar traslado.
             */
            $asset->update([
                'area_id' => $validated['area_id'],
                'location_id' => $validated['location_id'] ?? null,
                'responsible_name' => $newResponsibleName,
            ]);

            /*
             * Recargar las nuevas relaciones.
             */
            $asset->load([
                'area:id,name,code',
                'location:id,area_id,name,code',
            ]);

            $newValues = [
                'area_id' => $asset->area_id,
                'area_name' => $asset->area?->name,

                'location_id' => $asset->location_id,
                'location_name' => $asset->location?->name,

                'responsible_name' => $asset->responsible_name,
            ];

            /*
             * Registrar historial del traslado.
             */
            $asset->history()->create([
                'user_id' => $request->user()->id,
                'action' => 'transferred',

                'description' =>
                    'Activo trasladado de ' .
                    ($oldValues['area_name'] ?? 'Sin área') .
                    ' a ' .
                    ($newValues['area_name'] ?? 'Sin área') .
                    '.',

                'reason' => $validated['reason'],

                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);

            return $asset;
        });

        return response()->json([
            'message' => 'Activo trasladado correctamente.',
            'reason' => $validated['reason'],
            'asset' => $asset,
        ]);
    }

    /**
     * Cambiar el estado operativo de un activo.
     */
    public function changeStatus(Request $request, Asset $asset): JsonResponse
    {
        abort_unless(
            $request->user()->can('assets.change_status'),
            403
        );

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'operational',
                    'pending_review',
                    'faulty',
                    'maintenance',
                ]),
            ],

            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        /*
         * No registramos un falso cambio de estado.
         */
        $assetId = $asset->id;

        $asset = DB::transaction(function () use ($assetId, $validated, $request) {
            $asset = Asset::query()->lockForUpdate()->findOrFail($assetId);

            if ($asset->status === $validated['status']) {
                throw ValidationException::withMessages([
                    'status' => ['El activo ya se encuentra en el estado indicado.'],
                ]);
            }

            $oldStatus = $asset->status;

            /*
             * Actualizar estado.
             */
            $asset->update([
                'status' => $validated['status'],
            ]);

            /*
             * Registrar historial.
             */
            $asset->history()->create([
                'user_id' => $request->user()->id,
                'action' => 'status_changed',

                'description' =>
                    'Estado del activo cambiado de ' .
                    $oldStatus .
                    ' a ' .
                    $validated['status'] .
                    '.',

                'reason' => $validated['reason'],

                'old_values' => [
                    'status' => $oldStatus,
                ],

                'new_values' => [
                    'status' => $validated['status'],
                ],
            ]);

            $asset->load([
                'area:id,name,code',
                'location:id,area_id,name,code',
            ]);

            return $asset;
        });

        return response()->json([
            'message' => 'Estado del activo actualizado correctamente.',
            'reason' => $validated['reason'],
            'asset' => $asset,
        ]);
    }

    /**
 * Consultar el historial de un activo.
 */
    public function history(Request $request, Asset $asset): JsonResponse
{
    abort_unless(
        $request->user()->can('assets.view'),
        403
    );

    $history = $asset->history()
        ->with([
            'user:id,name,email',
        ])
        ->orderByDesc('created_at')
        ->paginate(20);

    return response()->json([
        'asset' => [
            'id' => $asset->id,
            'code' => $asset->code,
            'name' => $asset->name,
        ],
        'history' => $history,
    ]);
}

    private static function normalizeResponsibleName(?string $responsibleName): ?string
    {
        $normalized = trim((string) $responsibleName);

        return $normalized === '' ? null : $normalized;
    }
}
