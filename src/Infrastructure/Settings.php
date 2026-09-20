<?php

declare(strict_types=1);

namespace ABP\Infrastructure;

final class Settings
{
    public static function defaults(): array
    {
        return array(
            'business_name' => get_bloginfo('name'),
            'business_email' => get_option('admin_email'),
            'business_phone' => '',
            'business_address' => '',
            'date_format' => get_option('date_format', 'Y-m-d'),
            'time_format' => get_option('time_format', 'H:i'),
            'currency' => 'USD',
            'first_day_of_week' => (int) get_option('start_of_week', 1),
            'default_status' => 'pending',
            'slot_interval' => 15,
            'patient_registration_enabled' => true,
            'patient_reschedule_enabled' => true,
            'patient_cancellation_enabled' => true,
            'patient_email_editable' => false,
        );
    }

    public static function get(): array
    {
        return array_merge(self::defaults(), (array) get_option('abp_settings', array()));
    }

    public static function sanitize(array $input): array
    {
        $statuses = array('pending', 'approved');
        return array(
            'business_name' => sanitize_text_field($input['business_name'] ?? ''),
            'business_email' => sanitize_email($input['business_email'] ?? ''),
            'business_phone' => sanitize_text_field($input['business_phone'] ?? ''),
            'business_address' => sanitize_textarea_field($input['business_address'] ?? ''),
            'date_format' => sanitize_text_field($input['date_format'] ?? 'Y-m-d'),
            'time_format' => sanitize_text_field($input['time_format'] ?? 'H:i'),
            'currency' => strtoupper(substr(sanitize_text_field($input['currency'] ?? 'USD'), 0, 3)),
            'first_day_of_week' => max(0, min(6, absint($input['first_day_of_week'] ?? 1))),
            'default_status' => in_array($input['default_status'] ?? '', $statuses, true) ? $input['default_status'] : 'pending',
            'slot_interval' => max(5, min(120, absint($input['slot_interval'] ?? 15))),
            'patient_registration_enabled' => rest_sanitize_boolean($input['patient_registration_enabled'] ?? true),
            'patient_reschedule_enabled' => rest_sanitize_boolean($input['patient_reschedule_enabled'] ?? true),
            'patient_cancellation_enabled' => rest_sanitize_boolean($input['patient_cancellation_enabled'] ?? true),
            'patient_email_editable' => rest_sanitize_boolean($input['patient_email_editable'] ?? false),
        );
    }
}
