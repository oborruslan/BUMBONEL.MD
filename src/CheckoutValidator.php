<?php

class CheckoutValidator
{
    private $allowedDeliveryMethods = ['nova', 'dpd', 'post', 'pickup'];
    private $allowedPaymentMethods = ['card', 'ramburs'];
    private $catalog;

    public function __construct(ProductCatalog $catalog)
    {
        $this->catalog = $catalog;
    }

    public function validate(array $input)
    {
        $fullName = $this->cleanText($input['fullName'] ?? '', 120);
        $phone = $this->cleanText($input['phone'] ?? '', 32);
        $address = $this->cleanText($input['address'] ?? '', 255);
        $city = $this->cleanText($input['city'] ?? '', 80);
        $deliveryMethod = $this->cleanText($input['deliveryMethod'] ?? '', 32);
        $paymentMethod = $this->cleanText($input['paymentMethod'] ?? '', 32);

        if ($fullName === '') {
            throw new InvalidArgumentException('Numele este obligatoriu.');
        }
        if (!preg_match('/^\+373\d{8}$/', $phone)) {
            throw new InvalidArgumentException('Telefonul trebuie sa fie in format +373xxxxxxxx.');
        }
        if ($address === '') {
            throw new InvalidArgumentException('Adresa este obligatorie.');
        }
        if ($city === '') {
            throw new InvalidArgumentException('Orasul este obligatoriu.');
        }
        if (!in_array($deliveryMethod, $this->allowedDeliveryMethods, true)) {
            throw new InvalidArgumentException('Metoda de livrare nu este valida.');
        }
        if (!in_array($paymentMethod, $this->allowedPaymentMethods, true)) {
            throw new InvalidArgumentException('Metoda de plata nu este valida.');
        }

        $items = $this->validateItems($input['products'] ?? []);
        $deliveryCost = $this->money($input['deliveryCost'] ?? 0);
        $totalProducts = array_reduce($items, function ($sum, $item) {
            return $sum + $item['line_total'];
        }, 0.0);
        $totalAmount = round($totalProducts + $deliveryCost, 2);
        $clientTotal = $this->money($input['totalAmount'] ?? null);

        if ($clientTotal !== null && abs($clientTotal - $totalAmount) > 0.01) {
            throw new InvalidArgumentException('Totalul comenzii nu este valid.');
        }

        return [
            'user_id' => $this->cleanText($input['userId'] ?? '', 128),
            'user_email' => $this->cleanText($input['userEmail'] ?? '', 190),
            'customer' => [
                'full_name' => $fullName,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
            ],
            'delivery_method' => $deliveryMethod,
            'payment_method' => $paymentMethod,
            'delivery_cost' => $deliveryCost,
            'total_products' => round($totalProducts, 2),
            'total_amount' => $totalAmount,
            'client_total_amount' => $clientTotal,
            'items' => $items,
        ];
    }

    private function validateItems($items)
    {
        if (!is_array($items) || count($items) === 0) {
            throw new InvalidArgumentException('Cosul este gol.');
        }
        if (count($items) > 100) {
            throw new InvalidArgumentException('Cosul contine prea multe produse.');
        }

        $validated = [];
        foreach ($items as $item) {
            $productId = $this->cleanText($item['id'] ?? '', 64);
            if ($productId === '') {
                throw new InvalidArgumentException('Produs invalid in cos.');
            }

            $catalogProduct = $this->catalog->find($productId);
            if (!$catalogProduct) {
                throw new InvalidArgumentException('Produsul nu exista in catalog.');
            }

            $name = $this->cleanText($catalogProduct['name'], 255);
            $quantity = (int)($item['qty'] ?? 0);
            $unitPrice = round((float)$catalogProduct['price'], 2);
            $comment = $this->cleanText($item['comment'] ?? '', 500);

            if ($quantity < 1 || $quantity > 99) {
                throw new InvalidArgumentException('Cantitatea produsului nu este valida.');
            }

            $validated[] = [
                'product_id' => $productId,
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
                'comment' => $comment,
            ];
        }

        return $validated;
    }

    private function cleanText($value, $maxLength)
    {
        $value = trim((string)$value);
        $value = preg_replace('/\s+/u', ' ', $value);
        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    private function money($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Valoare monetara invalida.');
        }
        return round((float)$value, 2);
    }
}
