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
         * Keyed by position (1..n), not by product id. A JSON object's numeric
         * keys are re-sorted ascending by any client that decodes into a plain
         * object, JavaScript included, which silently destroyed the group's
         * manual order when the id was the key. Keying by position makes that
         * same ascending pass yield the intended order, so ordering stays the
         * application's job. Each product carries its own `id`.
         */
        $products = $sameProducts->mapWithKeys(fn (Product $sameProduct, int $index): array => [
            $index + 1 => (new ProductResource($sameProduct))->resolve($request),
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
