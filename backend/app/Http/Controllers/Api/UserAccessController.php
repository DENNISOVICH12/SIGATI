<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserAccessController extends Controller
{
    public function technicians(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tickets.assign'), 403);

        $technicians = User::query()
            ->select(['id', 'name', 'email'])
            ->where('active', true)
            ->role('technician')
            ->orderBy('name')
            ->get();

        return response()->json(['technicians' => $technicians]);
    }

    public function updateActive(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.deactivate'), 403);

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);
        $active = (bool) $validated['active'];

        if (! $active && $request->user()->is($user)) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta.',
            ], 422);
        }

        if (! $active && $user->hasRole('engineer')) {
            $otherActiveEngineers = User::query()
                ->where('id', '<>', $user->getKey())
                ->where('active', true)
                ->role('engineer')
                ->exists();

            if (! $otherActiveEngineers) {
                return response()->json([
                    'message' => 'Debe permanecer al menos un ingeniero activo.',
                ], 422);
            }
        }

        DB::transaction(function () use ($active, $user): void {
            $user->forceFill(['active' => $active])->save();

            if (! $active) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => $active ? 'Usuario reactivado correctamente.' : 'Usuario desactivado correctamente.',
            'user' => $user->only(['id', 'name', 'email', 'active']),
        ]);
    }
}
