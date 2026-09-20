<?php

declare(strict_types=1);

namespace ABP\Clinical;

use ABP\Infrastructure\Database;
use WP_Error;

final class PrescriptionService
{
    public function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT pr.*,d.name doctor_name,CONCAT(p.first_name," ",p.last_name) patient_name,a.start_at appointment_start FROM ' . Database::table('prescriptions') . ' pr INNER JOIN ' . Database::table('doctors') . ' d ON d.id=pr.doctor_id INNER JOIN ' . Database::table('patients') . ' p ON p.id=pr.patient_id INNER JOIN ' . Database::table('appointments') . ' a ON a.id=pr.appointment_id WHERE pr.id=%d',
            $id
        ), ARRAY_A);
        if (! $row) return null;
        $row['items'] = $wpdb->get_results($wpdb->prepare('SELECT id,medicine_name,dosage,frequency,duration,instructions,sort_order FROM ' . Database::table('prescription_items') . ' WHERE prescription_id=%d ORDER BY sort_order,id', $id), ARRAY_A);
        return $row;
    }

    /** @return array<string,mixed>|WP_Error */
    public function save(array $data, int $doctorId, int $id = 0)
    {
        global $wpdb;
        $existing = $id ? $this->get($id) : null;
        if ($id && ! $existing) return new WP_Error('abp_prescription_not_found', __('Prescription not found.', 'appointment-booking-plugin'), array('status' => 404));
        if ($existing && (int) $existing['doctor_id'] !== $doctorId && ! current_user_can('manage_options')) {
            return new WP_Error('abp_forbidden_prescription', __('You cannot edit this prescription.', 'appointment-booking-plugin'), array('status' => 403));
        }
        $appointmentId = absint($data['appointment_id'] ?? ($existing['appointment_id'] ?? 0));
        $appointment = $wpdb->get_row($wpdb->prepare('SELECT id,doctor_id,patient_id FROM ' . Database::table('appointments') . ' WHERE id=%d', $appointmentId), ARRAY_A);
        if (! $appointment || ((int) $appointment['doctor_id'] !== $doctorId && ! current_user_can('manage_options'))) {
            return new WP_Error('abp_invalid_clinical_context', __('The appointment is not assigned to this doctor.', 'appointment-booking-plugin'), array('status' => 403));
        }
        $items = array_values(array_filter((array) ($data['items'] ?? array()), static fn($item): bool => is_array($item) && sanitize_text_field($item['medicine_name'] ?? '') !== ''));
        if (! $items) return new WP_Error('abp_prescription_items_required', __('At least one medicine is required.', 'appointment-booking-plugin'), array('status' => 400));
        $attachmentId = absint($data['attachment_id'] ?? 0);
        if ($attachmentId && (! current_user_can('upload_files') || get_post_type($attachmentId) !== 'attachment')) return new WP_Error('abp_invalid_prescription_attachment', __('The attachment is not permitted.', 'appointment-booking-plugin'), array('status' => 403));
        $row = array(
            'patient_id' => (int) $appointment['patient_id'], 'doctor_id' => (int) $appointment['doctor_id'], 'appointment_id' => $appointmentId,
            'prescribed_on' => sanitize_text_field($data['prescribed_on'] ?? current_time('Y-m-d')),
            'diagnosis' => sanitize_textarea_field($data['diagnosis'] ?? ''),
            'instructions' => sanitize_textarea_field($data['instructions'] ?? ''),
            'additional_notes' => sanitize_textarea_field($data['additional_notes'] ?? ''),
            'attachment_id' => $attachmentId ?: null,
            'updated_at' => current_time('mysql'),
        );
        $validDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['prescribed_on']);
        if (! $validDate || $validDate->format('Y-m-d') !== $row['prescribed_on']) return new WP_Error('abp_invalid_prescription_date', __('Prescription date must use YYYY-MM-DD.', 'appointment-booking-plugin'), array('status' => 400));
        $wpdb->query('START TRANSACTION');
        try {
            if ($id) {
                $result = $wpdb->update(Database::table('prescriptions'), $row, array('id' => $id));
            } else {
                $row['reference'] = 'RX-' . strtoupper(wp_generate_password(10, false, false));
                $row['created_at'] = current_time('mysql');
                $result = $wpdb->insert(Database::table('prescriptions'), $row);
                $id = (int) $wpdb->insert_id;
            }
            if ($result === false) throw new \RuntimeException('prescription_write_failed');
            $wpdb->delete(Database::table('prescription_items'), array('prescription_id' => $id));
            foreach ($items as $order => $item) {
                $inserted = $wpdb->insert(Database::table('prescription_items'), array(
                    'prescription_id' => $id, 'medicine_name' => sanitize_text_field($item['medicine_name']),
                    'dosage' => sanitize_text_field($item['dosage'] ?? ''), 'frequency' => sanitize_text_field($item['frequency'] ?? ''),
                    'duration' => sanitize_text_field($item['duration'] ?? ''), 'instructions' => sanitize_textarea_field($item['instructions'] ?? ''),
                    'sort_order' => $order,
                ));
                if ($inserted === false) throw new \RuntimeException('prescription_item_write_failed');
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('abp_prescription_save_failed', __('Could not save the prescription.', 'appointment-booking-plugin'), array('status' => 500));
        }
        $saved = $this->get($id);
        do_action('abp_prescription_event', $existing ? 'prescription_updated' : 'prescription_created', $saved);
        return $saved;
    }
}
