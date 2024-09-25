
jQuery(document).ready(function($) {
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
            success: function(data,responseText){ 
                var jQuerydata = jQuery(data);
                jQuery(".user-import-ajax-loader").remove();
                jQuery('#team-leader-form').after(jQuerydata); 
                jQuery("#team-leader-form").remove(); 

          },
            error: function(jqXHR, textStatus, errorThrown) {
                jQuery(".user-import-ajax-loader").remove();
                jQuery("#team-leader-form").append('<div id="em-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }

        });
      
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
                jQuery(".user-import-ajax-loader").remove();
                jQuery('.emulation-form').after(jQuerydata); // initial loader icon for all users request
                jQuery(".emulation-form").remove();
          },
            error: function(jqXHR, textStatus, errorThrown) {
                jQuery(".user-import-ajax-loader").remove();
                jQuery("#emulate-team-leader-form").append('<div id="em-connect-error">Connection Error</div>');
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }

        });// ajax end
      
});
    // For emulation. Element will not be intitially there for site admin so check after ajax request for user to emulate.
    jQuery( document ).on( "ajaxComplete", function() {
        console.log(jQuery('#team-leader-form').length );
        if(jQuery('#team-leader-form').length == 1){
            jQuery( "#team-leader-form" ).submit(function( event ) {
                event.preventDefault();
                jQuery("#team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
                jQuery('#team-leader-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all users request
            
                // Serialize the data in the form
                var serializedData = jQuery('#team-leader-form' ).serialize()
                    jQuery.ajax({
                        type: "POST",
                        //datatype:'html';
                        url: team_Leader_Form_Submission.ajaxurl,
                        data: serializedData,
                        success: function(data,responseText){ 
                            var jQuerydata = jQuery(data);
                            jQuery(".user-import-ajax-loader").remove(); 
                            jQuery(' #team-leader-form').after(jQuerydata); // initial loader icon for all users request
                            jQuery(" #team-leader-form").remove();    
                    },
                        error: function(jqXHR, textStatus, errorThrown) {
                            jQuery(".user-import-ajax-loader").remove();
                            jQuery("#team-leader-form").append('<div id="em-connect-error">Connection Error</div>');
                            console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                        }
            
                    });
                
                });

        } // end if statement
 
       } );

 

});