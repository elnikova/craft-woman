<?php

namespace Modules\Marketplace\Domain;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';

    /** Physical products need variants with stock; digital ones need downloadable files. */
    public function tracksInventory(): bool
    {
        return $this === self::Physical;
    }
}
