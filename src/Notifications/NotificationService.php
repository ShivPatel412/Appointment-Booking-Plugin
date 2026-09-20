<?php

declare(strict_types=1);

namespace ABP\Notifications;

use ABP\Infrastructure\Database;
use ABP\Infrastructure\Logger;
use ABP\Infrastructure\Settings;

final class NotificationService
{
    public const EVENTS = array('appointment_created', 'appointment_rescheduled', 'appointment_cancelled', 'appointment_status_changed');

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
        $subject = sprintf(__('Appointment update: %s', 'appointment-booking-plugin'), str_replace('_', ' ', $event));
        $template = '<h2>{{business_name}}</h2><p>Hello {{patient_name}},</p><p>Your appointment with {{doctor_name}} for {{service_name}} is scheduled at {{start_at}}.</p><p>Status: {{status}}</p>';
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
}
