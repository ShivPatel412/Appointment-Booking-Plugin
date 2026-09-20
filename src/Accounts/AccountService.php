<?php

declare(strict_types=1);

namespace ABP\Accounts;

use ABP\Infrastructure\Database;
use ABP\Infrastructure\Settings;
use WP_Error;

final class AccountService
{
    public function currentPatient(): ?array
    {
        return $this->patientForUser(get_current_user_id());
    }

    public function currentDoctor(): ?array
    {
        global $wpdb;
        $userId = get_current_user_id();
        if (! $userId) return null;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Database::table('doctors') . ' WHERE user_id=%d AND active=1', $userId), ARRAY_A) ?: null;
    }

    public function patientForUser(int $userId): ?array
    {
        global $wpdb;
        if (! $userId) return null;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Database::table('patients') . " WHERE user_id=%d AND active=1 AND account_status='active'", $userId), ARRAY_A) ?: null;
    }

    /** @return array<string,mixed>|WP_Error */
    public function register(array $data)
    {
        global $wpdb;
        if (! Settings::get()['patient_registration_enabled']) {
            return new WP_Error('abp_registration_disabled', __('Patient registration is disabled.', 'appointment-booking-plugin'), array('status' => 403));
        }
        $remote = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rateKey = 'abp_reg_' . substr(hash('sha256', $remote), 0, 32);
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 5) return new WP_Error('abp_registration_rate_limited', __('Too many registration attempts. Please try again later.', 'appointment-booking-plugin'), array('status' => 429));
        set_transient($rateKey, $attempts + 1, 15 * MINUTE_IN_SECONDS);
        $email = sanitize_email($data['email'] ?? '');
        $first = sanitize_text_field($data['first_name'] ?? '');
        $last = sanitize_text_field($data['last_name'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (! is_email($email) || $first === '' || $last === '' || strlen($password) < 10) {
            return new WP_Error('abp_invalid_registration', __('Valid name, email, and a password of at least 10 characters are required.', 'appointment-booking-plugin'), array('status' => 400));
        }
        if (email_exists($email)) {
            return new WP_Error('abp_email_exists', __('An account already exists for this email. Use password reset instead.', 'appointment-booking-plugin'), array('status' => 409));
        }
        $patient = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Database::table('patients') . ' WHERE email=%s', $email), ARRAY_A);
        if ($patient && (int) $patient['user_id'] > 0) {
            return new WP_Error('abp_patient_linked', __('This patient record is already linked to an account.', 'appointment-booking-plugin'), array('status' => 409));
        }
        $username = sanitize_user(strstr($email, '@', true), true) ?: 'patient';
        $candidate = $username; $suffix = 1;
        while (username_exists($candidate)) $candidate = $username . $suffix++;
        $userId = wp_create_user($candidate, $password, $email);
        if (is_wp_error($userId)) return $userId;
        $user = get_user_by('id', $userId);
        $user->set_role('abp_patient');
        wp_update_user(array('ID' => $userId, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim($first . ' ' . $last)));
        $now = current_time('mysql');
        if ($patient) {
            $wpdb->update(Database::table('patients'), array('user_id' => $userId, 'first_name' => $first, 'last_name' => $last, 'account_status' => 'pending', 'active' => 1, 'updated_at' => $now), array('id' => $patient['id']));
            $patientId = (int) $patient['id'];
        } else {
            $wpdb->insert(Database::table('patients'), array(
                'user_id' => $userId, 'first_name' => $first, 'last_name' => $last, 'email' => $email,
                'reference' => 'PAT-' . strtoupper(wp_generate_password(8, false, false)), 'account_status' => 'pending',
                'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ));
            $patientId = (int) $wpdb->insert_id;
        }
        if (! $patientId) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
            return new WP_Error('abp_registration_failed', __('Could not create the patient record.', 'appointment-booking-plugin'), array('status' => 500));
        }
        $token = wp_generate_password(48, false, false);
        update_user_meta($userId, 'abp_activation_hash', wp_hash_password($token));
        update_user_meta($userId, 'abp_activation_expires', time() + DAY_IN_SECONDS);
        $activationUrl = add_query_arg(array('abp_activate' => $token, 'abp_user' => $userId), get_permalink((int) get_option('abp_patient_portal_page_id')));
        wp_mail($email, __('Activate your patient account', 'appointment-booking-plugin'), sprintf(__('Activate your secure patient account: %s', 'appointment-booking-plugin'), $activationUrl));
        return array('user_id' => $userId, 'patient_id' => $patientId, 'requires_activation' => true, 'message' => __('Check your email to activate the account before signing in.', 'appointment-booking-plugin'));
    }

    /** @return true|WP_Error */
    public function activate(int $userId, string $token)
    {
        global $wpdb;
        $hash = (string) get_user_meta($userId, 'abp_activation_hash', true);
        $expires = (int) get_user_meta($userId, 'abp_activation_expires', true);
        if (! $hash || $expires < time() || ! wp_check_password($token, $hash)) {
            return new WP_Error('abp_invalid_activation', __('This activation link is invalid or expired.', 'appointment-booking-plugin'));
        }
        $updated = $wpdb->update(Database::table('patients'), array('account_status' => 'active', 'updated_at' => current_time('mysql')), array('user_id' => $userId));
        if ($updated === false) return new WP_Error('abp_activation_failed', __('The account could not be activated.', 'appointment-booking-plugin'));
        delete_user_meta($userId, 'abp_activation_hash');
        delete_user_meta($userId, 'abp_activation_expires');
        return true;
    }

    public static function blockPendingLogin($user)
    {
        global $wpdb;
        if (is_wp_error($user) || ! $user instanceof \WP_User) return $user;
        $status = $wpdb->get_var($wpdb->prepare('SELECT account_status FROM ' . Database::table('patients') . ' WHERE user_id=%d', $user->ID));
        if ($status && $status !== 'active') return new WP_Error('abp_account_inactive', __('Activate your patient account before signing in.', 'appointment-booking-plugin'));
        return $user;
    }
}
