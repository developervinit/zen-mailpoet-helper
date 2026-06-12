<?php
/**
 * Frontend Popup Renderer and display rules evaluation.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Zen_Popup_Renderer {

    private static $instance = null;
    public static $shortcode_rendered = false;

    private $should_render = false;
    private $active_popup_id = 0;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'evaluate_and_enqueue_assets'), 11);
        add_action('wp_footer', array($this, 'maybe_render_global_popup'));
        add_shortcode('zen_mailpoet_popup', array($this, 'render_shortcode_popup'));
    }

    /**
     * Evaluates display rules and enqueues assets early in the page load.
     */
    public function evaluate_and_enqueue_assets() {
        // Do not process in admin
        if (is_admin()) {
            return;
        }

        // Check global setting: Hide for logged-in users
        if (get_option('zen_mp_hide_for_logged_in', 'no') === 'yes' && is_user_logged_in()) {
            return;
        }

        // Get the active popup CPT post
        $active_posts = get_posts(array(
            'post_type'   => 'zen_mp_popup',
            'post_status' => 'publish',
            'meta_key'    => '_zen_mp_enabled',
            'meta_value'  => 'yes',
            'numberposts' => 1
        ));

        if (empty($active_posts)) {
            return;
        }

        $popup = $active_posts[0];

        // Evaluate Display Rules
        $display_rules = get_post_meta($popup->ID, '_zen_mp_display_rules', true) ?: array('show_on' => 'all');
        $show_on = isset($display_rules['show_on']) ? $display_rules['show_on'] : 'all';

        if ($show_on === 'selected') {
            $selected_pages = isset($display_rules['selected_pages']) ? $display_rules['selected_pages'] : array();
            $selected_pages = array_map('intval', $selected_pages);
            if (!is_page($selected_pages)) {
                return;
            }
        } elseif ($show_on === 'excluded') {
            $excluded_pages = isset($display_rules['excluded_pages']) ? $display_rules['excluded_pages'] : array();
            $excluded_pages = array_map('intval', $excluded_pages);
            if (is_page($excluded_pages)) {
                return;
            }
        }

        // Rules satisfied, store popup state and enqueue assets
        $this->should_render = true;
        $this->active_popup_id = $popup->ID;

        wp_enqueue_style('zen-mailpoet-helper-style');
        wp_enqueue_script('zen-mailpoet-helper-script');
    }

    /**
     * Renders the active global popup in wp_footer if rules match.
     */
    public function maybe_render_global_popup() {
        // Do not render in admin, if not flagged, or if a shortcode already rendered a popup
        if (is_admin() || !$this->should_render || self::$shortcode_rendered) {
            return;
        }

        // Render Popup HTML
        echo $this->get_popup_html($this->active_popup_id);
    }

    /**
     * Renders a popup using the shortcode [zen_mailpoet_popup]
     */
    public function render_shortcode_popup($atts) {
        $atts = shortcode_atts(
            array(
                'list_ids'                   => '',
                'images'                     => '',
                'title'                      => __('SUBSCRIBE & GET 10% OFF YOUR FIRST PURCHASE', 'zen-mailpoet-helper'),
                'subtitle'                   => __('Subscribe to our mindful newsletter and stay connected with upcoming classes, events, and gentle inspirations.', 'zen-mailpoet-helper'),
                'privacy_text'               => __('I accept the Privacy Policy', 'zen-mailpoet-helper'),
                'privacy_link'               => '#',
                'success_message'            => __('Thank you! You have successfully subscribed.', 'zen-mailpoet-helper'),
                'error_message'              => __('An error occurred. Please try again.', 'zen-mailpoet-helper'),
                'already_subscribed_message' => __('You are already subscribed to this newsletter.', 'zen-mailpoet-helper'),
                'popup_id'                   => '' // Optional: render a configured popup by ID
            ),
            $atts,
            'zen_mailpoet_popup'
        );

        // Mark shortcode as rendered to suppress global footer popups
        self::$shortcode_rendered = true;

        // Enqueue Assets
        wp_enqueue_style('zen-mailpoet-helper-style');
        wp_enqueue_script('zen-mailpoet-helper-script');

        // If a configured popup ID is passed, render it directly
        if (!empty($atts['popup_id'])) {
            $popup_id = intval($atts['popup_id']);
            if (get_post_type($popup_id) === 'zen_mp_popup') {
                return $this->get_popup_html($popup_id);
            }
        }

        // Otherwise, render using shortcode attributes (legacy fallback support)
        $list_ids = sanitize_text_field($atts['list_ids']);
        if (empty($list_ids)) {
            return '<!-- Zen MailPoet Helper: Missing list_ids parameter -->';
        }

        // Parse images
        $images_str = sanitize_text_field($atts['images']);
        if (empty($images_str)) {
            $images = array(
                plugins_url('assets/images/bg1.png', dirname(dirname(__FILE__))),
                plugins_url('assets/images/bg2.png', dirname(dirname(__FILE__)))
            );
        } else {
            $images = array_map('trim', explode(',', $images_str));
            $images = array_map('esc_url', $images);
        }

        ob_start();
        ?>
        <!-- Zen MailPoet Helper Wrapper (Inline Shortcode Fallback) -->
        <div class="zen-mp-popup-wrapper" 
             data-list-ids="<?php echo esc_attr($list_ids); ?>" 
             data-msg-success="<?php echo esc_attr($atts['success_message']); ?>"
             data-msg-error="<?php echo esc_attr($atts['error_message']); ?>"
             data-msg-already="<?php echo esc_attr($atts['already_subscribed_message']); ?>"
             data-delay="2500"
             data-frequency="30"
             style="display: none;">

            <div class="zen-mp-overlay"></div>

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
                                    <input type="checkbox" id="zen-mp-privacy-agree-shortcode" class="zen-mp-checkbox-input" required />
                                    <label for="zen-mp-privacy-agree-shortcode" class="zen-mp-checkbox-label">
                                        <span class="zen-mp-checkbox-box"></span>
                                        <span class="zen-mp-checkbox-text">
                                            <?php 
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

    /**
     * Generates Popup HTML from database configuration.
     */
    private function get_popup_html($popup_id) {
        $title = get_post_meta($popup_id, '_zen_mp_title', true) ?: __('SUBSCRIBE & GET 10% OFF', 'zen-mailpoet-helper');
        $description = get_post_meta($popup_id, '_zen_mp_description', true) ?: '';
        $placeholder = get_post_meta($popup_id, '_zen_mp_placeholder', true) ?: __('Enter your email address', 'zen-mailpoet-helper');
        $button_text = get_post_meta($popup_id, '_zen_mp_button_text', true) ?: __('Subscribe', 'zen-mailpoet-helper');
        $success_message = get_post_meta($popup_id, '_zen_mp_success_message', true) ?: __('Subscribed successfully!', 'zen-mailpoet-helper');
        $error_message = get_post_meta($popup_id, '_zen_mp_error_message', true) ?: __('An error occurred. Please try again.', 'zen-mailpoet-helper');
        $already_message = __('You are already subscribed to this newsletter.', 'zen-mailpoet-helper');

        $list_ids = get_post_meta($popup_id, '_zen_mp_list_ids', true) ?: array();
        $list_ids_str = implode(',', $list_ids);

        $behavior_rules = get_post_meta($popup_id, '_zen_mp_behavior_rules', true) ?: array();
        $delay = isset($behavior_rules['delay']) ? intval($behavior_rules['delay']) : 2500;
        $frequency = isset($behavior_rules['frequency']) ? intval($behavior_rules['frequency']) : 30;
        $devices = isset($behavior_rules['devices']) ? $behavior_rules['devices'] : array('desktop', 'tablet', 'mobile');

        // Map excluded devices to responsive helper classes
        $device_classes = array();
        if (!in_array('desktop', $devices)) {
            $device_classes[] = 'zen-mp-hide-desktop';
        }
        if (!in_array('tablet', $devices)) {
            $device_classes[] = 'zen-mp-hide-tablet';
        }
        if (!in_array('mobile', $devices)) {
            $device_classes[] = 'zen-mp-hide-mobile';
        }
        $device_class_str = implode(' ', $device_classes);

        // Load premium default slides/images
        $images = array(
            plugins_url('assets/images/bg1.png', dirname(dirname(__FILE__))),
            plugins_url('assets/images/bg2.png', dirname(dirname(__FILE__)))
        );

        ob_start();
        ?>
        <!-- Zen MailPoet Helper Wrapper (Dynamic Configuration) -->
        <div class="zen-mp-popup-wrapper <?php echo esc_attr($device_class_str); ?>" 
             id="zen-mp-popup-<?php echo esc_attr($popup_id); ?>"
             data-list-ids="<?php echo esc_attr($list_ids_str); ?>" 
             data-msg-success="<?php echo esc_attr($success_message); ?>"
             data-msg-error="<?php echo esc_attr($error_message); ?>"
             data-msg-already="<?php echo esc_attr($already_message); ?>"
             data-delay="<?php echo esc_attr($delay); ?>"
             data-frequency="<?php echo esc_attr($frequency); ?>"
             style="display: none;">

            <div class="zen-mp-overlay"></div>

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
                            <h3 class="zen-mp-title"><?php echo esc_html($title); ?></h3>
                            <p class="zen-mp-subtitle"><?php echo esc_html($description); ?></p>
                            
                            <form class="zen-mp-custom-form" novalidate>
                                <div class="zen-mp-input-container">
                                    <input type="email" class="zen-mp-input-email" placeholder="<?php echo esc_attr($placeholder); ?>" required />
                                    <button type="submit" class="zen-mp-submit-btn" aria-label="<?php echo esc_attr($button_text); ?>">
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
                                    <input type="checkbox" id="zen-mp-privacy-agree-<?php echo esc_attr($popup_id); ?>" class="zen-mp-checkbox-input" required />
                                    <label for="zen-mp-privacy-agree-<?php echo esc_attr($popup_id); ?>" class="zen-mp-checkbox-label">
                                        <span class="zen-mp-checkbox-box"></span>
                                        <span class="zen-mp-checkbox-text">
                                            <?php 
                                            $link_html = '<a href="#privacy" target="_blank" class="zen-mp-privacy-link">' . esc_html__('Privacy Policy', 'zen-mailpoet-helper') . '</a>';
                                            echo sprintf(__('I accept the %s', 'zen-mailpoet-helper'), $link_html);
                                            ?>
                                        </span>
                                    </label>
                                </div>
                            </form>

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
