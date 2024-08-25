jQuery( "#team-leader-form" ).submit(function( event ) {
    event.preventDefault();
    console.log(team_Leader_Form_Submission.ajaxurl) ;
    jQuery("#team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
    jQuery('#team-leader-form').append('<div class="awc-ajax-loader"></div>'); // initial loader icon for all users request

    // Serialize the data in the form
    var serializedData = jQuery('#team-leader-form' ).serialize()
    console.log(serializedData);
        jQuery.ajax({
            type: "POST",
            url: team_Leader_Form_Submission.ajaxurl,
            data: serializedData,
            success: function(responseText){ 
               setTimeout( // timeout function to transition from loader icon to content less abruptly
                    function() {
                            jQuery(".awc-ajax-loader").remove();
                            jQuery('#team-leader-form').append(responseText); // initial loader icon for all users request
                    },
                    0
                );
				setTimeout(function () {
					window.location.href= ''; // the redirect goes here

				},3000); // 5 seconds


          },
            error: function(jqXHR, textStatus, errorThrown) {
                jQuery(".awc-ajax-loader").remove();
                jQuery("#team-leader-form").append('<div id="awc-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }

        });// ajax end
      
});