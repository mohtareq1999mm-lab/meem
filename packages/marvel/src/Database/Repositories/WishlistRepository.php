<?php

namespace Marvel\Database\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Variation;
use Marvel\Database\Models\Wishlist;
use Marvel\Exceptions\MarvelException;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WishlistRepository extends BaseRepository
{
    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }

    /**
     * @var array[]
     */
    protected $dataArray = [
        'user_id',
        'product_id',
        'product_variant_id'
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Wishlist::class;
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function storeWishlist($request)
    {
        try {
            $user_id = $request->user()->id;
            $wishlist = $this->findOneWhere((['user_id' => $user_id, 'product_id' => $request['product_id'] , 'product_variant_id' => $request['product_variant_id']]));
            if (empty($wishlist)) {
                $request['user_id'] = $user_id;
                $wishlistInput = $request->only($this->dataArray);
                return $this->create($wishlistInput);
            }else {
                throw new HttpException(400, ALREADY_ADDED_TO_WISHLIST_FOR_THIS_PRODUCT);
            }
        } catch (\Exception $e) {
            throw new HttpException(400, ALREADY_ADDED_TO_WISHLIST_FOR_THIS_PRODUCT);
        }
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function toggleWishlist($request)
    {
        try {
            $user_id = $request->user()->id;
            $wishlist = $this->findOneWhere((['user_id' => $user_id, 'product_id' => $request['product_id'], 'product_variant_id' => $request['product_variant_id']]));
            if (empty($wishlist)) {
                $request['user_id'] = $user_id;
                $wishlistInput = $request->only($this->dataArray);
                $this->create($wishlistInput);
                return true;
            } else {
                $this->delete($wishlist->id);
                return false;
            }
        } catch (\Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
}
