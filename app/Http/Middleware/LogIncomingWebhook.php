<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogIncomingWebhook
{
    /**
     * Log every incoming webhook request before it is handled.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Incoming webhook received', [
            'route' => $request->route()?->getName(),
            'url' => $request->fullUrl(),
            'language' => $request->route('language'),
            'ip' => $request->ip(),
            'product_id' => $request->header('x-product-id'),
            'variant_id' => $request->header('x-variant-id'),
            'payload' => $request->all(),
        ]);

        return $next($request);
    }
}
