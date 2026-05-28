<?php

class OrderRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $order)
    {
        $externalId = $this->generateExternalId();
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO orders (
                    external_id, user_id, user_email, status, customer_name,
                    customer_phone, customer_address, customer_city,
                    delivery_method, payment_method, currency,
                    total_products, delivery_cost, total_amount, client_total_amount,
                    created_at, updated_at
                ) VALUES (
                    :external_id, :user_id, :user_email, :status, :customer_name,
                    :customer_phone, :customer_address, :customer_city,
                    :delivery_method, :payment_method, :currency,
                    :total_products, :delivery_cost, :total_amount, :client_total_amount,
                    :created_at, :updated_at
                )
            ");
            $stmt->execute([
                ':external_id' => $externalId,
                ':user_id' => $order['user_id'] ?: null,
                ':user_email' => $order['user_email'] ?: null,
                ':status' => $order['payment_method'] === 'card' ? 'pending_payment' : 'pending',
                ':customer_name' => $order['customer']['full_name'],
                ':customer_phone' => $order['customer']['phone'],
                ':customer_address' => $order['customer']['address'],
                ':customer_city' => $order['customer']['city'],
                ':delivery_method' => $order['delivery_method'],
                ':payment_method' => $order['payment_method'],
                ':currency' => 498,
                ':total_products' => $order['total_products'],
                ':delivery_cost' => $order['delivery_cost'],
                ':total_amount' => $order['total_amount'],
                ':client_total_amount' => $order['client_total_amount'],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $orderId = (int)$this->pdo->lastInsertId();
            $itemStmt = $this->pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, name, quantity, unit_price,
                    line_total, comment, created_at
                ) VALUES (
                    :order_id, :product_id, :name, :quantity, :unit_price,
                    :line_total, :comment, :created_at
                )
            ");

            foreach ($order['items'] as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':name' => $item['name'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':line_total' => $item['line_total'],
                    ':comment' => $item['comment'] ?: null,
                    ':created_at' => $now,
                ]);
            }

            $this->pdo->commit();
            return [
                'id' => $orderId,
                'external_id' => $externalId,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findByExternalId($externalId)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE external_id = :external_id LIMIT 1');
        $stmt->execute([':external_id' => $externalId]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function findWithItemsByExternalId($externalId)
    {
        $order = $this->findByExternalId($externalId);
        if (!$order) {
            return null;
        }

        $stmt = $this->pdo->prepare('
            SELECT product_id, name, quantity, unit_price, line_total, comment
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ');
        $stmt->execute([':order_id' => $order['id']]);
        $order['items'] = $stmt->fetchAll();

        return $order;
    }

    public function markPaynetStatus($externalId, $status, $paymentId = null)
    {
        $nextStatus = ((int)$status === 4) ? 'paid' : 'pending_payment';
        $stmt = $this->pdo->prepare("
            UPDATE orders
            SET status = :status,
                paynet_status = :paynet_status,
                paynet_payment_id = COALESCE(:paynet_payment_id, paynet_payment_id),
                updated_at = :updated_at
            WHERE external_id = :external_id
        ");
        $stmt->execute([
            ':status' => $nextStatus,
            ':paynet_status' => $status,
            ':paynet_payment_id' => $paymentId,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':external_id' => $externalId,
        ]);
    }

    public function markStatus($externalId, $status)
    {
        $stmt = $this->pdo->prepare("
            UPDATE orders
            SET status = :status,
                updated_at = :updated_at
            WHERE external_id = :external_id
        ");
        $stmt->execute([
            ':status' => $status,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':external_id' => $externalId,
        ]);
    }

    private function generateExternalId()
    {
        return (string)round(microtime(true) * 1000) . random_int(100, 999);
    }
}
