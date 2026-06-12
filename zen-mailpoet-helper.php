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
        
        // Initialize admin and renderer components
        if (is_admin()) {
            new Zen_Popup_Admin();
        }
        Zen_Popup_Renderer::get_instance();
    }

    /**
     * Register scripts and styles so they can be enqueued conditionally.
     */
    public function register_assets() {
        wp_register_style(
            'zen-mailpoet-helper-style',
            plugins_url('assets/css/style.css', __FILE__),
            array(),
            '1.0.0'
        );

        wp_register_script(
            'zen-mailpoet-helper-script',
            plugins_url('assets/js/script.js', __FILE__),
            array('jquery'),
            '1.0.0',
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
