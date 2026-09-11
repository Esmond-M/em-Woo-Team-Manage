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
     * Prefix spreadsheet formula-like CSV cells so they remain text values.
     */
    public static function sanitize_csv_cell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }

    /**
     * Sends a plain-text notification email to a subordinate.
     *
     * @param int    $user_id  WP user ID of the subordinate.
     * @param string $type     'added' or 'removed'.
     * @param int    $leader_id WP user ID of the team leader.
     */
    private function send_team_notification(int $user_id, string $type, int $leader_id): void {
        $subordinate = get_user_by('id', $user_id);
        $leader      = get_user_by('id', $leader_id);
        if (!$subordinate) return;

        $site_name    = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $leader_name  = $leader ? trim($leader->first_name . ' ' . $leader->last_name) ?: $leader->user_login : 'your team leader';

        if ($type === 'added') {
            $subject = sprintf('[%s] You have been added to a team', $site_name);
            $message = sprintf(
                "Hi %s,\n\nYou have been added to %s's team on %s.\n\nIf you have any questions, please contact your team leader.\n\nRegards,\n%s",
                $subordinate->first_name ?: $subordinate->user_login,
                $leader_name,
                $site_name,
                $site_name
            );
        } else {
            $subject = sprintf('[%s] You have been removed from a team', $site_name);
            $message = sprintf(
                "Hi %s,\n\nYou have been removed from %s's team on %s.\n\nIf you believe this is a mistake, please contact your team leader.\n\nRegards,\n%s",
                $subordinate->first_name ?: $subordinate->user_login,
                $leader_name,
                $site_name,
                $site_name
            );
        }

        wp_mail($subordinate->user_email, $subject, $message);
    }

    /**
     * Enqueues admin styles and scripts for plugin pages.
     */
    public function load_Admin_Styles(){
        global $pagenow;
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        $team_workflow_page = in_array($page, ['user-import-controls', 'team-leader-admin'], true);
        $configured_admin_page = $team_workflow_page || $page === 'site-admin-team-leader-admin';
        $allowed = $team_workflow_page
            ? (current_user_can('team_leader') || current_user_can('manage_options'))
            : ($configured_admin_page && current_user_can('manage_options'));
        if ($pagenow === 'admin.php' && !$allowed) {
            return;
        }

        $config = [
            'user-import-controls' => [
                'styles' => [
                    ['team-leader-user-import-styles', plugins_url('admin/assets/css/team-leader-user-import.css', EMWTM_PLUGIN_FILE)],
                ],
                'scripts' => [
                    ['team-leader-subordinate-import-script', plugins_url('admin/assets/js/min/teamSubordinateImport.min.js', EMWTM_PLUGIN_FILE)],
                ],
                'localize' => [
                    ['team-leader-subordinate-import-script', 'user_import_submission', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('user_import_submission'),
                    ]],
                    ['team-leader-subordinate-import-script', 'add_single_subordinate', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                ],
            ],
            'site-admin-team-leader-admin' => [
                'styles' => [
                    ['team-leader-user-import-styles', plugins_url('admin/assets/css/team-leader-user-import.css', EMWTM_PLUGIN_FILE)],
                    ['site-admin-team-leader-styles', plugins_url('admin/assets/css/site-admin-team-leader.css', EMWTM_PLUGIN_FILE)],
                ],
                'scripts' => [
                    ['site-admin-team-leader-script', plugins_url('admin/assets/js/min/siteAdminTeamLeader.min.js', EMWTM_PLUGIN_FILE)],
                ],
                'localize' => [
                    ['site-admin-team-leader-script', 'siteAdminTeamLeader', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('get_subordinates'),
                    ]],
                ],
            ],
            'team-leader-admin' => [
                'styles' => [
                    ['team-leader-admin-styles', plugins_url('admin/assets/css/team-leader-admin.css', EMWTM_PLUGIN_FILE)],
                ],
                'scripts' => [
                    ['team-leader-admin', plugins_url('admin/assets/js/min/teamLeaderAdmin.min.js', EMWTM_PLUGIN_FILE)],
                    ['team-subordinate-import', plugins_url('admin/assets/js/min/teamSubordinateImport.min.js', EMWTM_PLUGIN_FILE)],
                ],
                'localize' => [
                    ['team-subordinate-import', 'team_Leader_Form_Submission', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                    ['team-leader-admin', 'handle_edit_subordinate', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    ]],
                    ['team-leader-admin', 'export_team_csv', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('export_team_csv'),
                    ]],
                    ['team-leader-admin', 'delete_user_account', [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('emwtm_delete_user_account'),
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
     * Handles AJAX submission for team leader actions (remove/resend password).
     */
    public function team_Leader_Form_Submission() {
        // Verify nonce and role
        if (
            empty($_POST['team_Leader_Form_Submission_nonce_field']) ||
            !wp_verify_nonce($_POST['team_Leader_Form_Submission_nonce_field'], 'team_Leader_Form_Submission')
        ) {
            wp_die('', '', ['response' => 403]);
        }
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            wp_die('', '', ['response' => 403]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
        if (current_user_can('manage_options') && !empty($_POST['leaderID'])) {
            $posted_leader = get_user_by('id', (int) $_POST['leaderID']);
            $current_leader_id = ($posted_leader && in_array('team_leader', (array) $posted_leader->roles))
                ? (int) $_POST['leaderID']
                : get_current_user_id();
        } else {
            $current_leader_id = get_current_user_id();
        }

        // Check if user IDs are provided
        if (!empty($_POST['userID'])) {
            ?>
            <div class="user-deletion-password-contain">
            <?php
            $action = isset($_POST['teamLeaderSelectOption']) ? sanitize_text_field($_POST['teamLeaderSelectOption']) : '';
            if ($action === 'delete' && (
                empty($_POST['confirm_removal']) ||
                sanitize_text_field(wp_unslash($_POST['confirm_removal'])) !== '1'
            )) {
                echo '<p class="newpost-error">Please confirm that you want to remove the selected users from this team.</p>';
                echo '<button class="refresh-btn" onClick="window.location.reload();">Refresh Page</button>';
                echo '</div>';
                wp_die();
            }
            foreach ($_POST['userID'] as $raw_id) {
                $id = (int) $raw_id;
                // Verify this subordinate belongs to the current leader
                $is_subordinate = (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE leader_id = %d AND subordinate_id = %d",
                    $current_leader_id, $id
                ));
                if (!$is_subordinate) {
                    echo '<p class="newpost-error">User ID ' . esc_html($id) . ' is not your subordinate.</p>';
                    continue;
                }
                $user = get_user_by('id', $id);
                if (!$user) {
                    echo '<p class="newpost-error">User ID ' . esc_html($id) . ' not found.</p>';
                    continue;
                }

                if ($action === 'delete') {
                    $removed = $wpdb->delete(
                        $table,
                        ['leader_id' => $current_leader_id, 'subordinate_id' => $id],
                        ['%d', '%d']
                    );
                    if ($removed === false) {
                        echo '<p class="newpost-error">Could not remove User ID ' . esc_html($id) . ' from the team.</p>';
                        continue;
                    }
                    $remaining_teams = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table WHERE subordinate_id = %d",
                        $id
                    ));
                    if ((string) get_user_meta($id, 'teamID', true) === (string) $current_leader_id) {
                        if ($remaining_teams === 0) {
                            delete_user_meta($id, 'teamID');
                            TeamManageCore::restore_customer_role_after_team_removal($id);
                        } else {
                            $remaining_leader_id = (int) $wpdb->get_var($wpdb->prepare(
                                "SELECT leader_id FROM $table WHERE subordinate_id = %d ORDER BY id ASC LIMIT 1",
                                $id
                            ));
                            update_user_meta($id, 'teamID', $remaining_leader_id);
                        }
                    }
                    $this->send_team_notification($id, 'removed', $current_leader_id);
                    echo '<p class="newpost-success">User: ' . esc_html($user->user_login) . ' removed from the team</p>';
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
        wp_die();
    }

    /**
     * Permanently deletes a pure team subordinate account for site admins.
     */
    public function delete_user_account(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '', ['response' => 403]);
        }
        if (!check_ajax_referer('emwtm_delete_user_account', '_nonce', false)) {
            wp_die('Security check failed', '', ['response' => 403]);
        }
        if (
            empty($_POST['confirm_deletion']) ||
            sanitize_text_field(wp_unslash($_POST['confirm_deletion'])) !== '1'
        ) {
            wp_die('Please confirm permanent account deletion.', '', ['response' => 400]);
        }

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = $user_id ? get_user_by('id', $user_id) : false;
        if (!$user || !in_array('team_subordinate', (array) $user->roles, true) || in_array('team_leader', (array) $user->roles, true)) {
            wp_die('Only pure team subordinate accounts can be permanently deleted.', '', ['response' => 400]);
        }

        if (!wp_delete_user($user_id)) {
            wp_die('Could not delete the user account.', '', ['response' => 500]);
        }

        wp_die('User account permanently deleted.');
    }

    /**
    * Handles the editing of a subordinate's details via AJAX.
    */
    public function handle_edit_subordinate() {
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            wp_die();
        }

        if (
            !isset($_POST['action']) ||
            $_POST['action'] !== 'edit_subordinate' ||
            !isset($_POST['edit_user_id'])
        ) {
            wp_send_json_error(['message' => 'Invalid request']);
            wp_die();
        }

        check_ajax_referer('edit_subordinate_action', 'edit_subordinate_nonce');

        $user_id = intval($_POST['edit_user_id']);

        // Site admins have full control — skip ownership check.
        if (!current_user_can('manage_options')) {
            // Verify ownership: subordinate must belong to the current team leader.
            global $wpdb;
            $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';

            $is_subordinate = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE leader_id = %d AND subordinate_id = %d",
                get_current_user_id(),
                $user_id
            ));

            if (!$is_subordinate) {
                wp_send_json_error(['message' => 'User is not your subordinate']);
                wp_die();
            }
        }

        $user_email = sanitize_email($_POST['edit_user_email'] ?? '');
        $user_name  = sanitize_text_field($_POST['edit_user_name'] ?? '');

        $userdata = [
            'ID'           => $user_id,
            'user_email'   => $user_email,
            'display_name' => $user_name,
        ];

        $result = wp_update_user($userdata);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'User updated successfully']);
        }
        wp_die();
    }

    /**
     * AJAX handler to export the current leader's subordinates as a CSV download.
     */
    public function export_team_csv() {
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            wp_die('Unauthorized', '', ['response' => 403]);
        }
        check_ajax_referer('export_team_csv', '_export_nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
        if (current_user_can('manage_options') && !empty($_POST['leaderID'])) {
            $posted_leader = get_user_by('id', (int) $_POST['leaderID']);
            $leader_id = ($posted_leader && in_array('team_leader', (array) $posted_leader->roles))
                ? (int) $_POST['leaderID']
                : get_current_user_id();
        } else {
            $leader_id = get_current_user_id();
        }
        $sub_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT subordinate_id FROM $table WHERE leader_id = %d", $leader_id
        ));

        $rows = [];
        if (!empty($sub_ids)) {
            $rows = get_users(['include' => $sub_ids, 'role__in' => ['team_subordinate']]);
        }

        $filename = 'team-export-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['email_address', 'first_name', 'last_name']);
        foreach ($rows as $user) {
            fputcsv($out, [
                self::sanitize_csv_cell($user->user_email),
                self::sanitize_csv_cell($user->first_name),
                self::sanitize_csv_cell($user->last_name),
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * AJAX handler to add a single subordinate by email.
     */
    public function add_single_subordinate() {
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            echo '<p class="newpost-error">Unauthorized.</p>';
            wp_die();
        }
        $nonce = isset($_POST['_single_subordinate_nonce']) ? sanitize_text_field(wp_unslash($_POST['_single_subordinate_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'add_single_subordinate')) {
            echo '<p class="newpost-error">Security check failed.</p>';
            wp_die();
        }

        $first_name = isset($_POST['single_first_name']) ? sanitize_text_field($_POST['single_first_name']) : '';
        $last_name  = isset($_POST['single_last_name'])  ? sanitize_text_field($_POST['single_last_name'])  : '';
        $email      = isset($_POST['single_email'])      ? sanitize_email($_POST['single_email'])           : '';
        $leader_id  = isset($_POST['teamLeaderID'])      ? (int) $_POST['teamLeaderID']                     : 0;

        if (empty($first_name) || empty($last_name) || empty($email) || !is_email($email)) {
            echo '<p class="newpost-error">Please fill in all fields with a valid email.</p>';
            wp_die();
        }

        // Authorise: team leaders can only add to themselves
        if (current_user_can('team_leader') && !current_user_can('manage_options')) {
            $leader_id = get_current_user_id();
        } elseif (current_user_can('manage_options')) {
            $posted_leader = get_user_by('id', $leader_id);
            if (!$posted_leader || !in_array('team_leader', (array) $posted_leader->roles, true)) {
                echo '<p class="newpost-error">Invalid team leader selected.</p>';
                wp_die();
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';

        $current_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE leader_id = %d", $leader_id
        ));
        if ($current_count >= TeamManageCore::get_max_subordinates()) {
            echo '<p class="newpost-error">Maximum subordinate limit (' . TeamManageCore::get_max_subordinates() . ') reached.</p>';
            wp_die();
        }

        if (email_exists($email)) {
            echo '<p class="newpost-error">A user with that email already exists.</p>';
            wp_die();
        }

        $password = wp_generate_password();
        $user_id  = wp_insert_user([
            'user_login' => $email,
            'user_pass'  => $password,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'role'       => 'team_subordinate',
        ]);

        if (is_wp_error($user_id)) {
            echo '<p class="newpost-error">' . esc_html($user_id->get_error_message()) . '</p>';
            wp_die();
        }

        $relationship_created = $wpdb->insert(
            $table,
            ['leader_id' => $leader_id, 'subordinate_id' => $user_id],
            ['%d', '%d']
        );
        if ($relationship_created === false) {
            wp_delete_user($user_id);
            echo '<p class="newpost-error">Could not add the subordinate to the team.</p>';
            wp_die();
        }

        add_user_meta($user_id, 'teamID', $leader_id);
        wp_new_user_notification($user_id, null, 'both');
        $this->send_team_notification($user_id, 'added', $leader_id);
        do_action('emwtm_subordinate_added', $leader_id, (int) $user_id);

        echo '<p class="newpost-success">' . esc_html($first_name . ' ' . $last_name) . ' (' . esc_html($email) . ') added successfully.</p>';
        wp_die();
    }

    /**
     * AJAX handler to download the sample import CSV.
     * Requires the user to be logged in with at least team_leader or manage_options capability.
     */
    public function sample_csv(): void
    {
        if (!check_ajax_referer('emwtm_sample_csv', '_nonce', false)) {
            wp_die('', '', ['response' => 403]);
        }
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            wp_die('', '', ['response' => 403]);
        }

        $csv = file_get_contents(plugin_dir_path(EMWTM_PLUGIN_FILE) . 'admin/assets/sample-user-import.csv');

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sample-user-import.csv"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $csv;
        exit;
    }

    /**
     * AJAX handler to get subordinates of a team leader.
     */
    public function ajax_get_subordinates() {
        if (!check_ajax_referer('get_subordinates', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
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
