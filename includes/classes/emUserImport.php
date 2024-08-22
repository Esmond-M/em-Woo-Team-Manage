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
		add_action( 'admin_enqueue_scripts', [$this, 'load_admin_styles' ]  );
        add_action('admin_init', [$this, 'profile_field_team_leader_email_disable' ] );  
        add_action( 'show_user_profile', [$this, 'profile_field_team_leader_email' ]  );
        add_action( 'edit_user_profile', [$this, 'profile_field_team_leader_email' ]  );
        add_action( 'personal_options_update', [$this, 'profile_save_team_leader_email' ]  );
        add_action( 'edit_user_profile_update', [$this, 'profile_save_team_leader_email' ]  );  
    
    }


    public function user_import_register_submenu_page() {

        //Add Custom Social Sharing Sub Menu
        add_submenu_page(
        'options-general.php',
        'EM User Import ',
        'EM User Import',
        "read",
        'user-import-controls',
        [$this, 'user_import_page'], 
        1
        );

    } // end of function

    public function user_import_page(){
        require WP_PLUGIN_DIR . '/em-user-import/templates/EM-user-import-admin-page.php';
        return;
    }

    public function load_admin_styles(){
        global $pagenow;
        $rand = rand(1, 99999999999);
        if ( 'options-general.php' === $pagenow &&  isset($_GET['page']) &&  $_GET['page']=== 'user-import-controls' ) {
            wp_enqueue_style( 'edit_screen_css',  '/wp-content/plugins/em-user-import/admin/assets/css/em-options.css' , array(),  $rand );
        }
      
       // wp_enqueue_script( 'EM-User-Import-scripts', '/wp-content/plugins/em-user-import/admin/assets/js/emUserImport.js', array('jquery'), $rand, true);
        return;
    }

    public function user_import_submission()
    {
        include(ABSPATH . "wp-includes/pluggable.php"); 
        // WordPress environmet
        require( ABSPATH . 'wp-load.php' );

        // it allows us to use wp_handle_upload() function
        require_once( ABSPATH . 'wp-admin/includes/file.php' );

        // can add some kind of validation here
        if( empty( $_FILES[ 'emcsv' ] ) ) {
            wp_die();
        }

        $upload = wp_handle_upload( 
            $_FILES[ 'emcsv' ], 
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
        
        $counter = 0;
        foreach ( $csv as $c ) {
            if ($counter++ == 0) continue; // skip headers     
            $username = $c[0];
            $email_address = $c[1];          
            $password = $c[2];

            if ( ! username_exists( $username ) ) {
                $user_id = wp_create_user( $username, $password, $email_address );
                $user = WP_User( $user_id );
                $user->set_role( 'administrator' );
            }    
        }  

        // send email of successful run
     
        $emailto = 'esmondmccain@gmail.com';

        // Email subject, "New {post_type_label}"
        $subject = 'This code ran:  ' . ' ' . date("m-d-y");

        // Email body
        $message = 'It ran';

        wp_mail( $emailto, $subject, $message );

        echo "file submitted" ;
        
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
    
 
    public function profile_field_team_leader_email_disable() {

        global $pagenow;

        // apply only to user profile or user edit pages
        if ($pagenow!=='profile.php' && $pagenow!=='user-edit.php') {
            return;
        }

        // do not change anything for the administrator
        if (current_user_can('administrator')) {
            return;
        }

        add_action( 'admin_footer', [$this,  'profile_field_team_leader_email_disable_js' ] );

    }
  
    /**
     * Disables selected fields in WP Admin user profile (profile.php, user-edit.php)
     */
    public function profile_field_team_leader_email_disable_js() {
    ?>
        <script>
            jQuery(document).ready( function($) {
                var fields_to_disable = ['teamLeaderEmail'];
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
    
      update_user_meta( $user_id, 'teamLeaderEmail', $_POST['teamLeaderEmail'] );
    }
    
    public function profile_field_team_leader_email( $user ) {
      $saved_teamLeaderEmail = get_user_meta( $user->ID, 'teamLeaderEmail', true ); ?>
      <h3><?php _e('Team Leader Email'); ?></h3>
    
      <table class="form-table">
        <tr>
          <th><label for="teamLeaderEmail"><?php _e('Email'); ?></label></th>
          <td>
            <input type="email" name="teamLeaderEmail" id="teamLeaderEmail" value="<?php echo esc_attr($saved_teamLeaderEmail); ?>" />
          </td>
        </tr>
      </table>
    <?php 
    }   
}

}