<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "display_name" => request()->routeIs('roles.index') ? $this->getTranslation('display_name', app()->getLocale()) : $this->getRawOriginal('display_name'),
            "permissions" => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
