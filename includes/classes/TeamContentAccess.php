<?php
declare(strict_types=1);
/**
 * TeamContentAccess
 *
 * Tracks which subordinates have access to a product because their team
 * leader purchased it. This class only maintains the access ledger — it does
 * not grant real permissions in WooCommerce or any other system. A separate
 * adapter reads this ledger and applies it to whatever system actually
 * restricts the content (WooCommerce downloads, a membership plugin, etc.).
 */
namespace emWooTeamManage\init_plugin\Classes;

class TeamContentAccess
{
    /**
     * Returns the ledger table name.
     */
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'emwtm_team_content_grants';
    }

    /**
     * Records that a subordinate has access to a product via a leader's order.
     * Idempotent — returns the existing grant ID if one is already active.
     */
    public function grant(int $leader_id, int $order_id, int $product_id, int $subordinate_id): int
    {
        global $wpdb;
        $table = $this->table();

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE leader_id = %d AND order_id = %d AND product_id = %d AND subordinate_id = %d AND revoked_at IS NULL",
            $leader_id,
            $order_id,
            $product_id,
            $subordinate_id
        ));
        if ($existing_id) {
            return $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'leader_id'      => $leader_id,
                'order_id'       => $order_id,
                'product_id'     => $product_id,
                'subordinate_id' => $subordinate_id,
                'granted_at'     => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Marks every active grant tied to an order as revoked.
     *
     * @return int Number of grants revoked.
     */
    public function revoke_by_order(int $order_id): int
    {
        global $wpdb;
        $table = $this->table();

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = %s WHERE order_id = %d AND revoked_at IS NULL",
            current_time('mysql'),
            $order_id
        ));
    }

    /**
     * Marks every active grant for a leader/subordinate pair as revoked.
     *
     * @return int Number of grants revoked.
     */
    public function revoke_by_subordinate(int $leader_id, int $subordinate_id): int
    {
        global $wpdb;
        $table = $this->table();

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = %s WHERE leader_id = %d AND subordinate_id = %d AND revoked_at IS NULL",
            current_time('mysql'),
            $leader_id,
            $subordinate_id
        ));
    }

    /**
     * Whether a user currently has team-granted access to a product.
     */
    public function has_access(int $user_id, int $product_id): bool
    {
        global $wpdb;
        $table = $this->table();

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE subordinate_id = %d AND product_id = %d AND revoked_at IS NULL",
            $user_id,
            $product_id
        ));

        return $count > 0;
    }

    /**
     * Returns every active grant row for a leader.
     *
     * @return array<int, array{id:int, order_id:int, product_id:int, subordinate_id:int}>
     */
    public function get_active_grants_for_leader(int $leader_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id, product_id, subordinate_id FROM {$table} WHERE leader_id = %d AND revoked_at IS NULL",
            $leader_id
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Returns every active grant row for an order, so an adapter can work out
     * which real permissions to remove before the order's grants are revoked.
     *
     * @return array<int, array{id:int, leader_id:int, product_id:int, subordinate_id:int}>
     */
    public function get_active_grants_for_order(int $order_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, leader_id, product_id, subordinate_id FROM {$table} WHERE order_id = %d AND revoked_at IS NULL",
            $order_id
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Filter callback for `emwtm_user_has_team_access`, letting any code ask
     * whether a user has access to a product through their team leader.
     */
    public function filter_user_has_team_access(bool $has_access, int $user_id, int $product_id): bool
    {
        return $has_access || $this->has_access($user_id, $product_id);
    }
}
