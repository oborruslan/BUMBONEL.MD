<?php

spl_autoload_register(function ($class) {
    $classes = [
        'CheckoutValidator' => dirname(__DIR__) . '/src/CheckoutValidator.php',
        'Database' => dirname(__DIR__) . '/src/Database.php',
        'OrderRepository' => dirname(__DIR__) . '/src/OrderRepository.php',
        'PaymentEmailNotifier' => dirname(__DIR__) . '/src/PaymentEmailNotifier.php',
        'ProductCatalog' => dirname(__DIR__) . '/src/ProductCatalog.php',
        'PaynetCode' => __DIR__ . '/paynet/PaynetCode.php',
        'PaynetEcomAPI' => __DIR__ . '/paynet/PaynetEcomAPI.php',
        'PaynetRequest' => __DIR__ . '/paynet/PaynetRequest.php',
        'PaynetResult' => __DIR__ . '/paynet/PaynetResult.php',
    ];

    if (isset($classes[$class])) {
        require_once $classes[$class];
    }
});
