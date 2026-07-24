<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\VariantWebhookRequest;
use App\Jobs\Webhooks\DestroyVariant;
use App\Jobs\Webhooks\StoreOrUpdateVariant;
use App\Models\Variant;
use Illuminate\Http\Response;

class VariantWebhookController extends Controller
{
    public function created(VariantWebhookRequest $request): Response
    {
        dispatch(new StoreOrUpdateVariant($request->getVariant()));

        return response('Variant Stored');
    }

    public function updated(VariantWebhookRequest $request): Response
    {
        dispatch(new StoreOrUpdateVariant($request->getVariant()));

        return response('Variant Updated');
    }

    public function deleted(VariantWebhookRequest $request): Response
    {
        $variant = Variant::find($request->getVariantId());

        if ($variant) {
            dispatch(new DestroyVariant($variant));
        }

        return response('Variant Destroyed');
    }
}
