<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;

class DigitalAssetResource extends Resource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'uuid'          => $this->uuid,
            'type'          => $this->type,
            'original_name' => $this->original_name,
            'mime'          => $this->mime,
            'size'          => (int) $this->size,
            'sort_order'    => (int) $this->sort_order,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
