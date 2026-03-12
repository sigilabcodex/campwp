<?php
/**
 * Plugin Name: CAMPWP
 * Description: Catalog of Albums & Music Publishing for WordPress.
 * Version: 0.1.0
 * Author: CAMPWP Contributors
 * License: GPL-2.0-or-later
 * Text Domain: campwp
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CAMPWP_VERSION', '0.1.0');
define('CAMPWP_FILE', __FILE__);
define('CAMPWP_PATH', plugin_dir_path(__FILE__));
define('CAMPWP_URL', plugin_dir_url(__FILE__));

$autoload = CAMPWP_PATH . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once CAMPWP_PATH . 'src/Plugin.php';
    require_once CAMPWP_PATH . 'src/PostTypes.php';
    require_once CAMPWP_PATH . 'src/MetaBoxes.php';
    require_once CAMPWP_PATH . 'src/Storage/LocalStorage.php';
    require_once CAMPWP_PATH . 'src/Media/MetadataExtractor.php';
    require_once CAMPWP_PATH . 'src/Frontend.php';
    require_once CAMPWP_PATH . 'src/Integrations/WooCommerceBridge.php';
}

add_action('plugins_loaded', static function () {
    $plugin = new CampWP\Plugin();
    $plugin->register();
});

register_activation_hook(__FILE__, static function () {
    $post_types = new CampWP\PostTypes();
    $post_types->register();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function () {
    flush_rewrite_rules();
});
