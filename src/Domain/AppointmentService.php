<?php

declare(strict_types=1);

namespace ABP\Domain;

use ABP\Infrastructure\Database;
use ABP\Infrastructure\Settings;
use DateInterval;
use DateTimeImmutable;
use WP_Error;

final class AppointmentService
{
    public const STATUSES = array('pending', 'approved', 'cancelled', 'rejected', 'completed', 'no-show');

    /** @return array<string,mixed>|WP_Error */
    public function save(array $data, int $id = 0)
    {
        global $wpdb;
        $wasUpdate = $id > 0;
        $notifyCustomer = ! array_key_exists('notify_customer', $data) || rest_sanitize_boolean($data['notify_customer']);
        $previous = $wasUpdate ? $this->get($id) : null;
        $doctorId = absint($data['doctor_id'] ?? 0);
        $serviceId = absint($data['service_id'] ?? 0);
        $patientId = absint($data['patient_id'] ?? 0);
        $startAt = sanitize_text_field($data['start_at'] ?? '');
        $status = sanitize_key($data['status'] ?? Settings::get()['default_status']);
        if (! $doctorId || ! $serviceId || ! $patientId || ! in_array($status, self::STATUSES, true)) {
            return new WP_Error('abp_invalid_appointment', __('Doctor, service, patient, and a valid status are required.', 'appointment-booking-plugin'), array('status' => 400));
        }
        $patientExists = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Database::table('patients') . ' WHERE id=%d AND active=1', $patientId));
        if (! $patientExists) {
            return new WP_Error('abp_invalid_patient', __('Active patient not found.', 'appointment-booking-plugin'), array('status' => 422));
        }

        $lockName = 'abp:' . $doctorId . ':' . substr($startAt, 0, 10);
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lockName));
        if ($locked !== 1) {
            return new WP_Error('abp_lock_timeout', __('Please retry; the schedule is being updated.', 'appointment-booking-plugin'), array('status' => 409));
        }
        try {
            $availability = new AvailabilityService();
            $valid = $availability->validateSlot($doctorId, $serviceId, $startAt, $id);
            if (is_wp_error($valid)) {
                return $valid;
            }
            $duration = (int) $wpdb->get_var($wpdb->prepare('SELECT duration FROM ' . Database::table('services') . ' WHERE id=%d', $serviceId));
            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startAt);
            if (! $start || $duration < 1) {
                return new WP_Error('abp_invalid_start', __('Invalid appointment start or service duration.', 'appointment-booking-plugin'), array('status' => 400));
            }
            $row = array(
                'doctor_id' => $doctorId,
                'service_id' => $serviceId,
                'patient_id' => $patientId,
                'start_at' => $startAt,
                'end_at' => $start->add(new DateInterval('PT' . $duration . 'M'))->format('Y-m-d H:i:s'),
                'duration' => $duration,
                'status' => $status,
                'notes' => sanitize_textarea_field($data['notes'] ?? ''),
                'updated_at' => current_time('mysql'),
            );
            if ($id > 0) {
                $result = $wpdb->update(Database::table('appointments'), $row, array('id' => $id));
            } else {
                $row['created_by'] = get_current_user_id();
                $row['created_at'] = current_time('mysql');
                $result = $wpdb->insert(Database::table('appointments'), $row);
                $id = (int) $wpdb->insert_id;
            }
            if ($result === false) {
                return new WP_Error('abp_database_error', __('Could not save appointment.', 'appointment-booking-plugin'), array('status' => 500));
            }
            $saved = $this->get($id);
            $event = ! $wasUpdate ? 'appointment_created' : (($previous['start_at'] ?? '') !== $startAt ? 'appointment_rescheduled' : 'appointment_status_changed');
            if ($notifyCustomer) {
                do_action('abp_appointment_event', $event, $saved);
            }
            return $saved;
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    public function get(int $id): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT a.*,d.name AS doctor_name,s.name AS service_name,s.color,CONCAT(p.first_name," ",p.last_name) AS patient_name,p.first_name,p.last_name,p.email AS patient_email,p.phone AS patient_phone,p.date_of_birth AS patient_date_of_birth,p.gender AS patient_gender,p.reference AS patient_reference FROM ' . Database::table('appointments') . ' a INNER JOIN ' . Database::table('doctors') . ' d ON d.id=a.doctor_id INNER JOIN ' . Database::table('services') . ' s ON s.id=a.service_id INNER JOIN ' . Database::table('patients') . ' p ON p.id=a.patient_id WHERE a.id=%d',
            $id
        ), ARRAY_A) ?: null;
    }
}
