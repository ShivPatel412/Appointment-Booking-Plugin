<?php

declare(strict_types=1);

namespace ABP\Domain;

use ABP\Infrastructure\Database;
use ABP\Infrastructure\Settings;
use DateTimeImmutable;
use WP_Error;

final class AvailabilityService
{
    private AvailabilityCalculator $calculator;
    private AvailabilityEligibility $eligibility;

    public function __construct(?AvailabilityCalculator $calculator = null, ?AvailabilityEligibility $eligibility = null)
    {
        $this->calculator = $calculator ?? new AvailabilityCalculator();
        $this->eligibility = $eligibility ?? new AvailabilityEligibility();
    }

    /** @return array<string,mixed>|WP_Error */
    public function slots(int $doctorId, int $serviceId, string $date, int $excludeAppointment = 0)
    {
        global $wpdb;
        $doctor = $wpdb->get_row($wpdb->prepare(
            'SELECT d.active, s.duration, s.active AS service_active FROM ' . Database::table('doctors') . ' d INNER JOIN ' . Database::table('doctor_services') . ' ds ON ds.doctor_id=d.id INNER JOIN ' . Database::table('services') . ' s ON s.id=ds.service_id WHERE d.id=%d AND s.id=%d',
            $doctorId,
            $serviceId
        ), ARRAY_A);
        $reason = $this->eligibility->reason((bool) ($doctor['active'] ?? false), (bool) ($doctor['service_active'] ?? false), (bool) $doctor);
        if ($reason === 'not_assigned') {
            return new WP_Error('abp_not_assigned', __('Doctor is not assigned to this service.', 'appointment-booking-plugin'), array('status' => 422));
        }
        if ($reason !== null) {
            return new WP_Error('abp_inactive', __('Doctor or service is inactive.', 'appointment-booking-plugin'), array('status' => 422));
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            return new WP_Error('abp_invalid_date', __('Date must use YYYY-MM-DD.', 'appointment-booking-plugin'), array('status' => 400));
        }

        $exception = $wpdb->get_row($wpdb->prepare(
            'SELECT is_working,start_time,end_time FROM ' . Database::table('schedule_exceptions') . ' WHERE doctor_id=%d AND exception_date=%s ORDER BY id DESC LIMIT 1',
            $doctorId,
            $date
        ), ARRAY_A);
        if ($exception && ! (int) $exception['is_working']) {
            return array('date' => $date, 'duration' => (int) $doctor['duration'], 'slots' => array());
        }
        if ($exception && $exception['start_time'] && $exception['end_time']) {
            $working = array(array($exception['start_time'], $exception['end_time']));
        } else {
            $weekday = (int) $parsed->format('w');
            $workingRows = $wpdb->get_results($wpdb->prepare(
                'SELECT start_time,end_time FROM ' . Database::table('working_hours') . ' WHERE doctor_id=%d AND weekday=%d ORDER BY start_time',
                $doctorId,
                $weekday
            ), ARRAY_A);
            $working = array_map(static fn(array $row): array => array($row['start_time'], $row['end_time']), $workingRows);
        }
        $weekday = (int) $parsed->format('w');
        $breakRows = $wpdb->get_results($wpdb->prepare(
            'SELECT start_time,end_time FROM ' . Database::table('breaks') . ' WHERE doctor_id=%d AND weekday=%d ORDER BY start_time',
            $doctorId,
            $weekday
        ), ARRAY_A);
        $breaks = array_map(static fn(array $row): array => array($row['start_time'], $row['end_time']), $breakRows);
        $busySql = 'SELECT TIME(start_at) AS start_time,TIME(end_at) AS end_time FROM ' . Database::table('appointments') . " WHERE doctor_id=%d AND DATE(start_at)=%s AND status NOT IN ('cancelled','rejected')";
        $params = array($doctorId, $date);
        if ($excludeAppointment > 0) {
            $busySql .= ' AND id<>%d';
            $params[] = $excludeAppointment;
        }
        $busyRows = $wpdb->get_results($wpdb->prepare($busySql, ...$params), ARRAY_A);
        $busy = array_map(static fn(array $row): array => array($row['start_time'], $row['end_time']), $busyRows);
        $interval = (int) Settings::get()['slot_interval'];
        $slots = $this->calculator->calculate($date, (int) $doctor['duration'], $interval, $working, $breaks, $busy);
        return array('date' => $date, 'duration' => (int) $doctor['duration'], 'interval' => $interval, 'slots' => $slots);
    }

    /** @return true|WP_Error */
    public function validateSlot(int $doctorId, int $serviceId, string $startAt, int $excludeAppointment = 0)
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startAt);
        if (! $parsed || $parsed->format('Y-m-d H:i:s') !== $startAt) {
            return new WP_Error('abp_invalid_datetime', __('Start time must use YYYY-MM-DD HH:MM:SS.', 'appointment-booking-plugin'), array('status' => 400));
        }
        $available = $this->slots($doctorId, $serviceId, $parsed->format('Y-m-d'), $excludeAppointment);
        if (is_wp_error($available)) {
            return $available;
        }
        if (! in_array($startAt, $available['slots'], true)) {
            return new WP_Error('abp_slot_unavailable', __('The selected time is no longer available.', 'appointment-booking-plugin'), array('status' => 409));
        }
        return true;
    }
}
