<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use Midtrans\Config;
use Midtrans\Snap;
use Exception;

class MidtransGateway implements PaymentGateway
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.serverKey');
        Config::$isProduction = config('midtrans.isProduction');
        Config::$is3ds = true;
    }

    public function generatePaymentUrl(array $params): string
    {
        try {
            $snap = Snap::createTransaction($params);
            return $snap->redirect_url;
        } catch (Exception $e) {
            throw new Exception('Gagal membuat link pembayaran: ' . $e->getMessage());
        }
    }
}
