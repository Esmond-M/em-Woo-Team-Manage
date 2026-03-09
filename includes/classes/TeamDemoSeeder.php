<?php
declare(strict_types=1);
/**
 * TeamDemoSeeder
 *
 * Creates and removes demo team leaders / subordinates for presentation or testing.
 * All demo accounts are tagged with user-meta so they can be cleanly wiped.
 */
namespace emWooTeamManage\init_plugin\Classes;

class TeamDemoSeeder
{
    /** User-meta key that marks an account as demo data. */
    const DEMO_META_KEY    = 'emwtm_is_demo';
    const MAX_LEADERS      = 2;
    const MAX_SUBORDINATES = 50;

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------

    /**
     * Returns IDs of every demo user (any role).
     *
     * @return int[]
     */
    public function get_demo_user_ids(): array
    {
        return (array) get_users([
            'meta_key'   => self::DEMO_META_KEY,
            'meta_value' => '1',
            'fields'     => 'ID',
            'number'     => -1,
        ]);
    }

    /**
     * Returns a summary of the current demo state.
     *
     * @return array{leaders:int, subordinates:int}
     */
    public function get_status(): array
    {
        $leaders = get_users([
            'role'       => 'team_leader',
            'meta_key'   => self::DEMO_META_KEY,
            'meta_value' => '1',
            'fields'     => 'ID',
            'number'     => -1,
        ]);

        $subs = get_users([
            'role'       => 'team_subordinate',
            'meta_key'   => self::DEMO_META_KEY,
            'meta_value' => '1',
            'fields'     => 'ID',
            'number'     => -1,
        ]);

        return [
            'leaders'      => count($leaders),
            'subordinates' => count($subs),
        ];
    }

    // -------------------------------------------------------------------------
    // Seed
    // -------------------------------------------------------------------------

    /**
     * Creates up to MAX_LEADERS demo team leaders, each with $subs_per_leader
     * demo subordinates, and links them in the relationships table.
     * Existing demo accounts are skipped (idempotent).
     *
     * @param int $subs_per_leader 1–MAX_SUBORDINATES.
     * @return array{leaders_created:int, subs_created:int, skipped:int}
     */
    public function seed(int $subs_per_leader = self::MAX_SUBORDINATES): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';

        $subs_per_leader = max(1, min(self::MAX_SUBORDINATES, $subs_per_leader));

        $leaders_created = 0;
        $subs_created    = 0;
        $skipped         = 0;

        for ($l = 1; $l <= self::MAX_LEADERS; $l++) {
            $leader_email = "demo.leader.{$l}@example.com";
            $leader_login = "demo-leader-{$l}";

            $leader_id = email_exists($leader_email);
            if (!$leader_id) {
                $result = wp_insert_user([
                    'user_login'   => $leader_login,
                    'user_email'   => $leader_email,
                    'user_pass'    => wp_generate_password(16),
                    'first_name'   => 'Demo',
                    'last_name'    => "Leader {$l}",
                    'display_name' => "Demo Leader {$l}",
                    'role'         => 'team_leader',
                ]);

                if (is_wp_error($result)) {
                    $skipped++;
                    continue;
                }

                $leader_id = (int) $result;
                update_user_meta($leader_id, self::DEMO_META_KEY, '1');
                $leaders_created++;
            } else {
                $leader_id = (int) $leader_id;
            }

            for ($s = 1; $s <= $subs_per_leader; $s++) {
                $sub_email = sprintf('demo.sub.%d.%03d@example.com', $l, $s);
                $sub_login = sprintf('demo-sub-%d-%03d', $l, $s);

                $sub_id = email_exists($sub_email);
                if (!$sub_id) {
                    $result = wp_insert_user([
                        'user_login'   => $sub_login,
                        'user_email'   => $sub_email,
                        'user_pass'    => wp_generate_password(16),
                        'first_name'   => 'Demo',
                        'last_name'    => "Sub {$l}-{$s}",
                        'display_name' => "Demo Sub {$l}-{$s}",
                        'role'         => 'team_subordinate',
                    ]);

                    if (is_wp_error($result)) {
                        $skipped++;
                        continue;
                    }

                    $sub_id = (int) $result;
                    update_user_meta($sub_id, self::DEMO_META_KEY, '1');
                    $subs_created++;
                } else {
                    $sub_id = (int) $sub_id;
                }

                // Link sub → leader only if the relationship doesn't exist yet.
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE leader_id = %d AND subordinate_id = %d",
                    $leader_id,
                    $sub_id
                ));

                if (!$exists) {
                    $wpdb->insert(
                        $table,
                        ['leader_id' => $leader_id, 'subordinate_id' => $sub_id],
                        ['%d', '%d']
                    );
                }
            }
        }

        return compact('leaders_created', 'subs_created', 'skipped');
    }

    // -------------------------------------------------------------------------
    // Clear
    // -------------------------------------------------------------------------

    /**
     * Deletes every demo user and removes their rows from the relationships table.
     *
     * @return int Number of WP users deleted.
     */
    public function clear(): int
    {
        global $wpdb;
        $table    = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
        $demo_ids = $this->get_demo_user_ids();
        $deleted  = 0;

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        foreach ($demo_ids as $uid) {
            $uid = (int) $uid;
            $wpdb->delete($table, ['leader_id'      => $uid], ['%d']);
            $wpdb->delete($table, ['subordinate_id' => $uid], ['%d']);
            if (wp_delete_user($uid)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /**
     * AJAX: seed demo data.
     * Expects POST: _nonce, subs_per_leader (optional).
     */
    public function ajax_seed(): void
    {
        if (!check_ajax_referer('emwtm_demo_seed', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $subs   = isset($_POST['subs_per_leader']) ? (int) $_POST['subs_per_leader'] : self::MAX_SUBORDINATES;
        $result = $this->seed($subs);
        $result['status'] = $this->get_status();

        wp_send_json_success($result);
    }

    /**
     * AJAX: clear all demo data.
     * Expects POST: _nonce.
     */
    public function ajax_clear(): void
    {
        if (!check_ajax_referer('emwtm_demo_clear', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $deleted = $this->clear();

        wp_send_json_success(['deleted' => $deleted, 'status' => $this->get_status()]);
    }
}
