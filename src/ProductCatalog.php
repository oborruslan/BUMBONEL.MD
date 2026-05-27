<?php

class ProductCatalog
{
    private $products = [];

    public function __construct($path)
    {
        if (!is_file($path)) {
            throw new RuntimeException('Product catalog file not found.');
        }

        $json = file_get_contents($path);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Product catalog JSON is invalid.');
        }

        foreach ($decoded as $product) {
            if (!isset($product['id'])) {
                continue;
            }
            $this->products[(string)$product['id']] = $product;
        }
    }

    public function all()
    {
        return array_values($this->products);
    }

    public function find($id)
    {
        return $this->products[(string)$id] ?? null;
    }
}
