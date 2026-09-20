<?php

declare(strict_types=1);

namespace ABP\Portal;

final class PortalPages
{
    public static function install(): void
    {
        self::ensurePage('abp_patient_portal_page_id', __('Patient Portal', 'appointment-booking-plugin'), '[abp_patient_portal]');
        self::ensurePage('abp_doctor_portal_page_id', __('Doctor Portal', 'appointment-booking-plugin'), '[abp_doctor_portal]');
    }

    private static function ensurePage(string $option, string $title, string $shortcode): void
    {
        $existing = (int) get_option($option);
        if ($existing && get_post($existing)) return;
        $pageId = wp_insert_post(array('post_title' => $title, 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page'), true);
        if (! is_wp_error($pageId)) update_option($option, (int) $pageId, false);
    }
}
