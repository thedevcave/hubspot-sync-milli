/**
 * Checkout JavaScript for HubSpot Sync - Milli
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        /**
         * Handle conditional field display logic
         */
        function updateSurveyLogic() {
            var source = $('#acquisition_source').val();
            var talked = $('#talked_to_provider').val();
            var referred = $('#provider_referred').val();

            // Hide all conditional sections initially
            $('#talked_to_provider_wrapper').slideUp(300);
            $('#provider_referred_wrapper').slideUp(300);
            $('#provider_details_section').slideUp(300);

            if (source === 'Healthcare Provider') {
                // Direct to details for healthcare provider
                $('#provider_details_section').slideDown(300);
            } else if (source && source !== '') {
                // Show "Have you talked to a healthcare provider?" for other sources
                $('#talked_to_provider_wrapper').slideDown(300);

                if (talked === 'Yes') {
                    // Show "Did a provider refer you?"
                    $('#provider_referred_wrapper').slideDown(300);

                    if (referred === 'Yes') {
                        // Show provider details
                        $('#provider_details_section').slideDown(300);
                    }
                }
            }
        }

        /**
         * Clear dependent fields when parent selection changes
         */
        function clearDependentFields() {
            $('#talked_to_provider').val('');
            $('#provider_referred').val('');
            $('#clinician_name').val('');
            $('#clinic_state').val('');
            $('#clinic_name').val('');
        }

        // Bind change events
        $('#acquisition_source').on('change', function() {
            var previousSource = $(this).data('previous-value');
            var currentSource = $(this).val();
            
            // Clear dependent fields if source changed significantly
            if (previousSource && previousSource !== currentSource) {
                if (previousSource === 'Healthcare Provider' || currentSource !== 'Healthcare Provider') {
                    clearDependentFields();
                }
            }
            
            $(this).data('previous-value', currentSource);
            updateSurveyLogic();
        });

        $('#talked_to_provider').on('change', function() {
            // Clear fields below this level if changed to "No" or empty
            var value = $(this).val();
            if (value !== 'Yes') {
                $('#provider_referred').val('');
                $('#clinician_name').val('');
                $('#clinic_state').val('');
                $('#clinic_name').val('');
            }
            
            updateSurveyLogic();
        });

        $('#provider_referred').on('change', function() {
            // Clear provider details if changed to "No" or empty
            var value = $(this).val();
            if (value !== 'Yes') {
                $('#clinician_name').val('');
                $('#clinic_state').val('');
                $('#clinic_name').val('');
            }
            
            updateSurveyLogic();
        });

        // Initialize the logic
        updateSurveyLogic();

        // Store initial values for comparison
        $('#acquisition_source').data('previous-value', $('#acquisition_source').val());
        
        /**
         * Form validation
         */
        function validateConditionalFields() {
            var source = $('#acquisition_source').val();
            var talked = $('#talked_to_provider').val();
            var referred = $('#provider_referred').val();
            
            // If provider details section should be visible, check for required fields
            if ((source === 'Healthcare Provider') || 
                (source && talked === 'Yes' && referred === 'Yes')) {
                
                var clinicianName = $('#clinician_name').val();
                var clinicState = $('#clinic_state').val();
                var clinicName = $('#clinic_name').val();
                
                // Add subtle visual indicators for empty fields
                $('#clinician_name, #clinic_state, #clinic_name').each(function() {
                    var $field = $(this);
                    if (!$field.val()) {
                        $field.addClass('incomplete-field');
                    } else {
                        $field.removeClass('incomplete-field');
                    }
                });
            }
        }

        // Validate fields when they change
        $('#clinician_name, #clinic_state, #clinic_name').on('blur', validateConditionalFields);

        /**
         * Smooth animations and UX improvements
         */
        
        // Add subtle loading state when form is submitted
        $('form.checkout').on('submit', function() {
            $('#hubspot_sync_milli_fields').addClass('submitting');
        });

        // Prevent accidental form submission if conditional logic is still processing
        var isUpdating = false;
        
        $('#acquisition_source, #talked_to_provider, #provider_referred').on('change', function() {
            isUpdating = true;
            setTimeout(function() {
                isUpdating = false;
            }, 350); // Slightly longer than animation time
        });

        // Focus management for better accessibility
        $('#acquisition_source').on('change', function() {
            var source = $(this).val();
            
            setTimeout(function() {
                if (source === 'Healthcare Provider') {
                    $('#clinician_name').focus();
                } else if (source && source !== '') {
                    $('#talked_to_provider').focus();
                }
            }, 350);
        });

        $('#talked_to_provider').on('change', function() {
            var talked = $(this).val();
            
            if (talked === 'Yes') {
                setTimeout(function() {
                    $('#provider_referred').focus();
                }, 350);
            }
        });

        $('#provider_referred').on('change', function() {
            var referred = $(this).val();
            
            if (referred === 'Yes') {
                setTimeout(function() {
                    $('#clinician_name').focus();
                }, 350);
            }
        });
    });

})(jQuery);