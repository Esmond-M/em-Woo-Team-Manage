/**
 * teamSubordinateImport.js
 *
 * Handles AJAX form submissions for subordinate user import.
 * - Submits the subordinate import form via AJAX, including CSV file upload
 * - Shows a loading spinner during requests
 * - Validates CSV file size (max 5MB)
 * - Displays results and removes forms after completion
 * - Handles connection errors gracefully
 * - Re-binds form handlers after AJAX loads new forms
 */
jQuery(document).ready(function($) {

    // AJAX handler for subordinate import form (team leader)
    $("#subordinate-import-form").submit(function(event) {
        event.preventDefault();
        var $form = $(this);
        $form.find("input[type='submit']").prop("disabled", true);
        $form.append('<div class="user-import-ajax-loader"></div>');
        var fd = new FormData($form[0]);
        fd.append("csvUpload", $("#csvUpload")[0].files[0]);
        fd.append("action", 'user_import_submission');
        $.ajax({
            type: "POST",
            url: user_import_submission.ajaxurl,
            dataType: "html",
            data: fd,
            processData: false,
            contentType: false,
            success: function(data) {
                $(".user-import-ajax-loader").remove();
                $form.after(data);
                $form.remove();
                $(".instructional-container").remove();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $(".user-import-ajax-loader").remove();
                $form.after('<div id="em-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }
        });
    });

    // File size validation for CSV upload (max 5MB)
    $(document).on("change", "#csvUpload", function() {
        if (this.files[0] && this.files[0].size > 5242880) {
            alert("File is too big!");
            this.value = "";
        }
    });

    // Re-bind AJAX handler after new forms are loaded via AJAX
    $(document).on("ajaxComplete", function() {
        if ($("#subordinate-import-form").length === 1) {
            $("#subordinate-import-form").off("submit").on("submit", function(event) {
                event.preventDefault();
                var $form = $(this);
                $form.find("input[type='submit']").prop("disabled", true);
                $form.append('<div class="user-import-ajax-loader"></div>');
                var fd = new FormData($form[0]);
                fd.append("csvUpload", $("#csvUpload")[0].files[0]);
                fd.append("action", 'user_import_submission');
                $.ajax({
                    type: "POST",
                    url: user_import_submission.ajaxurl,
                    dataType: "html",
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        $(".user-import-ajax-loader").remove();
                        $form.after(data);
                        $form.remove();
                        $(".instructional-container").remove();
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $(".user-import-ajax-loader").remove();
                        $form.after('<div id="em-connect-error">Connection Error</div>');
                        console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                    }
                });
            });
        }
    });

});
