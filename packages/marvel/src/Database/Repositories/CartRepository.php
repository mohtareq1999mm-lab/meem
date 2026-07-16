<?php

namespace Marvel\Database\Repositories;

use App\Services\General\CartInventoryService;
use App\Services\General\PromotionService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Product;
use Marvel\Enums\ShippingMethod;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CartRepository extends BaseRepository
{
    protected $fieldSearchable = [
        'user_id',
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }

    public function model()
    {
        return Cart::class;
    }

    public function revalidatePromotion(Cart $cart): void
    {
        $promotionIds = $cart->items
            ->pluck('promotion_id')
            ->filter()
            ->unique()
            ->values();

        if ($promotionIds->isEmpty()) {
            return;
        }

        if ($promotionIds->count() > 1) {
            app(PromotionService::class)->clearPromotionFromCart($cart);
            Log::warning('Multiple promotion_ids detected on cart items — auto-cleared', [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'promotion_ids' => $promotionIds->toArray(),
            ]);
            return;
        }

        $promotionId = (int) $promotionIds->first();

        $shippingMethod = $cart->items
            ->firstWhere(fn($item) => !is_null($item->promotion_id))
            ?->shipping_method ?? ShippingMethod::SCHEDULED;

        try {
            app(PromotionService::class)->applySelectedPromotion(
                $cart,
                (int) $promotionId,
                null,
                $shippingMethod,
            );
        } catch (\InvalidArgumentException $e) {
            app(PromotionService::class)->clearPromotionFromCart($cart);
            Log::info('Promotion auto-cleared from cart', [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'promotion_id' => $promotionId,
                'reason' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::critical('Unexpected error during promotion revalidation', [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'promotion_id' => $promotionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function storeCart(Request $request)
    {

        return $this->persistCart($request, 'add');
    }

    public function updateCart(Request $request)
    {
        return $this->persistCart($request, 'set');
    }

    private function persistCart(Request $request, string $mode)
    {
        try {
            DB::beginTransaction();

            $userId = $request->user()?->id;
            if (!$userId) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
            
            $cart = Cart::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
            
            if (!$cart) {
                $cart = Cart::create([
                    'user_id' => $userId,
                    'status' => 'active',
                ]);
            }
            
            $cart->update(['status' => 'active']);

            if ($request->filled('item')) {
                if (!$this->syncItems($cart, $request->item ?? [], $mode)) {
                    throw new Exception(INVALID_ITEM_DATA);
                }
            }

            $cart->update([
                'total_price' => $cart->items()->sum('total_price'),
            ]);

            $cart->load(['items.product', 'items.productVariant.attributeProducts.attributeValue.attribute']);
            $this->revalidatePromotion($cart);

            DB::commit();

            return $cart;
        } catch (AuthorizationException $e) {
            DB::rollBack();
            throw new HttpException(401, $e->getMessage());
        } catch (Exception $e) {
            DB::rollBack();
            throw new HttpException(400, $e->getMessage());
        }
    }

    private function syncItems(Cart $cart, array $item, string $mode): bool
    {
        $productId = $item['product_id'] ?? null;
        $quantity = (int) ($item['quantity'] ?? 0);
        $variantId = $item['product_variant_id'] ?? null;
        $attributes = $item['attributes'] ?? [];
        $shippingMethod = $item['shipping_method'] ?? ShippingMethod::SCHEDULED;

        if (!$productId || $quantity < 1) {
            return false;
        }

        $product = Product::findOrFail($productId);
        $productName = is_array($product->name) ? ($product->name[app()->getLocale()] ?? $product->name['en'] ?? '') : $product->name;

        if ($shippingMethod === ShippingMethod::FAST && !$product->is_fast_shipping_available) {
            throw new Exception(__('message.MESSAGE.FAST_SHIPPING_PRODUCT_NOT_ELIGIBLE', ['product_name' => $productName]));
        }

        $inventoryService = app(CartInventoryService::class);

        if ($variantId) {
            $variant = $product->variations()->whereKey($variantId)->first();
            if (!$variant) {
                throw new Exception(__('message.ERROR.INVALID_ITEM_DATA', ['product_name' => $productName]));
            }

            if ($variant->available_stock < $quantity) {
                throw new Exception(__('message.ERROR.VARIANT_STOCK_EXCEEDED', ['product_name' => $productName]));
            }

            $inventoryService->reserveItem($cart, $product, $variant, $quantity, $mode, $attributes, $shippingMethod);
            return true;
        }

        if ($product->product_type === 'variable') {
            throw new Exception(__('message.ERROR.INVALID_ITEM_DATA', ['product_name' => $productName]));
        }

        if ($product->available_stock < $quantity) {
            throw new Exception(__('message.ERROR.PRODUCT_STOCK_EXCEEDED', ['product_name' => $productName]));
        }

        $inventoryService->reserveItem($cart, $product, null, $quantity, $mode, $attributes, $shippingMethod);
        return true;
    }
}
