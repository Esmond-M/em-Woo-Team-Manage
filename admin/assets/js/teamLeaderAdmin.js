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

    function setSubordinateEditMessage($row, message, isError) {
        var $message = $row.find('.subordinate-edit-message');
        $message.text(message).prop('hidden', message === '').toggleClass('is-error', isError);
    }

    function resetSubordinateRowFields($row) {
        $row.find('.subordinate-email-input').val($row.find('.subordinate-email-value').text());
        $row.find('.subordinate-name-input').val($row.find('.subordinate-name-value').text());
    }

    function setSubordinateRowEditing($row, isEditing) {
        $row.toggleClass('is-editing', isEditing);
        $row.find('.subordinate-value').prop('hidden', isEditing);
        $row.find('.subordinate-edit-field').prop('hidden', !isEditing).prop('disabled', !isEditing);
        $row.find('.edit-subordinate-btn').prop('hidden', isEditing);
        $row.find('.save-subordinate-btn, .cancel-subordinate-edit-btn').prop('hidden', !isEditing);
        setSubordinateEditMessage($row, '', false);

        if (isEditing) {
            $row.find('.subordinate-email-input').trigger('focus').trigger('select');
        }
    }

    $(document).on('click', '.edit-subordinate-btn', function() {
        var $row = $(this).closest('.subordinate-row');
        resetSubordinateRowFields($row);
        setSubordinateRowEditing($row, true);
    });

    $(document).on('click', '.cancel-subordinate-edit-btn', function() {
        var $row = $(this).closest('.subordinate-row');
        resetSubordinateRowFields($row);
        setSubordinateRowEditing($row, false);
    });

    $(document).on('keydown', '.subordinate-edit-field', function(event) {
        var $row = $(this).closest('.subordinate-row');

        if (event.key === 'Enter') {
            event.preventDefault();
            $row.find('.save-subordinate-btn').trigger('click');
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            $row.find('.cancel-subordinate-edit-btn').trigger('click');
        }
    });

    $(document).on('click', '.save-subordinate-btn', function() {
        var $row = $(this).closest('.subordinate-row');
        var $table = $('#team-subordinates-table');
        var emailInput = $row.find('.subordinate-email-input').get(0);
        var nameInput = $row.find('.subordinate-name-input').get(0);

        if ((emailInput && !emailInput.reportValidity()) || (nameInput && !nameInput.reportValidity())) {
            return;
        }

        $row.find('.save-subordinate-btn, .cancel-subordinate-edit-btn').prop('disabled', true);
        setSubordinateEditMessage($row, 'Saving...', false);

        $.ajax({
            type: 'POST',
            url: handle_edit_subordinate.ajaxurl,
            data: {
                action: 'edit_subordinate',
                edit_user_id: $row.data('user-id'),
                leaderID: $table.data('leader-id'),
                edit_subordinate_nonce: $table.data('edit-nonce'),
                edit_user_email: $row.find('.subordinate-email-input').val(),
                edit_user_name: $row.find('.subordinate-name-input').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $row.find('.subordinate-email-value').text($row.find('.subordinate-email-input').val());
                    $row.find('.subordinate-name-value').text($row.find('.subordinate-name-input').val());
                    setSubordinateRowEditing($row, false);
                } else {
                    var msg = 'Error updating user';
                    if (response.data && response.data.message) {
                        msg = response.data.message;
                    }
                    setSubordinateEditMessage($row, msg, true);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                var message = 'AJAX error: ' + textStatus;
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    message += ': ' + jqXHR.responseJSON.data.message;
                } else if (jqXHR.responseText) {
                    message += ': ' + jqXHR.responseText.replace(/<[^>]*>/g, '').trim();
                }
                setSubordinateEditMessage($row, message, true);
                console.log('Subordinate edit failed:', errorThrown, jqXHR);
            },
            complete: function() {
                $row.find('.save-subordinate-btn, .cancel-subordinate-edit-btn').prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.emwtm-delete-user-btn', function(event) {
        event.preventDefault();
        var $button = $(this);
        var userId = $button.data('user-id');

        if (!window.confirm('Permanently delete this account? This cannot be undone.')) {
            return;
        }

        $button.prop('disabled', true).text('Deleting...');
        $.post(delete_user_account.ajaxurl, {
            action: 'emwtm_delete_user_account',
            _nonce: delete_user_account.nonce,
            user_id: userId,
            confirm_deletion: '1'
        }).done(function() {
            window.location.reload();
        }).fail(function(jqXHR) {
            var message = 'Could not delete the account.';
            if (jqXHR.responseText) {
                message += '\n' + jqXHR.responseText.replace(/<[^>]*>/g, '').trim();
            }
            window.alert(message);
            $button.prop('disabled', false).text('Permanently Delete Account');
        });
    });

});