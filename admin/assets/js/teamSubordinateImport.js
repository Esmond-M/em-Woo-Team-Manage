
jQuery(document).ready(function($) {
 
    jQuery( "#emulate-team-leader-form" ).submit(function( event ) {
        event.preventDefault();
        jQuery("#emulate-team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
        jQuery('#emulate-team-leader-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request
    
        // Serialize the data in the form
        var serializedData = jQuery('#emulate-team-leader-form' ).serialize()    
            jQuery.ajax({
                type: "POST",
                dataType: "html",            
                url: emulate_Team_subordinate_Form_Submission.ajaxurl,
                data: serializedData,
                success: function(data,responseText){ 
                    var jQuerydata = jQuery(data);
                    jQuery(".user-import-ajax-loader").remove();
                    jQuery('.emulation-form').after(jQuerydata);
                    jQuery(".emulation-form").remove();    
              },
                error: function(jqXHR, textStatus, errorThrown) {
                    jQuery(".user-import-ajax-loader").remove();
                    jQuery("#emulate-team-leader-form").append(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                    console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                }
    
            });
          
    });

    jQuery( "#subordinate-import-form" ).submit(function( event ) {
        event.preventDefault();
        jQuery("#subordinate-import-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
        jQuery('#subordinate-import-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request
    
            var fd = new FormData(jQuery('#subordinate-import-form')[0]);
            fd.append( "csvUpload", jQuery('#csvUpload')[0].files[0]);
            fd.append( "action", 'user_import_submission');  
                   jQuery.ajax({
                       type: "POST",
                       url:ajaxurl,   
                       dataType: "html",       
                       data: fd,
                       processData: false, 
                       contentType: false,  
                       success: function(data,responseText){ 
                        var jQuerydata = jQuery(data);
                        jQuery(".user-import-ajax-loader").remove();
                        jQuery('#subordinate-import-form').after(jQuerydata);
                        jQuery("#subordinate-import-form").remove();
                        jQuery(".instructional-container").remove();
           
                     },
                       error: function(data,jqXHR, textStatus, errorThrown) {
                           jQuery(".user-import-ajax-loader").remove();
                           jQuery('#subordinate-import-form').after(data); 
                           jQuery("#subordinate-import-form").append('<div id="em-connect-error">Connection Error</div>');
                           console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                       }
           
                   });
          
    });

    // For emulation. Element will not be intitially there for site admin so check after ajax request.
    jQuery( document ).on( "ajaxComplete", function() {
        console.log(jQuery('#subordinate-import-form').length );
        if(jQuery('#subordinate-import-form').length == 1){
            var uploadField = document.getElementById("csvUpload");
     
            uploadField.onchange = function() {
                if(this.files[0].size > 5242880){
                alert("File is too big!");
                this.value = "";
                };
            };
            jQuery( "#subordinate-import-form" ).submit(function( event ) {
                event.preventDefault();
                jQuery("#subordinate-import-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
                jQuery('#subordinate-import-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all user's request
     
                    var fd = new FormData(jQuery('#subordinate-import-form')[0]);
                    fd.append( "csvUpload", jQuery('#csvUpload')[0].files[0]);
                    fd.append( "action", 'user_import_submission');  
                        jQuery.ajax({
                            type: "POST",
                            url:ajaxurl,   
                            dataType: "html",       
                            data: fd,
                            processData: false,
                            contentType: false,  
                            success: function(data,responseText){ 
                                var jQuerydata = jQuery(data);
                                jQuery(".user-import-ajax-loader").remove();
                                jQuery('#subordinate-import-form').after(jQuerydata); 
                                jQuery("#subordinate-import-form").remove();
                                jQuery(".instructional-container").remove();
                                
                            },
                            error: function(data,jqXHR, textStatus, errorThrown) {
                                jQuery(".user-import-ajax-loader").remove();
                                jQuery('#subordinate-import-form').after(data);
                                jQuery("#subordinate-import-form").append('<div id="em-connect-error">Connection Error</div>');
                                console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                            }
                
                        });// ajax end
                
            }); 

        } // end if statement
 
       } );
   
});
