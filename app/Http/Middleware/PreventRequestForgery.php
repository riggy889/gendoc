<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * CSRF protection without the XSRF-TOKEN cookie. Our requests send the token from the page's
 * csrf-token meta tag, so the session cookie can be the only cookie we set.
 */
class PreventRequestForgery extends Middleware
{
    protected $addHttpCookie = false;
}
