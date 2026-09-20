<?php

declare(strict_types=1);

namespace ABP\Security;

final class AccessPolicy
{
    public function patientOwns(int $authenticatedPatientId, int $resourcePatientId): bool
    {
        return $authenticatedPatientId > 0 && $authenticatedPatientId === $resourcePatientId;
    }

    public function doctorOwnsAppointment(int $authenticatedDoctorId, int $appointmentDoctorId): bool
    {
        return $authenticatedDoctorId > 0 && $authenticatedDoctorId === $appointmentDoctorId;
    }

    public function doctorOwnsPrescription(int $authenticatedDoctorId, int $prescriptionDoctorId): bool
    {
        return $authenticatedDoctorId > 0 && $authenticatedDoctorId === $prescriptionDoctorId;
    }
}
