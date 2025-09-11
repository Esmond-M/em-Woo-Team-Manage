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
    handleFormAjax("#emulate-team-leader-form", team_Leader_Form_Submission.ajaxurl, ".emulation-form");

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


});