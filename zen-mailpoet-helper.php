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
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
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
        $atts = shortcode_atts(
            array(
                'list_ids'                   => '',
                'images'                     => '',
                'title'                      => esc_html__('SUBSCRIBE & GET 10% OFF YOUR FIRST PURCHASE', 'zen-mailpoet-helper'),
                'subtitle'                   => esc_html__('Subscribe to our mindful newsletter and stay connected with upcoming classes, events, and gentle inspirations.', 'zen-mailpoet-helper'),
                'privacy_text'               => esc_html__('I accept the Privacy Policy', 'zen-mailpoet-helper'),
                'privacy_link'               => '#',
                'success_message'            => esc_html__('Thank you! You have successfully subscribed.', 'zen-mailpoet-helper'),
                'error_message'              => esc_html__('An error occurred. Please try again.', 'zen-mailpoet-helper'),
                'already_subscribed_message' => esc_html__('You are already subscribed to this newsletter.', 'zen-mailpoet-helper'),
            ),
            $atts,
            'zen_mailpoet_popup'
        );

        $list_ids = sanitize_text_field($atts['list_ids']);

        if (empty($list_ids)) {
            return '<!-- Zen MailPoet Helper: Missing list_ids parameter -->';
        }

        // Check if MailPoet is active
        if (!class_exists('\MailPoet\API\API')) {
            return '<!-- Zen MailPoet Helper: MailPoet is not active -->';
        }

        // Enqueue registered assets
        wp_enqueue_style('zen-mailpoet-helper-style');
        wp_enqueue_script('zen-mailpoet-helper-script');

        // Parse images parameter or fall back to default assets
        $images_str = sanitize_text_field($atts['images']);
        if (empty($images_str)) {
            $images = array(
                plugins_url('assets/images/bg1.png', __FILE__),
                plugins_url('assets/images/bg2.png', __FILE__)
            );
        } else {
            $images = array_map('trim', explode(',', $images_str));
            $images = array_map('esc_url', $images);
        }

        ob_start();
        ?>
        <!-- Zen MailPoet Helper Wrapper -->
        <div class="zen-mp-popup-wrapper" 
             data-list-ids="<?php echo esc_attr($list_ids); ?>" 
             data-msg-success="<?php echo esc_attr($atts['success_message']); ?>"
             data-msg-error="<?php echo esc_attr($atts['error_message']); ?>"
             data-msg-already="<?php echo esc_attr($atts['already_subscribed_message']); ?>"
             style="display: none;">

            <!-- Custom Premium Popup Modal Overlay -->
            <div class="zen-mp-overlay"></div>

            <!-- Custom Premium Popup Modal (Split Layout) -->
            <div class="zen-mp-modal-card">
                <button type="button" class="zen-mp-close-btn" aria-label="<?php esc_html_e('Close Popup', 'zen-mailpoet-helper'); ?>">
                    <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                
                <div class="zen-mp-layout-split">
                    <!-- Left Column: Media Slideshow -->
                    <div class="zen-mp-col-media">
                        <?php foreach ($images as $index => $img_url) : ?>
                            <div class="zen-mp-slide<?php echo $index === 0 ? ' zen-mp-slide-active' : ''; ?>" style="background-image: url('<?php echo esc_url($img_url); ?>');"></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Right Column: Content & Form -->
                    <div class="zen-mp-col-content">
                        <div class="zen-mp-content-body">
                            <h3 class="zen-mp-title"><?php echo esc_html($atts['title']); ?></h3>
                            <p class="zen-mp-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
                            
                            <form class="zen-mp-custom-form" novalidate>
                                <div class="zen-mp-input-container">
                                    <input type="email" class="zen-mp-input-email" placeholder="<?php esc_attr_e('Enter your email', 'zen-mailpoet-helper'); ?>" required />
                                    <button type="submit" class="zen-mp-submit-btn" aria-label="<?php esc_attr_e('Subscribe', 'zen-mailpoet-helper'); ?>">
                                        <span class="zen-mp-btn-text">
                                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                                <polyline points="12 5 19 12 12 19"></polyline>
                                            </svg>
                                        </span>
                                        <span class="zen-mp-btn-loader" style="display: none;"></span>
                                    </button>
                                </div>
                                
                                <div class="zen-mp-checkbox-container">
                                    <input type="checkbox" id="zen-mp-privacy-agree" class="zen-mp-checkbox-input" required />
                                    <label for="zen-mp-privacy-agree" class="zen-mp-checkbox-label">
                                        <span class="zen-mp-checkbox-box"></span>
                                        <span class="zen-mp-checkbox-text">
                                            <?php 
                                            // Split text to style "Privacy Policy" as a link
                                            $privacy_text = esc_html($atts['privacy_text']);
                                            $link_html = '<a href="' . esc_url($atts['privacy_link']) . '" target="_blank" class="zen-mp-privacy-link">' . esc_html__('Privacy Policy', 'zen-mailpoet-helper') . '</a>';
                                            
                                            if (strpos($privacy_text, 'Privacy Policy') !== false) {
                                                echo str_replace('Privacy Policy', $link_html, $privacy_text);
                                            } else {
                                                echo $privacy_text . ' ' . $link_html;
                                            }
                                            ?>
                                        </span>
                                    </label>
                                </div>
                            </form>

                            <!-- Feedback message container (Success / Error states) -->
                            <div class="zen-mp-feedback-container" style="display: none;">
                                <p class="zen-mp-feedback-message"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the helper
add_action('plugins_loaded', array('Zen_MailPoet_Helper', 'get_instance'));
