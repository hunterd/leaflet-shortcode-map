jQuery(function($){

    // --- Helper Functions ---
    function initializeColorPicker($element) {
        if ($element.length && $.fn.wpColorPicker) {
            $element.wpColorPicker();
        }
    }

    function updateIconPreview($markerRow) {
        var iconClass = $markerRow.find('select[name$="[icon]"]').val();
        var color = $markerRow.find('.lsm-color-field').val();
        $markerRow.find('.lsm-icon-preview')
            .attr('class', 'lsm-icon-preview dashicons ' + iconClass) // Ensure base classes are present
            .css('color', color);
    }

    function resetMarkerFields($markerRow, defaultColor = '#d00', defaultIcon = 'dashicons-location') {
        $markerRow.find('input[type="text"], input[type="hidden"], textarea').not('.lsm-color-field').val('');
        $markerRow.find('select[name$="[icon]"]').val(defaultIcon); // Set default icon
        $markerRow.find('.lsm-color-field').wpColorPicker('color', defaultColor); // Reset color picker
        $markerRow.find('.lsm-custom-icon-id').val('');
        $markerRow.find('.lsm-custom-icon-preview').attr('src', '').hide();
        $markerRow.find('.lsm-remove-custom-icon').hide();
        updateIconPreview($markerRow); // Update preview after reset
    }

    // --- Event Handlers ---

    // Geocoder (for center and individual markers)
    $(document).on('click', '.lsm-geocode', function(){
        var $btn = $(this);
        var $row = $btn.closest('.lsm-centre, .lsm-marker'); // Find parent container
        var $addrInput = $row.find('.lsm-addr, #lsm_center_addr'); // Address input field
        var $latInput = $row.find('input[name$="[lat]"], #lsm_center_lat'); // Latitude input
        var $lonInput = $row.find('input[name$="[lon]"], #lsm_center_lon'); // Longitude input
        var address = $addrInput.val().trim();

        if (!address) {
            $addrInput.focus(); // Focus empty field
            return;
        }

        $btn.prop('disabled', true).text(lsm_params.i18n.geocoding); // Use localized string

        $.post(lsm_params.ajax_url, {
            action: 'lsm_geocode',
            nonce: lsm_params.nonce,
            addr: address
        })
        .done(function(res){
            if (res.success) {
                $latInput.val(res.data.lat);
                $lonInput.val(res.data.lon);
            } else {
                alert(lsm_params.i18n.geocode_fail + ' ' + (res.data.message || 'Unknown error')); // Use localized string
            }
        })
        .fail(function(){
            alert(lsm_params.i18n.geocode_fail + ' Request failed.'); // Generic fail message
        })
        .always(function(){
            $btn.prop('disabled', false).text(lsm_params.i18n.geocode); // Use localized string
        });
    });

    // Remove Marker
    $('#lsm_markers_list').on('click', '.lsm-remove-marker', function(){ // Delegated to container
        var $markerRow = $(this).closest('.lsm-marker');
        var $container = $('#lsm_markers_list');

        if ($container.find('.lsm-marker').length > 1) {
            // Add confirmation before removing
            if (confirm(lsm_params.i18n.confirm_remove)) {
                 // Clean up color picker instance before removing element
                 var $colorPicker = $markerRow.find('.lsm-color-field');
                 if ($colorPicker.length && $colorPicker.data('wpWpColorPicker')) {
                     $colorPicker.wpColorPicker('close'); // Close it if open
                 }
                $markerRow.remove();
                // Optional: Renumber markers if needed (usually not necessary as PHP handles array keys on save)
            }
        } else {
            // If it's the last marker, clear its fields instead of removing
            alert(lsm_params.i18n.last_marker_cleared); // Use localized string
            resetMarkerFields($markerRow);
        }
    });

    // Add Marker
    $('#lsm_add_marker').on('click', function(e){
        e.preventDefault();
        var $container = $('#lsm_markers_list');
        var $lastMarker = $container.find('.lsm-marker').last();
        var newIndex = 0;

        if ($lastMarker.length) {
            newIndex = parseInt($lastMarker.data('index'), 10) + 1;
        } else {
            // If no markers exist, we need a template. Let's assume PHP provides at least one empty one.
            // If not, this needs a hidden template row to clone.
             console.error("Cannot add marker: No existing marker found to clone.");
             alert(lsm_params.i18n.error_cannot_clone); // Use localized string
             return;
        }

        // Clone the last marker
        var $newMarker = $lastMarker.clone();

        // Update index and reset fields
        $newMarker.attr('data-index', newIndex);
        $newMarker.find('input, select, textarea').each(function(){
            var $input = $(this);
            var name = $input.attr('name');
            if (name) {
                // Update the index in the name attribute, e.g., lsm_markers[0][name] -> lsm_markers[1][name]
                $input.attr('name', name.replace(/\[\d+\]/, '[' + newIndex + ']'));
            }
            // Clear value/state, except for color which we handle separately
             if (!$input.hasClass('lsm-color-field')) {
                 if ($input.is(':checkbox') || $input.is(':radio')) {
                     $input.prop('checked', false);
                 } else if ($input.is('select') && $input.attr('name') && $input.attr('name').endsWith('[icon]')) {
                     // Set default icon (e.g., location pin)
                     $input.val('dashicons-location');
                 } else if (!$input.hasClass('lsm-custom-icon-id')) { // Don't clear hidden ID initially
                     $input.val('');
                 }
             }
        });

        // --- Special handling for Color Picker ---
        var $colorPickerInput = $newMarker.find('.lsm-color-field');
        var $colorPickerContainer = $colorPickerInput.closest('.wp-picker-container');
        if ($colorPickerContainer.length) {
            // Remove the cloned color picker instance elements
            $colorPickerContainer.replaceWith($colorPickerInput.clone().val('#d00')); // Replace container with a clean input, set default color
        } else {
             // Fallback if container wasn't found (shouldn't happen)
             $colorPickerInput.val('#d00');
        }
        // --- End Color Picker Handling ---

        // Reset custom icon fields
        $newMarker.find('.lsm-custom-icon-id').val('');
        $newMarker.find('.lsm-custom-icon-preview').attr('src', '').hide();
        $newMarker.find('.lsm-remove-custom-icon').hide();

        // Append the new marker row
        $container.append($newMarker);

        // Initialize the color picker on the *newly added* input
        initializeColorPicker($newMarker.find('.lsm-color-field'));

        // Update the icon preview for the new row
        updateIconPreview($newMarker);

        // Focus the name field of the new marker
        $newMarker.find('input[name$="[name]"]').focus();
    });

    // Update Icon Preview on change
    $('#lsm_markers_list').on('change', 'select[name$="[icon]"], .lsm-color-field', function(){
        var $markerRow = $(this).closest('.lsm-marker');
        updateIconPreview($markerRow);
    });
     // Also update preview when color picker color changes (using 'change' event)
     $('#lsm_markers_list').on('change', '.lsm-color-field', function() {
         var $markerRow = $(this).closest('.lsm-marker');
         updateIconPreview($markerRow);
     });


    // Bulk Import Markers
    $('#lsm_import_bulk').on('click', function(){
        var bulkText = $('#lsm_bulk_markers').val().trim();
        if (!bulkText) return;

        // Confirmation
        if (!confirm(lsm_params.i18n.bulk_import_confirm)) {
            return;
        }

        var lines = bulkText.split(/\r?\n/);
        var $container = $('#lsm_markers_list');
        var $existingMarkers = $container.find('.lsm-marker');
        var markerCount = lines.length;

        // Remove existing markers before adding new ones
        $existingMarkers.each(function() {
             var $colorPicker = $(this).find('.lsm-color-field');
             if ($colorPicker.length && $colorPicker.data('wpWpColorPicker')) {
                 $colorPicker.wpColorPicker('close');
             }
             $(this).remove();
        });


        if (markerCount === 0) {
            // If import text was empty or just whitespace, add one empty marker
            $('#lsm_add_marker').trigger('click'); // Simulate click to add one
            return;
        }

        // Add new markers based on import data
        lines.forEach(function(line, index){
            var parts = line.split('|').map(function(part) { return part.trim(); }); // Trim each part

            // Trigger add marker for each line (it clones the last one, or creates the first)
             if (index > 0 || $container.find('.lsm-marker').length === 0) { // Add new row for index > 0 or if container is empty
                $('#lsm_add_marker').trigger('click');
             }

            var $currentRow = $container.find('.lsm-marker').last(); // Get the newly added or first row

            if ($currentRow.length) {
                // Populate fields (indices match the specified format)
                $currentRow.find('input[name$="[name]"]').val(parts[0] || '');
                $currentRow.find('input[name$="[subtitle]"]').val(parts[1] || '');
                $currentRow.find('input.lsm-addr').val(parts[2] || '');
                $currentRow.find('input[name$="[lat]"]').val(parts[3] || '');
                $currentRow.find('input[name$="[lon]"]').val(parts[4] || '');
                $currentRow.find('select[name$="[icon]"]').val(parts[5] || ''); // Set icon class
                $currentRow.find('.lsm-color-field').wpColorPicker('color', parts[6] || '#d00'); // Set color

                // Custom icon ID and URL are not part of basic bulk import, clear them
                $currentRow.find('.lsm-custom-icon-id').val('');
                $currentRow.find('.lsm-custom-icon-preview').attr('src', '').hide();
                $currentRow.find('.lsm-remove-custom-icon').hide();

                updateIconPreview($currentRow); // Update preview
            }
        });
         $('#lsm_bulk_markers').val(''); // Clear textarea after import
    });

    // --- Global Icon/Color ---

    function updateGlobalPreview(){
        var iconClass = $('#lsm_global_icon').val();
        var color = $('#lsm_global_color').val();
        $('#lsm_global_preview')
            .attr('class', 'dashicons ' + iconClass)
            .css('color', color);
    }

    // Initialize Global Color Picker
    initializeColorPicker($('#lsm_global_color').on('change', updateGlobalPreview)); // Update on change
    $('#lsm_global_icon').on('change', updateGlobalPreview);
    updateGlobalPreview(); // Initial call

    // Apply Global Settings to All Markers
    $('#lsm_apply_all').on('click', function(){
        var globalIcon = $('#lsm_global_icon').val();
        var globalColor = $('#lsm_global_color').val();
        var globalCustomIconId = $('#lsm_global_custom_icon_id').val();
        var globalCustomIconUrl = $('#lsm_global_custom_icon_preview').attr('src');

        $('#lsm_markers_list .lsm-marker').each(function(){
            var $row = $(this);

            if (globalCustomIconId && globalCustomIconUrl) {
                // Apply global custom icon
                $row.find('.lsm-custom-icon-id').val(globalCustomIconId);
                $row.find('.lsm-custom-icon-preview').attr('src', globalCustomIconUrl).show();
                 $row.find('.lsm-remove-custom-icon').show();
                // Optionally clear Dashicon/color or keep them as fallback? Current logic prioritizes custom.
                // $row.find('select[name$="[icon]"]').val('');
                // $row.find('.lsm-color-field').wpColorPicker('color', '#ffffff'); // Example: clear color
                // updateIconPreview($row);
            } else {
                // Apply global Dashicon and color
                $row.find('select[name$="[icon]"]').val(globalIcon);
                $row.find('.lsm-color-field').wpColorPicker('color', globalColor);

                // Clear custom icon if global custom is not set
                $row.find('.lsm-custom-icon-id').val('');
                $row.find('.lsm-custom-icon-preview').attr('src', '').hide();
                $row.find('.lsm-remove-custom-icon').hide();

                updateIconPreview($row); // Update preview
            }
        });
    });

    // --- Custom Icon Upload ---

    var mediaFrame; // Reuse frame instance

    function setupMediaFrame(options) {
        // If the media frame already exists, reopen it.
        if (mediaFrame) {
            mediaFrame.options.lsm_update_target = options.updateTarget; // Update target callback
            mediaFrame.open();
            return;
        }

        // Create the media frame.
        mediaFrame = wp.media({
            title: options.title, // Use localized title
            button: { text: options.buttonText }, // Use localized button text
            library: { type: 'image' },
            multiple: false
        });

         // Store the update target callback in the frame's options
         mediaFrame.options.lsm_update_target = options.updateTarget;

        // When an image is selected, run the callback.
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
             // Call the stored update target function
             if (mediaFrame.options.lsm_update_target) {
                 mediaFrame.options.lsm_update_target(attachment);
             }
        });

        mediaFrame.open();
    }

    // Handle Individual Marker Icon Upload
    $('#lsm_markers_list').on('click', '.lsm-upload-icon', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $row = $button.closest('.lsm-marker');
        var $idInput = $row.find('.lsm-custom-icon-id');
        var $preview = $row.find('.lsm-custom-icon-preview');
        var $removeBtn = $row.find('.lsm-remove-custom-icon');

        setupMediaFrame({
            title: lsm_params.i18n.choose_icon,
            buttonText: lsm_params.i18n.use_image,
            updateTarget: function(attachment) {
                $idInput.val(attachment.id);
                // Use thumbnail or medium size for preview if available
                var previewUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                $preview.attr('src', previewUrl).show();
                $removeBtn.show();
            }
        });
    });

     // Handle Remove Individual Marker Icon
     $('#lsm_markers_list').on('click', '.lsm-remove-custom-icon', function(e) {
         e.preventDefault();
         var $button = $(this);
         var $row = $button.closest('.lsm-marker');
         $row.find('.lsm-custom-icon-id').val('');
         $row.find('.lsm-custom-icon-preview').attr('src', '').hide();
         $button.hide();
     });

    // Handle Global Icon Upload
    $('#lsm_upload_global_icon').on('click', function(e) {
        e.preventDefault();
        var $idInput = $('#lsm_global_custom_icon_id');
        var $preview = $('#lsm_global_custom_icon_preview');
        // var $removeBtn = $('#lsm_remove_global_custom_icon'); // Assuming you add a remove button for global

        setupMediaFrame({
            title: lsm_params.i18n.choose_global_icon,
            buttonText: lsm_params.i18n.use_image,
            updateTarget: function(attachment) {
                $idInput.val(attachment.id);
                var previewUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                $preview.attr('src', previewUrl).show();
                // $removeBtn.show(); // Show remove button if added
            }
        });
    });

     // Add handler for removing global icon if you add a button for it
     /*
     $('#lsm_remove_global_custom_icon').on('click', function(e) {
         e.preventDefault();
         $('#lsm_global_custom_icon_id').val('');
         $('#lsm_global_custom_icon_preview').attr('src', '').hide();
         $(this).hide();
     });
     */

    // --- Initialization ---

    // Initialize existing color pickers on page load
    $('.lsm-color-field').each(function(){
        initializeColorPicker($(this));
    });

    // Ensure at least one marker row exists on load (if PHP didn't provide one)
    if ($('#lsm_markers_list .lsm-marker').length === 0) {
        $('#lsm_add_marker').trigger('click');
    }

});