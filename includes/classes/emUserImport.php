<?php

/**
* Main plugin file.
* PHP version 7.3

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
*/

declare(strict_types=1);
namespace emUserImport;

if (!class_exists('emUserImport')) {
/**
* Declaring class

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
*/

    class emUserImport
{
    //begin class


    /**
    *  Declaring constructor
    */
    public function __construct()
    {
        add_action('init', [$this, 'user_import_inits' ] );
        add_action('admin_menu', [$this, 'user_import_register_submenu_page' ] );
		add_action( 'admin_enqueue_scripts', [$this, 'load_Admin_Styles' ]  );
        add_action('admin_init', [$this, 'profile_field_team_ID_disable' ] );  
        add_action( 'show_user_profile', [$this, 'profile_field_team_ID' ]  );
        add_action( 'edit_user_profile', [$this, 'profile_field_team_ID' ]  );
        add_action( 'personal_options_update', [$this, 'profile_save_team_leader_email' ]  );
        add_action( 'edit_user_profile_update', [$this, 'profile_save_team_leader_email' ]  );  
        add_action('wp_ajax_team_Leader_Form_Submission', [$this, 'team_Leader_Form_Submission' ] );
        add_action('wp_ajax_emulate_Team_Leader_Form_Submission', [$this, 'emulate_Team_Leader_Form_Submission' ] );
        add_action('wp_ajax_emulate_Team_subordinate_Form_Submission', [$this, 'emulate_Team_subordinate_Form_Submission' ] );
        add_action('wp_ajax_user_import_submission', [$this, 'user_import_submission' ] );
        add_action( 'woocommerce_thankyou', [$this, 'create_Team_Leader_After_Payment'], 10, 1 );

    }

    public function create_Team_Leader_After_Payment( $order_id ) {
        // If user is logged in, do nothing because they already have an account
        if( is_user_logged_in() ) return;

        // Get the newly created order
        $order = wc_get_order( $order_id );

        // Get the billing email address
        $order_email = $order->billing_email;

        // Check if there are any users with the billing email as user or email
        $email = email_exists( $order_email );
        $user = username_exists( $order_email );

        // Get the order status (see if the customer has paid)
        $order_status = $order->get_status();

        // Check if the user exists and if the order status is processing or completed (paid)
        if( $user == false && $email == false && $order->has_status( 'processing' ) || $user == false && $email == false && $order->has_status( 'completed' ) ) {
            // Check on category ( multiple categories can be entered, separated by a comma )

            // Random password with 12 chars
            $random_password = wp_generate_password();

            // Firstname
            $first_name = $order->get_billing_first_name();

            // Lastname
            $last_name = $order->get_billing_last_name();

            // Role
            $role = 'team_leader';

            // Create new user with email as username, newly created password and user role
            $user_id = wp_insert_user(
                array(
                    'user_email' => $order_email,
                    'user_login' => $order_email,
                    'user_pass'  => $random_password,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'role'       => $role,
                )
            );
            //$user_id->add_role( 'team_leader' );
            /* (Optional) WC guest customer identification


            $emailto = 'esmondmccain@gmail.com';

            // Email subject, "New {post_type_label}"
            $subject = 'User ID: ' . $user_id . '';
    
            // Email body
            $message = 'It ran';
    
            wp_mail( $emailto, $subject, $message );*/

            //$userInfo = get_user_by('id',$user_id);
            //retrieve_password( $userInfo->user_login );
            wp_new_user_notification($user_id, null , "both");
            update_user_meta( $user_id, 'guest', 'yes' );

            // User's billing data
            update_user_meta( $user_id, 'billing_address_1', $order->billing_address_1 );
            update_user_meta( $user_id, 'billing_address_2', $order->billing_address_2 );
            update_user_meta( $user_id, 'billing_city', $order->billing_city );
            update_user_meta( $user_id, 'billing_company', $order->billing_company );
            update_user_meta( $user_id, 'billing_country', $order->billing_country );
			update_user_meta( $user_id, 'billing_state', $order->billing_state );
            update_user_meta( $user_id, 'billing_email', $order->billing_email );
            update_user_meta( $user_id, 'billing_first_name', $order->billing_first_name );
            update_user_meta( $user_id, 'billing_last_name', $order->billing_last_name );
            update_user_meta( $user_id, 'billing_phone', $order->billing_phone );
            update_user_meta( $user_id, 'billing_postcode', $order->billing_postcode );

            // User's shipping data
            update_user_meta( $user_id, 'shipping_address_1', $order->shipping_address_1 );
            update_user_meta( $user_id, 'shipping_address_2', $order->shipping_address_2 );
            update_user_meta( $user_id, 'shipping_city', $order->shipping_city );
            update_user_meta( $user_id, 'shipping_company', $order->shipping_company );
			update_user_meta( $user_id, 'shipping_state', $order->shipping_state );
            update_user_meta( $user_id, 'shipping_country', $order->shipping_country );
            update_user_meta( $user_id, 'shipping_first_name', $order->shipping_first_name );
            update_user_meta( $user_id, 'shipping_last_name', $order->shipping_last_name );
            update_user_meta( $user_id, 'shipping_method', $order->shipping_method );
            update_user_meta( $user_id, 'shipping_postcode', $order->shipping_postcode );

            // Link past orders to this newly created customer
            wc_update_new_customer_past_orders( $user_id );

        }
    }


    public function load_Admin_Styles(){
        global $pagenow;
        $rand = rand(1, 99999999999);
        if ( 'admin.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'user-import-controls' ) {
            wp_enqueue_style( 'team-leader-user-import-styles',  '/wp-content/plugins/em-user-import/admin/assets/css/team-leader-user-import.css' , array(),  $rand );
            wp_enqueue_script( 'team-leader-subordinate-import-script', '/wp-content/plugins/em-user-import/admin/assets/js/teamSubordinateImport.js', array('jquery'), $rand, true); 
            wp_localize_script('team-leader-subordinate-import-script', 'emulate_Team_subordinate_Form_Submission', array(
                'ajaxurl' => admin_url('admin-ajax.php') ,
                'noposts' => __('No older posts found', 'em-theme') ,
              )); 
            wp_localize_script('team-leader-subordinate-import-script', 'user_import_submission', array(
            'ajaxurl' => admin_url('admin-ajax.php') ,
            'noposts' => __('No older posts found', 'em-theme') ,
            ));
                               
        }
        if ( 'admin.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'site-admin-team-leader-admin' ) {
            wp_enqueue_style( 'team-leader-user-import-styles',  '/wp-content/plugins/em-user-import/admin/assets/css/team-leader-user-import.css' , array(),  $rand );
        }
        if ( 'admin.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'team-leader-admin' ) {
            wp_enqueue_style( 'team-leader-admin-styles',  '/wp-content/plugins/em-user-import/admin/assets/css/team-leader-admin.css' , array(),  $rand );
            wp_enqueue_script( 'team-leader-admin-script', '/wp-content/plugins/em-user-import/admin/assets/js/teamLeaderAdmin.js', array('jquery'), $rand, true); 
            wp_enqueue_script( 'team-leader-admin-script', '/wp-content/plugins/em-user-import/admin/assets/js/teamSubordinateImport.js', array('jquery'), $rand, true); 
            wp_localize_script('team-leader-admin-script', 'team_Leader_Form_Submission', array(
                'ajaxurl' => admin_url('admin-ajax.php') ,
                'noposts' => __('No older posts found', 'em-theme') ,
              ));
            wp_localize_script('team-leader-admin-script', 'emulate_Team_Leader_Form_Submission', array(
            'ajaxurl' => admin_url('admin-ajax.php') ,
            'noposts' => __('No older posts found', 'em-theme') ,
            ));                                      
        }
        if ( 'admin.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'site-admin-team-leader-admin' ) {
            wp_enqueue_style( 'site-admin-team-leader-styles',  '/wp-content/plugins/em-user-import/admin/assets/css/site-admin-team-leader.css' , array(),  $rand );
        }
       
        return;
    }

    public function user_import_submission()
    {
        // It allows create user functions
        require_once(ABSPATH . 'wp-includes/user.php'); 
        
        // WordPress environment
        require_once( ABSPATH . 'wp-load.php' );

        // it allows us to use wp_handle_upload() function
        require_once( ABSPATH . 'wp-admin/includes/file.php' );

        // can add some kind of validation here
        if( empty( $_FILES[ 'csvUpload' ] ) ) {
            wp_die();
        }

        $upload = wp_handle_upload( 
            $_FILES[ 'csvUpload' ], 
            array( 'test_form' => false ) 
        );

        if( ! empty( $upload[ 'error' ] ) ) {
            wp_die( $upload[ 'error' ] );
        }

        // it is time to add our uploaded image into WordPress media library
        $attachment_id = wp_insert_attachment(
          array(
            'guid'           => $upload[ 'url' ],
            'post_mime_type' => $upload[ 'type' ],
            'post_title'     => basename( $upload[ 'file' ] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
          ),
             $upload[ 'file' ]
        );

        if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            wp_die( 'Upload error.' );
        }

        //$csvFile = fopen('Data.csv', 'r'); // location of file
        $csv =   $this->readCSV($upload[ 'url' ] ); 

        $csvLoopCounter = 0;
        $csvCounter = 0;
        $successfullUserCreationCounter = 0;
        foreach ( $csv as $c ) {
      
            if ($csvCounter++ == 0) continue; // skip headers     
            
            $email_address = $c[0];
            $firstName = $c[1];          
            $LastName = $c[2];
            $password= wp_generate_password();
            $user_data = array(
                'user_login'    => $email_address,
                'user_pass'     => $password,
                'user_email'    => $email_address ,
                'first_name'    => $firstName,
                'last_name'     => $LastName,
                'user_url'      => '',
                'description'   => '',
                'role'          => 'team_subordinate'
            );
            
            $user_id = wp_insert_user( $user_data );
            
            if ( is_wp_error( $user_id ) ) {
               
                echo '<p>' . $firstName . ' '.  $LastName .' did not import please check info</p>';
            }
            
            else
            {
                
                 // The user was successfully created
                add_user_meta($user_id, 'teamID', $_POST['teamLeaderID']);
                wp_new_user_notification($user_id, null , "both");
                $successfullUserCreationCounter = $successfullUserCreationCounter + 1;

                // then it is last iteration        
                if( $csvLoopCounter == count( $c  ) - 2) {
                    echo '<p>Number of succesful subordinates imported: ' .$successfullUserCreationCounter . '</p>';
                    
                  }
            }
            $csvLoopCounter = $csvLoopCounter + 1;
                    
        }  

        // send email of successful run
     /*
        $emailto = 'esmondmccain@gmail.com';

        // Email subject, "New {post_type_label}"
        $subject = 'This code ran:  ' . ' ' . date("m-d-y");

        // Email body
        $message = 'It ran';

        wp_mail( $emailto, $subject, $message );

        echo "file submitted" ;
        */
        wp_delete_attachment( $attachment_id, true);   
        exit;
      
    }

    public function readCSV($filename, $delimeter=',')
    {
        $handle = fopen($filename, "r"); 
        if ($handle === false) {
            return false;
        }
    
        while (($data = fgetcsv($handle, 1000, $delimeter)) !== false) {
           yield $data;
        }
    
        fclose($handle);
    }

    public function team_leader_user_import_page(){
        require_once(WP_PLUGIN_DIR . '/em-user-import/templates/team-leader-user-import-page.php'); 
        return;
    }

    public function team_leader_admin_page(){
        require_once(WP_PLUGIN_DIR . '/em-user-import/templates/team-leader-admin-page.php');
        return;
    }

    public function site_admin_team_leader_admin_page(){
        require_once(WP_PLUGIN_DIR . '/em-user-import/templates/site-admin-team-leader-page.php') ;
        return;
    }

    public function user_import_register_submenu_page() {

        //Add Custom Social Sharing Sub Menu
        add_menu_page(
        'Add Subordinates',
        'Team Manage',
        'read',
        "user-import-controls",
        '',
        '',
        2
        );
        add_submenu_page(
            'user-import-controls',
            'Add Subordinates',
            'Add Subordinates',
            "read",
            'user-import-controls',
            [$this, 'team_leader_user_import_page'], 
            3
            );
        add_submenu_page(
            'user-import-controls',
            'View Subordinates',
            'View Subordinates',
            "read",
            'team-leader-admin',
            [$this, 'team_leader_admin_page'], 
            1
            );
        add_submenu_page(
            'user-import-controls',
            'Site Admin View',
            'Site Admin View',
            "manage_options",
            'site-admin-team-leader-admin',
            [$this, 'site_admin_team_leader_admin_page'], 
            2
            );                
    } 

    public function user_import_inits() {
        add_role('team_leader', 'Team Leader', array(
            'read' => true,
            'create_posts' => false,
            'edit_posts' => false,
            'edit_others_posts' => false,
            'publish_posts' => false,
            'manage_categories' => false,
        ));

        add_role('team_subordinate', 'Team Subordinate', array(
            'read' => true,
            'create_posts' => false,
            'edit_posts' => false,
            'edit_others_posts' => false,
            'publish_posts' => false,
            'manage_categories' => false,
        ));            
    }

     
    public function profile_field_team_ID_disable() {

        global $pagenow;

        // apply only to user profile or user edit pages
        if ($pagenow!=='profile.php' && $pagenow!=='user-edit.php') {
            return;
        }

        // do not change anything for the administrator
        if (current_user_can('administrator')) {
            return;
        }

        add_action( 'admin_footer', [$this,  'profile_field_team_ID_disable_js' ] );

    }
  
    /**
     * Disables selected fields in WP Admin user profile (profile.php, user-edit.php)
     */
    public function profile_field_team_ID_disable_js() {
    ?>
        <script>
            jQuery(document).ready( function($) {
                var fields_to_disable = ['teamID'];
                for(i=0; i<fields_to_disable.length; i++) {
                    if ( $('#'+ fields_to_disable[i]).length ) {
                        $('#'+ fields_to_disable[i]).attr("disabled", "disabled");
                    }
                }
            });
        </script>
    <?php
    }

    public function profile_save_team_leader_email( $user_id ) {
      if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
        return;
      }
    
      if ( !current_user_can( 'edit_user', $user_id ) ) {
        return;
      }
    
      update_user_meta( $user_id, 'teamID', $_POST['teamID'] );
    }
    
    public function profile_field_team_ID( $user ) {
      $saved_teamID = get_user_meta( $user->ID, 'teamID', true ); ?>
      <h3><?php _e('Team Info'); ?></h3>
    
      <table class="form-table">
        <tr>
          <th><label for="teamID"><?php _e('User Team ID'); ?></label></th>
          <td>
          <select name="teamID">
          <option value="" <?php selected($saved_teamID,'') ?> ></option>
<?php

    $teamLeaderArgs = array(  
      'role__in' => array( 'team_leader' ),  
  );
  $teamLeaderUsers = get_users( $teamLeaderArgs );
foreach ( $teamLeaderUsers as $user ) {
?>
<option value="<?php echo $user->ID; ?>" <?php selected($saved_teamID,$user->ID) ?> ><?php echo $user->ID; ?></option>
<?php  
}
?>
  </select>
           
          </td>
        </tr>
      </table>
    <?php 
    }

    public function team_Leader_Form_Submission() {
        if ( ! isset( $_POST['team_Leader_Form_Submission_nonce_field'] ) || ! wp_verify_nonce( $_POST['team_Leader_Form_Submission_nonce_field'], 'team_Leader_Form_Submission' ) ) 
        {
    
          exit;
        } 
        
        else
        {
            if(!empty($_POST['userID'])) {
               

                if(!empty($_POST['teamLeaderSelectOption']) && $_POST['teamLeaderSelectOption'] == 'delete') {
                    foreach($_POST['userID'] as $id) {
                     
                        wp_delete_user( $id);          
                        echo '<p class="newpost-success">Users deleted</p>';
                         
                    }
                }

                if(!empty($_POST['teamLeaderSelectOption']) && $_POST['teamLeaderSelectOption'] == 'resend') {

                    foreach($_POST['userID'] as $id) {
                        $user = get_user_by('id',$id);
                        retrieve_password( $user->user_login );        
            
                    }

                    echo '<p class="newpost-success">Passwords sent</p>';
                 
                }  

           } 
  
        }
        exit; 
    }  
        
    public function emulate_Team_Leader_Form_Submission() {
        
        $teamLeaderArgs = array(  
            'role__in' => array( 'team_subordinate' ),
            'meta_key'     => 'teamID',
            'meta_value'   =>  $_POST['teamLeaderSelectOption'],    
        );
        $teamLeaderUsers = get_users( $teamLeaderArgs );
        // Array of WP_User objects.
        ?>
        <h2 class="emulation-title">Emulating user: <?php echo $_POST['teamLeaderSelectOption'];?></h2>
        <form id="team-leader-form" method="POST" action="">
            <table>
          <tr>
            <th>Number of Subordinates</th>
            <th>Action</th>
          </tr>
          <?php
            $number_of_users = count($teamLeaderUsers);
         ?>
          <tr>
            <td><?php echo '<span>' . esc_html( $number_of_users) . '</span>'; ?></td>
            <td>
              <select name="teamLeaderSelectOption" form="team-leader-form">
                <option value="delete">Delete</option>
                <option value="resend">Send Password Reset Link</option>
              </select>
            </td>  
          </tr>
            
            <?php
        ?>
        </table> 
            <table>
          <tr>
            <th>Subordinate email</th>
            <th>Subordinate name</th>
            <th>Select Subordinate</th>
          </tr>
          <?php
        foreach ( $teamLeaderUsers as $user ) {
        
            $number_of_users = count($teamLeaderUsers);
         ?>
        
          <tr>
            <td><?php echo '<span>' . esc_html( $user->user_email ) . '</span>'; ?></td>
            <td><?php echo '<span>' . esc_html( $user->display_name ) . '</span>'; ?></td>
            <td><input type="checkbox" name="userID[]" value="<?php echo $user->ID;  ?>"/></td>
          </tr>
        
          
            
            <?php
        }
        ?>
        </table>
        <?php wp_nonce_field( 'team_Leader_Form_Submission', 'team_Leader_Form_Submission_nonce_field' ); ?>
        <input type="hidden" name="action" value="team_Leader_Form_Submission" />
        <input type="submit" value="submit">
        </form> 
    <!-- Have to localize this to run -->
        <script>
        jQuery( "#team-leader-form" ).submit(function( event ) {
        event.preventDefault();
        jQuery("#team-leader-form input[type='submit']").prop( "disabled", true ); // disable all form buttons
        jQuery('#team-leader-form').append('<div class="user-import-ajax-loader"></div>'); // initial loader icon for all users request

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
                        },
                        0
                    );
                    setTimeout(function () {
                        window.location.href= ''; // the redirect goes here

                    },3000); 


            },
                error: function(jqXHR, textStatus, errorThrown) {
                    jQuery(".user-import-ajax-loader").remove();
                    jQuery("#team-leader-form").append('<div id="em-connect-error">Connection Error</div>');
                    console.log(JSON.stringify(jqXHR) + " :: " + textStatus + " :: " + errorThrown);
                }

            });// ajax end
        
        });    
        </script>    
        <?php
    }

    public function emulate_Team_subordinate_Form_Submission() {
     
        ?>
        <form id="em-user-import-form" action="" method="post" enctype="multipart/form-data">
        <label>CSV file  <input id="csvUpload" type="file" name="csvUpload"  type="file" accept=".csv" /></label> 
        <input name="teamLeaderID"  type="hidden" value="<?php echo $_POST['teamLeaderSelectOption'];?>"/>
            <input type="submit" value="Import">
        
        </form>
        <script>
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
                                console.log(serializedData);    
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
        </script>
        <?php 

    }     
}


}


  



