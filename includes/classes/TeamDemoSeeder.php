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

    /** Post-meta key that marks the WooCommerce product used for live demo checkouts. */
    const DEMO_PRODUCT_META_KEY = 'emwtm_is_demo_product';
    const DEMO_PRODUCT_SKU      = 'emwtm-demo-team-leader';
    const DEMO_DOWNLOAD_DIR     = 'emwtm-demo-content';
    const DEMO_DOWNLOAD_FILE    = 'team-demo-ebook.txt';

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
     * Also removes the demo WooCommerce product, if one was created.
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

        $this->remove_demo_product();

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Demo product (live purchase → Team Leader provisioning walkthrough)
    // -------------------------------------------------------------------------

    /**
     * Returns the demo product's post ID, or 0 if it doesn't exist.
     */
    public function get_demo_product_id(): int
    {
        $ids = get_posts([
            'post_type'      => 'product',
            'meta_key'       => self::DEMO_PRODUCT_META_KEY,
            'meta_value'     => '1',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 1,
        ]);

        return !empty($ids) ? (int) $ids[0] : 0;
    }

    /**
     * Returns the current demo product state for the admin UI.
     *
     * @return array{product_id:int, checkout_url:string}
     */
    public function get_product_status(): array
    {
        $product_id = $this->get_demo_product_id();

        return [
            'product_id'   => $product_id,
            'checkout_url' => $product_id ? (string) get_permalink($product_id) : '',
        ];
    }

    /**
     * Creates a $0 virtual WooCommerce product for exercising the real
     * purchase → Team Leader provisioning flow. Idempotent.
     *
     * @return array{success:bool, product_id:int, checkout_url:string, message:string}
     */
    public function create_demo_product(): array
    {
        if (!class_exists('WC_Product_Simple')) {
            return [
                'success'      => false,
                'product_id'   => 0,
                'checkout_url' => '',
                'message'      => 'WooCommerce must be active to create the demo product.',
            ];
        }

        $existing_id = $this->get_demo_product_id();
        if ($existing_id) {
            return [
                'success'      => true,
                'product_id'   => $existing_id,
                'checkout_url' => (string) get_permalink($existing_id),
                'message'      => 'Demo product already exists.',
            ];
        }

        $product = new \WC_Product_Simple();
        $product->set_name('Team Leader Demo Purchase');
        $product->set_regular_price('0');
        $product->set_price('0');
        $product->set_virtual(true);
        $product->set_catalog_visibility('hidden');
        $product->set_sku(self::DEMO_PRODUCT_SKU);
        $product->set_status('publish');

        $download_file = $this->ensure_demo_download_file();
        if ($download_file !== '') {
            $download = new \WC_Product_Download();
            $download->set_id(md5($download_file));
            $download->set_name('Team Demo eBook');
            $download->set_file($download_file);

            $product->set_downloadable(true);
            $product->set_downloads([$download]);
        }

        $product_id = $product->save();

        update_post_meta($product_id, self::DEMO_PRODUCT_META_KEY, '1');

        return [
            'success'      => true,
            'product_id'   => $product_id,
            'checkout_url' => (string) get_permalink($product_id),
            'message'      => $download_file !== ''
                ? 'Demo product created with downloadable content.'
                : 'Demo product created, but the demo download file could not be written.',
        ];
    }

    /**
     * Writes the demo download file into uploads and makes sure WooCommerce
     * will accept it. Returns the file path, or an empty string on failure.
     */
    private function ensure_demo_download_file(): string
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return '';
        }

        $directory = trailingslashit($upload_dir['basedir']) . self::DEMO_DOWNLOAD_DIR;
        if (!wp_mkdir_p($directory)) {
            return '';
        }

        $file_path = trailingslashit($directory) . self::DEMO_DOWNLOAD_FILE;
        if (!file_exists($file_path)) {
            $contents = "EM Woo Team Manage — Demo Content\n\n"
                . "This file stands in for the real downloadable product a team leader would buy.\n"
                . "Every subordinate on the leader's team can download it without buying it separately.\n";
            if (file_put_contents($file_path, $contents) === false) {
                return '';
            }
        }

        $directory_url = trailingslashit($upload_dir['baseurl']) . self::DEMO_DOWNLOAD_DIR;
        $this->approve_download_directory($directory_url);

        return $file_path;
    }

    /**
     * Registers a directory with WooCommerce's approved download directories,
     * which otherwise rejects programmatically added download files.
     */
    private function approve_download_directory(string $directory_url): void
    {
        if (!function_exists('wc_get_container')) {
            return;
        }

        $register_class = '\Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register';
        if (!class_exists($register_class)) {
            return;
        }

        try {
            $register = wc_get_container()->get($register_class);
            if (!$register->approved_directory_exists($directory_url)) {
                $register->add_approved_directory($directory_url);
            }
        } catch (\Exception $e) {
            // Approved directories are unavailable; the product is still created.
        }
    }

    /**
     * Permanently removes the demo product and its download file, if present.
     */
    public function remove_demo_product(): bool
    {
        $this->remove_demo_download_file();

        $product_id = $this->get_demo_product_id();
        if (!$product_id) {
            return false;
        }

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->delete(true);
                return true;
            }
        }

        return (bool) wp_delete_post($product_id, true);
    }

    /**
     * Deletes the demo download file and its directory.
     */
    private function remove_demo_download_file(): void
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return;
        }

        $directory = trailingslashit($upload_dir['basedir']) . self::DEMO_DOWNLOAD_DIR;
        $file_path = trailingslashit($directory) . self::DEMO_DOWNLOAD_FILE;

        if (file_exists($file_path)) {
            unlink($file_path);
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
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

    /**
     * AJAX: create the $0 demo product for live checkout testing.
     * Expects POST: _nonce.
     */
    public function ajax_create_demo_product(): void
    {
        if (!check_ajax_referer('emwtm_demo_product', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $result = $this->create_demo_product();
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: remove the demo product.
     * Expects POST: _nonce.
     */
    public function ajax_remove_demo_product(): void
    {
        if (!check_ajax_referer('emwtm_demo_product', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $removed = $this->remove_demo_product();

        wp_send_json_success(['removed' => $removed, 'status' => $this->get_product_status()]);
    }
}
