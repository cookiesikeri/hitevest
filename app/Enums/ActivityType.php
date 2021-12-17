<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class ActivityType extends Enum
{
    const WALLET_TRANSFER =   'Wallet Transfer';
    const LOGIN =   'Auth-login';
    const REGISTER =   'Auth-register';
    const REQUEST_RIDE = 'Request-Ride';
    const CARD_PAYMENT = 'Card Payment';

}
