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
});
