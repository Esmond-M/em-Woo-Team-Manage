jQuery(document).ready(function ($) {    
    
    jQuery( "#em-user-import-form" ).submit(function( event ) {
        event.preventDefault();
        jQuery("#em-user-import-form .em-sv-btn").prop( "disabled", true ); // disable all form buttons
        jQuery("#em-user-import-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
        jQuery('#em-user-import-form').append('<div class="awc-ajax-loader"></div>'); // initial loader icon for all users request
     
    });
    
    });