/**
 * teamLeaderAdmin.js
 *
 * Handles AJAX form submissions for team leader actions in the admin interface.
 * - Submits forms via AJAX for team leader and emulation actions
 * - Shows a loading spinner during requests
 * - Displays results and removes forms after completion
 * - Handles connection errors gracefully
 * - Re-binds form handlers after AJAX loads new forms (for admin emulation)
 */
jQuery(document).ready(function($) {

    function handleFormAjax(formSelector, ajaxurl, afterSelector) {
        $(formSelector).submit(function(event) {
            event.preventDefault();
            $(formSelector + " input[type='submit']").prop("disabled", true);
            $(formSelector).append('<div class="user-import-ajax-loader"></div>');
            var serializedData = $(formSelector).serialize();
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: serializedData,
                success: function(data) {
                    $(".user-import-ajax-loader").remove();
                    var $data = $(data);
                    $(afterSelector).after($data);
                    $(formSelector).remove();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $(".user-import-ajax-loader").remove();
                    $(formSelector).append('<div id="em-connect-error">Connection Error</div>');
                    console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                }
            });
        });
    }

    // Initial bindings
    handleFormAjax("#team-leader-form", team_Leader_Form_Submission.ajaxurl, "#team-leader-form");

    // Re-bind after AJAX loads new forms (for admin emulation)
    $(document).on("ajaxComplete", function() {
        if ($("#team-leader-form").length === 1) {
            handleFormAjax("#team-leader-form", team_Leader_Form_Submission.ajaxurl, "#team-leader-form");
        }
    });

    // Handles modal open/close for editing subordinate profiles
    document.querySelectorAll('.edit-subordinate-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = btn.getAttribute('data-user-id');
            document.getElementById('edit_user_email').value = btn.getAttribute('data-user-email');
            document.getElementById('edit_user_name').value = btn.getAttribute('data-user-name');
            document.getElementById('editSubordinateModal').style.display = 'block';
        });
    });
    var closeBtn = document.getElementById('closeEditModal');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            document.getElementById('editSubordinateModal').style.display = 'none';
        });
    }

    // Trigger AJAX handler on modal form submit
    $('#editSubordinateForm').submit(function(event) {
        event.preventDefault();
        var $form = $(this);
        $form.find("input[type='submit']").prop("disabled", true);
        $form.append('<div class="user-import-ajax-loader"></div>');
        var formData = $form.serialize();
        $.ajax({
            type: "POST",
            url: handle_edit_subordinate.ajaxurl, // Make sure this is localized in your PHP
            data: formData,
            dataType: "json",
            success: function(response) {
                $(".user-import-ajax-loader").remove();
                if (response.success) {
                    window.location.reload();
                } else {
                    // Defensive: check if response.data exists
                    var msg = "Error updating user";
                    if (response.data && response.data.message) {
                        msg = response.data.message;
                    }
                    alert(msg);
                    $form.find("input[type='submit']").prop("disabled", false);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $(".user-import-ajax-loader").remove();
                let message = "AJAX error: " + textStatus;
                // Try to get server response text
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    message += "\nServer message: " + jqXHR.responseJSON.data.message;
                } else if (jqXHR.responseText) {
                    message += "\nResponse: " + jqXHR.responseText;
                }
                message += "\nError thrown: " + errorThrown;
                alert(message);
                $form.find("input[type='submit']").prop("disabled", false);
                // Optionally log full jqXHR for debugging
                console.log("Full jqXHR:", jqXHR);
            }
        });
    });

});