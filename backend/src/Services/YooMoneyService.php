<?php

namespace App\Services;

class YooMoneyService
{
    private string $receiver;
    private string $secret;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $yoomoney = $config['yoomoney'];
        $this->receiver = $yoomoney['receiver'];
        $this->secret = $yoomoney['secret'] ?? '';
    }

    public function createPaymentLink(int $userId, string $plan): string
    {
        $config = require __DIR__ . '/../../config/config.php';
        $plans = $config['plans'];
        
        if (!isset($plans[$plan]) || !isset($plans[$plan]['price'])) {
            throw new \InvalidArgumentException('Invalid plan');
        }

        $amount = $plans[$plan]['price'];
        $paymentId = 'payment_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(8));
        
        // Сохранить платеж в БД
        $db = \App\Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO payments (user_id, plan, amount, payment_id, status)
            VALUES (?, ?, ?, ?, "pending")
        ');
        $stmt->execute([$userId, $plan, $amount, $paymentId]);

        // Создать ссылку на оплату через ЮMoney P2P
        // Формат: https://yoomoney.ru/quickpay/confirm?receiver=...&sum=...&formcomment=...&short-dest=...&label=...
        $params = [
            'receiver' => $this->receiver,
            'sum' => $amount,
            'formcomment' => 'Подписка ' . $plans[$plan]['name'],
            'short-dest' => 'Подписка ' . $plans[$plan]['name'],
            'label' => $paymentId,
            'quickpay-form' => 'small',
            'targets' => 'Подписка ' . $plans[$plan]['name'],
            'successURL' => ($_ENV['FRONTEND_URL'] ?? 'http://localhost') . '/payment/success?payment_id=' . urlencode($paymentId),
        ];

        return 'https://yoomoney.ru/quickpay/confirm?' . http_build_query($params);
    }

    public function verifyPayment(string $paymentId): bool
    {
        // В реальном проекте здесь должна быть проверка через API ЮMoney
        // Для MVP просто проверяем наличие платежа в БД
        $db = \App\Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM payments WHERE payment_id = ?');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        return $payment !== false;
    }
}

