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
       // add_action('init', [$this, 'user_import_submission' ] );
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
        // global $pagenow;
       // $rand = rand(1, 99999999999);

       // wp_enqueue_style( 'edit_screen_css',  '/wp-content/plugins/em-user-import/admin/assets/css/em-options.css' , array(),  $rand );
       // wp_enqueue_script( 'EM-User-Import-scripts', '/wp-content/plugins/em-user-import/admin/assets/js/emUserImport.js', array('jquery'), $rand, true);
    return;
    }

    public function user_import_submission()
    {
        include(ABSPATH . "wp-includes/pluggable.php"); 
        // WordPress environmet
        require( ABSPATH . '/wp-load.php' );

        // it allows us to use wp_handle_upload() function
        require_once( ABSPATH . 'wp-admin/includes/file.php' );

        // you can add some kind of validation here
        if( empty( $_FILES[ 'emcsv' ] ) ) {
            wp_die( 'No files selected.' );
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
            $password = $c[1];
            $email_address = $c[2];  
            if ( ! username_exists( $username ) ) {
                $user_id = wp_create_user( $username, $password, $email_address );
                $user = WP_User( $user_id );
                $user->set_role( 'administrator' );
            }    
        }  
        // echo 'This window is out of date. Weekly update failed.';
        // send email of new post
        // Recipient, in this case the administrator email
        $emailto = 'esmondmccain@gmail.com';

        // Email subject, "New {post_type_label}"
        $subject = 'This code ran:  ' . ' ' . date("m-d-y");

        // Email body
        $message = 'It ran';

        wp_mail( $emailto, $subject, $message );
        
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

   
}
}