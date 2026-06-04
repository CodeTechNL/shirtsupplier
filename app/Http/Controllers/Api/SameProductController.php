<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GetSameProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

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

        if (! $group) {
            return response()->json(['products' => [], 'count' => 0]);
        }

        $sameProducts = $group->products
            ->where('id', '!=', $product->id);

        return response()->json([
            'products' => ProductResource::collection($sameProducts),
            'count' => $sameProducts->count(),
        ]);
    }
}
