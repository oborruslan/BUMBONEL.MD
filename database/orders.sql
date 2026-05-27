CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(64) NOT NULL UNIQUE,
    user_id VARCHAR(128) NULL,
    user_email VARCHAR(190) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    customer_name VARCHAR(120) NOT NULL,
    customer_phone VARCHAR(32) NOT NULL,
    customer_address VARCHAR(255) NOT NULL,
    customer_city VARCHAR(80) NOT NULL,
    delivery_method VARCHAR(32) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    currency SMALLINT UNSIGNED NOT NULL DEFAULT 498,
    total_products DECIMAL(10,2) NOT NULL,
    delivery_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    client_total_amount DECIMAL(10,2) NULL,
    paynet_payment_id VARCHAR(128) NULL,
    paynet_status INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
