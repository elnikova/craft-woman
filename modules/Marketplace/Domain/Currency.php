<?php

namespace Modules\Marketplace\Domain;

/** Supported currencies (PRD §16, schema §2.6). */
enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case UAH = 'UAH';
}
