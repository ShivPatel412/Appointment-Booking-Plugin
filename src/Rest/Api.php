<?php

declare(strict_types=1);

namespace ABP\Rest;

use ABP\Domain\AppointmentService;
use ABP\Domain\AvailabilityService;
use ABP\Infrastructure\Capabilities;
use ABP\Infrastructure\Database;
use ABP\Infrastructure\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class Api
{
    private const NS = 'appointment-booking/v1';

    public static function registerRoutes(): void
    {
        self::crud('categories', array(self::class, 'categories'));
        self::crud('services', array(self::class, 'services'));
        self::crud('doctors', array(self::class, 'doctors'));
        self::crud('patients', array(self::class, 'patients'));
        self::crud('appointments', array(self::class, 'appointments'));
        register_rest_route(self::NS, '/appointments/bulk-status', array(
            'methods' => 'PATCH', 'callback' => array(self::class, 'bulkStatus'),
            'permission_callback' => array(self::class, 'canManage'),
        ));
        register_rest_route(self::NS, '/availability', array(
            'methods' => 'GET', 'callback' => array(self::class, 'availability'),
            'permission_callback' => array(self::class, 'canView'),
            'args' => array(
                'doctor_id' => array('required' => true, 'sanitize_callback' => 'absint'),
                'service_id' => array('required' => true, 'sanitize_callback' => 'absint'),
                'date' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'exclude_appointment' => array('required' => false, 'sanitize_callback' => 'absint'),
            ),
        ));
        register_rest_route(self::NS, '/dashboard', array(
            'methods' => 'GET', 'callback' => array(self::class, 'dashboard'),
            'permission_callback' => array(self::class, 'canView'),
        ));
        register_rest_route(self::NS, '/calendar', array(
            'methods' => 'GET', 'callback' => array(self::class, 'calendar'),
            'permission_callback' => array(self::class, 'canView'),
        ));
        register_rest_route(self::NS, '/settings', array(
            array('methods' => 'GET', 'callback' => array(self::class, 'settings'), 'permission_callback' => array(self::class, 'canManage')),
            array('methods' => 'PUT', 'callback' => array(self::class, 'settings'), 'permission_callback' => array(self::class, 'canManage')),
        ));
        register_rest_route(self::NS, '/notifications', array(
            'methods' => 'GET', 'callback' => array(self::class, 'notifications'),
            'permission_callback' => array(self::class, 'canManage'),
        ));
    }

    private static function crud(string $resource, callable $callback): void
    {
        register_rest_route(self::NS, '/' . $resource, array(
            array('methods' => 'GET', 'callback' => $callback, 'permission_callback' => array(self::class, 'canView')),
            array('methods' => 'POST', 'callback' => $callback, 'permission_callback' => array(self::class, 'canManage')),
        ));
        register_rest_route(self::NS, '/' . $resource . '/(?P<id>\d+)', array(
            array('methods' => 'GET', 'callback' => $callback, 'permission_callback' => array(self::class, 'canView')),
            array('methods' => array('PUT', 'PATCH'), 'callback' => $callback, 'permission_callback' => array(self::class, 'canManage')),
            array('methods' => 'DELETE', 'callback' => $callback, 'permission_callback' => $resource === 'appointments' ? array(self::class, 'canDelete') : array(self::class, 'canManage')),
        ));
    }

    public static function canView(): bool
    {
        return current_user_can(Capabilities::VIEW) || current_user_can(Capabilities::MANAGE);
    }

    public static function canManage(): bool
    {
        return current_user_can(Capabilities::MANAGE);
    }

    public static function canDelete(): bool
    {
        return current_user_can(Capabilities::DELETE);
    }

    public static function categories(WP_REST_Request $request)
    {
        global $wpdb;
        $table = Database::table('categories');
        $id = absint($request['id'] ?? 0);
        if ($request->get_method() === 'GET') {
            if ($id) {
                return self::one($table, $id);
            }
            $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY name", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL
            return rest_ensure_response(is_array($rows) ? $rows : array());
        }
        if ($request->get_method() === 'DELETE') {
            $used = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Database::table('services') . ' WHERE category_id=%d', $id));
            if ($used) {
                return new WP_Error('abp_category_in_use', __('Deactivate categories that still contain services.', 'appointment-booking-plugin'), array('status' => 409));
            }
            return self::delete($table, $id);
        }
        $data = self::body($request);
        $name = sanitize_text_field($data['name'] ?? '');
        if ($name === '') {
            return self::required('name');
        }
        return self::upsert($table, array(
            'name' => $name,
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'active' => self::bool($data['active'] ?? true),
        ), $id);
    }

    public static function services(WP_REST_Request $request)
    {
        global $wpdb;
        $table = Database::table('services');
        $id = absint($request['id'] ?? 0);
        if ($request->get_method() === 'GET') {
            if ($id) {
                $row = self::one($table, $id);
                if (is_wp_error($row)) return $row;
                $payload = $row->get_data();
                $payload['doctor_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT doctor_id FROM ' . Database::table('doctor_services') . ' WHERE service_id=%d', $id)));
                $row->set_data($payload);
                return $row;
            }
            $search = sanitize_text_field($request->get_param('search') ?? '');
            $where = $search !== '' ? $wpdb->prepare(' WHERE s.name LIKE %s', '%' . $wpdb->esc_like($search) . '%') : '';
            $rows = $wpdb->get_results('SELECT s.*,c.name AS category_name FROM ' . $table . ' s LEFT JOIN ' . Database::table('categories') . ' c ON c.id=s.category_id' . $where . ' ORDER BY s.name', ARRAY_A);
            return rest_ensure_response(is_array($rows) ? $rows : array());
        }
        if ($request->get_method() === 'DELETE') {
            return self::deactivate($table, $id);
        }
        $data = self::body($request);
        $name = sanitize_text_field($data['name'] ?? '');
        $duration = absint($data['duration'] ?? 0);
        $color = sanitize_hex_color($data['color'] ?? '#4f46e5');
        if ($name === '' || $duration < 1 || ! $color) {
            return new WP_Error('abp_invalid_service', __('Name, positive duration, and valid color are required.', 'appointment-booking-plugin'), array('status' => 400));
        }
        $response = self::upsert($table, array(
            'category_id' => ($category = absint($data['category_id'] ?? 0)) ?: null,
            'name' => $name,
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'image_id' => ($image = absint($data['image_id'] ?? 0)) ?: null,
            'color' => $color,
            'price' => max(0, (float) ($data['price'] ?? 0)),
            'duration' => $duration,
            'active' => self::bool($data['active'] ?? true),
        ), $id);
        return $response;
    }

    public static function doctors(WP_REST_Request $request)
    {
        global $wpdb;
        $table = Database::table('doctors');
        $id = absint($request['id'] ?? 0);
        if ($request->get_method() === 'GET') {
            if ($id) {
                $response = self::one($table, $id);
                if (is_wp_error($response)) return $response;
                $payload = $response->get_data();
                $payload['services'] = $wpdb->get_results($wpdb->prepare('SELECT service_id,price FROM ' . Database::table('doctor_services') . ' WHERE doctor_id=%d', $id), ARRAY_A);
                $payload['working_hours'] = $wpdb->get_results($wpdb->prepare('SELECT id,weekday,start_time,end_time FROM ' . Database::table('working_hours') . ' WHERE doctor_id=%d ORDER BY weekday,start_time', $id), ARRAY_A);
                $payload['breaks'] = $wpdb->get_results($wpdb->prepare('SELECT id,weekday,start_time,end_time FROM ' . Database::table('breaks') . ' WHERE doctor_id=%d ORDER BY weekday,start_time', $id), ARRAY_A);
                $payload['exceptions'] = $wpdb->get_results($wpdb->prepare('SELECT id,exception_date,is_working,start_time,end_time,label FROM ' . Database::table('schedule_exceptions') . ' WHERE doctor_id=%d ORDER BY exception_date', $id), ARRAY_A);
                $response->set_data($payload);
                return $response;
            }
            return self::searchPeople($table, $request, 'name');
        }
        if ($request->get_method() === 'DELETE') {
            return self::deactivate($table, $id);
        }
        $data = self::body($request);
        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        if ($name === '' || ! is_email($email)) {
            return new WP_Error('abp_invalid_doctor', __('Name and valid email are required.', 'appointment-booking-plugin'), array('status' => 400));
        }
        $response = self::upsert($table, array(
            'user_id' => ($user = absint($data['user_id'] ?? 0)) ?: null,
            'image_id' => ($image = absint($data['image_id'] ?? 0)) ?: null,
            'name' => $name, 'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'bio' => sanitize_textarea_field($data['bio'] ?? ''),
            'active' => self::bool($data['active'] ?? true),
        ), $id);
        if (is_wp_error($response)) return $response;
        $doctorId = (int) $response->get_data()['id'];
        self::replaceDoctorRelations($doctorId, $data);
        return self::one($table, $doctorId);
    }

    public static function patients(WP_REST_Request $request)
    {
        global $wpdb;
        $table = Database::table('patients');
        $id = absint($request['id'] ?? 0);
        if ($request->get_method() === 'GET') {
            if ($id) {
                $response = self::one($table, $id);
                if (is_wp_error($response)) return $response;
                $payload = $response->get_data();
                $payload['appointments'] = $wpdb->get_results($wpdb->prepare('SELECT a.*,d.name AS doctor_name,s.name AS service_name FROM ' . Database::table('appointments') . ' a INNER JOIN ' . Database::table('doctors') . ' d ON d.id=a.doctor_id INNER JOIN ' . Database::table('services') . ' s ON s.id=a.service_id WHERE a.patient_id=%d ORDER BY a.start_at DESC', $id), ARRAY_A);
                $response->set_data($payload);
                return $response;
            }
            return self::searchPeople($table, $request, "CONCAT(first_name,' ',last_name)");
        }
        if ($request->get_method() === 'DELETE') {
            return self::deactivate($table, $id);
        }
        $data = self::body($request);
        $firstName = sanitize_text_field($data['first_name'] ?? '');
        $lastName = sanitize_text_field($data['last_name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        if ($firstName === '' || $lastName === '' || ! is_email($email)) {
            return new WP_Error('abp_invalid_patient', __('First name, last name, and valid email are required.', 'appointment-booking-plugin'), array('status' => 400));
        }
        $dob = self::dateOrNull($data['date_of_birth'] ?? '');
        if (($data['date_of_birth'] ?? '') && ! $dob) return new WP_Error('abp_invalid_dob', __('Date of birth must use YYYY-MM-DD.', 'appointment-booking-plugin'), array('status' => 400));
        return self::upsert($table, array(
            'image_id' => ($image = absint($data['image_id'] ?? 0)) ?: null,
            'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email,
            'phone' => sanitize_text_field($data['phone'] ?? ''), 'date_of_birth' => $dob,
            'gender' => sanitize_text_field($data['gender'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'reference' => sanitize_text_field($data['reference'] ?? ('PAT-' . strtoupper(wp_generate_password(8, false, false)))),
            'admin_notes' => sanitize_textarea_field($data['admin_notes'] ?? ''),
            'active' => self::bool($data['active'] ?? true),
        ), $id);
    }

    public static function appointments(WP_REST_Request $request)
    {
        global $wpdb;
        $service = new AppointmentService();
        $id = absint($request['id'] ?? 0);
        if ($request->get_method() === 'GET') {
            if ($id) {
                $appointment = $service->get($id);
                return $appointment ? rest_ensure_response($appointment) : new WP_Error('abp_not_found', __('Appointment not found.', 'appointment-booking-plugin'), array('status' => 404));
            }
            return rest_ensure_response(self::appointmentList($request));
        }
        if ($request->get_method() === 'DELETE') {
            $hard = rest_sanitize_boolean($request->get_param('hard'));
            if (! $hard) {
                $updated = $wpdb->update(Database::table('appointments'), array('status' => 'cancelled', 'updated_at' => current_time('mysql')), array('id' => $id));
                if ($updated === false) return self::dbError();
                $appointment = $service->get($id);
                do_action('abp_appointment_event', 'appointment_cancelled', $appointment);
                return rest_ensure_response(array('id' => $id, 'status' => 'cancelled'));
            }
            return self::delete(Database::table('appointments'), $id);
        }
        return $service->save(self::body($request), $id);
    }

    public static function bulkStatus(WP_REST_Request $request)
    {
        global $wpdb;
        $data = self::body($request);
        $ids = array_values(array_filter(array_map('absint', (array) ($data['ids'] ?? array()))));
        $status = sanitize_key($data['status'] ?? '');
        if (! $ids || ! in_array($status, AppointmentService::STATUSES, true)) {
            return new WP_Error('abp_invalid_bulk_update', __('Valid appointment IDs and status are required.', 'appointment-booking-plugin'), array('status' => 400));
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = 'UPDATE ' . Database::table('appointments') . " SET status=%s, updated_at=%s WHERE id IN ($placeholders)";
        $updated = $wpdb->query($wpdb->prepare($sql, $status, current_time('mysql'), ...$ids));
        return $updated === false ? self::dbError() : rest_ensure_response(array('updated' => $updated));
    }

    public static function availability(WP_REST_Request $request)
    {
        return (new AvailabilityService())->slots(absint($request['doctor_id']), absint($request['service_id']), sanitize_text_field($request['date']), absint($request['exclude_appointment'] ?? 0));
    }

    public static function calendar(WP_REST_Request $request): WP_REST_Response
    {
        return rest_ensure_response(self::appointmentList($request));
    }

    public static function dashboard(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $from = self::dateOrNull($request->get_param('from')) ?: current_time('Y-m-01');
        $to = self::dateOrNull($request->get_param('to')) ?: current_time('Y-m-t');
        $appointments = Database::table('appointments');
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) total, SUM(DATE(start_at)=%s) today, SUM(start_at>%s AND status IN ('pending','approved')) upcoming, SUM(status='pending') pending FROM $appointments WHERE DATE(start_at) BETWEEN %s AND %s",
            current_time('Y-m-d'), current_time('mysql'), $from, $to
        ), ARRAY_A);
        $activity = $wpdb->get_results($wpdb->prepare("SELECT DATE(start_at) day,COUNT(*) total FROM $appointments WHERE DATE(start_at) BETWEEN %s AND %s GROUP BY DATE(start_at) ORDER BY day", $from, $to), ARRAY_A);
        $recent = $wpdb->get_results('SELECT a.*,d.name doctor_name,s.name service_name,CONCAT(p.first_name," ",p.last_name) patient_name FROM ' . $appointments . ' a INNER JOIN ' . Database::table('doctors') . ' d ON d.id=a.doctor_id INNER JOIN ' . Database::table('services') . ' s ON s.id=a.service_id INNER JOIN ' . Database::table('patients') . ' p ON p.id=a.patient_id ORDER BY a.created_at DESC LIMIT 8', ARRAY_A);
        return rest_ensure_response(array(
            'range' => array('from' => $from, 'to' => $to),
            'counts' => array_merge($counts ?: array(), array(
                'patients' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Database::table('patients') . ' WHERE active=1'),
                'doctors' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Database::table('doctors') . ' WHERE active=1'),
            )),
            'activity' => is_array($activity) ? $activity : array(),
            'recent' => is_array($recent) ? $recent : array(),
        ));
    }

    public static function settings(WP_REST_Request $request): WP_REST_Response
    {
        if ($request->get_method() === 'PUT') {
            $settings = Settings::sanitize(self::body($request));
            update_option('abp_settings', $settings, false);
            return rest_ensure_response($settings);
        }
        return rest_ensure_response(Settings::get());
    }

    public static function notifications(): WP_REST_Response
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . Database::table('notification_log') . ' ORDER BY created_at DESC LIMIT 100', ARRAY_A);
        return rest_ensure_response($rows);
    }

    private static function appointmentList(WP_REST_Request $request): array
    {
        global $wpdb;
        $where = array('1=1'); $args = array();
        foreach (array('doctor_id', 'service_id', 'patient_id') as $field) {
            if (($value = absint($request->get_param($field)))) { $where[] = 'a.' . $field . '=%d'; $args[] = $value; }
        }
        if (($status = sanitize_key($request->get_param('status') ?? '')) && in_array($status, AppointmentService::STATUSES, true)) { $where[] = 'a.status=%s'; $args[] = $status; }
        if ($from = self::dateOrNull($request->get_param('from'))) { $where[] = 'DATE(a.start_at)>=%s'; $args[] = $from; }
        if ($to = self::dateOrNull($request->get_param('to'))) { $where[] = 'DATE(a.start_at)<=%s'; $args[] = $to; }
        if ($search = sanitize_text_field($request->get_param('search') ?? '')) {
            $like = '%' . $wpdb->esc_like($search) . '%'; $where[] = '(d.name LIKE %s OR s.name LIKE %s OR p.first_name LIKE %s OR p.last_name LIKE %s)'; array_push($args, $like, $like, $like, $like);
        }
        $sql = 'SELECT a.*,d.name doctor_name,s.name service_name,s.color,CONCAT(p.first_name," ",p.last_name) patient_name,p.email patient_email FROM ' . Database::table('appointments') . ' a INNER JOIN ' . Database::table('doctors') . ' d ON d.id=a.doctor_id INNER JOIN ' . Database::table('services') . ' s ON s.id=a.service_id INNER JOIN ' . Database::table('patients') . ' p ON p.id=a.patient_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.start_at';
        return $wpdb->get_results($args ? $wpdb->prepare($sql, ...$args) : $sql, ARRAY_A);
    }

    private static function replaceDoctorRelations(int $doctorId, array $data): void
    {
        global $wpdb;
        $mapping = array('services' => 'doctor_services', 'working_hours' => 'working_hours', 'breaks' => 'breaks', 'exceptions' => 'schedule_exceptions');
        foreach ($mapping as $key => $tableName) {
            if (! array_key_exists($key, $data)) continue;
            $table = Database::table($tableName);
            $wpdb->delete($table, array('doctor_id' => $doctorId));
            foreach ((array) $data[$key] as $item) {
                if (! is_array($item)) continue;
                $row = array('doctor_id' => $doctorId);
                if ($key === 'services') {
                    $row['service_id'] = absint($item['service_id'] ?? 0);
                    $row['price'] = isset($item['price']) && $item['price'] !== '' ? max(0, (float) $item['price']) : null;
                    if (! $row['service_id']) continue;
                } elseif ($key === 'exceptions') {
                    $row['exception_date'] = self::dateOrNull($item['exception_date'] ?? '');
                    $row['is_working'] = self::bool($item['is_working'] ?? false);
                    $row['start_time'] = self::timeOrNull($item['start_time'] ?? '');
                    $row['end_time'] = self::timeOrNull($item['end_time'] ?? '');
                    $row['label'] = sanitize_text_field($item['label'] ?? '');
                    if (! $row['exception_date']) continue;
                } else {
                    $row['weekday'] = max(0, min(6, absint($item['weekday'] ?? 0)));
                    $row['start_time'] = self::timeOrNull($item['start_time'] ?? '');
                    $row['end_time'] = self::timeOrNull($item['end_time'] ?? '');
                    if (! $row['start_time'] || ! $row['end_time'] || $row['start_time'] >= $row['end_time']) continue;
                }
                $wpdb->insert($table, $row);
            }
        }
    }

    private static function searchPeople(string $table, WP_REST_Request $request, string $expression): WP_REST_Response
    {
        global $wpdb;
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $sql = "SELECT * FROM $table";
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(" WHERE $expression LIKE %s OR email LIKE %s", $like, $like);
        }
        return rest_ensure_response($wpdb->get_results($sql . ' ORDER BY ' . ($expression === 'name' ? 'name' : 'last_name,first_name'), ARRAY_A));
    }

    private static function body(WP_REST_Request $request): array
    {
        return (array) ($request->get_json_params() ?: $request->get_params());
    }

    private static function one(string $table, int $id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id), ARRAY_A);
        return $row ? rest_ensure_response($row) : new WP_Error('abp_not_found', __('Record not found.', 'appointment-booking-plugin'), array('status' => 404));
    }

    private static function upsert(string $table, array $row, int $id)
    {
        global $wpdb;
        $row['updated_at'] = current_time('mysql');
        if ($id) {
            if (! (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id=%d", $id))) return new WP_Error('abp_not_found', __('Record not found.', 'appointment-booking-plugin'), array('status' => 404));
            $result = $wpdb->update($table, $row, array('id' => $id));
        } else {
            $row['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $row); $id = (int) $wpdb->insert_id;
        }
        return $result === false ? self::dbError() : self::one($table, $id);
    }

    private static function deactivate(string $table, int $id)
    {
        global $wpdb;
        $result = $wpdb->update($table, array('active' => 0, 'updated_at' => current_time('mysql')), array('id' => $id));
        return $result === false ? self::dbError() : rest_ensure_response(array('id' => $id, 'active' => 0));
    }

    private static function delete(string $table, int $id)
    {
        global $wpdb;
        $result = $wpdb->delete($table, array('id' => $id));
        return $result === false ? self::dbError() : rest_ensure_response(array('deleted' => (bool) $result, 'id' => $id));
    }

    private static function required(string $field): WP_Error { return new WP_Error('abp_required', sprintf(__('%s is required.', 'appointment-booking-plugin'), $field), array('status' => 400)); }
    private static function dbError(): WP_Error { return new WP_Error('abp_database_error', __('The database operation failed.', 'appointment-booking-plugin'), array('status' => 500)); }
    private static function bool($value): int { return rest_sanitize_boolean($value) ? 1 : 0; }
    private static function dateOrNull($value): ?string { $value = sanitize_text_field((string) $value); $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $date && $date->format('Y-m-d') === $value ? $value : null; }
    private static function timeOrNull($value): ?string { $value = sanitize_text_field((string) $value); if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) return strlen($value) === 5 ? $value . ':00' : $value; return null; }
}
