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

        add_action('admin_menu', [$this, 'EMUserImport_register_submenu_page' ] );
		add_action( 'admin_enqueue_scripts', [$this, 'load_admin_em_styles' ]  );
        add_action('wp_ajax_EM_User_Import_Submission_ajax', [$this, 'EM_User_Import_Submission_ajax' ] );
		add_action('wp_ajax_nopriv_EM_User_Import_Submission_ajax', [$this, 'EM_User_Import_Submission_ajax' ]);

    }

    public function EMUserImport_register_submenu_page() {

        //Add Custom Social Sharing Sub Menu
        add_submenu_page(
        'options-general.php',
        'EM User Import ',
        'EM User Import',
        "manage_options",
        'user-import-controls',
        [$this, 'EM_user_import_page'], 
        1
        );

    } // end of function


    public function EM_user_import_page(){
        require WP_PLUGIN_DIR . '/em-user-import/templates/EM-user-import-admin-page.php';
        return;
    }

    public function load_admin_em_styles(){
       // global $pagenow;
        $rand = rand(1, 99999999999);

        wp_enqueue_style( 'edit_screen_css',  '/wp-content/plugins/em-user-import/admin/assets/css/em-options.css' , array(),  $rand );
        wp_enqueue_script( 'EM-User-Import-scripts', '/wp-content/plugins/em-user-import/admin/assets/js/emUserImport.js', array('jquery'), $rand, true);
        wp_localize_script('EM-User-Import-scripts', 'ajax_EM_User_Import_Submission', array(
        'ajaxurl_EM_User_Import_Submission' => admin_url('admin-ajax.php') ,
        'noposts' => __('No older posts found', 'awc-white') ,
      )); 
    return;
    }

    public function EM_User_Import_Submission_ajax()
    {


        // Do some minor form validation to make sure there is content
        if ( isset($_POST['emcsv'])  ) {
            //$csvFile = fopen('Data.csv', 'r'); // location of file
            $csv =   $this->readCSV(WP_PLUGIN_DIR . '/em-user-import/csv/user.csv' ); 
          
            foreach ( $csv as $c ) {
                $username = $c[0];
                $password = $c[1];
                $email_address = $c[2];  
                if ( ! username_exists( $username ) ) {
                    $user_id = wp_create_user( $username, $password, $email_address );
                    $user = WP_User( $user_id );
                    $user->set_role( 'administrator' );
                }    
            }  
              }
              else{
                 // echo 'This window is out of date. Weekly update failed.';
                  // send email of new post
                  // Recipient, in this case the administrator email
                  $emailto = 'esmondmccain@gmail.com';
      
                  // Email subject, "New {post_type_label}"
                  $subject = 'This failed to add users:  ' . ' ' . date("m-d-y");
      
                  // Email body
                  $message = 'It ran but else statement shows failed';
      
                  wp_mail( $emailto, $subject, $message );
                    //  wp_die('<p class="newpost-fail">Server error please resubmit.</p>');
              }
      
      
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