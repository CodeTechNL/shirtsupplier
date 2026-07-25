<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

it('excludes webhook routes from CSRF verification', function () {
    $neverVerify = (new ReflectionProperty(PreventRequestForgery::class, 'neverVerify'))->getValue();

    expect($neverVerify)->toContain('webhooks/*');
});
