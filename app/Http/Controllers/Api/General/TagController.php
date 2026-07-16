<?php

namespace App\Http\Controllers\Api\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Marvel\Database\Models\Tag;
use Marvel\Http\Resources\TagResource;
use Marvel\Traits\ApiResponse;

class TagController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $tags = Tag::query()->get();
        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, TagResource::collection($tags));
    }

    public function show(Request $request, string $slug)
    {
        $tag = Tag::query()->where('slug', $slug)->first();

        if (!$tag) {
            return $this->apiResponse(NOT_FOUND, 404, false);
        }

        return $this->apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, TagResource::make($tag));
    }
}
