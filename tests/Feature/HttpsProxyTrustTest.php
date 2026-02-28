<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('uses forwarded https scheme for generated urls behind a trusted proxy', function () {
    Route::get('/proxy-check', function (Request $request) {
        return [
            'secure' => $request->isSecure(),
            'dashboard' => route('dashboard'),
        ];
    });

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'carexp.cranbrookbells.net',
        'HTTP_X_FORWARDED_PORT' => '443',
        'HTTP_HOST' => 'carexp.cranbrookbells.net',
    ])->get('/proxy-check');

    $response
        ->assertOk()
        ->assertJson([
            'secure' => true,
        ]);

    expect($response->json('dashboard'))->toStartWith('https://');
});
