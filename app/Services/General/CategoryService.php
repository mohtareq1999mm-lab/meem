<?php

namespace App\Services\General;

use App\Traits\HasChannelFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Marvel\Database\Models\Category;

class CategoryService
{
    use HasChannelFilter;
    public function paginate(Request $request)
    {
        $limit = $this->getLimit($request);
        $term = trim((string) $request->get('search', ''));
        $pestCategory = $request->query('pest_category', false);
        $parent = $request->query('parent', false);
        $categoriesId = $request->query('categoriesId');
        $order = $request->query('order', 'desc');
        $query = Category::query()->active()->withCount('products');

        if (!empty($categoriesId)) {
            $ids = is_array($categoriesId) ? $categoriesId : explode(',', $categoriesId);
            $ids = array_filter($ids, 'is_numeric');
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        if ($term !== '') {
            $query->where(function (Builder $builder) use ($term) {
                $this->applyTranslatableLike($builder, 'name', $term, app()->getLocale());
                $builder->orWhere(function (Builder $sub) use ($term) {
                    $this->applyTranslatableLike($sub, 'details', $term, app()->getLocale());
                });
            });
        }
        if ($parent) {
            $query->whereNull('parent_id');
        }
        if ($pestCategory) {
            $query->orderBy('products_count', $order);
        } else {
            $query->orderBy('id', $order);
        }


        return $query->paginate($limit);
    }

    public function getBySlug($slug)
    {
        $category = Category::query()
            ->active()
            ->with([
                'products' => fn($q) => $this->applyChannelHomeFilter($q),
                'children' => function ($query) {
                    $query->active()->withCount('products');
                },
            ])
            ->withCount('products')
            ->where('slug', $slug)
            ->firstOrFail();

        app(ProductService::class)->enrichCollectionWithPricing($category->products);

        return $category;
    }

    private function applyTranslatableLike(Builder $query, string $field, string $term, string $locale): void
    {
        $query->where(function ($q) use ($field, $term, $locale) {
            $q->where($field . '->' . $locale, 'like', "%$term%")
                ->orWhere($field, 'like', "%$term%");
        });
    }

    private function getLimit(Request $request): int
    {
        $limit = (int) $request->get('limit', 15);
        if ($limit <= 0) {
            return 15;
        }

        return min($limit, 100);
    }
}
