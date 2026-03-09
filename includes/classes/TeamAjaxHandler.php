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
    /**
     * Enqueues admin styles and scripts for plugin pages.
     */
    public function load_Admin_Styles(){
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        $config = [
            'user-import-controls' => [
                'styles' => [
                    ['team-leader-user-import-styles', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-user-import.css'],
                ],
                'scripts' => [
                    ['team-leader-subordinate-import-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/min/teamSubordinateImport.min.js'],
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
                'scripts' => [
                    ['site-admin-team-leader-script', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/min/siteAdminTeamLeader.min.js'],
                ],
                'localize' => [
                    ['site-admin-team-leader-script', 'siteAdminTeamLeader', [
                        'ajaxurl' => admin_url('admin-ajax.php')
                    ]],
                ],
            ],
            'team-leader-admin' => [
                'styles' => [
                    ['team-leader-admin-styles', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/css/team-leader-admin.css'],
                ],
                'scripts' => [
                    ['team-leader-admin', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/min/teamLeaderAdmin.min.js'],
                    ['team-subordinate-import', '/wp-content/plugins/em-Woo-Team-Manage/admin/assets/js/min/teamSubordinateImport.min.js'],
                ],
                'localize' => [
                    ['team-subordinate-import', 'team_Leader_Form_Submission', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                    ['team-leader-admin', 'handle_edit_subordinate', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                ],
            ],
        ];

        if ($pagenow === 'admin.php' && isset($config[$page])) {
            $entry = $config[$page];
            if (!empty($entry['styles'])) {
                foreach ($entry['styles'] as $style) {
                    wp_enqueue_style($style[0], $style[1], array(), EMWTM_VERSION);
                }
            }
            if (!empty($entry['scripts'])) {
                foreach ($entry['scripts'] as $script) {
                    wp_enqueue_script($script[0], $script[1], array('jquery'), EMWTM_VERSION, true);
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
    * Handles the editing of a subordinate's details via AJAX.
    */
    public function handle_edit_subordinate() {
         error_log('handle_edit_subordinate called'); // Log entry
        if (
            isset($_POST['action']) &&
            $_POST['action'] === 'edit_subordinate' &&
            isset($_POST['edit_user_id']) &&
            check_admin_referer('edit_subordinate_action', 'edit_subordinate_nonce')
        ) {
            $user_id = intval($_POST['edit_user_id']);
            $user_email = sanitize_email($_POST['edit_user_email']);
            $user_name = sanitize_text_field($_POST['edit_user_name']);

            $userdata = [
                'ID' => $user_id,
                'user_email' => $user_email,
                'display_name' => $user_name,
            ];

            $result = wp_update_user($userdata);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            } else {
                wp_send_json_success(['message' => 'User updated successfully']);
            }
        } else {
            wp_send_json_error(['message' => 'Invalid request']);
        }
        wp_die();
    }

    /**
     * AJAX handler to get subordinates of a team leader.
     */
    public function ajax_get_subordinates() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    $leader_id = isset($_POST['leader_id']) ? intval($_POST['leader_id']) : 0;
    if (!$leader_id) {
        wp_send_json_error(['message' => 'Invalid leader ID']);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    $subordinate_ids = $wpdb->get_col($wpdb->prepare("SELECT subordinate_id FROM $table WHERE leader_id = %d", $leader_id));
    $teamSubordinates = [];
    if (!empty($subordinate_ids)) {
        $teamSubordinates = get_users([
            'include' => $subordinate_ids,
            'role__in' => ['team_subordinate']
        ]);
    }
    ob_start();
    if ($teamSubordinates) {
        echo '<ul>';
        foreach ($teamSubordinates as $sub) {
            echo '<li>' . esc_html($sub->user_email) . ' - ' . esc_html($sub->display_name) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<span>No subordinates</span>';
    }
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

}
