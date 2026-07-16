<?php

namespace App\Services\General;

use Marvel\Database\Models\PickupLocation;

class PickupLocationService
{
    public function getPickupLocations($request)
    {
        $limit = $request->get('limit', 10);
        $search = $request->query('search');

        $query = PickupLocation::active()->ordered();

        if ($search) {
            $query->where('store_name', 'like', "%{$search}%");
        }

        return $query->paginate($limit);
    }

    public function getPickupLocationById($id)
    {
        return PickupLocation::active()->findOrFail($id);
    }
}
