jQuery( "#team-leader-form" ).submit(function( event ) {
    event.preventDefault();
    jQuery("#team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
    jQuery('#team-leader-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request

    // Serialize the data in the form
    var serializedData = jQuery('#team-leader-form' ).serialize()
        jQuery.ajax({
            type: "POST",
            url: team_Leader_Form_Submission.ajaxurl,
            data: serializedData,
            success: function(responseText){ 
               setTimeout( // timeout function to transition from loader icon to content less abruptly
                    function() {
                            jQuery(".user-import-ajax-loader").remove();
                            console.log(serializedData);
                            console.log(responseText );        
                    },
                    0
                );
				setTimeout(function () {
					window.location.href= ''; // the redirect goes here

				},3000); // 5 seconds


          },
            error: function(jqXHR, textStatus, errorThrown) {
                jQuery(".user-import-ajax-loader").remove();
                jQuery("#team-leader-form").append('<div id="em-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }

        });// ajax end
      
});

jQuery( "#emulate-team-leader-form" ).submit(function( event ) {
    event.preventDefault();
    jQuery("#emulate-team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
    jQuery('#emulate-team-leader-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request

    // Serialize the data in the form
    var serializedData = jQuery('#emulate-team-leader-form' ).serialize()
        jQuery.ajax({
            type: "POST",
            url: team_Leader_Form_Submission.ajaxurl,
            data: serializedData,
            success: function(data,responseText){ 
                var jQuerydata = jQuery(data);
               setTimeout( // timeout function to transition from loader icon to content less abruptly
                    function() {
                            jQuery(".user-import-ajax-loader").remove();
                            jQuery('#emulate-team-leader-form').after(jQuerydata); // initial loader icon for all users request
                            jQuery("#emulate-team-leader-form").remove();
                            //console.log(serializedData);    
                           console.log(responseText);      
                    },
                    0
                );

          },
            error: function(jqXHR, textStatus, errorThrown) {
                jQuery(".user-import-ajax-loader").remove();
                jQuery("#emulate-team-leader-form").append('<div id="em-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }

        });// ajax end
      
});