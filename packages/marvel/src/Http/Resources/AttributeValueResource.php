<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;

class AttributeValueResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                   => $this->id,
            'value'                => request()->routeIs('attribute-values.index') ? $this->getTranslation('value', app()->getLocale()) : $this->getRawOriginal('value'),
            'slug'                 => $this->slug,
            'attribute_id'         => $this->attribute_id,
        ];
    }
}
