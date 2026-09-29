<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'area' => $this->area?->name,
            'location' => $this->location?->name,
            'status' => $this->status,
        ];
    }
}
