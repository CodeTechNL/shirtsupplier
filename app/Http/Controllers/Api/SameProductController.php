<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetSameProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SameProductController extends Controller
{
    public function show(GetSameProductsRequest $request): JsonResponse
    {
        $product = Product::with([
            'sameProductGroups' => fn ($query) => $query->with([
                'products' => fn ($query) => $query->where('is_visible', true),
            ]),
        ])->find($request->validated('product'));

        $group = $product->sameProductGroups->first();

        $sameProducts = $group
            ? $group->products->where('id', '!=', $product->id)
            : new Collection;

        $products = $sameProducts->mapWithKeys(fn (Product $sameProduct): array => [
            $sameProduct->id => (new ProductResource($sameProduct))->resolve($request),
        ]);

        return response()->json([
            'data' => [
                'products' => $products->isEmpty() ? (object) [] : $products,
                'count' => $sameProducts->count(),
            ],
            'meta' => [],
            'status' => [
                'code' => 200,
                'status' => 'success',
            ],
        ]);
    }
}
