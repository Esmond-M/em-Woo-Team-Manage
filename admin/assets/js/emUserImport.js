jQuery(document).ready(function ($) {    
    
    jQuery( "#em-user-import-form" ).submit(function( event ) {
        event.preventDefault();
        jQuery("#em-user-import-form .em-sv-btn").prop( "disabled", true ); // disable all form buttons
        jQuery("#em-user-import-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
        jQuery('#em-user-import-form').append('<div class="awc-ajax-loader"></div>'); // initial loader icon for all users request
    
        // Serialize the data in the form
        var serializedData = jQuery('#em-user-import-form').serialize()
        //console.log(serializedData);
            jQuery.ajax({
                type: "POST",
                url: ajax_EM_User_Import_Submission.ajaxurl_EM_User_Import_Submission,
                dataType: "html",
                data: serializedData,
                success: function(responseText){ 
                   setTimeout( // timeout function to transition from loader icon to content less abruptly
                        function() {
                                jQuery(".awc-ajax-loader").remove();
                                jQuery('#em-user-import-form').append(responseText); // initial loader icon for all users request
                                console.log(serializedData);
                        },
                        1000
                    );/*
                    setTimeout(function () {
                        window.location.href= 'https://site.test/em-site/wp-admin/options-general.php?page=user-import-controls'; // the redirect goes here
    
                    },3000); // 5 seconds*/
    
    
              },
                error: function(jqXHR, textStatus, errorThrown) {
                    jQuery(".awc-ajax-loader").remove();
                    jQuery("#em-user-import-form .em-sv-btn").prop( "disabled", false ); // enable all form buttons
                    jQuery("#em-user-import-form input[type='submit']").prop( "disabled", false ); // enable all form buttons
                    jQuery("#em-user-import-form").append('<div id="awc-connect-error">Connection Error</div>');
    
                    console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                }
    
            });// ajax end
    });
    
    });