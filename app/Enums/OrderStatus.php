<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum OrderStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Failed = 'failed';
}
