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
     * Imports users from a CSV file.
     */
    public function importUsersFromCSV($filepath, $teamLeaderID = 0)
    {
        $csv = $this->readCSV($filepath);
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
                continue;
            }

            $user_data = array(
                'user_login'    => $email_address,
                'user_pass'     => $password,
                'user_email'    => $email_address,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'role'          => 'team_subordinate'
            );

            $user_id = wp_insert_user($user_data);

            if (!is_wp_error($user_id)) {
                add_user_meta($user_id, 'teamID', intval($teamLeaderID));
                wp_new_user_notification($user_id, null, "both");
                $successCount++;
            } else {
                $errorCount++;
            }

            if ($rowCount >= 50) {
                break;
            }
        }
        return ['success' => $successCount, 'error' => $errorCount];
    }
}
