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
                'orderedProducts' => fn ($query) => $query->where('is_visible', true),
            ]),
        ])->find($request->validated('product'));

        $group = $product->sameProductGroups->first();

        $sameProducts = $group
            ? $group->orderedProducts->where('id', '!=', $product->id)->values()
            : new Collection;

        /**
         * Products are emitted in the group's manual order, but JSON object
         * keys are numeric here and clients that decode into a plain object
         * (JavaScript included) will re-sort them by id. `sort_order` carries
         * the intended position so the order survives decoding.
         */
        $products = $sameProducts->mapWithKeys(fn (Product $sameProduct, int $index): array => [
            $sameProduct->id => [
                ...(new ProductResource($sameProduct))->resolve($request),
                'sort_order' => $index + 1,
            ],
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
