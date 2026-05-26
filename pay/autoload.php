<?php

spl_autoload_register(function ($class) {
    $classes = [
        'PaynetCode' => __DIR__ . '/paynet/PaynetCode.php',
        'PaynetEcomAPI' => __DIR__ . '/paynet/PaynetEcomAPI.php',
        'PaynetRequest' => __DIR__ . '/paynet/PaynetRequest.php',
        'PaynetResult' => __DIR__ . '/paynet/PaynetResult.php',
    ];

    if (isset($classes[$class])) {
        require_once $classes[$class];
    }
});
