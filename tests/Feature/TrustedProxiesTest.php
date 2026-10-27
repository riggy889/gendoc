<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_client', fn (Request $request) => $request->ip().' '.$request->getScheme());
});

it('takes the client IP and scheme from the reverse proxy on the Docker network', function () {
    $this->withServerVariables([
        'REMOTE_ADDR' => '172.18.0.5',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('/_client')->assertSeeText('203.0.113.9 https');
});

it('ignores forwarded headers from anyone else', function () {
    $this->withServerVariables([
        'REMOTE_ADDR' => '198.51.100.7',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get('/_client')->assertSeeText('198.51.100.7 http');
});
