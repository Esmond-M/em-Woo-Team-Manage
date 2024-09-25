<?php

/**
* Main plugin file.
* PHP version 7.4

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
*/

declare(strict_types=1);
namespace emWooTeamManage;

if (!class_exists('emWooTeamManage')) {
/**
* Declaring class

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
*/

    class emWooTeamManage
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

        $user = wp_get_current_user();
        // check if woocommerce is active
        if ( in_array(  'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){

            if ( in_array( 'team_leader', (array) $user->roles ) ) {
                add_filter( 'woocommerce_prevent_admin_access', '__return_false' );
                add_filter( 'woocommerce_disable_admin_bar', '__return_false' );
            }            
        }

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

    public function team_leader_user_import_page(){
        require_once(dirname(__DIR__, 2) . '/templates/team-leader-user-import-page.php'); 
        return;
    }

    public function team_leader_admin_page(){
        require_once(dirname(__DIR__, 2) . '/templates/team-leader-admin-page.php');
        return;
    }

    public function site_admin_team_leader_admin_page(){
        require_once(dirname(__DIR__, 2) . '/templates/site-admin-team-leader-page.php') ;
        return;
    }

    public function load_Admin_Styles(){
        global $pagenow;
        $rand = rand(1, 99999999999);
        if ( 'admin.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'user-import-controls' ) {
            wp_enqueue_style( 'team-leader-user-import-styles',  '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-user-import.css' , array(),  $rand );
            wp_enqueue_script( 'team-leader-subordinate-import-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/teamSubordinateImport.js', array('jquery'), $rand, true); 
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
            wp_enqueue_style( 'team-leader-user-import-styles',  '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-user-import.css' , array(),  $rand );
        }
        if ( 'admin.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'team-leader-admin' ) {
            wp_enqueue_style( 'team-leader-admin-styles',  '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-admin.css' , array(),  $rand );
            wp_enqueue_script( 'team-leader-admin-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/teamLeaderAdmin.js', array('jquery'), $rand, true); 
            wp_enqueue_script( 'team-leader-admin-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/teamSubordinateImport.js', array('jquery'), $rand, true); 
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
            wp_enqueue_style( 'site-admin-team-leader-styles',  '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/site-admin-team-leader.css' , array(),  $rand );
        }
       
        return;
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
            if(!empty($_POST['userID'])) {?>
               
                <div class="user-deletion-password-contain">
                <?php
                if(!empty($_POST['teamLeaderSelectOption']) && $_POST['teamLeaderSelectOption'] == 'delete') {
                    foreach($_POST['userID'] as $id) {
                        $user = get_user_by('id',$id);
                        wp_delete_user( $id);          
                        echo '<p class="newpost-success">User: '. $user->user_login .' deleted</p>';
                         
                    }
                }

                if(!empty($_POST['teamLeaderSelectOption']) && $_POST['teamLeaderSelectOption'] == 'resend') {

                    foreach($_POST['userID'] as $id) {
                        $user = get_user_by('id',$id);
                        retrieve_password( $user->user_login );    
                        echo '<p class="newpost-success">User: '. $user->user_login .' password sent</p>';    
            
                    }

                    echo '<p class="newpost-success">Passwords sent</p>';
                 
                }?>
                <button class="refresh-btn" onClick="window.location.reload();">Refresh Page</button>
                </div>
                <?php  
 
           } 
  
        }
        exit; 
    }  
        
    public function emulate_Team_Leader_Form_Submission() {
        $teamLeader_obj = get_user_by('id', $_POST['teamLeaderSelectOption']);
        $teamLeaderArgs = array(  
            'role__in' => array( 'team_subordinate' ),
            'meta_key'     => 'teamID',
            'meta_value'   =>  $_POST['teamLeaderSelectOption'],    
        );
        $teamLeaderUsers = get_users( $teamLeaderArgs );
        // Array of WP_User objects.
        ?>
        <p style="color:red;"><strong>Emulating: <?php echo $teamLeader_obj->user_login?></strong></p>
          <h2>View Subordinates</h2>
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
        </script>    
        <?php
    }

    public function emulate_Team_subordinate_Form_Submission() {
        $teamLeader_obj = get_user_by('id', $_POST['teamLeaderSelectOption']);
        $siteURL = get_site_url();
        ?>
        <p style="color:red;"><strong>Emulating: <?php echo $teamLeader_obj->user_login?></strong></p>
          <h2>Import Users from CSV</h2>
        <form id="subordinate-import-form" action="" method="post" enctype="multipart/form-data">
        <label>CSV file limit 5MB   <input id="csvUpload" type="file" name="csvUpload"  type="file" accept=".csv" /></label> 
        <input name="teamLeaderID"  type="hidden" value="<?php echo $_POST['teamLeaderSelectOption'];?>"/>
            <input type="submit" value="Import">
        
        </form>

        <div class="instructional-container">

        <p>Import up to 50 users at once. CSV requires first row fields be email_address,first_name,last_name. Those are the three pieces of info needed for each user.</p>
          <img alt="user import example"title="user import example" src="<?php echo $siteURL .  '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/img/user-import-screenshot.png';?>" />
       
        </div>
        <?php 
    }

    public function user_import_submission()
    {
        // It allows create user functions
        require_once(ABSPATH . 'wp-includes/user.php'); 
        
        // WordPress environment
        require_once( ABSPATH . 'wp-load.php' );

        // it allows us to use wp_handle_upload() function
        require_once( ABSPATH . 'wp-admin/includes/file.php' );


        ?>
        <div class="user-upload-results-contain">
        <?php
        // check if file submitted
        if( empty( $_FILES[ 'csvUpload' ] ) ) {
            wp_die('<p style="color:red;">File does not exist.</p>');
        }
            // check if file is too large 5MB
        $file_size = $_FILES['csvUpload']['size'];
        if ((  $file_size > 5242880)){      
   
            wp_die('<p>File too large. File must be less than 5 megabytes.</p>'); ; 
        }
        $upload = wp_handle_upload( 
            $_FILES[ 'csvUpload' ],
            array( 'test_form' => false ) 
        );

        if( ! empty( $upload[ 'error' ] ) ) {
            wp_die( '<p style="color:red;">'. $upload[ "error" ] .'</p>' );
        }

        //  add uploaded file into WordPress media library
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
            wp_die( '<p style="color:red;">Upload error.</p>' );
        }

        //$csvFile = fopen('Data.csv', 'r'); // location of file
        $csv =   $this->readCSV($upload[ 'url' ] ); 

        $csvLoopCounter = 0;
        $csvRowCounter = 0; // used to skip first row
        $successfullUserCreationCounter = 0;
        $errorUserCreationCounter = 0;
        foreach ( $csv as $c ) {
            
            if ($csvRowCounter++ == 0) continue; // skip headers     
            
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
                // $user_id->get_error_message()
                $errorUserCreationCounter = $errorUserCreationCounter + 1;
                echo '<p style="color:red;">'. $errorUserCreationCounter . '. ' . $firstName . ' '.  $LastName .' did not import. Error Message: ' . $user_id->get_error_message() . '. Please check info from CSV that was uploaded.</p>';
            }
            
            else
            {
                
                 // The user was successfully created
                add_user_meta($user_id, 'teamID', $_POST['teamLeaderID']); //Give team Id
                wp_new_user_notification($user_id, null , "both"); // Send account email notification
                $successfullUserCreationCounter = $successfullUserCreationCounter + 1;


            }
            // then it is last iteration        
            if( $csvLoopCounter == count( $c  ) - 2) {
                echo '<p style="color:green;">Number of succesful subordinates imported: ' .$successfullUserCreationCounter . '</p>';
                
                }

            $csvLoopCounter = $csvLoopCounter + 1;

            if($csvLoopCounter >= 50){ // only import up to 50 users
                echo '<p style="color:red;">Only first 50 user can be imported from CSV file.</p>';
               break;
          
            }       
        }  
        ?>
        </div>
        <?php
        wp_delete_attachment( $attachment_id, true);   
        exit;
      
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
}


}


  



