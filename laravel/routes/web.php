<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $legacy = rtrim((string) config('legacy.site_url', ''), '/');

    return $legacy !== ''
        ? redirect()->away($legacy.'/index.php?page=home')
        : view('welcome');
});

require __DIR__.'/auth.php';
