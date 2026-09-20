<?php

declare(strict_types=1);

namespace ABP\Infrastructure;

final class Capabilities
{
    public const MANAGE = 'abp_manage_appointments';
    public const VIEW = 'abp_view_appointments';
    public const DELETE = 'abp_delete_appointments';
    public const CLINICAL = 'abp_manage_clinical_records';
    public const DOCTOR_PORTAL = 'abp_use_doctor_portal';
    public const PATIENT_PORTAL = 'abp_use_patient_portal';

    public static function install(): void
    {
        $admin = get_role('administrator');
        if (! $admin) {
            return;
        }
        foreach (array(self::MANAGE, self::VIEW, self::DELETE, self::CLINICAL, self::DOCTOR_PORTAL, self::PATIENT_PORTAL) as $capability) {
            $admin->add_cap($capability);
        }
        add_role('abp_patient', __('Appointment Patient', 'appointment-booking-plugin'), array('read' => true));
        add_role('abp_doctor', __('Appointment Doctor', 'appointment-booking-plugin'), array('read' => true));
        $patient = get_role('abp_patient');
        if ($patient) $patient->add_cap(self::PATIENT_PORTAL);
        $doctor = get_role('abp_doctor');
        if ($doctor) {
            foreach (array(self::CLINICAL, self::DOCTOR_PORTAL) as $capability) $doctor->add_cap($capability);
            foreach (array(self::VIEW, self::MANAGE, self::DELETE) as $capability) $doctor->remove_cap($capability);
        }
    }
}
