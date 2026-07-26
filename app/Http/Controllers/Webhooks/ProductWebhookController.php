<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\ProductWebhookRequest;
use App\Jobs\Webhooks\DestroyProduct;
use App\Jobs\Webhooks\StoreOrUpdateProduct;
use App\Models\Product;
use Illuminate\Http\Response;

class ProductWebhookController extends Controller
{
    public function created(ProductWebhookRequest $request): Response
    {
        if (! $request->hasProduct()) {
            return response('Product Payload Missing');
        }

        dispatch(new StoreOrUpdateProduct($request->getProduct(), $request->getLanguage()));

        return response('Product Stored');
    }

    public function updated(ProductWebhookRequest $request): Response
    {
        if (! $request->hasProduct()) {
            return response('Product Payload Missing');
        }

        dispatch(new StoreOrUpdateProduct($request->getProduct(), $request->getLanguage()));

        return response('Product Updated');
    }

    public function deleted(ProductWebhookRequest $request): Response
    {
        $product = Product::find($request->getProductId());

        if ($product) {
            dispatch(new DestroyProduct($product));
        }

        return response('Product Destroyed');
    }
}
