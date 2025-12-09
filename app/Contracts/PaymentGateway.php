<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function generatePaymentUrl(array $params): string;
}
