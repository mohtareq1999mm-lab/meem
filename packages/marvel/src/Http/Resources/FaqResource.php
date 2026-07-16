<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;

class FaqResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request)
    {
        return [
            'id'              => $this->id,
            'faq_title'       => request()->routeIs('faqs.index') ? $this->getTranslation('faq_title', app()->getLocale()) : $this->getRawOriginal('faq_title'),
            'faq_description' => request()->routeIs('faqs.index') ? $this->getTranslation('faq_description', app()->getLocale()) : $this->getRawOriginal('faq_description'),
        ];
    }
}
