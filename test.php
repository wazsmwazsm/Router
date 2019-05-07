<?php

require_once __DIR__.'/vendor/autoload.php';

use Http\Router;

Router::get('/test', function () {
    return 'aaa';
});

echo Router::dispatch('/test', 'GET');
