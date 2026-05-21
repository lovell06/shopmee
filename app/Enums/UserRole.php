<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case Admin = 'admin';
    case Seller = 'seller';
    case Buyer = 'buyer';
}
