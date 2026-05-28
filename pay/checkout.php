<?php

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/autoload.php';

function checkout_response($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    checkout_response(['ok' => false, 'message' => 'Metoda HTTP nu este permisa.'], 405);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') !== 0) {
    checkout_response(['ok' => false, 'message' => 'Content-Type invalid.'], 415);
}

$rawInput = file_get_contents('php://input');

try {
    $config = require dirname(__DIR__) . '/config.php';
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        checkout_response(['ok' => false, 'message' => 'Cerere invalida.'], 400);
    }

    $catalog = new ProductCatalog($config['products']['file']);
    $validator = new CheckoutValidator($catalog);
    $order = $validator->validate($input);

    $database = new Database($config['database']);
    $orders = new OrderRepository($database->pdo());
    $created = $orders->create($order);

    if ($order['payment_method'] !== 'card') {
        checkout_response([
            'ok' => true,
            'orderId' => $created['id'],
            'externalId' => $created['external_id'],
            'status' => 'pending',
        ]);
    }

    $paynetConfig = $config['paynet'];
    $appBaseUrl = rtrim($config['app']['base_url'], '/');
    $paynetRequest = new PaynetRequest();
    $paynetRequest->ExternalID = $created['external_id'];
    $paynetRequest->LinkSuccess = $appBaseUrl . '/pay/return?result=ok&id=' . rawurlencode($created['external_id']);
    $paynetRequest->LinkCancel = $appBaseUrl . '/pay/return?result=cancel&id=' . rawurlencode($created['external_id']);
    $paynetLang = strtolower((string)($input['lang'] ?? 'ro'));
    $paynetRequest->Lang = in_array($paynetLang, ['ro', 'ru', 'en'], true) ? $paynetLang : 'ro';
    $paynetRequest->Products = array_map(function ($item, $index) {
        return [
            'LineNo' => (string)($index + 1),
            'Code' => $item['product_id'],
            'Barcode' => $item['product_id'],
            'Name' => $item['name'],
            'Description' => $item['comment'] ?: $item['name'],
            'Quantity' => $item['quantity'] * 100,
            'UnitPrice' => $item['unit_price'] * 100,
        ];
    }, $order['items'], array_keys($order['items']));
    $paynetRequest->Amount = $order['total_products'] * 100;
    $paynetRequest->Service = [
        'Name' => 'Bumbonel',
        'Description' => 'Comanda Bumbonel #' . $created['external_id'],
        'Amount' => $paynetRequest->Amount,
        'Products' => $paynetRequest->Products,
    ];
    $paynetRequest->Customer = [
        'Code' => $order['customer']['phone'],
        'Address' => $order['customer']['address'] . ', ' . $order['customer']['city'],
        'Name' => $order['customer']['full_name'],
    ];

    $api = new PaynetEcomAPI(
        $paynetConfig['merchant_code'],
        $paynetConfig['merchant_secret_key'],
        $paynetConfig['merchant_user'],
        $paynetConfig['merchant_user_password'],
        $paynetConfig['environment'],
        $paynetConfig
    );
    $form = $api->FormCreate($paynetRequest);
    if (!$form->IsOk()) {
        checkout_response([
            'ok' => false,
            'message' => $form->Message ?: 'Nu am putut pregati plata Paynet.',
        ], 502);
    }

    checkout_response([
        'ok' => true,
        'orderId' => $created['id'],
        'externalId' => $created['external_id'],
        'status' => 'pending_payment',
        'paymentFormHtml' => $form->Data,
    ]);
} catch (InvalidArgumentException $e) {
    checkout_response(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log($e);
    checkout_response(['ok' => false, 'message' => 'Nu am putut salva comanda.'], 500);
}
