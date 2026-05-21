<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Purpose: string
{
    use HasValues;

    case UserRegistration = 'userRegistration';
    case PasswordForgot = 'passwordForgot';
}
