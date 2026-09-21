<?php

declare(strict_types=1);

namespace ABP\Admin;

use ABP\Infrastructure\Capabilities;

final class Admin
{
    private const SLUG = 'appointment-booking';

    public static function registerMenu(): void
    {
        add_menu_page(
            __('Appointment', 'appointment-booking-plugin'),
            __('Appointment', 'appointment-booking-plugin'),
            Capabilities::VIEW,
            self::SLUG,
            array(self::class, 'render'),
            'dashicons-calendar-alt',
            26
        );

        $pages = array(
            'dashboard' => __('Dashboard', 'appointment-booking-plugin'),
            'calendar' => __('Calendar', 'appointment-booking-plugin'),
            'bookings' => __('Appointments', 'appointment-booking-plugin'),
            'doctors' => __('Employee', 'appointment-booking-plugin'),
            'catalog' => __('Catalog', 'appointment-booking-plugin'),
            'patients' => __('Customers', 'appointment-booking-plugin'),
            'notifications' => __('Notifications', 'appointment-booking-plugin'),
            'settings' => __('Settings', 'appointment-booking-plugin'),
        );
        foreach ($pages as $page => $label) {
            add_submenu_page(
                self::SLUG,
                $label,
                $label,
                Capabilities::VIEW,
                $page === 'dashboard' ? self::SLUG : self::SLUG . '-' . $page,
                array(self::class, 'render')
            );
        }
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::VIEW) && ! current_user_can(Capabilities::MANAGE)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'appointment-booking-plugin'));
        }
        echo '<div class="wrap abp-wrap"><div id="abp-admin-app"><p>' . esc_html__('Loading appointment manager…', 'appointment-booking-plugin') . '</p></div></div>';
    }

    public static function enqueue(string $hook): void
    {
        if (strpos($hook, self::SLUG) === false) return;
        $pageMap = array(
            self::SLUG => 'dashboard',
            self::SLUG . '-calendar' => 'calendar',
            self::SLUG . '-bookings' => 'bookings',
            self::SLUG . '-doctors' => 'doctors',
            self::SLUG . '-catalog' => 'catalog',
            self::SLUG . '-patients' => 'patients',
            self::SLUG . '-notifications' => 'notifications',
            self::SLUG . '-settings' => 'settings',
        );
        $requested = isset($_GET['page']) && is_string($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : self::SLUG;
        wp_enqueue_media();
        wp_enqueue_style('abp-admin', ABP_URL . 'assets/admin.css', array(), ABP_VERSION);
        wp_enqueue_style('abp-admin-extra', ABP_URL . 'assets/admin-extra.css', array('abp-admin'), ABP_VERSION);
        wp_enqueue_style('abp-admin-schedule', ABP_URL . 'assets/admin-schedule.css', array('abp-admin'), ABP_VERSION);
        wp_enqueue_style('abp-admin-media', ABP_URL . 'assets/admin-media.css', array('abp-admin'), ABP_VERSION);
        wp_enqueue_script('abp-admin', ABP_URL . 'assets/admin.js', array('wp-element', 'wp-api-fetch', 'wp-i18n'), ABP_VERSION, true);
        wp_add_inline_script('abp-admin', 'window.ABP_CONFIG=' . wp_json_encode(array(
            'root' => esc_url_raw(rest_url('appointment-booking/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'canDelete' => current_user_can(Capabilities::DELETE),
            'initialPage' => $pageMap[$requested] ?? 'dashboard',
        )) . ';', 'before');
    }
}
