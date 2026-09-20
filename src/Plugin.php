<?php

declare(strict_types=1);

namespace ABP;

use ABP\Admin\Admin;
use ABP\Infrastructure\Database;
use ABP\Rest\Api;
use ABP\Notifications\NotificationService;
use ABP\Portal\PortalFrontend;
use ABP\Rest\PortalApi;
use ABP\Accounts\AccountService;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        add_action('init', array(Database::class, 'maybeMigrate'));
        add_action('admin_menu', array(Admin::class, 'registerMenu'));
        add_action('admin_enqueue_scripts', array(Admin::class, 'enqueue'));
        add_action('rest_api_init', array(Api::class, 'registerRoutes'));
        add_action('rest_api_init', array(PortalApi::class, 'registerRoutes'));
        add_action('abp_appointment_event', array(NotificationService::class, 'handleAppointmentEvent'), 10, 2);
        add_action('abp_prescription_event', array(NotificationService::class, 'handlePrescriptionEvent'), 10, 2);
        add_shortcode('abp_patient_portal', array(PortalFrontend::class, 'patientShortcode'));
        add_shortcode('abp_doctor_portal', array(PortalFrontend::class, 'doctorShortcode'));
        add_filter('authenticate', array(AccountService::class, 'blockPendingLogin'), 30, 1);
    }
}
