<?php

declare(strict_types=1);

namespace ABP\Domain;

final class AvailabilityEligibility
{
    public function reason(bool $doctorActive, bool $serviceActive, bool $assigned): ?string
    {
        if (! $assigned) return 'not_assigned';
        if (! $doctorActive) return 'inactive_doctor';
        if (! $serviceActive) return 'inactive_service';
        return null;
    }
}
