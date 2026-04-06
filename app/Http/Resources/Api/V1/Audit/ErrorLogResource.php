<?php

namespace App\Http\Resources\Api\V1\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'source'     => $this->source,
            'source_id'  => $this->source_id,
            'message'    => $this->message,
            'details'    => $this->details,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
