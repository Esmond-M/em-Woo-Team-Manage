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

});