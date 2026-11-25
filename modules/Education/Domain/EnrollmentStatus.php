<?php

namespace Modules\Education\Domain;

/** Enrollment state (schema §5.5). Expiry is computed from expires_at; progress is never deleted. */
enum EnrollmentStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
