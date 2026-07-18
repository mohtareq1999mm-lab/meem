<?php

namespace App\Services\General;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductVariant;
use Marvel\Database\Models\Promotion;
use Marvel\Database\Models\User;
use Marvel\Enums\ShippingMethod;
use Marvel\Services\Pricing\ProductPricingService;

class CartInventoryService
{
    private const CART_TTL_DAYS = 3;

    public function reserveItem(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity, string $mode = 'add', array $attributes = [], string $shippingMethod = ShippingMethod::SCHEDULED): CartItem
    {
        return DB::transaction(function () use ($cart, $product, $variant, $quantity, $mode, $attributes, $shippingMethod) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = $this->findCartItemForLock($cart, $product->id, $variant?->id, $shippingMethod);
            $desiredQuantity = $mode === 'set'
                ? $quantity
                : (($item?->quantity ?? 0) + $quantity);

            if ($desiredQuantity < 1) {
                throw new Exception(__(QUANTITY_MINIMUM));
            }

            $stock = $this->lockInventoryRow($product, $variant);
            $reservedQuantity = (int) ($item?->reserved_quantity ?? 0);
            $delta = $desiredQuantity - $reservedQuantity;

            if ($delta > 0) {
                $this->reserveStock($stock, $delta);
            } elseif ($delta < 0) {
                $this->releaseStock($stock, abs($delta));
            }

            $price = $variant
                ? app(ProductPricingService::class)->calculateVariantCurrentPrice($product, $variant)
                : app(ProductPricingService::class)->calculateProductCurrentPrice($product);

            if ($variant && !$variant->relationLoaded('attributeProducts')) {
                $variant->load('attributeProducts.attributeValue.attribute');
            }

            $payload = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $desiredQuantity,
                'reserved_quantity' => $desiredQuantity,
                'price' => $price,
                'total_price' => $price * $desiredQuantity,
                'attributes' => $variant ? $this->getVariantAttributes($variant) : ($attributes ?: null),
                'shipping_method' => $shippingMethod,
                'promotion_id' => null,
                'discount_amount' => 0,
            ];

            if ($item) {
                $item->update($payload);
                $this->touchCartReservation($cart);

                return $item->refresh();
            }

            $item = $cart->items()->create($payload);
            $this->touchCartReservation($cart);

            return $item;
        });
    }

    public function reserveGiftItem(Cart $cart, Product $product, Promotion $promotion, int $quantity, ?int $productVariantId = null, ?string $shippingMethod = null): CartItem
    {
        return DB::transaction(function () use ($cart, $product, $promotion, $quantity, $productVariantId, $shippingMethod) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('promotion_id', $promotion->id)
                ->where('is_gift', true)
                ->lockForUpdate()
                ->first();

            $variant = null;
            if (method_exists($product, 'isSimple') && !$product->isSimple()) {
                if ($productVariantId) {
                    $variant = $product->variations()
                        ->whereKey($productVariantId)
                        ->lockForUpdate()
                        ->first();

                    if (!$variant) {
                        throw new Exception(__(GIFT_VARIANT_NOT_AVAILABLE));
                    }
                } elseif ($item?->product_variant_id) {
                    $variant = ProductVariant::query()
                        ->whereKey($item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$variant) {
                        $item?->delete();
                        $item = null;
                    }
                }

                if (!$variant && !$productVariantId) {
                    $variant = $product->variations()
                        ->whereRaw('(COALESCE(stock_quantity, 0) - COALESCE(reserved_quantity, 0)) > 0')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$variant) {
                    throw new Exception(__(GIFT_VARIANT_NO_STOCK));
                }
            }

            $desiredQuantity = max(1, $quantity);
            $stock = $this->lockInventoryRow($product, $variant);
            $reservedQuantity = (int) ($item?->reserved_quantity ?? 0);
            $delta = $desiredQuantity - $reservedQuantity;

            if ($delta > 0) {
                $this->reserveStock($stock, $delta);
            } elseif ($delta < 0) {
                $this->releaseStock($stock, abs($delta));
            }

            $payload = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $desiredQuantity,
                'reserved_quantity' => $desiredQuantity,
                'price' => 0,
                'total_price' => 0,
                'attributes' => null,
                'is_gift' => true,
                'promotion_id' => $promotion->id,
                'shipping_method' => $shippingMethod ?? ShippingMethod::SCHEDULED,
            ];

            if ($item) {
                $item->update($payload);
                $this->touchCartReservation($cart);

                return $item->refresh();
            }

            $item = $cart->items()->create($payload);
            $this->touchCartReservation($cart);

            return $item;
        });
    }

    public function releaseItem(CartItem $item, bool $deleteItem = false): bool
    {
        return DB::transaction(function () use ($item, $deleteItem) {
            $item = CartItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->reserved_quantity > 0) {
                $stock = $this->lockInventoryRowByItem($item);
                $this->releaseStock($stock, (int) $item->reserved_quantity);
            }

            if ($deleteItem) {
                $cartId = $item->cart_id;
                $deleted = (bool) $item->delete();
                if ($deleted) {
                    $remaining = CartItem::where('cart_id', $cartId)->lockForUpdate()->count();
                    if ($remaining === 0) {
                        Cart::whereKey($cartId)->lockForUpdate()->update(['coupon' => null]);
                    }
                }
                return $deleted;
            }

            return (bool) $item->update(['reserved_quantity' => 0]);
        });
    }

    public function releaseCart(Cart $cart, bool $deleteItems = false): bool
    {
        return DB::transaction(function () use ($cart, $deleteItems) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->with('items')->firstOrFail();

            foreach ($cart->items as $item) {
                $this->releaseItem($item, $deleteItems);
            }

            $cart->update([
                'status' => 'active',
                'expires_at' => null,
                'reserved_at' => null,
                'total_price' => $deleteItems ? 0 : $cart->items()->sum('total_price'),
            ]);

            return true;
        });
    }

    public function finalizeCart(Cart $cart): bool
    {
        return DB::transaction(function () use ($cart) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->with('items')->firstOrFail();

            foreach ($cart->items as $item) {
                if ($item->reserved_quantity > 0) {
                    $stock = $this->lockInventoryRowByItem($item);
                    $this->finalizeStock($stock, (int) $item->reserved_quantity);
                }

                $item->delete();
            }

            $cart->update([
                'status' => 'checked_out',
                'expires_at' => null,
                'reserved_at' => null,
                'total_price' => 0,
            ]);

            return true;
        });
    }

    public function finalizeItemsByShippingMethod(Cart $cart, string $shippingMethod): bool
    {
        return DB::transaction(function () use ($cart, $shippingMethod) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();

            $items = CartItem::where('cart_id', $cart->id)
                ->where('shipping_method', $shippingMethod)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                if ($item->reserved_quantity > 0) {
                    $stock = $this->lockInventoryRowByItem($item);
                    $this->finalizeStock($stock, (int) $item->reserved_quantity);
                }

                $item->delete();
            }

            $remainingItems = CartItem::where('cart_id', $cart->id)->count();

            if ($remainingItems === 0) {
                $cart->update([
                    'status' => 'checked_out',
                    'expires_at' => null,
                    'reserved_at' => null,
                    'total_price' => 0,
                ]);
            } else {
                $cart->update([
                    'total_price' => CartItem::where('cart_id', $cart->id)->sum('total_price'),
                ]);
            }

            return true;
        });
    }

    public function expireCarts(): int
    {
        $expiredCount = 0;
        Cart::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($carts) use (&$expiredCount) {
                foreach ($carts as $cart) {
                    $this->expireCart($cart);
                    $expiredCount++;
                }
            });

        return $expiredCount;
    }

    public function ensureCartReservation(Cart $cart): Cart
    {
        return DB::transaction(function () use ($cart) {
            $cart = Cart::whereKey($cart->id)
                ->lockForUpdate()
                ->with(['items.product', 'items.productVariant.attributeProducts.attributeValue.attribute'])->firstOrFail();
            foreach ($cart->items as $item) {
                $this->syncCartItemReservation($item);
            }

            $this->touchCartReservation($cart);

            return $cart->refresh();
        });
    }

    public function getActiveCartForUser(User $user): ?Cart
    {
        return Cart::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with([
                'items.product.flash_sales' => fn($q) => $q->valid(),
                'items.productVariant.attributeProducts.attributeValue.attribute',
            ])
            ->first();
    }

    private function syncCartItemReservation(CartItem $item): void
    {
        $item = CartItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
        $stock = $this->lockInventoryRowByItem($item);
        $desiredQuantity = (int) $item->quantity;
        $reservedQuantity = (int) $item->reserved_quantity;
        $delta = $desiredQuantity - $reservedQuantity;

        if ($delta > 0) {
            $this->reserveStock($stock, $delta);
        } elseif ($delta < 0) {
            $this->releaseStock($stock, abs($delta));
        }

        if ($delta !== 0) {
            $item->update(['reserved_quantity' => $desiredQuantity]);
        }
    }

    private function expireCart(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->with('items')->firstOrFail();

            if ($cart->expires_at && $cart->expires_at->isFuture()) {
                return;
            }

            foreach ($cart->items as $item) {
                if ($item->reserved_quantity > 0) {
                    $stock = $this->lockInventoryRowByItem($item);
                    $this->releaseStock($stock, (int) $item->reserved_quantity);
                }
            }

            $cart->items()->delete();
            $cart->update([
                'status' => 'expired',
                'expires_at' => null,
                'reserved_at' => null,
                'total_price' => 0,
            ]);
        });
    }

    private function lockInventoryRow(Product $product, ?ProductVariant $variant)
    {
        if ($variant) {
            return ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
        }

        return Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
    }

    private function lockInventoryRowByItem(CartItem $item)
    {
        if ($item->product_variant_id) {
            return ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->firstOrFail();
        }

        return Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
    }

    private function reserveStock($stock, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $availableStock = $this->getAvailableStock($stock);
        if ($availableStock < $quantity) {
            throw new Exception(__(QUANTITY_EXCEEDS_STOCK));
        }

        $stock->reserved_quantity = (int) ($stock->reserved_quantity ?? 0) + $quantity;
        $stock->in_stock = $availableStock - $quantity > 0;
        $stock->save();
    }

    private function releaseStock($stock, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $stock->reserved_quantity = max(0, (int) ($stock->reserved_quantity ?? 0) - $quantity);
        $stock->in_stock = $this->getAvailableStock($stock) > 0;
        $stock->save();
    }

    private function finalizeStock($stock, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }
        $reservedQuantity = (int) ($stock->reserved_quantity ?? 0);
        $physicalQuantity = (int) ($stock->stock_quantity ?? 0);

        if ($reservedQuantity < $quantity) {
            throw new Exception(__(RESERVED_STOCK_INSUFFICIENT));
        }

        if ($physicalQuantity < $quantity) {
            throw new Exception(__(PHYSICAL_STOCK_INSUFFICIENT));
        }

        $stock->stock_quantity = $physicalQuantity - $quantity;
        $stock->reserved_quantity = $reservedQuantity - $quantity;
        $stock->sold_quantity = (int) ($stock->sold_quantity ?? 0) + $quantity;
        $stock->in_stock = $this->getAvailableStock($stock) > 0;
        $stock->save();
    }

    private function findCartItemForLock(Cart $cart, int $productId, ?int $variantId, ?string $shippingMethod = null): ?CartItem
    {
        $query = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->where('is_gift', false)
            ->lockForUpdate();

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->whereNull('product_variant_id');
        }

        if ($shippingMethod !== null) {
            $query->where('shipping_method', $shippingMethod);
        }

        return $query->first();
    }

    private function touchCartReservation(Cart $cart): void
    {
        $cart->update([
            'status' => 'active',
            'reserved_at' => now(),
            'expires_at' => Carbon::now()->addDays(self::CART_TTL_DAYS),
        ]);
    }

    private function getAvailableStock($stock): int
    {
        return max(0, (int) ($stock->stock_quantity ?? 0) - (int) ($stock->reserved_quantity ?? 0));
    }

    private function getVariantAttributes(ProductVariant $variant): array
    {
        return $variant->attributeProducts->map(function ($ap) {
            return [
                'attribute' => $ap->attributeValue?->attribute?->name,
                'value' => $ap->attributeValue?->value,
            ];
        })->toArray();
    }
}
