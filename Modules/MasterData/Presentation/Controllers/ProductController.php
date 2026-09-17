<?php

namespace Modules\MasterData\Presentation\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends SimpleMasterDataController
{
    protected string $model = Product::class;

    /**
     * Supports ?product_category_id= for the dependent Kategori Produk ->
     * Produk/Varietas dropdown described in docs section 16.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with('category');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('product_category_id')) {
            $query->where('product_category_id', $request->integer('product_category_id'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => Product::with('category')->findOrFail($id)]);
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            ...parent::rules($ignoreId),
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
        ];
    }
}
