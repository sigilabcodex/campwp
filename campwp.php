<?php
/**
 * Plugin Name: CAMPWP
 * Plugin URI: https://example.com/campwp
 * Description: Catalog of Albums & Music Publishing for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: CAMPWP Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: campwp
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CAMPWP_VERSION', '0.1.0');
define('CAMPWP_FILE', __FILE__);
define('CAMPWP_BASENAME', plugin_basename(__FILE__));
define('CAMPWP_PATH', plugin_dir_path(__FILE__));
define('CAMPWP_URL', plugin_dir_url(__FILE__));

$autoload = CAMPWP_PATH . 'vendor/autoload.php';

if (! file_exists($autoload)) {
    return;
}

require_once $autoload;

register_activation_hook(CAMPWP_FILE, ['CampWP\\Core\\Activator', 'activate']);
register_deactivation_hook(CAMPWP_FILE, ['CampWP\\Core\\Deactivator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    $plugin = new CampWP\Core\Application();
    $plugin->run();
});
