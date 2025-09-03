<?php
declare(strict_types=1);
/**
 * TeamAjaxHandler
 *
 * Handles AJAX requests for team leader actions and user imports.
 * - Enqueues admin styles and scripts
 * - Processes team leader form submissions (delete/resend password)
 * - Emulates team leader view of subordinates
 * - Handles CSV import of subordinate users
 */
namespace emWooTeamManage\init_plugin\Classes;
require_once __DIR__ . '/TeamUserImporter.php';
class TeamAjaxHandler
{
    private $importer;

    public function __construct() {
        $this->importer = new TeamUserImporter();
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
    public function user_import_submission() {
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
        $csv = $this->importer->readCSV($upload['file']);

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
    
}
