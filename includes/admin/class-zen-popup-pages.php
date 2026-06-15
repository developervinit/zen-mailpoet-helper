<?php
/**
 * Renders Dashboard and Settings pages in the WordPress admin area.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Zen_Popup_Pages {

    /**
     * Renders the main Dashboard page.
     */
    public static function render_dashboard() {
        // Fetch active popup details
        $active_popups = get_posts(array(
            'post_type'   => 'zen_mp_popup',
            'post_status' => 'publish',
            'meta_key'    => '_zen_mp_enabled',
            'meta_value'  => 'yes',
            'numberposts' => 1
        ));
        $active_popup = !empty($active_popups) ? $active_popups[0] : null;

        // Check MailPoet status
        $mailpoet_active = class_exists('\MailPoet\API\API');

        ?>
        <div class="wrap zen-mp-admin-page">
            <h1 class="wp-heading-inline"><?php _e('Newsletter Popup Dashboard', 'zen-mailpoet-helper'); ?></h1>
            <hr class="wp-header-end">

            <div class="zen-mp-dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Main Column -->
                <div class="dashboard-main">
                    <!-- Welcome Card -->
                    <div class="zen-card welcome-card" style="background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 4px solid #cca853;">
                        <h2 style="margin-top: 0; color: #3a3a3a; font-weight: 600;"><?php _e('Welcome to Zenctuary Newsletter Popups', 'zen-mailpoet-helper'); ?></h2>
                        <p style="font-size: 15px; color: #666; line-height: 1.6;">
                            <?php _e('Create and manage elegant, high-fidelity popups to engage your visitors and automatically synchronize subscribers with MailPoet lists.', 'zen-mailpoet-helper'); ?>
                        </p>
                        <div style="margin-top: 20px;">
                            <a href="<?php echo admin_url('post-new.php?post_type=zen_mp_popup'); ?>" class="button button-primary button-hero" style="background-color: #cca853; border-color: #cca853; text-shadow: none; font-weight: 600;"><?php _e('Create Your First Popup', 'zen-mailpoet-helper'); ?></a>
                            &nbsp;&nbsp;
                            <a href="<?php echo admin_url('edit.php?post_type=zen_mp_popup'); ?>" class="button button-secondary button-hero"><?php _e('Manage Popups', 'zen-mailpoet-helper'); ?></a>
                        </div>
                    </div>

                    <!-- Usage Quick Guide -->
                    <div class="zen-card" style="background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 12px; color: #3a3a3a;"><?php _e('How to Use', 'zen-mailpoet-helper'); ?></h3>
                        
                        <div class="guide-step" style="margin-bottom: 16px;">
                            <h4 style="margin: 0 0 6px 0; color: #cca853;">1. <?php _e('Create a Popup configuration', 'zen-mailpoet-helper'); ?></h4>
                            <p style="margin: 0; color: #666;"><?php _e('Go to the Popups tab and create a new popup. Set your headline title, description, and list target.', 'zen-mailpoet-helper'); ?></p>
                        </div>
                        
                        <div class="guide-step" style="margin-bottom: 16px;">
                            <h4 style="margin: 0 0 6px 0; color: #cca853;">2. <?php _e('Set Display & Behavior Rules', 'zen-mailpoet-helper'); ?></h4>
                            <p style="margin: 0; color: #666;"><?php _e('Target specific pages or the entire website under the Display Rules tab. Set open delays and device rules under Behavior.', 'zen-mailpoet-helper'); ?></p>
                        </div>

                    </div>
                </div>

                <!-- Sidebar Column -->
                <div class="dashboard-sidebar">
                    <!-- Status Widget -->
                    <div class="zen-card status-widget" style="background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px;">
                        <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 12px; color: #3a3a3a;"><?php _e('System Status', 'zen-mailpoet-helper'); ?></h3>
                        
                        <div class="status-item" style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #666;"><?php _e('MailPoet Status:', 'zen-mailpoet-helper'); ?></span>
                            <?php if ($mailpoet_active) : ?>
                                <span style="color: #10b981; font-weight: 600;"><?php _e('Active', 'zen-mailpoet-helper'); ?></span>
                            <?php else : ?>
                                <span style="color: #ef4444; font-weight: 600;"><?php _e('Not Active', 'zen-mailpoet-helper'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="status-item" style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #666;"><?php _e('Active Popup:', 'zen-mailpoet-helper'); ?></span>
                            <?php if ($active_popup) : ?>
                                <span style="color: #cca853; font-weight: 600;"><?php echo esc_html($active_popup->post_title); ?></span>
                            <?php else : ?>
                                <span style="color: #999; font-style: italic;"><?php _e('None', 'zen-mailpoet-helper'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the Settings page.
     */
    public static function render_settings() {
        // Handle options saving
        if (isset($_POST['zen_mp_save_settings'])) {
            check_admin_referer('zen_mp_settings_action', 'zen_mp_settings_nonce');
            
            $hide_for_logged_in = isset($_POST['zen_mp_hide_for_logged_in']) ? 'yes' : 'no';
            update_option('zen_mp_hide_for_logged_in', $hide_for_logged_in);

            $exclude_admin_pages = isset($_POST['zen_mp_exclude_admin_pages']) ? 'yes' : 'no';
            update_option('zen_mp_exclude_admin_pages', $exclude_admin_pages);
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'zen-mailpoet-helper') . '</p></div>';
        }

        $hide_for_logged_in = get_option('zen_mp_hide_for_logged_in', 'no');
        $exclude_admin_pages = get_option('zen_mp_exclude_admin_pages', 'yes');

        ?>
        <div class="wrap zen-mp-admin-page">
            <h1 class="wp-heading-inline"><?php _e('Newsletter Popup Settings', 'zen-mailpoet-helper'); ?></h1>
            <hr class="wp-header-end">

            <div class="zen-card" style="background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-top: 20px; max-width: 800px;">
                <form method="post" action="">
                    <?php wp_nonce_field('zen_mp_settings_action', 'zen_mp_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="zen_mp_hide_for_logged_in"><?php _e('Hide for Logged-in Users', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="checkbox" id="zen_mp_hide_for_logged_in" name="zen_mp_hide_for_logged_in" value="yes" <?php checked($hide_for_logged_in, 'yes'); ?>>
                                <p class="description"><?php _e('If checked, popups will not show to logged-in WordPress users.', 'zen-mailpoet-helper'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_exclude_admin_pages"><?php _e('Disable in WP Admin Area', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="checkbox" id="zen_mp_exclude_admin_pages" name="zen_mp_exclude_admin_pages" value="yes" <?php checked($exclude_admin_pages, 'yes'); ?> disabled>
                                <p class="description"><?php _e('Popups are automatically prevented from displaying in the WordPress administrative screens.', 'zen-mailpoet-helper'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="zen_mp_save_settings" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'zen-mailpoet-helper'); ?>" style="background-color: #cca853; border-color: #cca853; text-shadow: none; font-weight: 600;">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
