jQuery( "#emulate-team-leader-form" ).submit(function( event ) {
    event.preventDefault();
    jQuery("#emulate-team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
    jQuery('#emulate-team-leader-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request

    // Serialize the data in the form
    var serializedData = jQuery('#emulate-team-leader-form' ).serialize()
    console.log(serializedData);
    console.log(    emulate_Team_subordinate_Form_Submission.ajaxurl);

        jQuery.ajax({
            type: "POST",
            dataType: "html",            
            url: emulate_Team_subordinate_Form_Submission.ajaxurl,
            data: serializedData,
            success: function(data,responseText){ 
                var jQuerydata = jQuery(data);
               setTimeout( // timeout function to transition from loader icon to content less abruptly
                    function() {
                            jQuery(".user-import-ajax-loader").remove();
                            jQuery('#emulate-team-leader-form').after(jQuerydata); // initial loader icon for all users request
                            jQuery("#emulate-team-leader-form").remove();
                            //console.log(serializedData);    
                            // console.log(responseText);      
                    },
                    0
                );

          },
            error: function(jqXHR, textStatus, errorThrown) {
                jQuery(".user-import-ajax-loader").remove();
                jQuery("#emulate-team-leader-form").append(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
            }

        });// ajax end
      
});

jQuery( "#em-user-import-form" ).submit(function( event ) {
    event.preventDefault();
    jQuery("#em-user-import-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
    jQuery('#em-user-import-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request

        var fd = new FormData(jQuery('#em-user-import-form')[0]);
        fd.append( "csvUpload", jQuery('#csvUpload')[0].files[0]);
        fd.append( "action", 'user_import_submission');  
               jQuery.ajax({
                   type: "POST",
                   url:ajaxurl,   
                   dataType: "html",       
                   data: fd,
                   processData: false, // important
                   contentType: false, // important  
                   success: function(data,responseText){ 
                    var jQuerydata = jQuery(data);
                    jQuery(".user-import-ajax-loader").remove();
                    jQuery('#em-user-import-form').after(jQuerydata); // initial loader icon for all users request
                    jQuery("#em-user-import-form").remove();
                    //console.log(serializedData);    
                   //console.log(data);      
       
                 },
                   error: function(data,jqXHR, textStatus, errorThrown) {
                       jQuery(".user-import-ajax-loader").remove();
                       jQuery('#em-user-import-form').after(data); // initial loader icon for all users request
                       jQuery("#em-user-import-form").append('<div id="em-connect-error">Connection Error</div>');
                       console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                   }
       
               });// ajax end
      
});