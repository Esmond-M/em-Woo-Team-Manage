<?php
declare(strict_types=1);
/**
 * TeamUserImporter
 *
 * Handles reading CSV files and importing subordinate users for team leaders.
 * - Reads CSV files and yields rows
 * - Imports users from CSV, assigns them to a team leader
 * - Limits import to 50 users per file
 */
namespace emWooTeamManage\init_plugin\Classes;

class TeamUserImporter
{
    /**
     * Sends a "you've been added to a team" notification email (used during CSV import).
     */
    private function send_team_added_notification(int $user_id, int $leader_id): void {
        $subordinate = get_user_by('id', $user_id);
        $leader      = get_user_by('id', $leader_id);
        if (!$subordinate) return;
        $site_name   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $leader_name = $leader ? (trim($leader->first_name . ' ' . $leader->last_name) ?: $leader->user_login) : 'your team leader';
        $subject     = sprintf('[%s] You have been added to a team', $site_name);
        $message     = sprintf(
            "Hi %s,\n\nYou have been added to %s's team on %s.\n\nIf you have any questions, please contact your team leader.\n\nRegards,\n%s",
            $subordinate->first_name ?: $subordinate->user_login,
            $leader_name,
            $site_name,
            $site_name
        );
        wp_mail($subordinate->user_email, $subject, $message);
    }

    /**
     * Opens a CSV file and returns a Generator that yields rows, or false on failure.
     *
     * Separating the file-open check from the generator body is necessary because
     * any function containing `yield` is implicitly a generator in PHP and can only
     * return a Generator object to the caller — never a plain false.
     *
     * @return \Generator|false
     */
    public function readCSV($filename, $delimiter = ',')
    {
        $handle = @fopen($filename, 'r');
        if ($handle === false) {
            return false;
        }

        return $this->iterateCsvRows($handle, $delimiter);
    }

    /**
     * Generator that yields rows from an already-open CSV file handle.
     */
    private function iterateCsvRows($handle, string $delimiter): \Generator
    {
        while (($data = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
            yield $data;
        }
        fclose($handle);
    }

    /**
     * Imports one CSV data row for a team leader.
     *
     * @return array{success:bool,message:string,user_id:int}
     */
    public function import_row(array $row, int $leader_id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
        $email_address = isset($row[0]) ? sanitize_email($row[0]) : '';
        $first_name = isset($row[1]) ? sanitize_text_field($row[1]) : '';
        $last_name = isset($row[2]) ? sanitize_text_field($row[2]) : '';

        if (empty($email_address) || empty($first_name) || empty($last_name)) {
            return ['success' => false, 'message' => 'Missing required fields.', 'user_id' => 0];
        }

        $current_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE leader_id = %d",
            $leader_id
        ));
        if ($current_count >= TeamManageCore::get_max_subordinates()) {
            return [
                'success' => false,
                'message' => 'Maximum number of subordinates (' . TeamManageCore::get_max_subordinates() . ') reached for this team leader.',
                'user_id' => 0,
            ];
        }

        $user_id = wp_insert_user([
            'user_login' => $email_address,
            'user_pass' => wp_generate_password(),
            'user_email' => $email_address,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_url' => '',
            'description' => '',
            'role' => 'team_subordinate',
        ]);

        if (is_wp_error($user_id)) {
            return [
                'success' => false,
                'message' => $user_id->get_error_message(),
                'user_id' => 0,
            ];
        }

        add_user_meta($user_id, 'teamID', $leader_id);
        wp_new_user_notification($user_id, null, 'both');
        $this->send_team_added_notification((int) $user_id, $leader_id);
        $wpdb->insert($table, ['leader_id' => $leader_id, 'subordinate_id' => (int) $user_id], ['%d', '%d']);

        return ['success' => true, 'message' => $first_name . ' ' . $last_name, 'user_id' => (int) $user_id];
    }

    /**
     * Handles AJAX CSV import of subordinate users for a team leader.
     */
    public function user_import_submission() {
        // Auth: must be a team leader or admin
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            wp_die('<p style="color:red;">Unauthorized.</p>');
        }
        // Nonce verification
        $nonce = isset($_POST['_import_nonce']) ? sanitize_text_field(wp_unslash($_POST['_import_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'user_import_submission')) {
            wp_die('<p style="color:red;">Security check failed.</p>');
        }

        // Resolve and validate leader ID before touching any files.
        if (current_user_can('manage_options') && !empty($_POST['teamLeaderID'])) {
            $posted_leader = get_user_by('id', (int) $_POST['teamLeaderID']);
            if (!$posted_leader || !in_array('team_leader', (array) $posted_leader->roles, true)) {
                wp_die('<p style="color:red;">Invalid team leader selected.</p>');
            }
            $resolved_leader_id = (int) $_POST['teamLeaderID'];
        } else {
            $resolved_leader_id = get_current_user_id();
        }

        // Load required WordPress files
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        ?>
        <div class="user-upload-results-contain">
        <?php
        // Validate file upload
        if (empty($_FILES['csvUpload']) || $_FILES['csvUpload']['error'] !== UPLOAD_ERR_OK) {
            wp_die('<p style="color:red;">File does not exist or upload error.</p>');
        }
        $file_size = (int) $_FILES['csvUpload']['size'];
        if ($file_size > 5242880) {
            wp_die('<p>File too large. File must be less than 5 megabytes.</p>');
        }

        // Move to a private temp file — never touches the media library
        $tmp_file = wp_tempnam('emwtm_csv_');
        if (!move_uploaded_file($_FILES['csvUpload']['tmp_name'], $tmp_file)) {
            wp_die('<p style="color:red;">Could not process the uploaded file.</p>');
        }

        // Use local file path for reading CSV
        $csv = $this->readCSV($tmp_file);

        $successCount = 0;
        $errorCount = 0;
        $rowCount = 0;
        $dataRowCount = 0;
        foreach ($csv as $row) {
            if ($rowCount++ == 0) continue; // skip headers
            if ($dataRowCount >= 50) {
                echo '<p style="color:orange;">Only the first 50 users can be imported per CSV file.</p>';
                break;
            }
            $dataRowCount++;
            $result = $this->import_row($row, $resolved_leader_id);

            if (!$result['success']) {
                if (strpos($result['message'], 'Maximum number of subordinates') === 0) {
                    echo '<p style="color:red;">' . esc_html($result['message']) . '</p>';
                    @unlink($tmp_file);
                    wp_die();
                }
                $errorCount++;
                echo '<p style="color:red;">' . $errorCount . '. Row ' . $rowCount . ' did not import. Error: ' . esc_html($result['message']) . '</p>';
            } else {
                $successCount++;
            }


        }

        echo '<p style="color:green;">Number of successful subordinates imported: ' . $successCount . '</p>';
        ?>
        </div>
        <?php
        @unlink($tmp_file);
        exit;
    }
}
