<?php

namespace Modules\Orders\Domain;

/** domain-rules.md §25: online payment and payment on delivery. */
enum PaymentMethod: string
{
    case Online = 'online';
    case CashOnDelivery = 'cash_on_delivery';
}
