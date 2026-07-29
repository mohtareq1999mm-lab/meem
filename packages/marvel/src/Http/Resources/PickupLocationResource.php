<?php

namespace Marvel\Http\Resources;

use Illuminate\Http\Request;

class PickupLocationResource extends Resource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'store_name' => request()->routeIs('pickup-locations.index') || request()->routeIs('pickup-locations.index') ? $this->store_name : [
                'ar' => $this->getTranslation('store_name', 'ar'),
                'en' => $this->getTranslation('store_name', 'en'),
            ],
            'address' => request()->routeIs('pickup-locations.index') || request()->routeIs('pickup-locations.index') ? $this->address : [
                'ar' => $this->getTranslation('address', 'ar'),
                'en' => $this->getTranslation('address', 'en'),
            ],
            'phone' => $this->phone,
            'email' => $this->email,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'working_hours' => $this->working_hours,
            'status' => (bool) $this->status,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at,
        ];
    }
}