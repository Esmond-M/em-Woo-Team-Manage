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
     * Reads a CSV file and yields each row as an array.
     */
    public function readCSV($filename, $delimiter = ',')
    {
        $handle = fopen($filename, "r");
        if ($handle === false) {
            return false;
        }

        while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
            yield $data;
        }

        fclose($handle);
    }

    /**
     * Handles AJAX CSV import of subordinate users for a team leader.
     */
    public function user_import_submission() {
        // Auth: must be a team leader, admin, or emulating via admin
        if (!current_user_can('team_leader') && !current_user_can('manage_options')) {
            wp_die('<p style="color:red;">Unauthorized.</p>');
        }
        // Nonce verification
        $nonce = isset($_POST['_import_nonce']) ? sanitize_text_field(wp_unslash($_POST['_import_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'user_import_submission')) {
            wp_die('<p style="color:red;">Security check failed.</p>');
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
            global $wpdb;
            // Define table and leader_id here
            $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
            $leader_id = isset($_POST['teamLeaderID']) ? intval($_POST['teamLeaderID']) : 0;

            // Check current subordinate count for this leader
            $current_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE leader_id = %d",
                $leader_id
            ));
            $max_subordinates = 200;
            if ($current_count >= $max_subordinates) {
                echo '<p style="color:red;">Maximum number of subordinates ('.$max_subordinates.') reached for this team leader. No more can be imported.</p>';
                @unlink($tmp_file);
                wp_die();
            }

            // ...now process the row and create user...
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
                // Insert leader/subordinate relationship into custom table
                $wpdb->insert($table, [
                    'leader_id' => $leader_id,
                    'subordinate_id' => intval($user_id)
                ]);
                if ($wpdb->last_error) {
                    error_log('DB Insert Error: ' . $wpdb->last_error);
                }
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
