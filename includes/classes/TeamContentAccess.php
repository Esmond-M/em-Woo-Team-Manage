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
    /** Grant created by a team leader's own WooCommerce purchase. */
    const SOURCE_PURCHASE = 'purchase';

    /** Grant created by an administrator assigning a product to a team. */
    const SOURCE_ASSIGNMENT = 'assignment';

    /**
     * Returns the ledger table name.
     */
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'emwtm_team_content_grants';
    }

    /**
     * Records that a user has access to a product via a leader's order.
     * Idempotent — returns the existing grant ID if one is already active.
     */
    public function grant(int $leader_id, int $order_id, int $product_id, int $subordinate_id, string $source = self::SOURCE_PURCHASE): int
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
                'source'         => $source,
                'granted_at'     => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s']
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
     * Marks every active grant for a subordinate as revoked, regardless of
     * which leader granted it. Used when the subordinate's account is deleted.
     *
     * @return int Number of grants revoked.
     */
    public function revoke_all_by_subordinate(int $subordinate_id): int
    {
        global $wpdb;
        $table = $this->table();

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = %s WHERE subordinate_id = %d AND revoked_at IS NULL",
            current_time('mysql'),
            $subordinate_id
        ));
    }

    /**
     * Marks every active grant made by a leader as revoked. Used when the
     * leader's own account is deleted, so their purchase no longer grants access.
     *
     * @return int Number of grants revoked.
     */
    public function revoke_by_leader(int $leader_id): int
    {
        global $wpdb;
        $table = $this->table();

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = %s WHERE leader_id = %d AND revoked_at IS NULL",
            current_time('mysql'),
            $leader_id
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
     * Returns every active grant row for one leader/subordinate relationship,
     * so an adapter can revoke only the access tied to that team.
     *
     * @return array<int, array{id:int, order_id:int, product_id:int}>
     */
    public function get_active_grants_for_leader_and_subordinate(int $leader_id, int $subordinate_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id, product_id FROM {$table} WHERE leader_id = %d AND subordinate_id = %d AND revoked_at IS NULL",
            $leader_id,
            $subordinate_id
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Returns every active grant row for a subordinate, across all leaders.
     * Used when the subordinate's account is deleted entirely.
     *
     * @return array<int, array{id:int, order_id:int, product_id:int}>
     */
    public function get_active_grants_for_subordinate(int $subordinate_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id, product_id FROM {$table} WHERE subordinate_id = %d AND revoked_at IS NULL",
            $subordinate_id
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

    /**
     * Returns the distinct products a leader's team currently has access to,
     * with how many users hold each grant. Powers the admin and leader views.
     *
     * @return array<int, array{product_id:int, order_id:int, source:string, user_count:int, granted_at:string}>
     */
    public function get_team_products(int $leader_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, MIN(order_id) AS order_id, MIN(source) AS source,
                    COUNT(DISTINCT subordinate_id) AS user_count, MIN(granted_at) AS granted_at
             FROM {$table}
             WHERE leader_id = %d AND revoked_at IS NULL
             GROUP BY product_id",
            $leader_id
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Returns the user IDs holding an active grant for one leader/product pair.
     *
     * @return int[]
     */
    public function get_users_with_product(int $leader_id, int $product_id): array
    {
        global $wpdb;
        $table = $this->table();

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT subordinate_id FROM {$table}
             WHERE leader_id = %d AND product_id = %d AND revoked_at IS NULL",
            $leader_id,
            $product_id
        )));
    }

    /**
     * Returns every active grant grouped by leader and product, for the
     * site-wide admin assignments table.
     *
     * @return array<int, array{leader_id:int, product_id:int, order_id:int, source:string, user_count:int, granted_at:string}>
     */
    public function get_all_team_products(): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results(
            "SELECT leader_id, product_id, MIN(order_id) AS order_id, MIN(source) AS source,
                    COUNT(DISTINCT subordinate_id) AS user_count, MIN(granted_at) AS granted_at
             FROM {$table}
             WHERE revoked_at IS NULL
             GROUP BY leader_id, product_id
             ORDER BY leader_id ASC, granted_at DESC",
            ARRAY_A
        );

        return $rows ?: [];
    }

    /**
     * Returns the distinct products a single user can access through any team
     * they belong to. Powers the My Account content view.
     *
     * @return array<int, array{product_id:int, order_id:int, leader_id:int, source:string, granted_at:string}>
     */
    public function get_user_products(int $user_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, MIN(order_id) AS order_id, MIN(leader_id) AS leader_id,
                    MIN(source) AS source, MIN(granted_at) AS granted_at
             FROM {$table}
             WHERE subordinate_id = %d AND revoked_at IS NULL
             GROUP BY product_id
             ORDER BY granted_at DESC",
            $user_id
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Marks active grants for one leader/product pair as revoked.
     *
     * @return int Number of grants revoked.
     */
    public function revoke_by_leader_and_product(int $leader_id, int $product_id): int
    {
        global $wpdb;
        $table = $this->table();

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET revoked_at = %s
             WHERE leader_id = %d AND product_id = %d AND revoked_at IS NULL",
            current_time('mysql'),
            $leader_id,
            $product_id
        ));
    }

    /**
     * Returns the active grant rows for one leader/product pair.
     *
     * @return array<int, array{id:int, order_id:int, subordinate_id:int}>
     */
    public function get_active_grants_for_leader_and_product(int $leader_id, int $product_id): array
    {
        global $wpdb;
        $table = $this->table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id, subordinate_id FROM {$table}
             WHERE leader_id = %d AND product_id = %d AND revoked_at IS NULL",
            $leader_id,
            $product_id
        ), ARRAY_A);

        return $rows ?: [];
    }
}
