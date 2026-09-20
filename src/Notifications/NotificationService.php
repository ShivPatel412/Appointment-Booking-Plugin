<?php

declare(strict_types=1);

namespace ABP\Notifications;

use ABP\Infrastructure\Database;
use ABP\Infrastructure\Logger;
use ABP\Infrastructure\Settings;

final class NotificationService
{
    public const EVENTS = array('account_created', 'appointment_created', 'appointment_rescheduled', 'appointment_cancelled', 'appointment_status_changed', 'appointment_completed', 'prescription_created', 'prescription_updated');

    private MailProvider $mailer;
    private TemplateRenderer $renderer;

    public function __construct(?MailProvider $mailer = null, ?TemplateRenderer $renderer = null)
    {
        $this->mailer = $mailer ?? new WordPressMailProvider();
        $this->renderer = $renderer ?? new TemplateRenderer();
    }

    public function send(string $event, int $appointmentId, string $recipient, array $values): bool
    {
        global $wpdb;
        if (! in_array($event, self::EVENTS, true) || ! is_email($recipient)) {
            return false;
        }
        $isPrescription = str_starts_with($event, 'prescription_');
        $subject = $isPrescription ? __('A prescription is available in your secure portal', 'appointment-booking-plugin') : sprintf(__('Appointment update: %s', 'appointment-booking-plugin'), str_replace('_', ' ', $event));
        $template = $isPrescription
            ? '<h2>{{business_name}}</h2><p>Hello {{patient_name}},</p><p>Your doctor has updated a prescription. For your privacy, medicine and clinical details are available only in the secure patient portal:</p><p>{{portal_url}}</p>'
            : '<h2>{{business_name}}</h2><p>Hello {{patient_name}},</p><p>Your appointment with {{doctor_name}} for {{service_name}} is scheduled at {{start_at}}.</p><p>Status: {{status}}</p>';
        $sent = $this->mailer->send($recipient, $subject, $this->renderer->render($template, $values));
        $wpdb->insert(Database::table('notification_log'), array(
            'event' => $event, 'appointment_id' => $appointmentId, 'recipient' => $recipient,
            'subject' => $subject, 'status' => $sent ? 'sent' : 'failed',
            'error' => $sent ? null : __('Mail provider rejected the message.', 'appointment-booking-plugin'),
            'created_at' => current_time('mysql'),
        ));
        if (! $sent) Logger::error('Email notification failed.', array('event' => $event, 'appointment_id' => $appointmentId));
        return $sent;
    }

    public static function handleAppointmentEvent(string $event, ?array $appointment): void
    {
        if (! $appointment || empty($appointment['patient_email'])) return;
        (new self())->send($event, (int) $appointment['id'], $appointment['patient_email'], array(
            'business_name' => Settings::get()['business_name'],
            'patient_name' => trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? '')),
            'doctor_name' => $appointment['doctor_name'] ?? '',
            'service_name' => $appointment['service_name'] ?? '',
            'start_at' => $appointment['start_at'] ?? '',
            'status' => $appointment['status'] ?? '',
        ));
    }

    public static function handlePrescriptionEvent(string $event, ?array $prescription): void
    {
        global $wpdb;
        if (! $prescription) return;
        $recipient = $wpdb->get_var($wpdb->prepare('SELECT email FROM ' . Database::table('patients') . ' WHERE id=%d', (int) $prescription['patient_id']));
        if (! is_email($recipient)) return;
        $portalUrl = get_permalink((int) get_option('abp_patient_portal_page_id'));
        (new self())->send($event, (int) $prescription['appointment_id'], $recipient, array(
            'business_name' => Settings::get()['business_name'], 'patient_name' => $prescription['patient_name'] ?? '',
            'doctor_name' => $prescription['doctor_name'] ?? '', 'service_name' => __('your visit', 'appointment-booking-plugin'),
            'start_at' => '', 'portal_url' => $portalUrl,
            'status' => __('Available', 'appointment-booking-plugin'),
        ));
    }
}
