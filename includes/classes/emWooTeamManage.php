<?php
declare(strict_types=1);
namespace emWooTeamManage\init_plugin\Classes;

/**
 * Main plugin class for EM Woo Team Manage.
 * 
 * Handles:
 * - Custom user roles for team leaders and subordinates
 * - Admin menu and page registration
 * - Enqueuing admin styles and scripts
 * - User profile field management for team assignment
 * - AJAX handlers for team management actions (import, emulation, deletion, password reset)
 * - CSV import of subordinate users
 * - WooCommerce integration for automatic team leader creation after payment
 */

class emWooTeamManage
{
    /**
     * Constructor: Registers hooks for plugin initialization, admin, AJAX, and WooCommerce integration.
     */
    public function __construct()
    {
        // Initialization hooks
        add_action('init', [$this, 'user_import_inits']);
        add_action('admin_init', [$this, 'profile_field_team_ID_disable']);

        // Admin menu and styles
        add_action('admin_menu', [$this, 'user_import_register_submenu_page']);
        add_action('admin_enqueue_scripts', [$this, 'load_Admin_Styles']);

        // User profile fields and saving
        add_action('show_user_profile', [$this, 'profile_field_team_ID']);
        add_action('edit_user_profile', [$this, 'profile_field_team_ID']);
        add_action('personal_options_update', [$this, 'profile_save_team_leader_email']);
        add_action('edit_user_profile_update', [$this, 'profile_save_team_leader_email']);

        // AJAX handlers
        add_action('wp_ajax_team_Leader_Form_Submission', [$this, 'team_Leader_Form_Submission']);
        add_action('wp_ajax_emulate_Team_Leader_Form_Submission', [$this, 'emulate_Team_Leader_Form_Submission']);
        add_action('wp_ajax_emulate_Team_subordinate_Form_Submission', [$this, 'emulate_Team_subordinate_Form_Submission']);
        add_action('wp_ajax_user_import_submission', [$this, 'user_import_submission']);

        // WooCommerce hook
        add_action('woocommerce_thankyou', [$this, 'create_Team_Leader_After_Payment'], 10, 1);
    }

    /**
     * Helper to require template files from the templates directory.
     */
    private function require_template($template) {
        require_once(dirname(__DIR__, 2) . "/templates/{$template}");
    }

    /**
     * Registers custom user roles and WooCommerce admin access for team leaders.
     */
    public function user_import_inits() {
        // Common capabilities for custom roles
        $role_caps = array(
            'read' => true,
            'create_posts' => false,
            'edit_posts' => false,
            'edit_others_posts' => false,
            'publish_posts' => false,
            'manage_categories' => false,
        );

        // Add custom roles if not already present
        if (!get_role('team_leader')) {
            add_role('team_leader', 'Team Leader', $role_caps);
        }
        if (!get_role('team_subordinate')) {
            add_role('team_subordinate', 'Team Subordinate', $role_caps);
        }

        // Check if WooCommerce is active and user is a team leader
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $user = wp_get_current_user();
            if (in_array('team_leader', (array) $user->roles)) {
                add_filter('woocommerce_prevent_admin_access', '__return_false');
                add_filter('woocommerce_disable_admin_bar', '__return_false');
            }
        }
    }

    /**
     * Registers admin menu and submenu pages for team management.
     */
    public function user_import_register_submenu_page() {

        // Main menu page
        add_menu_page(
            'Add Subordinates',
            'Team Manage',
            'read',
            'user-import-controls',
            '',
            '',
            2
        );

        // Submenu pages configuration
        $submenus = [
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'Add Subordinates',
                'menu_title'  => 'Add Subordinates',
                'capability'  => 'read',
                'menu_slug'   => 'user-import-controls',
                'template'    => 'team-leader-user-import-page.php',
                'position'    => 3
            ],
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'View Subordinates',
                'menu_title'  => 'View Subordinates',
                'capability'  => 'read',
                'menu_slug'   => 'team-leader-admin',
                'template'    => 'team-leader-admin-page.php',
                'position'    => 1
            ],
            [
                'parent_slug' => 'user-import-controls',
                'page_title'  => 'Site Admin View',
                'menu_title'  => 'Site Admin View',
                'capability'  => 'manage_options',
                'menu_slug'   => 'site-admin-team-leader-admin',
                'template'    => 'site-admin-team-leader-page.php',
                'position'    => 2
            ],
        ];

        // Add submenus with a generic callback
        foreach ($submenus as $submenu) {
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                function() use ($submenu) { $this->require_template($submenu['template']); },
                $submenu['position']
            );
        }
    }


    /**
     * Enqueues admin styles and scripts for plugin pages.
     */
    public function load_Admin_Styles(){
        global $pagenow;
        $rand = rand(1, 99999999999);
        $page = isset($_GET['page']) ? $_GET['page'] : '';

        $config = [
            'user-import-controls' => [
                'styles' => [
                    ['team-leader-user-import-styles', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-user-import.css'],
                ],
                'scripts' => [
                    ['team-leader-subordinate-import-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/teamSubordinateImport.js'],
                ],
                'localize' => [
                    ['team-leader-subordinate-import-script', 'emulate_Team_subordinate_Form_Submission', [
                        'ajaxurl' => admin_url('admin-ajax.php')
                    ]],
                    ['team-leader-subordinate-import-script', 'user_import_submission', [
                        'ajaxurl' => admin_url('admin-ajax.php')
                    ]],
                ],
            ],
            'site-admin-team-leader-admin' => [
                'styles' => [
                    ['team-leader-user-import-styles', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-user-import.css'],
                    ['site-admin-team-leader-styles', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/site-admin-team-leader.css'],
                ],
            ],
            'team-leader-admin' => [
                'styles' => [
                    ['team-leader-admin-styles', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-admin.css'],
                ],
                'scripts' => [
                    ['team-leader-admin-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/teamLeaderAdmin.js'],
                    ['team-leader-admin-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/teamSubordinateImport.js'],
                ],
                'localize' => [
                    ['team-leader-admin-script', 'team_Leader_Form_Submission', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                    ['team-leader-admin-script', 'emulate_Team_Leader_Form_Submission', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                ],
            ],
        ];

        if ($pagenow === 'admin.php' && isset($config[$page])) {
            $entry = $config[$page];
            if (!empty($entry['styles'])) {
                foreach ($entry['styles'] as $style) {
                    wp_enqueue_style($style[0], $style[1], array(), $rand);
                }
            }
            if (!empty($entry['scripts'])) {
                foreach ($entry['scripts'] as $script) {
                    wp_enqueue_script($script[0], $script[1], array('jquery'), $rand, true);
                }
            }
            if (!empty($entry['localize'])) {
                foreach ($entry['localize'] as $loc) {
                    wp_localize_script($loc[0], $loc[1], $loc[2]);
                }
            }
        }
        return;
    }

    /**
     * Reads a CSV file and yields each row as an array.
     */
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

    /**
     * Disables the teamID field on user profile pages for non-admins.
     */
    public function profile_field_team_ID_disable() {

        global $pagenow;

        // Only apply on user profile or user edit pages, and not for administrators
        if (
            ($pagenow !== 'profile.php' && $pagenow !== 'user-edit.php') ||
            current_user_can('administrator')
        ) {
            return;
        }

        add_action('admin_footer', [$this, 'profile_field_team_ID_disable_js']);
    }

    /**
     * Outputs JS to disable selected fields in WP Admin user profile.
     */
    public function profile_field_team_ID_disable_js() {
    ?>
    <script>
    jQuery(function($) {
        ['teamID'].forEach(function(field) {
            var $el = $('#' + field);
            if ($el.length) {
                $el.prop('disabled', true);
            }
        });
    });
    </script>
    <?php
    }

    /**
     * Saves the teamID field from the user profile, with nonce and capability checks.
     */
    public function profile_save_team_leader_email( $user_id ) {
        // Verify nonce and capability before saving
        if (
            empty($_POST['_wpnonce']) ||
            !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id) ||
            !current_user_can('edit_user', $user_id)
        ) {
            return;
        }

        // Sanitize and update teamID
        $teamID = isset($_POST['teamID']) ? sanitize_text_field($_POST['teamID']) : '';
        update_user_meta($user_id, 'teamID', $teamID);
    }

    /**
     * Displays the teamID field in the user profile edit screen.
     */
    public function profile_field_team_ID( $user ) {
        $saved_teamID = get_user_meta($user->ID, 'teamID', true);
        ?>
        <h3><?php esc_html_e('Team Info'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="teamID"><?php esc_html_e('User Team ID'); ?></label></th>
                <td>
                    <select name="teamID" id="teamID">
                        <option value="" <?php selected($saved_teamID, ''); ?>></option>
                        <?php
                        $teamLeaderArgs = array(
                            'role__in' => array('team_leader'),
                            'fields'   => array('ID', 'display_name')
                        );
                        $teamLeaderUsers = get_users($teamLeaderArgs);
                        foreach ($teamLeaderUsers as $leader) {
                            ?>
                            <option value="<?php echo esc_attr($leader->ID); ?>" <?php selected($saved_teamID, $leader->ID); ?>>
                                <?php echo esc_html($leader->display_name . ' (' . $leader->ID . ')'); ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handles AJAX submission for team leader actions (delete/resend password).
     */
    public function team_Leader_Form_Submission() {
        // Verify nonce for security
        if (
            empty($_POST['team_Leader_Form_Submission_nonce_field']) ||
            !wp_verify_nonce($_POST['team_Leader_Form_Submission_nonce_field'], 'team_Leader_Form_Submission')
        ) {
            exit;
        }

        // Check if user IDs are provided
        if (!empty($_POST['userID'])) {
            ?>
            <div class="user-deletion-password-contain">
            <?php
            $action = isset($_POST['teamLeaderSelectOption']) ? sanitize_text_field($_POST['teamLeaderSelectOption']) : '';
            foreach ($_POST['userID'] as $id) {
                $user = get_user_by('id', $id);
                if (!$user) {
                    echo '<p class="newpost-error">User ID ' . esc_html($id) . ' not found.</p>';
                    continue;
                }

                if ($action === 'delete') {
                    wp_delete_user($id);
                    echo '<p class="newpost-success">User: ' . esc_html($user->user_login) . ' deleted</p>';
                } elseif ($action === 'resend') {
                    retrieve_password($user->user_login);
                    echo '<p class="newpost-success">User: ' . esc_html($user->user_login) . ' password sent</p>';
                }
            }

            if ($action === 'resend') {
                echo '<p class="newpost-success">Passwords sent</p>';
            }
            ?>
            <button class="refresh-btn" onClick="window.location.reload();">Refresh Page</button>
            </div>
            <?php
        }
        exit;
    }

    /**
     * Handles AJAX emulation of a team leader, displaying their subordinates.
     */
    public function emulate_Team_Leader_Form_Submission() {
    // Sanitize input
    $team_leader_id = isset($_POST['teamLeaderSelectOption']) ? intval($_POST['teamLeaderSelectOption']) : 0;
    $teamLeader_obj = get_user_by('id', $team_leader_id);

    // Get subordinates for this team leader
    $teamLeaderArgs = [
        'role__in'   => ['team_subordinate'],
        'meta_key'   => 'teamID',
        'meta_value' => $team_leader_id,
    ];
    $teamLeaderUsers = get_users($teamLeaderArgs);
    $number_of_users = count($teamLeaderUsers);
    ?>
    <p style="color:red;"><strong>Emulating: <?php echo esc_html($teamLeader_obj ? $teamLeader_obj->user_login : 'Unknown'); ?></strong></p>
    <h2>View Subordinates</h2>
    <h2 class="emulation-title">Emulating user: <?php echo esc_html($team_leader_id); ?></h2>
    <form id="team-leader-form" method="POST" action="">
        <table>
            <tr>
                <th>Number of Subordinates</th>
                <th>Action</th>
            </tr>
            <tr>
                <td><span><?php echo esc_html($number_of_users); ?></span></td>
                <td>
                    <select name="teamLeaderSelectOption" form="team-leader-form">
                        <option value="delete">Delete</option>
                        <option value="resend">Send Password Reset Link</option>
                    </select>
                </td>
            </tr>
        </table>
        <table>
            <tr>
                <th>Subordinate email</th>
                <th>Subordinate name</th>
                <th>Select Subordinate</th>
            </tr>
            <?php foreach ($teamLeaderUsers as $user): ?>
                <tr>
                    <td><span><?php echo esc_html($user->user_email); ?></span></td>
                    <td><span><?php echo esc_html($user->display_name); ?></span></td>
                    <td><input type="checkbox" name="userID[]" value="<?php echo esc_attr($user->ID); ?>" /></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php wp_nonce_field('team_Leader_Form_Submission', 'team_Leader_Form_Submission_nonce_field'); ?>
        <input type="hidden" name="action" value="team_Leader_Form_Submission" />
        <input type="submit" value="Submit">
    </form>
    <?php
    }

    /**
     * Handles AJAX emulation for importing subordinates via CSV for a team leader.
     */
    public function emulate_Team_subordinate_Form_Submission() {
        // Sanitize and validate input
        $team_leader_id = isset($_POST['teamLeaderSelectOption']) ? intval($_POST['teamLeaderSelectOption']) : 0;
        $teamLeader_obj = get_user_by('id', $team_leader_id);
        $siteURL = esc_url(get_site_url());

        ?>
        <p style="color:red;">
            <strong>
                Emulating: <?php echo esc_html($teamLeader_obj ? $teamLeader_obj->user_login : 'Unknown'); ?>
            </strong>
        </p>
        <h2>Import Users from CSV</h2>
        <form id="subordinate-import-form" action="" method="post" enctype="multipart/form-data">
            <label>
                CSV file limit 5MB
                <input id="csvUpload" type="file" name="csvUpload" accept=".csv" />
            </label>
            <input name="teamLeaderID" type="hidden" value="<?php echo esc_attr($team_leader_id); ?>" />
            <input type="submit" value="Import">
        </form>

        <div class="instructional-container">
            <p>
                Import up to 50 users at once. CSV requires first row fields be <strong>email_address, first_name, last_name</strong>.
                Those are the three pieces of info needed for each user.
            </p>
            <img
                alt="user import example"
                title="user import example"
                src="<?php echo $siteURL . '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/img/user-import-screenshot.png'; ?>"
                style="max-width:100%;height:auto;"
            />
        </div>
        <?php
    }

    /**
     * Handles AJAX CSV import of subordinate users for a team leader.
     */
    public function user_import_submission()
    {
        // Load required WordPress files

        // It allows create user functions
        require_once(ABSPATH . 'wp-includes/user.php');

        // WordPress environment
        require_once(ABSPATH . 'wp-load.php');

        // it allows us to use wp_handle_upload() function
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        ?>
        <div class="user-upload-results-contain">
        <?php
        // Validate file upload
        if (empty($_FILES['csvUpload'])) {
            wp_die('<p style="color:red;">File does not exist.</p>');
        }
        $file_size = $_FILES['csvUpload']['size'];
        if ($file_size > 5242880) {
            wp_die('<p>File too large. File must be less than 5 megabytes.</p>');
        }
        $upload = wp_handle_upload(
            $_FILES['csvUpload'],
            array('test_form' => false)
        );

        if (!empty($upload['error'])) {
            wp_die('<p style="color:red;">' . esc_html($upload["error"]) . '</p>');
        }

        // Add uploaded file into WordPress media library
        $attachment_id = wp_insert_attachment(
            array(
                'guid'           => $upload['url'],
                'post_mime_type' => $upload['type'],
                'post_title'     => basename($upload['file']),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ),
            $upload['file']
        );

        if (is_wp_error($attachment_id) || !$attachment_id) {
            wp_die('<p style="color:red;">Upload error.</p>');
        }

        // Use local file path for reading CSV to avoid SSL errors
        $csv = $this->readCSV($upload['file']);

        $successCount = 0;
        $errorCount = 0;
        $rowCount = 0;
        foreach ($csv as $row) {
            if ($rowCount++ == 0) continue; // skip headers

            $email_address = isset($row[0]) ? sanitize_email($row[0]) : '';
            $firstName     = isset($row[1]) ? sanitize_text_field($row[1]) : '';
            $lastName      = isset($row[2]) ? sanitize_text_field($row[2]) : '';
            $password      = wp_generate_password();

            if (empty($email_address) || empty($firstName) || empty($lastName)) {
                $errorCount++;
                echo '<p style="color:red;">Row ' . $rowCount . ' missing required fields.</p>';
                continue;
            }

            $user_data = array(
                'user_login'    => $email_address,
                'user_pass'     => $password,
                'user_email'    => $email_address,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'user_url'      => '',
                'description'   => '',
                'role'          => 'team_subordinate'
            );

            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                $errorCount++;
                echo '<p style="color:red;">' . $errorCount . '. ' . esc_html($firstName . ' ' . $lastName) . ' did not import. Error: ' . esc_html($user_id->get_error_message()) . '</p>';
            } else {
                add_user_meta($user_id, 'teamID', isset($_POST['teamLeaderID']) ? intval($_POST['teamLeaderID']) : 0);
                wp_new_user_notification($user_id, null, "both");
                $successCount++;
            }

            if ($rowCount >= 50) {
                echo '<p style="color:red;">Only first 50 users can be imported from CSV file.</p>';
                break;
            }
        }

        echo '<p style="color:green;">Number of successful subordinates imported: ' . $successCount . '</p>';
        ?>
        </div>
        <?php
        wp_delete_attachment($attachment_id, true);
        exit;
    }

    /**
     * Creates a team leader user after WooCommerce payment if not already registered.
     */
    public function create_Team_Leader_After_Payment( $order_id ) {
        // If user is logged in, do nothing because they already have an account
        if ( is_user_logged_in() ) return;

        // Get the newly created order
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Get the billing email address
        $order_email = $order->get_billing_email();

        // Check if there are any users with the billing email as user or email
        $email_exists = email_exists( $order_email );
        $user_exists = username_exists( $order_email );

        // Get the order status (see if the customer has paid)
        $order_status = $order->get_status();

        // Only create user if not exists and order is paid
        if (
            ! $user_exists && 
            ! $email_exists && 
            ( $order->has_status( 'processing' ) || $order->has_status( 'completed' ) )
        ) {
            // Generate random password
            $random_password = wp_generate_password( 12 );

            // Get billing and shipping data
            $first_name = $order->get_billing_first_name();
            $last_name  = $order->get_billing_last_name();
            $role       = 'team_leader';

            // Create new user with email as username, password, and role
            $user_id = wp_insert_user( array(
                'user_email' => $order_email,
                'user_login' => $order_email,
                'user_pass'  => $random_password,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => $role,
            ) );

            if ( ! is_wp_error( $user_id ) ) {
                wp_new_user_notification( $user_id, null, "both" );
                update_user_meta( $user_id, 'guest', 'yes' );

                // User's billing data
                $billing_fields = [
                    'billing_address_1', 'billing_address_2', 'billing_city', 'billing_company',
                    'billing_country', 'billing_state', 'billing_email', 'billing_first_name',
                    'billing_last_name', 'billing_phone', 'billing_postcode'
                ];
                foreach ( $billing_fields as $field ) {
                    update_user_meta( $user_id, $field, $order->{"get_$field"}() );
                }

                // User's shipping data
                $shipping_fields = [
                    'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_company',
                    'shipping_state', 'shipping_country', 'shipping_first_name', 'shipping_last_name',
                    'shipping_method', 'shipping_postcode'
                ];
                foreach ( $shipping_fields as $field ) {
                    // Some shipping fields may not have a getter, fallback to meta if needed
                    $value = method_exists( $order, "get_$field" ) ? $order->{"get_$field"}() : $order->$field;
                    update_user_meta( $user_id, $field, $value );
                }

                // Link past orders to this newly created customer
                wc_update_new_customer_past_orders( $user_id );
            }
        }
    }
}

new emWooTeamManage;




