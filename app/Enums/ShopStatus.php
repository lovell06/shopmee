<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ShopStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Blocked = 'blocked';
    case Closed = 'closed';
}
