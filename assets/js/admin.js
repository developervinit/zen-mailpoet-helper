/**
 * Admin Panel script - Zen MailPoet Helper
 */

jQuery(document).ready(function($) {

    // 1. Tabbed Interface Controller
    $('.zen-mp-tabs').on('click', 'li', function() {
        var tabId = $(this).attr('data-tab');

        // Toggle active class on menu tabs
        $('.zen-mp-tabs li').removeClass('active');
        $(this).addClass('active');

        // Toggle active class on tab contents
        $('.zen-mp-tab-content .tab-pane').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    // 2. Display Rules Conditional Visibility
    function handleDisplayRulesVisibility() {
        var selectedVal = $('input[name="zen_mp_show_on"]:checked').val();
        
        // Hide all toggled inputs by default
        $('.display-rule-toggle').hide();

        if (selectedVal === 'selected') {
            $('.selected-pages-row').show();
        } else if (selectedVal === 'excluded') {
            $('.excluded-pages-row').show();
        }
    }

    // Run display logic on load and on change
    $('input[name="zen_mp_show_on"]').on('change', handleDisplayRulesVisibility);
    handleDisplayRulesVisibility();

    // 3. Media Uploader for Custom Images
    $('.zen-mp-images-grid').on('click', '.zen-mp-add-image-btn', function(e) {
        e.preventDefault();
        var $slot = $(this).closest('.zen-mp-image-slot');
        var $preview = $slot.find('.zen-mp-image-preview');
        var $input = $slot.find('.zen-mp-image-input');
        var $removeBtn = $slot.find('.zen-mp-remove-image-btn');
        var $addBtn = $(this);

        var file_frame = wp.media({
            title: 'Select or Upload Popup Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        file_frame.on('select', function() {
            var attachment = file_frame.state().get('selection').first().toJSON();
            $preview.css('background-image', 'url(' + attachment.url + ')');
            $input.val(attachment.url);
            $slot.removeClass('empty').addClass('has-image');
            $addBtn.hide();
            $removeBtn.show();
        });

        file_frame.open();
    });

    $('.zen-mp-images-grid').on('click', '.zen-mp-remove-image-btn', function(e) {
        e.preventDefault();
        var $slot = $(this).closest('.zen-mp-image-slot');
        var $preview = $slot.find('.zen-mp-image-preview');
        var $input = $slot.find('.zen-mp-image-input');
        var $addBtn = $slot.find('.zen-mp-add-image-btn');
        var $removeBtn = $(this);

        $preview.css('background-image', 'none');
        $input.val('');
        $slot.removeClass('has-image').addClass('empty');
        $removeBtn.hide();
        $addBtn.show();
    });
});
