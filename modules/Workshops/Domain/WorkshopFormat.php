<?php

namespace Modules\Workshops\Domain;

/** Set for the whole workshop: all sessions happen online (meeting link) or at one place. */
enum WorkshopFormat: string
{
    case Online = 'online';
    case Offline = 'offline';
}
