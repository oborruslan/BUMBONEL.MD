<?php

class PaymentEmailNotifier
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function notify($paymentResult, ?array $order = null, array $payment = [])
    {
        if (!($this->config['enabled'] ?? false)) {
            return false;
        }

        $to = $this->normalizeRecipients($this->config['to'] ?? []);
        if (!$to) {
            return false;
        }

        $externalId = $order['external_id'] ?? ($payment['ExternalId'] ?? 'unknown');
        $subject = sprintf('[Bumbonel] Plata %s pentru comanda %s', $this->labelResult($paymentResult), $externalId);
        $body = $this->buildBody($paymentResult, $order, $payment);
        $headers = $this->buildHeaders();

        return mail(implode(',', $to), $subject, $body, $headers);
    }

    private function buildBody($paymentResult, ?array $order = null, array $payment = [])
    {
        $lines = [
            'Rezultat plata Bumbonel',
            '=======================',
            '',
            'Rezultat plata: ' . $this->labelResult($paymentResult),
            'Generat la: ' . date('Y-m-d H:i:s'),
            '',
        ];

        if (!$order) {
            $lines[] = 'Comanda: nu a fost gasita local';
            $lines[] = '';
            $lines[] = 'Date procesator plata:';
            $lines[] = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            return implode("\n", $lines);
        }

        $lines = array_merge($lines, [
            'Comanda',
            '-------',
            'ID intern: ' . $order['id'],
            'ID extern: ' . $order['external_id'],
            'Status: ' . $order['status'],
            'Metoda plata: ' . $order['payment_method'],
            'Metoda livrare: ' . $order['delivery_method'],
            'Creata la: ' . $order['created_at'],
            'Actualizata la: ' . $order['updated_at'],
            '',
            'Client',
            '------',
            'Nume: ' . $order['customer_name'],
            'Telefon: ' . $order['customer_phone'],
            'Email: ' . ($order['user_email'] ?: '-'),
            'Adresa: ' . $order['customer_address'],
            'Oras: ' . $order['customer_city'],
            '',
            'Totaluri',
            '--------',
            'Total produse: ' . $this->money($order['total_products']),
            'Cost livrare: ' . $this->money($order['delivery_cost']),
            'Total comanda: ' . $this->money($order['total_amount']),
            'Total trimis de client: ' . ($order['client_total_amount'] !== null ? $this->money($order['client_total_amount']) : '-'),
            'Valuta: ' . $order['currency'],
            'ID plata Paynet: ' . ($order['paynet_payment_id'] ?: '-'),
            'Paynet status: ' . ($order['paynet_status'] !== null ? $order['paynet_status'] : '-'),
            '',
            'Produse',
            '-------',
        ]);

        foreach (($order['items'] ?? []) as $index => $item) {
            $lines[] = sprintf(
                '%d. [%s] %s | Cantitate: %s | Pret unitar: %s | Total linie: %s',
                $index + 1,
                $item['product_id'],
                $item['name'],
                $item['quantity'],
                $this->money($item['unit_price']),
                $this->money($item['line_total'])
            );
            if (!empty($item['comment'])) {
                $lines[] = '   Comentariu: ' . $item['comment'];
            }
        }

        return implode("\n", $lines);
    }

    private function buildHeaders()
    {
        $from = $this->cleanHeaderValue($this->config['from'] ?? 'orders@localhost');
        $replyTo = $this->cleanHeaderValue($this->config['reply_to'] ?? $from);

        return implode("\r\n", [
            'From: ' . $from,
            'Reply-To: ' . $replyTo,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion(),
        ]);
    }

    private function normalizeRecipients($recipients)
    {
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }

        return array_values(array_filter(array_map(function ($email) {
            $email = trim((string)$email);
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }, is_array($recipients) ? $recipients : [])));
    }

    private function cleanHeaderValue($value)
    {
        return trim(str_replace(["\r", "\n"], '', (string)$value));
    }

    private function money($value)
    {
        return number_format((float)$value, 2, '.', '') . ' MDL';
    }

    private function labelResult($paymentResult)
    {
        $labels = [
            'successful' => 'reusita',
            'failed' => 'esuată',
            'cancelled' => 'anulata',
            'pending' => 'in asteptare',
        ];

        return $labels[$paymentResult] ?? (string)$paymentResult;
    }
}
