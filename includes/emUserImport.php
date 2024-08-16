<?php
declare(strict_types=1);
namespace inc\awc_class_automate_photo_net_submissions;

if (!class_exists('automatePhotoNetSubmissions')) {

    class automatePhotoNetSubmissions
    {


        public function __construct()
        {

		add_action('wp_ajax_form_post_new_net_photo_submission_ajax', [$this, 'form_post_new_net_photo_submission_ajax' ] );
		add_action('wp_ajax_nopriv_form_post_new_net_photo_submission_ajax', [$this, 'form_post_new_net_photo_submission_ajax' ]);
		add_action( 'wp_enqueue_scripts', [$this, 'net_style_scripts' ]  );

        }

	

	

	
			public function form_post_new_net_photo_submission_ajax()
			{

			/*
			if(isset($_FILES['net_image'])) {
				$errors     = array();
				$maxsize    = 10240;
				$acceptable = array(
					'image/jpeg',
					'image/jpg',
					'image/png'
				);

				if(($_FILES['uploaded_file']['size'] >= $maxsize) || ($_FILES["uploaded_file"]["size"] == 0)) {
					$errors[] = 'File too large. File must be less than 5 megabytes.';
				}

				if((!in_array($_FILES['uploaded_file']['type'], $acceptable)) && (!empty($_FILES["uploaded_file"]["type"]))) {
					$errors[] = 'Invalid file type. Only JPG and PNG types are accepted.';
				}

				if(count($errors) === 0) {
					die(); //Ensure no more processing is done
				} 
			}
			*/


				// Do some minor form validation to make sure there is content
				if ( isset($_POST['net_employee_name']) && isset($_POST['net_title']) && isset($_POST['net_beat_team']) && isset($_POST['net_region']) ) {

				}
				else{
						wp_die('<p class="newpost-fail">Server error please resubmit.</p>');
				}

				// Add the content of the form to $post as an array
				$new_post = array(
					'post_title'    => $_POST['net_employee_name'] . ' ' . $_POST['net_title'] . ' ' . date("m-d-y") ,
					'post_status'   => 'draft',           // Choose: publish, preview, future, draft, etc.
					'meta_input'   => array(
					'net_employee_name' => '' . $_POST['net_employee_name'] . '',
					'net_title' => '' . $_POST['net_title'] . '',
					'net_beat_team' => '' . $_POST['net_beat_team'] . '',
					'net_region' => '' . $_POST['net_region'] . '',
					'net_caption' => '' . $_POST['net_caption'] . '',
					),
					'post_type' => 'net_submission'  //'post',page' or use a custom post type if you want to
					);
					//save the new post
				$pid = wp_insert_post($new_post); 

				// The nonce was valid and the user has the capabilities, it is safe to continue.

				// These files need to be included as dependencies when on the front end.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				require_once( ABSPATH . 'wp-admin/includes/media.php' );

				$attachment_id = media_handle_upload( 'net_image', $pid );
				//Set Image as thumbnail
				set_post_thumbnail($pid, $attachment_id);

                $headers = array('Content-Type: text/html; charset=UTF-8','From: Esmond Mccain <Esmond.Mccain@awc-inc.com>', 'Reply-To: Esmond Mccain <Esmond.Mccain@awc-inc.com>');// this does not work but also does not hinder
				// send email of new post
				// Recipient, in this case the administrator email
				$emailto = 'Esmond.Mccain@awc-inc.com,trey.castleberry@awc-inc.com,shelley.painter@awc-inc.com';

				// Email subject, "New {post_type_label}"
				$subject = 'New Photo Submission for: ' . $_POST['net_employee_name'] . ' ' . date("m-d-y");

                wp_set_current_user(604); // get user that can edit posts so edit link function will work
				// Email body
				$message = 'View it: ' . get_permalink( $pid ) . "<br><br>Edit it: " . get_edit_post_link( $pid, "&" );
                 wp_set_current_user(0);  // turn off get user after get link function

				wp_mail( $emailto, $subject, $message, $headers );
				echo '<p class="newpost-success">Thank you for your submission!</p>';
				wp_die();
			}

			public function net_style_scripts(){

				$rand = rand(1, 99999999999);
					 if( is_page(3396) ){
						wp_enqueue_script( 'awc-submit-photo-submission-script', get_stylesheet_directory_uri() . '/assets/js/awc-submit-photo-submission.js', array('jquery'), $rand, true);
							wp_localize_script('awc-submit-photo-submission-script', 'ajax_form_post_new_net_photo_submission', array(
							'ajaxurl_form_post_new_net_photo_submission' => admin_url('admin-ajax.php') ,
							'noposts' => __('No older posts found', 'awc-white') ,
						  )); 
					 }

			}

	} // Closing bracket for classes

}

use inc\awc_class_automate_photo_net_submissions;
new automatePhotoNetSubmissions;