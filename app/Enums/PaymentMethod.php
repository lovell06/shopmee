<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentMethod: string
{
    use HasValues;

    case CashOnDelivery = 'cash_on_delivery';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';
    case Momo = 'momo';
    case ZaloPay = 'zalopay';
    case VNPay = 'vnpay';
}
