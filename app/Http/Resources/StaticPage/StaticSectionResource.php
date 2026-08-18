<?php

namespace App\Http\Resources\StaticPage;

use Illuminate\Http\Resources\Json\JsonResource;

class StaticSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'static_page_id' => $this->static_page_id,
            'title' => $this->getTranslation('title', app()->getLocale()),
            'content' => $this->getTranslations('content'),
            'order' => (int) $this->order,
        ];
    }
}