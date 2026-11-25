<?php

namespace Modules\Education\Domain;

/** How long a student keeps access after buying (domain-rules.md §13). */
enum AccessType: string
{
    case Lifetime = 'lifetime';
    case Limited = 'limited';
}
