jQuery(document).ready(function($) {
    $('.view-subs-btn').on('click', function() {
        var leaderId = $(this).data('leader-id');
        var $container = $('#sub-details-' + leaderId);
        $container.html('<div class="loader">Loading...</div>');
        $.post(siteAdminTeamLeader.ajaxurl, {
            action: 'get_subordinates',
            leader_id: leaderId
        }, function(response) {
            if (response.success) {
                $container.html(response.data.html);
            } else {
                $container.html('<span class="error">' + response.data.message + '</span>');
            }
        });
    });
});