<?php

namespace Modules\Identity\Domain;

/** Account access state. A blocked user cannot sign in; their orders and history stay intact. */
enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
