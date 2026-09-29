<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicAssetReportRequest;
use App\Http\Resources\PublicAssetResource;
use App\Models\Asset;
use App\Services\Tickets\TicketCreationService;
use Illuminate\Http\JsonResponse;

class PublicAssetController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $asset = $this->findAsset($token);
        $asset->load(['area:id,name', 'location:id,name']);

        return response()->json(['asset' => new PublicAssetResource($asset)]);
    }

    public function report(
        StorePublicAssetReportRequest $request,
        string $token,
        TicketCreationService $tickets,
    ): JsonResponse {
        $asset = $this->findAsset($token);
        $validated = $request->validated();

        $ticket = $tickets->create([
            'asset_id' => $asset->id,
            'reporter_name' => $validated['reporter_name'],
            'reporter_email' => $validated['reporter_email'] ?? null,
            'reporter_phone' => $validated['reporter_phone'] ?? null,
            'title' => 'Falla reportada: '.$validated['problem'],
            'description' => $validated['description'],
            'category' => $validated['problem'],
            'priority' => 'medium',
            'source' => 'qr',
        ]);

        return response()->json([
            'message' => 'Reporte enviado correctamente.',
            'ticket_code' => $ticket->code,
        ], 201);
    }

    private function findAsset(string $token): Asset
    {
        abort_unless(
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $token) === 1,
            404,
            'Identificación no disponible.'
        );

        return Asset::query()->where('public_token', $token)->firstOr(function (): never {
            abort(404, 'Identificación no disponible.');
        });
    }
}
