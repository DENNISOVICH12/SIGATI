<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * Listar catálogo de áreas y sus ubicaciones activas.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('assets.view'), 403);

        $areas = Area::query()
            ->where('active', true)
            ->with([
                'locations' => function ($query) {
                    $query->where('active', true)
                          ->orderBy('name')
                          ->select('id', 'area_id', 'name', 'code');
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'areas' => $areas,
        ]);
    }
}
