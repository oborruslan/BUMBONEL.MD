<?php

require __DIR__ . '/autoload.php';

function paynet_return_redirect($params)
{
    $query = http_build_query($params);
    header('Location: /index.html' . ($query ? '?' . $query : '') . '#account', true, 303);
    exit;
}

$result = strtolower((string)($_GET['result'] ?? ''));
$externalId = trim((string)($_GET['id'] ?? ''));

if (!in_array($result, ['ok', 'cancel'], true) || $externalId === '') {
    paynet_return_redirect(['paynet' => 'error']);
}

if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $externalId)) {
    paynet_return_redirect(['paynet' => 'error']);
}

if ($result === 'cancel') {
    paynet_return_redirect([
        'paynet' => 'cancelled',
        'order' => $externalId,
    ]);
}

try {
    $config = require dirname(__DIR__) . '/config.php';
    $paynetConfig = $config['paynet'];
    $api = new PaynetEcomAPI(
        $paynetConfig['merchant_code'],
        $paynetConfig['merchant_secret_key'],
        $paynetConfig['merchant_user'],
        $paynetConfig['merchant_user_password'],
        $paynetConfig['environment'],
        $paynetConfig
    );

    $check = $api->PaymentGet($externalId);
    if (!$check->IsOk()) {
        paynet_return_redirect([
            'paynet' => 'pending',
            'order' => $externalId,
        ]);
    }

    $payment = $check->Data[0] ?? [];
    $status = (int)($payment['Status'] ?? 0);

    if ($status === 4) {
        $database = new Database($config['database']);
        $orders = new OrderRepository($database->pdo());
        $orders->markPaynetStatus($externalId, $status, $payment['PaymentId'] ?? null);
    }

    paynet_return_redirect([
        'paynet' => $status === 4 ? 'paid' : 'pending',
        'order' => $externalId,
    ]);
} catch (Throwable $e) {
    error_log($e);
    paynet_return_redirect([
        'paynet' => 'pending',
        'order' => $externalId,
    ]);
}
