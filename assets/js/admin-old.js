/**
 * Admin JavaScript for HubSpot Sync - Milli
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize active tab on page load
        initializeActiveTab();
        
        // Tab functionality (additional logic, as basic tabs are in inline JS)
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            var tabName = target.substring(1); // Remove the # 
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show/hide content
            $('.tab-content').removeClass('active').hide();
            $(target).addClass('active').show();
            
            // Update hidden field for form submission
            $('#active_tab').val(tabName);\n            localStorage.setItem('hubspot_sync_milli_active_tab', tabName);
            
            // Update URL without reloading page
            if (history.replaceState) {
                history.replaceState(null, null, window.location.pathname + '?page=hubspot-sync-milli&tab=' + tabName);
            }
        });
        
        // Test HubSpot connection
        $('#test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#connection-status');
            var apiToken = $('#api_token').val();
            
            if (!apiToken) {
                $status.removeClass('success error').addClass('error')
                       .text(hubspotSyncMilli.strings.error + ' API token is required.');
                return;
            }
            
            // Update button state
            $button.prop('disabled', true).text(hubspotSyncMilli.strings.testing);
            $status.removeClass('success error').text('');
            
            // Make AJAX request
            $.ajax({
                url: hubspotSyncMilli.ajax_url,
                type: 'POST',
                data: {
                    action: 'hubspot_sync_milli_test_connection',
                    nonce: hubspotSyncMilli.nonce,
                    api_token: apiToken
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('error').addClass('success')
                               .text(hubspotSyncMilli.strings.success);
                        
                        if (response.data && response.data.portal_id) {
                            $status.append(' Portal ID: ' + response.data.portal_id);
                        }
                    } else {
                        $status.removeClass('success').addClass('error')
                               .text(hubspotSyncMilli.strings.error + ' ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    $status.removeClass('success').addClass('error')
                           .text(hubspotSyncMilli.strings.error + ' ' + error);
                },
                complete: function() {
                    // Reset button state
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        });
        
        // Enable/disable test button based on API token input
        $('#api_token').on('input', function() {
            var hasToken = $(this).val().length > 0;
            $('#test-connection').prop('disabled', !hasToken);
            
            if (!hasToken) {
                $('#connection-status').removeClass('success error').text('');
            }
        });
        
        // Field mapping validation
        $('input[name^="hubspot_sync_milli_settings[contact_field_mapping]"]').on('blur', function() {
            var $input = $(this);
            var value = $input.val();
            
            // Basic validation for HubSpot property names
            if (value && !/^[a-z0-9_]+$/.test(value)) {
                $input.css('border-color', '#d63638');
                
                if (!$input.next('.field-warning').length) {
                    $input.after('<span class="field-warning" style="color: #d63638; font-size: 12px; display: block;">Property names should only contain lowercase letters, numbers, and underscores.</span>');
                }
            } else {
                $input.css('border-color', '');
                $input.next('.field-warning').remove();
            }
        });
        
        // Auto-generate site prefix based on environment
        $('#site_environment').on('change', function() {
            var environment = $(this).val();
            var $prefix = $('#site_prefix');
            
            // Only auto-fill if prefix is empty
            if (!$prefix.val()) {
                var prefixes = {
                    'staging': 'StagingTest',
                    'production': 'Production',
                    'development': 'Development'
                };
                
                if (prefixes[environment]) {
                    $prefix.val(prefixes[environment]);
                }
            }
        });
        
        // Show confirmation before saving if debug logging is enabled
        $('form').on('submit', function(e) {
            var debugEnabled = $('input[name="hubspot_sync_milli_settings[debug_logging]"]').is(':checked');
            
            if (debugEnabled) {
                if (!confirm('Debug logging is enabled. This may impact site performance and should only be used for troubleshooting. Continue?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
    
    /**
     * Initialize the active tab based on URL parameter or saved state
     */
    function initializeActiveTab() {
        var urlParams = new URLSearchParams(window.location.search);
        var activeTab = urlParams.get('tab') || localStorage.getItem('hubspot_sync_milli_active_tab') || $('#active_tab').val() || 'general';
        
        // Activate the correct tab
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[href="#' + activeTab + '"]').addClass('nav-tab-active');
        
        // Show the correct content
        $('.tab-content').removeClass('active').hide();
        $('#' + activeTab).addClass('active').show();
        
        // Update hidden field
        $('#active_tab').val(activeTab);\n        localStorage.setItem('hubspot_sync_milli_active_tab', activeTab);
    }

})(jQuery);