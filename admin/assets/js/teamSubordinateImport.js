jQuery(document).ready(function($) {

    // Handle team leader emulation form submission via AJAX
    jQuery("#emulate-team-leader-form").submit(function(event) {
        event.preventDefault();
        // Disable submit button and show loader
        jQuery("#emulate-team-leader-form input[type='submit']").prop("disabled", true);
        jQuery('#emulate-team-leader-form').append('<div class="user-import-ajax-loader"></div>');

        // Serialize form data and send AJAX request
        var serializedData = jQuery('#emulate-team-leader-form').serialize();
        jQuery.ajax({
            type: "POST",
            dataType: "html",
            url: emulate_Team_subordinate_Form_Submission.ajaxurl,
            data: serializedData,
            success: function(data, responseText) {
                // Remove loader, show response, remove form
                var jQuerydata = jQuery(data);
                jQuery(".user-import-ajax-loader").remove();
                jQuery('.emulation-form').after(jQuerydata);
                jQuery(".emulation-form").remove();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Remove loader, show error
                jQuery(".user-import-ajax-loader").remove();
                jQuery("#emulate-team-leader-form").append(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }
        });
    });

    // Handle subordinate import form submission via AJAX
    jQuery("#subordinate-import-form").submit(function(event) {
        event.preventDefault();
        // Disable submit button and show loader
        jQuery("#subordinate-import-form input[type='submit']").prop("disabled", true);
        jQuery('#subordinate-import-form').append('<div class="user-import-ajax-loader"></div>');

        // Prepare FormData for file upload and send AJAX request
        var fd = new FormData(jQuery('#subordinate-import-form')[0]);
        fd.append("csvUpload", jQuery('#csvUpload')[0].files[0]);
        fd.append("action", 'user_import_submission'); // Ensure action is set

        jQuery.ajax({
            type: "POST",
            url: user_import_submission.ajaxurl, // Use localized variable
            dataType: "html",
            data: fd,
            processData: false,
            contentType: false,
            success: function(data, responseText,jqXHR, textStatus, errorThrown) {
                // Remove loader, show response, remove form and instructions
                var jQuerydata = jQuery(data);
                jQuery(".user-import-ajax-loader").remove();
                jQuery('#subordinate-import-form').after(jQuerydata);
                jQuery("#subordinate-import-form").remove();
                jQuery(".instructional-container").remove();
                                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            },
            error: function(data, jqXHR, textStatus, errorThrown) {
                // Remove loader, show error
                jQuery(".user-import-ajax-loader").remove();
                jQuery('#subordinate-import-form').after(data);
                jQuery("#subordinate-import-form").append('<div id="em-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }
        });
    });

    // Re-bind handlers after AJAX loads new forms (for admin emulation)
    jQuery(document).on("ajaxComplete", function() {
        console.log(jQuery('#subordinate-import-form').length);
        if (jQuery('#subordinate-import-form').length == 1) {
            // File size validation for CSV upload (max 5MB)
            var uploadField = document.getElementById("csvUpload");
            uploadField.onchange = function() {
                if (this.files[0].size > 5242880) {
                    alert("File is too big!");
                    this.value = "";
                }
            };
            // Re-bind subordinate import form AJAX submission
            jQuery("#subordinate-import-form").submit(function(event) {
                event.preventDefault();
                jQuery("#subordinate-import-form input[type='submit']").prop("disabled", true);
                jQuery('#subordinate-import-form').append('<div class="user-import-ajax-loader"></div>');

                var fd = new FormData(jQuery('#subordinate-import-form')[0]);
                fd.append("csvUpload", jQuery('#csvUpload')[0].files[0]);
                fd.append("action", 'user_import_submission');
                jQuery.ajax({
                    type: "POST",
                    url: ajaxurl,
                    dataType: "html",
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(data, responseText) {
                        var jQuerydata = jQuery(data);
                        jQuery(".user-import-ajax-loader").remove();
                        jQuery('#subordinate-import-form').after(jQuerydata);
                        jQuery("#subordinate-import-form").remove();
                        jQuery(".instructional-container").remove();
                    },
                    error: function(data, jqXHR, textStatus, errorThrown) {
                        jQuery(".user-import-ajax-loader").remove();
                        jQuery('#subordinate-import-form').after(data);
                        jQuery("#subordinate-import-form").append('<div id="em-connect-error">Connection Error</div>');
                        console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                    }
                }); // ajax end
            });
        } // end if statement
    });
});
