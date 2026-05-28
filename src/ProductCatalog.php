<?php

class ProductCatalog
{
    private $products = [];

    public function __construct($path)
    {
        if (!is_file($path)) {
            throw new RuntimeException('Product catalog file not found.');
        }

        $products = $this->loadCsv($path);

        foreach ($products as $product) {
            if (!isset($product['id'])) {
                continue;
            }
            $this->products[(string)$product['id']] = $product;
        }
    }

    private function loadCsv($path)
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException('Product catalog CSV could not be opened.');
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('Product catalog CSV is invalid.');
        }

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        $products = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }

            $row = array_pad($row, count($headers), '');
            $record = array_combine($headers, array_slice($row, 0, count($headers)));
            if (!is_array($record)) {
                continue;
            }

            $product = $this->normalizeCsvProduct($record);
            if (!$this->isActiveProduct($product)) {
                continue;
            }

            $products[] = $product;
        }
        fclose($handle);

        return $products;
    }

    private function normalizeCsvProduct(array $record)
    {
        $product = [
            'id' => $this->numberOrString($record['id'] ?? ''),
            'name' => trim((string)($record['name'] ?? '')),
            'code' => trim((string)($record['code'] ?? '')),
            'category' => trim((string)($record['category'] ?? '')),
            'subcategory' => trim((string)($record['subcategory'] ?? '')),
            'brand' => trim((string)($record['brand'] ?? '')),
            'age' => trim((string)($record['age'] ?? '')),
            'price' => $this->nullableNumber($record['price'] ?? ''),
            'oldPrice' => $this->nullableNumber($record['old_price'] ?? ''),
            'img' => trim((string)($record['img'] ?? '')),
            'images' => $this->splitList($record['images'] ?? ''),
            'shortDescription' => trim((string)($record['short_description'] ?? '')),
            'description' => trim((string)($record['description'] ?? '')),
            'stock' => trim((string)($record['stock'] ?? '')),
            'active' => $this->truthy($record['active'] ?? ''),
            'videoUrls' => $this->splitList($record['video_urls'] ?? ''),
            'warehouse' => trim((string)($record['warehouse'] ?? '')),
            'weight' => trim((string)($record['weight'] ?? '')),
            'dimensions' => trim((string)($record['dimensions'] ?? '')),
            'material' => trim((string)($record['material'] ?? '')),
        ];

        if (!$product['img'] && count($product['images']) > 0) {
            $product['img'] = $product['images'][0];
        }

        return $product;
    }

    private function isActiveProduct(array $product)
    {
        return !array_key_exists('active', $product) || $product['active'];
    }

    private function splitList($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $value)), function ($item) {
            return $item !== '';
        }));
    }

    private function nullableNumber($value)
    {
        $value = trim((string)$value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function numberOrString($value)
    {
        $value = trim((string)$value);
        if ($value !== '' && ctype_digit($value)) {
            return (int)$value;
        }

        return $value;
    }

    private function truthy($value)
    {
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'da'], true);
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
