<?php
/**
 * Plugin Name: zen-mailpoet-helper
 * Plugin URI: https://zenctuary.com
 * Description: Integrates custom-designed premium HTML/CSS subscription popups with MailPoet lists via a secure AJAX endpoint.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: zen-mail-poet-helper
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Load service adapter and AJAX handlers
require_once plugin_dir_path(__FILE__) . 'includes/services/mailpoet-adapter.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax/subscribe.php';

// Load admin and renderer modules
require_once plugin_dir_path(__FILE__) . 'includes/admin/class-zen-popup-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/class-zen-popup-pages.php';
require_once plugin_dir_path(__FILE__) . 'includes/display/class-zen-popup-renderer.php';

class Zen_MailPoet_Helper {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('zen_mailpoet_popup', array($this, 'render_popup_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'), 9);
        add_action('init', array($this, 'maybe_clear_cache'));
        
        // Initialize admin and renderer components
        if (is_admin()) {
            new Zen_Popup_Admin();
        }
        Zen_Popup_Renderer::get_instance();
    }

    /**
     * Compute a dynamic version string based on the latest modification time of plugin files.
     */
    public static function get_version() {
        $plugin_dir = plugin_dir_path(__FILE__);
        $files = array(
            $plugin_dir . 'zen-mailpoet-helper.php',
            $plugin_dir . 'assets/css/style.css',
            $plugin_dir . 'assets/js/script.js',
            $plugin_dir . 'assets/css/admin.css',
            $plugin_dir . 'assets/js/admin.js',
            $plugin_dir . 'includes/display/class-zen-popup-renderer.php',
            $plugin_dir . 'includes/services/mailpoet-adapter.php',
            $plugin_dir . 'includes/ajax/subscribe.php',
            $plugin_dir . 'includes/admin/class-zen-popup-admin.php',
            $plugin_dir . 'includes/admin/class-zen-popup-pages.php',
        );
        $max_mtime = 0;
        foreach ($files as $file) {
            if (file_exists($file)) {
                $max_mtime = max($max_mtime, @filemtime($file));
            }
        }
        return '1.0.0.' . ($max_mtime ? $max_mtime : time());
    }

    /**
     * Purge all caching plugins if the plugin code version has changed.
     */
    public function maybe_clear_cache() {
        $current_ver = self::get_version();
        $stored_ver = get_option('zen_mp_code_version', '');
        if ($current_ver !== $stored_ver) {
            update_option('zen_mp_code_version', $current_ver);
            
            // W3 Total Cache
            if (function_exists('w3tc_flush_all')) {
                w3tc_flush_all();
            }
            // WP Super Cache
            if (function_exists('wp_cache_clean_cache')) {
                global $file_prefix;
                wp_cache_clean_cache($file_prefix);
            }
            // LiteSpeed Cache
            if (has_action('litespeed_purgesall')) {
                do_action('litespeed_purgesall');
            }
            // WP Rocket
            if (function_exists('rocket_clean_domain')) {
                rocket_clean_domain();
            }
            // SG Optimizer
            if (function_exists('sg_cachepress_purge_cache')) {
                sg_cachepress_purge_cache();
            }
            // Autoptimize
            if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
                autoptimizeCache::clearall();
            }
            // WP Fastest Cache
            if (class_exists('WpFastestCache') && method_exists('WpFastestCache', 'deleteCache')) {
                $wpfc = new WpFastestCache();
                $wpfc->deleteCache(true);
            }
            // WP Object Cache & Transients
            wp_cache_flush();
        }
    }

    /**
     * Register scripts and styles so they can be enqueued conditionally.
     */
    public function register_assets() {
        $version = self::get_version();

        wp_register_style(
            'zen-mailpoet-helper-style',
            plugins_url('assets/css/style.css', __FILE__),
            array(),
            $version
        );

        wp_register_script(
            'zen-mailpoet-helper-script',
            plugins_url('assets/js/script.js', __FILE__),
            array('jquery'),
            $version,
            true
        );

        // Localize script with AJAX URL and secure CSRF Nonce token
        wp_localize_script(
            'zen-mailpoet-helper-script',
            'zenMailPoetHelper',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('zen-mailpoet-subscribe-nonce'),
            )
        );
    }

    /**
     * Render the custom subscription popup shortcode.
     */
    public function render_popup_shortcode($atts) {
        return Zen_Popup_Renderer::get_instance()->render_shortcode_popup($atts);
    }
}

// Initialize the helper
add_action('plugins_loaded', array('Zen_MailPoet_Helper', 'get_instance'));
