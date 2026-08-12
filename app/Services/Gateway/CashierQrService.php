<?php

namespace App\Services\Gateway;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Common\EccLevel;
use Marvel\Database\Models\Transaction;

class CashierQrService
{
    public function generateSvg(Transaction $transaction): string
    {
        $order = $transaction->order;

        $payload = json_encode([
            'transaction' => $transaction->uuid,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => round((float) $transaction->amount, 2),
            'currency' => $transaction->currency ?? $order->currency_code ?? $order->base_currency_code,
        ]);

        $size = (int) config('payment.pay_at_cashier.size', 50);

        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::L,
            'scale' => max(1, (int) ($size / 50)),
            'outputBase64' => false,
            'svgAddXmlHeader' => true,
        ]);

        $qrcode = new QRCode($options);

        return $qrcode->render($payload);
    }

    public function generateBase64DataUri(Transaction $transaction): string
    {
        $svg = $this->generateSvg($transaction);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
