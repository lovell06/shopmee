<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ProductStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Active = 'active';
    case Hidden = 'hidden';
    case OutOfStock = 'out_of_stock';
}
