<?php
/**
 * Admin interface and Custom Post Type configuration.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Zen_Popup_Admin {

    public function __construct() {
        // CPT & Menus
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_filter('parent_file', array($this, 'set_parent_menu'));

        // Custom listing columns
        add_filter('manage_zen_mp_popup_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_zen_mp_popup_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
        add_filter('post_row_actions', array($this, 'add_row_actions'), 10, 2);
        
        // Duplication action handler
        add_action('admin_action_zen_mp_duplicate_popup', array($this, 'handle_popup_duplication'));

        // Bulk actions
        add_filter('bulk_actions-edit-zen_mp_popup', array($this, 'register_bulk_actions'));
        add_filter('handle_bulk_actions-edit-zen_mp_popup', array($this, 'handle_bulk_actions'), 10, 3);

        // Metaboxes
        add_action('add_meta_boxes_zen_mp_popup', array($this, 'add_popup_metabox'));
        add_action('save_post_zen_mp_popup', array($this, 'save_popup_metadata'));

        // Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Register Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Newsletter Popups', 'Post type general name', 'zen-mailpoet-helper'),
            'singular_name'         => _x('Newsletter Popup', 'Post type singular name', 'zen-mailpoet-helper'),
            'menu_name'             => _x('Newsletter Popups', 'Admin Menu text', 'zen-mailpoet-helper'),
            'add_new'               => __('Add New', 'zen-mailpoet-helper'),
            'add_new_item'          => __('Add New Newsletter Popup', 'zen-mailpoet-helper'),
            'edit_item'             => __('Edit Newsletter Popup', 'zen-mailpoet-helper'),
            'all_items'             => __('All Popups', 'zen-mailpoet-helper'),
            'not_found'             => __('No popups found.', 'zen-mailpoet-helper'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // Handled manually for grouping
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title'),
        );

        register_post_type('zen_mp_popup', $args);
    }

    /**
     * Register Admin Menus
     */
    public function register_admin_menu() {
        // Parent menu page (renders Dashboard)
        add_menu_page(
            __('Newsletter Popup', 'zen-mailpoet-helper'),
            __('Newsletter Popup', 'zen-mailpoet-helper'),
            'manage_options',
            'zen-mailpoet-helper',
            array($this, 'render_dashboard_page'),
            'dashicons-email-alt',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'zen-mailpoet-helper',
            __('Dashboard', 'zen-mailpoet-helper'),
            __('Dashboard', 'zen-mailpoet-helper'),
            'manage_options',
            'zen-mailpoet-helper',
            array($this, 'render_dashboard_page')
        );

        // Popups CPT Submenu
        add_submenu_page(
            'zen-mailpoet-helper',
            __('All Popups', 'zen-mailpoet-helper'),
            __('Popups', 'zen-mailpoet-helper'),
            'manage_options',
            'edit.php?post_type=zen_mp_popup'
        );

        // Settings submenu
        add_submenu_page(
            'zen-mailpoet-helper',
            __('Settings', 'zen-mailpoet-helper'),
            __('Settings', 'zen-mailpoet-helper'),
            'manage_options',
            'zen-mailpoet-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_dashboard_page() {
        if (class_exists('Zen_Popup_Pages')) {
            Zen_Popup_Pages::render_dashboard();
        }
    }

    public function render_settings_page() {
        if (class_exists('Zen_Popup_Pages')) {
            Zen_Popup_Pages::render_settings();
        }
    }

    /**
     * Keep parent menu active in sidebar
     */
    public function set_parent_menu($parent_file) {
        global $current_screen;
        if ($current_screen && $current_screen->post_type === 'zen_mp_popup') {
            $parent_file = 'zen-mailpoet-helper';
        }
        return $parent_file;
    }

    /**
     * Custom Columns for Listing Screen
     */
    public function set_custom_columns($columns) {
        $new_columns = array(
            'cb'            => $columns['cb'],
            'title'         => __('Name', 'zen-mailpoet-helper'),
            'status'        => __('Status', 'zen-mailpoet-helper'),
            'lists'         => __('MailPoet Lists', 'zen-mailpoet-helper'),
            'display_rules' => __('Display Rules', 'zen-mailpoet-helper'),
            'date'          => $columns['date']
        );
        return $new_columns;
    }

    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'status':
                $enabled = get_post_meta($post_id, '_zen_mp_enabled', true);
                if ($enabled === 'yes') {
                    echo '<span class="badge-status enabled">' . __('Enabled', 'zen-mailpoet-helper') . '</span>';
                } else {
                    echo '<span class="badge-status disabled">' . __('Disabled', 'zen-mailpoet-helper') . '</span>';
                }
                break;

            case 'lists':
                $list_ids = get_post_meta($post_id, '_zen_mp_list_ids', true);
                if (empty($list_ids) || !is_array($list_ids)) {
                    echo '<em>' . __('No lists configured', 'zen-mailpoet-helper') . '</em>';
                    break;
                }
                
                $adapter = new Zen_MailPoet_Adapter();
                $mp_lists = $adapter->get_lists();
                $list_names = array();

                foreach ($mp_lists as $list) {
                    if (in_array($list['id'], $list_ids)) {
                        $list_names[] = esc_html($list['name']);
                    }
                }
                
                if (empty($list_names)) {
                    echo esc_html(implode(', ', $list_ids)) . ' <small style="color:red;">(Unknown lists)</small>';
                } else {
                    echo esc_html(implode(', ', $list_names));
                }
                break;

            case 'display_rules':
                $rules = get_post_meta($post_id, '_zen_mp_display_rules', true);
                $show_on = isset($rules['show_on']) ? $rules['show_on'] : 'all';
                
                if ($show_on === 'all') {
                    echo __('Entire Website', 'zen-mailpoet-helper');
                } elseif ($show_on === 'selected') {
                    $pages = isset($rules['selected_pages']) ? $rules['selected_pages'] : array();
                    echo sprintf(__('Selected Pages (%d)', 'zen-mailpoet-helper'), count($pages));
                } elseif ($show_on === 'excluded') {
                    $pages = isset($rules['excluded_pages']) ? $rules['excluded_pages'] : array();
                    echo sprintf(__('Excluded Pages (%d)', 'zen-mailpoet-helper'), count($pages));
                }
                break;
        }
    }

    /**
     * Add Row Actions (Duplicate)
     */
    public function add_row_actions($actions, $post) {
        if ($post->post_type === 'zen_mp_popup') {
            $nonce = wp_create_nonce('zen-mp-duplicate-' . $post->ID);
            $actions['duplicate'] = sprintf(
                '<a href="%s" title="%s">%s</a>',
                admin_url('admin.php?action=zen_mp_duplicate_popup&post_id=' . $post->ID . '&nonce=' . $nonce),
                esc_attr__('Duplicate this popup', 'zen-mailpoet-helper'),
                __('Duplicate', 'zen-mailpoet-helper')
            );
        }
        return $actions;
    }

    /**
     * Handle popup duplication
     */
    public function handle_popup_duplication() {
        if (!isset($_GET['post_id']) || !isset($_GET['nonce'])) {
            wp_die(__('Missing request parameters.', 'zen-mailpoet-helper'));
        }

        $post_id = intval($_GET['post_id']);
        if (!wp_verify_nonce($_GET['nonce'], 'zen-mp-duplicate-' . $post_id)) {
            wp_die(__('Security check failed.', 'zen-mailpoet-helper'));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'zen_mp_popup') {
            wp_die(__('Popup not found.', 'zen-mailpoet-helper'));
        }

        $current_user = wp_get_current_user();
        
        $new_post_args = array(
            'post_title'     => $post->post_title . ' (' . __('Copy', 'zen-mailpoet-helper') . ')',
            'post_status'    => 'draft',
            'post_type'      => 'zen_mp_popup',
            'post_author'    => $current_user->ID,
        );

        $new_post_id = wp_insert_post($new_post_args);
        
        if ($new_post_id) {
            // Duplicate all metadata
            $metadata = get_post_custom($post_id);
            foreach ($metadata as $key => $values) {
                // Skip WP default internal keys
                if (strpos($key, '_zen_mp_') === 0) {
                    foreach ($values as $value) {
                        update_post_meta($new_post_id, $key, maybe_unserialize($value));
                    }
                }
            }
            // Always set duplicated popup to disabled
            update_post_meta($new_post_id, '_zen_mp_enabled', 'no');

            wp_redirect(admin_url('edit.php?post_type=zen_mp_popup'));
            exit;
        } else {
            wp_die(__('Failed to create duplicate.', 'zen-mailpoet-helper'));
        }
    }

    /**
     * Register Bulk Actions
     */
    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['enable_popups'] = __('Enable', 'zen-mailpoet-helper');
        $bulk_actions['disable_popups'] = __('Disable', 'zen-mailpoet-helper');
        return $bulk_actions;
    }

    /**
     * Handle Bulk Actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if (empty($post_ids)) {
            return $redirect_to;
        }

        if ($action === 'enable_popups') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, '_zen_mp_enabled', 'yes');
            }
        } elseif ($action === 'disable_popups') {
            foreach ($post_ids as $post_id) {
                update_post_meta($post_id, '_zen_mp_enabled', 'no');
            }
        }

        return add_query_arg('bulk_processed', count($post_ids), $redirect_to);
    }

    /**
     * Metabox Registration
     */
    public function add_popup_metabox() {
        add_meta_box(
            'zen_mp_popup_config',
            __('Popup Settings & Content Builder', 'zen-mailpoet-helper'),
            array($this, 'render_popup_metabox'),
            'zen_mp_popup',
            'normal',
            'high'
        );
    }

    /**
     * Render Tabbed Metabox HTML
     */
    public function render_popup_metabox($post) {
        wp_nonce_field('zen-mp-save-popup', 'zen_mp_popup_nonce');

        // Load current meta values
        $enabled = get_post_meta($post->ID, '_zen_mp_enabled', true) ?: 'no';
        $title = get_post_meta($post->ID, '_zen_mp_title', true) ?: '';
        $description = get_post_meta($post->ID, '_zen_mp_description', true) ?: '';
        $placeholder = get_post_meta($post->ID, '_zen_mp_placeholder', true) ?: '';
        $button_text = get_post_meta($post->ID, '_zen_mp_button_text', true) ?: '';
        $success_message = get_post_meta($post->ID, '_zen_mp_success_message', true) ?: '';
        $error_message = get_post_meta($post->ID, '_zen_mp_error_message', true) ?: '';
        $privacy_page_id = get_post_meta($post->ID, '_zen_mp_privacy_page_id', true) ?: '';
        
        $list_ids = get_post_meta($post->ID, '_zen_mp_list_ids', true) ?: array();
        
        $display_rules = get_post_meta($post->ID, '_zen_mp_display_rules', true) ?: array(
            'show_on' => 'all',
            'selected_pages' => array(),
            'excluded_pages' => array()
        );
        
        $behavior_rules = get_post_meta($post->ID, '_zen_mp_behavior_rules', true) ?: array(
            'delay' => 2500,
            'frequency' => 30,
            'devices' => array('desktop', 'mobile', 'tablet')
        );

        $adapter = new Zen_MailPoet_Adapter();
        $mp_lists = $adapter->get_lists();

        // Get all pages for Display Rules options
        $all_pages = get_pages(array('post_type' => 'page', 'post_status' => 'publish'));

        ?>
        <div class="zen-mp-metabox-wrapper">
            <!-- Tabs Menu -->
            <ul class="zen-mp-tabs">
                <li class="active" data-tab="tab-general"><?php _e('General', 'zen-mailpoet-helper'); ?></li>
                <li data-tab="tab-content"><?php _e('Content', 'zen-mailpoet-helper'); ?></li>
                <li data-tab="tab-subscription"><?php _e('Subscription', 'zen-mailpoet-helper'); ?></li>
                <li data-tab="tab-display"><?php _e('Display', 'zen-mailpoet-helper'); ?></li>
                <li data-tab="tab-behavior"><?php _e('Behavior', 'zen-mailpoet-helper'); ?></li>
            </ul>

            <div class="zen-mp-tab-content">
                <!-- TAB 1: GENERAL -->
                <div id="tab-general" class="tab-pane active">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('Enable Popup', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <label class="zen-switch">
                                    <input type="checkbox" name="zen_mp_enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <p class="description"><?php _e('Turn on to activate this popup on the front-end (only one popup can be active globally).', 'zen-mailpoet-helper'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB 2: CONTENT -->
                <div id="tab-content" class="tab-pane">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="zen_mp_title"><?php _e('Headline Title', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="text" id="zen_mp_title" name="zen_mp_title" value="<?php echo esc_attr($title); ?>" class="large-text" placeholder="SUBSCRIBE & GET 10% OFF">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_description"><?php _e('Description Text', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <textarea id="zen_mp_description" name="zen_mp_description" class="large-text" rows="3" placeholder="Subscribe to stay connected with upcoming events."><?php echo esc_textarea($description); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_placeholder"><?php _e('Email Placeholder', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="text" id="zen_mp_placeholder" name="zen_mp_placeholder" value="<?php echo esc_attr($placeholder); ?>" class="regular-text" placeholder="Enter your email address">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_button_text"><?php _e('CTA Button Text', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="text" id="zen_mp_button_text" name="zen_mp_button_text" value="<?php echo esc_attr($button_text); ?>" class="regular-text" placeholder="Subscribe">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_success_message"><?php _e('Success Message', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="text" id="zen_mp_success_message" name="zen_mp_success_message" value="<?php echo esc_attr($success_message); ?>" class="large-text" placeholder="Thank you! You have successfully subscribed.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_error_message"><?php _e('Error Message', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="text" id="zen_mp_error_message" name="zen_mp_error_message" value="<?php echo esc_attr($error_message); ?>" class="large-text" placeholder="An error occurred. Please try again.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('Popup Images (Up to 5)', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <div class="zen-mp-image-manager">
                                    <div class="zen-mp-images-grid" id="zen-mp-images-grid">
                                        <?php
                                        $saved_images = get_post_meta($post->ID, '_zen_mp_images', true) ?: array();
                                        for ($i = 0; $i < 5; $i++) {
                                            $img_url = isset($saved_images[$i]) ? $saved_images[$i] : '';
                                            $has_image = !empty($img_url);
                                            ?>
                                            <div class="zen-mp-image-slot <?php echo $has_image ? 'has-image' : 'empty'; ?>" data-slot="<?php echo $i; ?>">
                                                <div class="zen-mp-image-preview" style="background-image: url('<?php echo esc_url($img_url); ?>');"></div>
                                                <div class="zen-mp-image-actions">
                                                    <button type="button" class="button zen-mp-add-image-btn" <?php echo $has_image ? 'style="display:none;"' : ''; ?>><?php _e('Add Image', 'zen-mailpoet-helper'); ?></button>
                                                    <button type="button" class="button button-link-delete zen-mp-remove-image-btn" <?php echo !$has_image ? 'style="display:none;"' : ''; ?>><?php _e('Remove', 'zen-mailpoet-helper'); ?></button>
                                                </div>
                                                <input type="hidden" name="zen_mp_images[]" class="zen-mp-image-input" value="<?php echo esc_attr($img_url); ?>">
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <p class="description"><?php _e('Upload or select up to 5 images for the left-side slideshow. Recommend image size: 600x800px.', 'zen-mailpoet-helper'); ?></p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB 3: SUBSCRIPTION -->
                <div id="tab-subscription" class="tab-pane">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('MailPoet Lists', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <?php if (empty($mp_lists)) : ?>
                                    <div class="notice notice-warning inline"><p><?php _e('No lists found. Please ensure MailPoet is active and lists are configured.', 'zen-mailpoet-helper'); ?></p></div>
                                <?php else : ?>
                                    <div class="zen-list-checkbox-grid">
                                        <?php foreach ($mp_lists as $list) : ?>
                                            <label class="list-label">
                                                <input type="checkbox" name="zen_mp_list_ids[]" value="<?php echo esc_attr($list['id']); ?>" <?php checked(in_array($list['id'], $list_ids)); ?>>
                                                <?php echo esc_html($list['name']); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description"><?php _e('Select which subscriber lists users will be added to upon submission.', 'zen-mailpoet-helper'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB 4: DISPLAY RULES -->
                <div id="tab-display" class="tab-pane">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php _e('Display Target', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <label class="display-radio-label">
                                    <input type="radio" name="zen_mp_show_on" value="all" <?php checked($display_rules['show_on'], 'all'); ?>>
                                    <?php _e('Entire Website', 'zen-mailpoet-helper'); ?>
                                </label><br>
                                <label class="display-radio-label">
                                    <input type="radio" name="zen_mp_show_on" value="selected" <?php checked($display_rules['show_on'], 'selected'); ?>>
                                    <?php _e('Selected Pages', 'zen-mailpoet-helper'); ?>
                                </label><br>
                                <label class="display-radio-label">
                                    <input type="radio" name="zen_mp_show_on" value="excluded" <?php checked($display_rules['show_on'], 'excluded'); ?>>
                                    <?php _e('Exclude Selected Pages', 'zen-mailpoet-helper'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr class="display-rule-toggle selected-pages-row" style="display:none;">
                            <th scope="row"><label><?php _e('Target Pages', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <div class="page-select-grid">
                                    <?php foreach ($all_pages as $page) : ?>
                                        <label class="page-label">
                                            <input type="checkbox" name="zen_mp_selected_pages[]" value="<?php echo esc_attr($page->ID); ?>" <?php checked(in_array($page->ID, isset($display_rules['selected_pages']) ? $display_rules['selected_pages'] : array())); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="display-rule-toggle excluded-pages-row" style="display:none;">
                            <th scope="row"><label><?php _e('Excluded Pages', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <div class="page-select-grid">
                                    <?php foreach ($all_pages as $page) : ?>
                                        <label class="page-label">
                                            <input type="checkbox" name="zen_mp_excluded_pages[]" value="<?php echo esc_attr($page->ID); ?>" <?php checked(in_array($page->ID, isset($display_rules['excluded_pages']) ? $display_rules['excluded_pages'] : array())); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB 5: BEHAVIOR RULES -->
                <div id="tab-behavior" class="tab-pane">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="zen_mp_delay"><?php _e('Display Delay (ms)', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="number" id="zen_mp_delay" name="zen_mp_delay" value="<?php echo esc_attr($behavior_rules['delay']); ?>" class="small-text" min="0" step="500">
                                <p class="description"><?php _e('Delay before showing popup on page load (in milliseconds, e.g. 2500 = 2.5s).', 'zen-mailpoet-helper'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_frequency"><?php _e('Dismissal Expiry (Days)', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <input type="number" id="zen_mp_frequency" name="zen_mp_frequency" value="<?php echo esc_attr($behavior_rules['frequency']); ?>" class="small-text" min="0">
                                <p class="description"><?php _e('Number of days to hide the popup after user closes or subscribes (e.g. 30 days).', 'zen-mailpoet-helper'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="zen_mp_privacy_page_id"><?php _e('Privacy Policy Page', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <select id="zen_mp_privacy_page_id" name="zen_mp_privacy_page_id">
                                    <option value=""><?php _e('-- Select Page --', 'zen-mailpoet-helper'); ?></option>
                                    <?php foreach ($all_pages as $page) : ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($privacy_page_id, $page->ID); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Select the page that the "Privacy Policy" link in the popup will redirect to.', 'zen-mailpoet-helper'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label><?php _e('Target Devices', 'zen-mailpoet-helper'); ?></label></th>
                            <td>
                                <?php
                                $devices = isset($behavior_rules['devices']) ? $behavior_rules['devices'] : array('desktop', 'mobile', 'tablet');
                                ?>
                                <label><input type="checkbox" name="zen_mp_devices[]" value="desktop" <?php checked(in_array('desktop', $devices)); ?>> <?php _e('Desktop', 'zen-mailpoet-helper'); ?></label>&nbsp;&nbsp;&nbsp;
                                <label><input type="checkbox" name="zen_mp_devices[]" value="tablet" <?php checked(in_array('tablet', $devices)); ?>> <?php _e('Tablet', 'zen-mailpoet-helper'); ?></label>&nbsp;&nbsp;&nbsp;
                                <label><input type="checkbox" name="zen_mp_devices[]" value="mobile" <?php checked(in_array('mobile', $devices)); ?>> <?php _e('Mobile', 'zen-mailpoet-helper'); ?></label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save Popup metadata on post save
     */
    public function save_popup_metadata($post_id) {
        if (!isset($_POST['zen_mp_popup_nonce']) || !wp_verify_nonce($_POST['zen_mp_popup_nonce'], 'zen-mp-save-popup')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 1. Enable / Disable state
        $enabled = isset($_POST['zen_mp_enabled']) && $_POST['zen_mp_enabled'] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, '_zen_mp_enabled', $enabled);

        // If enabled, disable all other popups
        if ($enabled === 'yes') {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} pm 
                     JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     SET pm.meta_value = 'no' 
                     WHERE pm.meta_key = '_zen_mp_enabled' 
                     AND pm.post_id != %d
                     AND p.post_type = 'zen_mp_popup'",
                    $post_id
                )
            );
        }

        // 2. Content
        if (isset($_POST['zen_mp_title'])) {
            update_post_meta($post_id, '_zen_mp_title', sanitize_text_field($_POST['zen_mp_title']));
        }
        if (isset($_POST['zen_mp_description'])) {
            update_post_meta($post_id, '_zen_mp_description', sanitize_textarea_field($_POST['zen_mp_description']));
        }
        if (isset($_POST['zen_mp_placeholder'])) {
            update_post_meta($post_id, '_zen_mp_placeholder', sanitize_text_field($_POST['zen_mp_placeholder']));
        }
        if (isset($_POST['zen_mp_button_text'])) {
            update_post_meta($post_id, '_zen_mp_button_text', sanitize_text_field($_POST['zen_mp_button_text']));
        }
        if (isset($_POST['zen_mp_success_message'])) {
            update_post_meta($post_id, '_zen_mp_success_message', sanitize_text_field($_POST['zen_mp_success_message']));
        }
        if (isset($_POST['zen_mp_error_message'])) {
            update_post_meta($post_id, '_zen_mp_error_message', sanitize_text_field($_POST['zen_mp_error_message']));
        }

        // Save customized images list
        if (isset($_POST['zen_mp_images'])) {
            $images = array_map('esc_url_raw', $_POST['zen_mp_images']);
            $images = array_filter($images);
            $images = array_values($images);
            update_post_meta($post_id, '_zen_mp_images', $images);
        } else {
            delete_post_meta($post_id, '_zen_mp_images');
        }

        // 3. Lists
        $list_ids = isset($_POST['zen_mp_list_ids']) ? array_map('intval', $_POST['zen_mp_list_ids']) : array();
        update_post_meta($post_id, '_zen_mp_list_ids', $list_ids);

        // 4. Display Rules
        $show_on = isset($_POST['zen_mp_show_on']) ? sanitize_text_field($_POST['zen_mp_show_on']) : 'all';
        $selected_pages = isset($_POST['zen_mp_selected_pages']) ? array_map('intval', $_POST['zen_mp_selected_pages']) : array();
        $excluded_pages = isset($_POST['zen_mp_excluded_pages']) ? array_map('intval', $_POST['zen_mp_excluded_pages']) : array();
        
        $display_rules = array(
            'show_on' => $show_on,
            'selected_pages' => $selected_pages,
            'excluded_pages' => $excluded_pages
        );
        update_post_meta($post_id, '_zen_mp_display_rules', $display_rules);

        // 5. Behavior Rules
        $delay = isset($_POST['zen_mp_delay']) ? max(0, intval($_POST['zen_mp_delay'])) : 2500;
        $frequency = isset($_POST['zen_mp_frequency']) ? max(0, intval($_POST['zen_mp_frequency'])) : 30;
        $devices = isset($_POST['zen_mp_devices']) ? array_map('sanitize_text_field', $_POST['zen_mp_devices']) : array('desktop', 'tablet', 'mobile');

        $behavior_rules = array(
            'delay' => $delay,
            'frequency' => $frequency,
            'devices' => $devices
        );
        update_post_meta($post_id, '_zen_mp_behavior_rules', $behavior_rules);

        // 6. Privacy Policy Page
        if (isset($_POST['zen_mp_privacy_page_id'])) {
            update_post_meta($post_id, '_zen_mp_privacy_page_id', intval($_POST['zen_mp_privacy_page_id']));
        }
    }

    /**
     * Enqueue Admin Styles and Scripts
     */
    public function enqueue_admin_assets($hook) {
        // Enqueue only on CPT screen
        global $post_type;
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'zen_mp_popup') {
            wp_enqueue_media();
            
            $version = class_exists('Zen_MailPoet_Helper') ? Zen_MailPoet_Helper::get_version() : '1.0.0';
            wp_enqueue_style(
                'zen-mp-admin-style',
                plugins_url('assets/css/admin.css', dirname(dirname(__FILE__))),
                array(),
                $version
            );

            wp_enqueue_script(
                'zen-mp-admin-script',
                plugins_url('assets/js/admin.js', dirname(dirname(__FILE__))),
                array('jquery'),
                $version,
                true
            );
        }
    }
}
