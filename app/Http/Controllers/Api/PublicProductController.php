<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public REST API untuk integrasi pihak ketiga. Auth via header X-API-KEY
 * (lihat AuthenticateApiClient middleware).
 */
class PublicProductController extends Controller
{
    /**
     * GET /api/v1/products?per_page=20&page=1&category=&q=
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $q = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category');

        $query = Product::query()
            ->with(['category', 'variants'])
            ->orderByDesc('is_best_seller')
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Product $p) => $this->serializeProduct($p))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'variants'])->find($id);
        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'data' => $this->serializeProduct($product, full: true),
        ]);
    }

    /**
     * GET /api/v1/categories
     */
    public function categories(): JsonResponse
    {
        $cats = Category::orderBy('name')->get(['id', 'name', 'created_at']);

        return response()->json([
            'data' => $cats->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    protected function serializeProduct(Product $product, bool $full = false): array
    {
        $variants = $product->variants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => (int) $v->effectivePrice(),
            'list_price' => (int) $v->price,
            'stock_available' => $v->availableStocks()->count(),
            'warranty_days' => (int) ($v->warranty_days ?? 0),
            'share_type' => $v->share_type,
            'is_auto_send' => (bool) $v->is_auto_send,
        ])->all();

        $base = [
            'id' => $product->id,
            'name' => $product->name,
            'short_description' => $product->short_description,
            'lowest_price' => $product->lowestPrice(),
            'is_best_seller' => (bool) $product->is_best_seller,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'image_url' => $product->imageUrl(),
            'sold_count' => $product->displaySoldCount(),
            'variants' => $variants,
        ];

        if ($full) {
            $base['description'] = $product->description;
            $base['terms_html'] = $product->terms_html;
        }

        return $base;
    }
}
