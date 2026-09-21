<?php
/**
 * Plugin Name: Appointment
 * Description: Secure appointment scheduling for service-based practices.
 * Version: 0.1.2
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: ShivPatel412
 * Text Domain: appointment-booking-plugin
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ABP_VERSION', '0.1.2');
define('ABP_FILE', __FILE__);
define('ABP_PATH', plugin_dir_path(__FILE__));
define('ABP_URL', plugin_dir_url(__FILE__));

require_once ABP_PATH . 'src/Autoloader.php';
\ABP\Autoloader::register();

register_activation_hook(__FILE__, array(\ABP\Infrastructure\Activator::class, 'activate'));
register_deactivation_hook(__FILE__, array(\ABP\Infrastructure\Activator::class, 'deactivate'));

add_action('plugins_loaded', static function (): void {
    \ABP\Plugin::instance()->boot();
});
