<?php

declare(strict_types=1);

namespace ABP\Rest;

use ABP\Accounts\AccountService;
use ABP\Clinical\PrescriptionService;
use ABP\Domain\AppointmentService;
use ABP\Infrastructure\Capabilities;
use ABP\Infrastructure\Database;
use ABP\Infrastructure\Settings;
use ABP\Security\AccessPolicy;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PortalApi
{
    private const NS = 'appointment-booking/v1';

    public static function registerRoutes(): void
    {
        register_rest_route(self::NS, '/auth/register', array('methods' => 'POST', 'callback' => array(self::class, 'register'), 'permission_callback' => '__return_true'));
        register_rest_route(self::NS, '/me', array('methods' => 'GET', 'callback' => array(self::class, 'me'), 'permission_callback' => array(self::class, 'loggedIn')));
        register_rest_route(self::NS, '/patient/dashboard', array('methods' => 'GET', 'callback' => array(self::class, 'patientDashboard'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/profile', array(
            array('methods' => 'GET', 'callback' => array(self::class, 'patientProfile'), 'permission_callback' => array(self::class, 'patient')),
            array('methods' => 'PUT', 'callback' => array(self::class, 'patientProfile'), 'permission_callback' => array(self::class, 'patient')),
        ));
        register_rest_route(self::NS, '/patient/appointments', array('methods' => 'GET', 'callback' => array(self::class, 'patientAppointments'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/appointments/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array(self::class, 'patientAppointment'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/appointments/(?P<id>\d+)/reschedule', array('methods' => 'POST', 'callback' => array(self::class, 'patientReschedule'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/appointments/(?P<id>\d+)/cancel', array('methods' => 'POST', 'callback' => array(self::class, 'patientCancel'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/book-again', array('methods' => 'POST', 'callback' => array(self::class, 'bookAgain'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/booking-options', array('methods' => 'GET', 'callback' => array(self::class, 'bookingOptions'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/prescriptions', array('methods' => 'GET', 'callback' => array(self::class, 'patientPrescriptions'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/patient/prescriptions/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array(self::class, 'prescription'), 'permission_callback' => array(self::class, 'patient')));
        register_rest_route(self::NS, '/doctor/profile', array(
            array('methods' => 'GET', 'callback' => array(self::class, 'doctorProfile'), 'permission_callback' => array(self::class, 'doctor')),
            array('methods' => 'PUT', 'callback' => array(self::class, 'doctorProfile'), 'permission_callback' => array(self::class, 'doctor')),
        ));
        register_rest_route(self::NS, '/doctor/appointments', array('methods' => 'GET', 'callback' => array(self::class, 'doctorAppointments'), 'permission_callback' => array(self::class, 'doctor')));
        register_rest_route(self::NS, '/doctor/appointments/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array(self::class, 'doctorAppointment'), 'permission_callback' => array(self::class, 'doctor')));
        register_rest_route(self::NS, '/doctor/appointments/(?P<id>\d+)/complete', array('methods' => 'POST', 'callback' => array(self::class, 'completeAppointment'), 'permission_callback' => array(self::class, 'doctor')));
        register_rest_route(self::NS, '/appointments/(?P<id>\d+)/notes', array('methods' => 'PUT', 'callback' => array(self::class, 'appointmentNotes'), 'permission_callback' => array(self::class, 'clinical')));
        register_rest_route(self::NS, '/prescriptions', array(
            array('methods' => 'GET', 'callback' => array(self::class, 'prescriptions'), 'permission_callback' => array(self::class, 'loggedIn')),
            array('methods' => 'POST', 'callback' => array(self::class, 'prescriptions'), 'permission_callback' => array(self::class, 'clinical')),
        ));
        register_rest_route(self::NS, '/prescriptions/(?P<id>\d+)', array(
            array('methods' => 'GET', 'callback' => array(self::class, 'prescription'), 'permission_callback' => array(self::class, 'loggedIn')),
            array('methods' => 'PUT', 'callback' => array(self::class, 'prescription'), 'permission_callback' => array(self::class, 'clinical')),
        ));
    }

    public static function loggedIn(): bool { return is_user_logged_in(); }
    public static function patient(): bool { return (new AccountService())->currentPatient() !== null; }
    public static function doctor(): bool { return current_user_can('manage_options') || (new AccountService())->currentDoctor() !== null; }
    public static function clinical(): bool { return current_user_can(Capabilities::CLINICAL) || current_user_can('manage_options'); }

    public static function register(WP_REST_Request $request)
    {
        $data = self::body($request);
        if (! empty($data['website'])) return new WP_Error('abp_registration_rejected', __('Registration rejected.', 'appointment-booking-plugin'), array('status' => 400));
        return (new AccountService())->register($data);
    }

    public static function me(): WP_REST_Response
    {
        $accounts = new AccountService(); $user = wp_get_current_user();
        return rest_ensure_response(array(
            'id' => $user->ID, 'display_name' => $user->display_name, 'email' => $user->user_email,
            'patient' => $accounts->currentPatient(), 'doctor' => $accounts->currentDoctor(),
            'is_admin' => current_user_can('manage_options'),
        ));
    }

    public static function patientProfile(WP_REST_Request $request)
    {
        global $wpdb;
        $patient = (new AccountService())->currentPatient();
        if ($request->get_method() === 'GET') return rest_ensure_response(self::publicPatient($patient));
        $data = self::body($request); $settings = Settings::get();
        $dobInput = sanitize_text_field($data['date_of_birth'] ?? '');
        if ($dobInput !== '' && ! self::dateOrNull($dobInput)) return new WP_Error('abp_invalid_dob', __('Date of birth must use YYYY-MM-DD.', 'appointment-booking-plugin'), array('status' => 400));
        $imageId = absint($data['image_id'] ?? $patient['image_id']);
        if ($imageId && ! wp_attachment_is_image($imageId)) return new WP_Error('abp_invalid_image', __('Select a valid image attachment.', 'appointment-booking-plugin'), array('status' => 422));
        $row = array(
            'first_name' => sanitize_text_field($data['first_name'] ?? $patient['first_name']),
            'last_name' => sanitize_text_field($data['last_name'] ?? $patient['last_name']),
            'phone' => sanitize_text_field($data['phone'] ?? $patient['phone']),
            'date_of_birth' => self::dateOrNull($dobInput) ?: null,
            'gender' => sanitize_text_field($data['gender'] ?? $patient['gender']),
            'address' => sanitize_textarea_field($data['address'] ?? $patient['address']),
            'image_id' => $imageId ?: null,
            'updated_at' => current_time('mysql'),
        );
        if ($row['first_name'] === '' || $row['last_name'] === '') return new WP_Error('abp_invalid_profile', __('First and last name are required.', 'appointment-booking-plugin'), array('status' => 400));
        if ($settings['patient_email_editable'] && isset($data['email'])) {
            $email = sanitize_email($data['email']);
            if (! is_email($email) || (($owner = email_exists($email)) && (int) $owner !== get_current_user_id())) return new WP_Error('abp_email_unavailable', __('That email cannot be used.', 'appointment-booking-plugin'), array('status' => 409));
            $updated = wp_update_user(array('ID' => get_current_user_id(), 'user_email' => $email));
            if (is_wp_error($updated)) return $updated;
            $row['email'] = $email;
        }
        $wpdb->update(Database::table('patients'), $row, array('id' => $patient['id']));
        wp_update_user(array('ID' => get_current_user_id(), 'first_name' => $row['first_name'], 'last_name' => $row['last_name'], 'display_name' => trim($row['first_name'] . ' ' . $row['last_name'])));
        return rest_ensure_response(self::publicPatient((new AccountService())->currentPatient()));
    }

    public static function patientDashboard(): WP_REST_Response
    {
        global $wpdb;
        $patient = (new AccountService())->currentPatient(); $patientId = (int) $patient['id'];
        $next = $wpdb->get_row($wpdb->prepare(self::appointmentSelect() . " WHERE a.patient_id=%d AND a.start_at>=%s AND a.status IN ('pending','approved') ORDER BY a.start_at LIMIT 1", $patientId, current_time('mysql')), ARRAY_A);
        $upcoming = $wpdb->get_results($wpdb->prepare(self::appointmentSelect() . " WHERE a.patient_id=%d AND a.start_at>=%s AND a.status IN ('pending','approved') ORDER BY a.start_at LIMIT 5", $patientId, current_time('mysql')), ARRAY_A);
        $previous = $wpdb->get_row($wpdb->prepare(self::appointmentSelect() . " WHERE a.patient_id=%d AND a.start_at<%s AND a.status='completed' ORDER BY a.start_at DESC LIMIT 1", $patientId, current_time('mysql')), ARRAY_A);
        $prescription = $wpdb->get_row($wpdb->prepare('SELECT pr.id,pr.reference,pr.prescribed_on,d.name doctor_name FROM ' . Database::table('prescriptions') . ' pr INNER JOIN ' . Database::table('doctors') . ' d ON d.id=pr.doctor_id WHERE pr.patient_id=%d ORDER BY pr.prescribed_on DESC,pr.id DESC LIMIT 1', $patientId), ARRAY_A);
        return rest_ensure_response(array('profile' => self::publicPatient($patient), 'next_appointment' => self::patientAppointmentShape($next), 'upcoming' => array_map(array(self::class, 'patientAppointmentShape'), $upcoming), 'previous_visit' => self::patientAppointmentShape($previous), 'latest_prescription' => $prescription));
    }

    public static function patientAppointments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $patient = (new AccountService())->currentPatient(); $view = sanitize_key($request->get_param('view') ?? 'upcoming');
        if ($view === 'past') $condition = "a.start_at<%s AND a.status NOT IN ('cancelled','rejected')";
        elseif ($view === 'cancelled') $condition = "a.status IN ('cancelled','rejected')";
        else $condition = "a.start_at>=%s AND a.status IN ('pending','approved')";
        $args = array((int) $patient['id']);
        if ($view !== 'cancelled') $args[] = current_time('mysql');
        $rows = $wpdb->get_results($wpdb->prepare(self::appointmentSelect() . " WHERE a.patient_id=%d AND $condition ORDER BY a.start_at " . ($view === 'upcoming' ? 'ASC' : 'DESC'), ...$args), ARRAY_A);
        return rest_ensure_response(array_map(array(self::class, 'patientAppointmentShape'), $rows));
    }

    public static function patientAppointment(WP_REST_Request $request)
    {
        $row = self::ownedPatientAppointment(absint($request['id']));
        return is_wp_error($row) ? $row : rest_ensure_response(self::patientAppointmentShape($row));
    }

    public static function patientReschedule(WP_REST_Request $request)
    {
        if (! Settings::get()['patient_reschedule_enabled']) return new WP_Error('abp_reschedule_disabled', __('Patient rescheduling is disabled.', 'appointment-booking-plugin'), array('status' => 403));
        $row = self::ownedPatientAppointment(absint($request['id'])); if (is_wp_error($row)) return $row;
        if (! in_array($row['status'], array('pending', 'approved'), true) || $row['start_at'] <= current_time('mysql')) return new WP_Error('abp_reschedule_forbidden', __('This appointment cannot be rescheduled.', 'appointment-booking-plugin'), array('status' => 422));
        $data = self::body($request);
        return (new AppointmentService())->save(array('doctor_id' => $row['doctor_id'], 'service_id' => $row['service_id'], 'patient_id' => $row['patient_id'], 'start_at' => sanitize_text_field($data['start_at'] ?? ''), 'status' => $row['status'], 'notes' => $row['notes']), (int) $row['id']);
    }

    public static function patientCancel(WP_REST_Request $request)
    {
        global $wpdb;
        if (! Settings::get()['patient_cancellation_enabled']) return new WP_Error('abp_cancellation_disabled', __('Patient cancellation is disabled.', 'appointment-booking-plugin'), array('status' => 403));
        $row = self::ownedPatientAppointment(absint($request['id'])); if (is_wp_error($row)) return $row;
        if (! in_array($row['status'], array('pending', 'approved'), true) || $row['start_at'] <= current_time('mysql')) return new WP_Error('abp_cancellation_forbidden', __('This appointment cannot be cancelled.', 'appointment-booking-plugin'), array('status' => 422));
        $wpdb->update(Database::table('appointments'), array('status' => 'cancelled', 'updated_at' => current_time('mysql')), array('id' => $row['id']));
        $saved = (new AppointmentService())->get((int) $row['id']); do_action('abp_appointment_event', 'appointment_cancelled', $saved);
        return rest_ensure_response(self::patientAppointmentShape($saved));
    }

    public static function bookAgain(WP_REST_Request $request)
    {
        $data = self::body($request); $patient = (new AccountService())->currentPatient();
        if (! empty($data['source_appointment_id'])) {
            $source = self::ownedPatientAppointment(absint($data['source_appointment_id'])); if (is_wp_error($source)) return $source;
            $doctorId = (int) $source['doctor_id']; $serviceId = (int) $source['service_id'];
        } else {
            $doctorId = absint($data['doctor_id'] ?? 0); $serviceId = absint($data['service_id'] ?? 0);
        }
        return (new AppointmentService())->save(array('doctor_id' => $doctorId, 'service_id' => $serviceId, 'patient_id' => (int) $patient['id'], 'start_at' => sanitize_text_field($data['start_at'] ?? ''), 'status' => Settings::get()['default_status'], 'notes' => ''), 0);
    }

    public static function bookingOptions(): WP_REST_Response
    {
        global $wpdb;
        $services = $wpdb->get_results('SELECT id,name,duration,color FROM ' . Database::table('services') . ' WHERE active=1 ORDER BY name', ARRAY_A);
        $doctors = $wpdb->get_results('SELECT d.id,d.name,ds.service_id FROM ' . Database::table('doctors') . ' d INNER JOIN ' . Database::table('doctor_services') . ' ds ON ds.doctor_id=d.id INNER JOIN ' . Database::table('services') . ' s ON s.id=ds.service_id WHERE d.active=1 AND s.active=1 ORDER BY d.name', ARRAY_A);
        return rest_ensure_response(array('services' => $services, 'doctors' => $doctors));
    }

    public static function patientPrescriptions(): WP_REST_Response
    {
        global $wpdb; $patient = (new AccountService())->currentPatient();
        $rows = $wpdb->get_results($wpdb->prepare('SELECT pr.id,pr.reference,pr.prescribed_on,pr.appointment_id,d.name doctor_name FROM ' . Database::table('prescriptions') . ' pr INNER JOIN ' . Database::table('doctors') . ' d ON d.id=pr.doctor_id WHERE pr.patient_id=%d ORDER BY pr.prescribed_on DESC,pr.id DESC', (int) $patient['id']), ARRAY_A);
        return rest_ensure_response($rows);
    }

    public static function doctorProfile(WP_REST_Request $request)
    {
        global $wpdb; $doctor = (new AccountService())->currentDoctor();
        if (! $doctor) return new WP_Error('abp_doctor_profile_required', __('No active doctor profile is linked to this account.', 'appointment-booking-plugin'), array('status' => 403));
        if ($request->get_method() === 'GET') return rest_ensure_response(self::doctorProfileShape($doctor));
        $data = self::body($request);
        $imageId = absint($data['image_id'] ?? $doctor['image_id']);
        if ($imageId && ! wp_attachment_is_image($imageId)) return new WP_Error('abp_invalid_image', __('Select a valid image attachment.', 'appointment-booking-plugin'), array('status' => 422));
        $row = array('phone' => sanitize_text_field($data['phone'] ?? $doctor['phone']), 'bio' => sanitize_textarea_field($data['bio'] ?? $doctor['bio']), 'image_id' => $imageId ?: null, 'updated_at' => current_time('mysql'));
        $wpdb->update(Database::table('doctors'), $row, array('id' => $doctor['id']));
        return rest_ensure_response(self::doctorProfileShape((new AccountService())->currentDoctor()));
    }

    public static function doctorAppointments(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb; $doctor = (new AccountService())->currentDoctor();
        if (! $doctor) return rest_ensure_response(array());
        $from = self::dateOrNull($request->get_param('from')) ?: current_time('Y-m-d'); $to = self::dateOrNull($request->get_param('to')) ?: current_datetime()->modify('+30 days')->format('Y-m-d');
        $rows = $wpdb->get_results($wpdb->prepare(self::appointmentSelect() . ' WHERE a.doctor_id=%d AND DATE(a.start_at) BETWEEN %s AND %s ORDER BY a.start_at', (int) $doctor['id'], $from, $to), ARRAY_A);
        return rest_ensure_response(array_map(array(self::class, 'doctorAppointmentShape'), $rows));
    }

    public static function doctorAppointment(WP_REST_Request $request)
    {
        $row = self::ownedDoctorAppointment(absint($request['id']));
        return is_wp_error($row) ? $row : rest_ensure_response(self::doctorAppointmentShape($row));
    }

    public static function appointmentNotes(WP_REST_Request $request)
    {
        global $wpdb; $row = self::ownedDoctorAppointment(absint($request['id'])); if (is_wp_error($row)) return $row;
        $data = self::body($request);
        $updated = $wpdb->update(Database::table('appointments'), array('public_notes' => sanitize_textarea_field($data['public_notes'] ?? $row['public_notes']), 'internal_notes' => sanitize_textarea_field($data['internal_notes'] ?? $row['internal_notes']), 'updated_at' => current_time('mysql')), array('id' => $row['id']));
        return $updated === false ? new WP_Error('abp_notes_failed', __('Could not save appointment notes.', 'appointment-booking-plugin'), array('status' => 500)) : rest_ensure_response(self::doctorAppointmentShape((new AppointmentService())->get((int) $row['id'])));
    }

    public static function completeAppointment(WP_REST_Request $request)
    {
        global $wpdb; $row = self::ownedDoctorAppointment(absint($request['id'])); if (is_wp_error($row)) return $row;
        if (in_array($row['status'], array('cancelled', 'rejected'), true)) return new WP_Error('abp_completion_forbidden', __('A cancelled or rejected appointment cannot be completed.', 'appointment-booking-plugin'), array('status' => 422));
        $wpdb->update(Database::table('appointments'), array('status' => 'completed', 'updated_at' => current_time('mysql')), array('id' => $row['id']));
        $saved = (new AppointmentService())->get((int) $row['id']); do_action('abp_appointment_event', 'appointment_completed', $saved);
        return rest_ensure_response(self::doctorAppointmentShape($saved));
    }

    public static function prescriptions(WP_REST_Request $request)
    {
        global $wpdb;
        if ($request->get_method() === 'POST') {
            $doctor = (new AccountService())->currentDoctor();
            if ($doctor) $doctorId = (int) $doctor['id'];
            elseif (current_user_can('manage_options')) $doctorId = absint(self::body($request)['doctor_id'] ?? 0);
            else return new WP_Error('abp_linked_doctor_required', __('Your account is not linked to an active doctor.', 'appointment-booking-plugin'), array('status' => 403));
            if (! $doctorId) return new WP_Error('abp_doctor_required', __('A linked doctor is required.', 'appointment-booking-plugin'), array('status' => 403));
            return (new PrescriptionService())->save(self::body($request), $doctorId);
        }
        $patient = (new AccountService())->currentPatient(); $doctor = (new AccountService())->currentDoctor();
        if ($patient) return self::patientPrescriptions();
        if ($doctor) return rest_ensure_response($wpdb->get_results($wpdb->prepare('SELECT pr.id,pr.reference,pr.prescribed_on,pr.appointment_id,CONCAT(p.first_name," ",p.last_name) patient_name FROM ' . Database::table('prescriptions') . ' pr INNER JOIN ' . Database::table('patients') . ' p ON p.id=pr.patient_id WHERE pr.doctor_id=%d ORDER BY pr.prescribed_on DESC', (int) $doctor['id']), ARRAY_A));
        if (current_user_can('manage_options')) return rest_ensure_response($wpdb->get_results('SELECT * FROM ' . Database::table('prescriptions') . ' ORDER BY prescribed_on DESC', ARRAY_A));
        return new WP_Error('abp_forbidden', __('You cannot access prescriptions.', 'appointment-booking-plugin'), array('status' => 403));
    }

    public static function prescription(WP_REST_Request $request)
    {
        $service = new PrescriptionService(); $prescription = $service->get(absint($request['id']));
        if (! $prescription) return new WP_Error('abp_prescription_not_found', __('Prescription not found.', 'appointment-booking-plugin'), array('status' => 404));
        $accounts = new AccountService(); $patient = $accounts->currentPatient(); $doctor = $accounts->currentDoctor(); $policy = new AccessPolicy();
        $allowed = current_user_can('manage_options') || ($patient && $policy->patientOwns((int) $patient['id'], (int) $prescription['patient_id'])) || ($doctor && $policy->doctorOwnsPrescription((int) $doctor['id'], (int) $prescription['doctor_id']));
        if (! $allowed) return new WP_Error('abp_forbidden_prescription', __('You cannot access this prescription.', 'appointment-booking-plugin'), array('status' => 403));
        if ($request->get_method() === 'PUT') {
            if (! current_user_can(Capabilities::CLINICAL) && ! current_user_can('manage_options')) return new WP_Error('abp_read_only_prescription', __('Patients cannot edit prescriptions.', 'appointment-booking-plugin'), array('status' => 403));
            $doctorId = $doctor ? (int) $doctor['id'] : (int) $prescription['doctor_id'];
            return $service->save(self::body($request), $doctorId, (int) $prescription['id']);
        }
        return rest_ensure_response($prescription);
    }

    private static function ownedPatientAppointment(int $id)
    {
        $patient = (new AccountService())->currentPatient(); $row = (new AppointmentService())->get($id);
        if (! $row) return new WP_Error('abp_appointment_not_found', __('Appointment not found.', 'appointment-booking-plugin'), array('status' => 404));
        if (! (new AccessPolicy())->patientOwns((int) $patient['id'], (int) $row['patient_id'])) return new WP_Error('abp_forbidden_appointment', __('You cannot access this appointment.', 'appointment-booking-plugin'), array('status' => 403));
        return $row;
    }

    private static function ownedDoctorAppointment(int $id)
    {
        $row = (new AppointmentService())->get($id); if (! $row) return new WP_Error('abp_appointment_not_found', __('Appointment not found.', 'appointment-booking-plugin'), array('status' => 404));
        if (current_user_can('manage_options')) return $row;
        $doctor = (new AccountService())->currentDoctor();
        if (! $doctor || ! (new AccessPolicy())->doctorOwnsAppointment((int) $doctor['id'], (int) $row['doctor_id'])) return new WP_Error('abp_forbidden_appointment', __('You cannot access this appointment.', 'appointment-booking-plugin'), array('status' => 403));
        return $row;
    }

    private static function appointmentSelect(): string
    {
        return 'SELECT a.*,d.name doctor_name,s.name service_name,s.color,CONCAT(p.first_name," ",p.last_name) patient_name,p.email patient_email,p.phone patient_phone,p.date_of_birth,p.gender,p.address,CASE WHEN EXISTS(SELECT 1 FROM ' . Database::table('prescriptions') . ' px WHERE px.appointment_id=a.id) THEN "available" ELSE "none" END prescription_status FROM ' . Database::table('appointments') . ' a INNER JOIN ' . Database::table('doctors') . ' d ON d.id=a.doctor_id INNER JOIN ' . Database::table('services') . ' s ON s.id=a.service_id INNER JOIN ' . Database::table('patients') . ' p ON p.id=a.patient_id';
    }

    private static function patientAppointmentShape(?array $row): ?array
    {
        if (! $row) return null;
        return array_intersect_key($row, array_flip(array('id','reference','doctor_id','service_id','start_at','end_at','duration','status','doctor_name','service_name','color','public_notes','prescription_status')));
    }

    private static function doctorAppointmentShape(?array $row): ?array
    {
        if (! $row) return null;
        return array_intersect_key($row, array_flip(array('id','reference','patient_id','service_id','start_at','end_at','duration','status','patient_name','patient_email','patient_phone','date_of_birth','gender','address','service_name','public_notes','internal_notes')));
    }

    private static function publicPatient(array $patient): array
    {
        return array_intersect_key($patient, array_flip(array('id','image_id','first_name','last_name','email','phone','date_of_birth','gender','address','reference','account_status')));
    }

    private static function doctorProfileShape(array $doctor): array
    {
        return array_intersect_key($doctor, array_flip(array('id','image_id','name','email','phone','bio','active')));
    }

    private static function body(WP_REST_Request $request): array { return (array) ($request->get_json_params() ?: $request->get_params()); }
    private static function dateOrNull($value): ?string { $value = sanitize_text_field((string) $value); $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $date && $date->format('Y-m-d') === $value ? $value : null; }
}
