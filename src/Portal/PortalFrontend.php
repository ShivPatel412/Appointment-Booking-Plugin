<?php

declare(strict_types=1);

namespace ABP\Portal;

use ABP\Accounts\AccountService;
use ABP\Infrastructure\Settings;

final class PortalFrontend
{
    public static function patientShortcode(): string
    {
        self::enqueue('patient');
        $activation = '';
        if (! empty($_GET['abp_activate']) && ! empty($_GET['abp_user'])) {
            $result = (new AccountService())->activate(absint($_GET['abp_user']), sanitize_text_field(wp_unslash($_GET['abp_activate'])));
            $activation = '<div class="abpp-notice ' . (is_wp_error($result) ? '' : 'success') . '">' . esc_html(is_wp_error($result) ? $result->get_error_message() : __('Account activated. You can now sign in.', 'appointment-booking-plugin')) . '</div>';
        }
        if (! is_user_logged_in()) {
            $login = wp_login_form(array('echo' => false, 'redirect' => get_permalink()));
            $reset = '<a href="' . esc_url(wp_lostpassword_url(get_permalink())) . '">' . esc_html__('Forgot password?', 'appointment-booking-plugin') . '</a>';
            $registration = Settings::get()['patient_registration_enabled'] ? '<div id="abp-registration"></div>' : '';
            return $activation . '<div class="abp-portal-auth"><section><h2>' . esc_html__('Patient login', 'appointment-booking-plugin') . '</h2>' . $login . $reset . '</section>' . $registration . '</div>';
        }
        if (! (new AccountService())->currentPatient()) return '<div class="abp-portal-message">' . esc_html__('This account is not linked to an active patient profile.', 'appointment-booking-plugin') . '</div>';
        return '<div id="abp-patient-portal"></div>';
    }

    public static function doctorShortcode(): string
    {
        self::enqueue('doctor');
        if (! is_user_logged_in()) {
            return '<div class="abp-portal-auth"><section><h2>' . esc_html__('Doctor login', 'appointment-booking-plugin') . '</h2>' . wp_login_form(array('echo' => false, 'redirect' => get_permalink())) . '<a href="' . esc_url(wp_lostpassword_url(get_permalink())) . '">' . esc_html__('Forgot password?', 'appointment-booking-plugin') . '</a></section></div>';
        }
        if (! current_user_can('manage_options') && ! (new AccountService())->currentDoctor()) return '<div class="abp-portal-message">' . esc_html__('This account is not linked to an active doctor profile.', 'appointment-booking-plugin') . '</div>';
        return '<div id="abp-doctor-portal"></div>';
    }

    private static function enqueue(string $mode): void
    {
        wp_enqueue_style('abp-portal', ABP_URL . 'assets/portal.css', array(), ABP_VERSION);
        wp_enqueue_script('abp-portal', ABP_URL . 'assets/portal.js', array('wp-element'), ABP_VERSION, true);
        wp_add_inline_script('abp-portal', 'window.ABP_PORTAL=' . wp_json_encode(array(
            'mode' => $mode, 'root' => esc_url_raw(rest_url('appointment-booking/v1')),
            'nonce' => wp_create_nonce('wp_rest'), 'logoutUrl' => wp_logout_url(home_url('/')),
            'passwordUrl' => admin_url('profile.php'), 'loginUrl' => wp_login_url(get_permalink()),
            'registrationEnabled' => (bool) Settings::get()['patient_registration_enabled'],
        )) . ';', 'before');
    }
}
