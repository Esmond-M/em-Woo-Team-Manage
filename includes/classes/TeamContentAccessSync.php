<?php
declare(strict_types=1);
/**
 * TeamContentAccessSync
 *
 * WooCommerce-specific adapter that applies TeamContentAccess ledger grants
 * to real downloadable product permissions, and keeps them in sync with
 * order status changes and new subordinates being added to a team.
 *
 * A different client restricting content another way (a membership plugin,
 * an LMS, a protected page) would replace this class only — the ledger and
 * the `emwtm_user_has_team_access` filter stay the same.
 */
namespace emWooTeamManage\init_plugin\Classes;

class TeamContentAccessSync
{
    /** Order statuses that should grant team-wide access. */
    const GRANTING_STATUSES = ['processing', 'completed'];

    /** Order statuses that should revoke previously granted access. */
    const REVOKING_STATUSES = ['refunded', 'cancelled', 'failed'];

    private $ledger;

    public function __construct(TeamContentAccess $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Responds to `woocommerce_order_status_changed`.
     */
    public function handle_order_status_changed(int $order_id, string $from_status, string $to_status): void
    {
        if (in_array($to_status, self::GRANTING_STATUSES, true)) {
            $this->grant_order($order_id);
            return;
        }

        if (in_array($to_status, self::REVOKING_STATUSES, true)) {
            $this->revoke_order($order_id);
        }
    }

    /**
     * Grants every current subordinate of the order's team leader access to
     * the order's downloadable products. The leader receives a ledger record
     * too; WooCommerce grants their actual download permissions natively as
     * the order's customer.
     */
    public function grant_order(int $order_id): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $leader_id = (int) $order->get_customer_id();
        if (!$leader_id || !user_can($leader_id, 'team_leader')) {
            return;
        }

        $source = $order->get_meta(TeamContentAssignment::ORDER_META_KEY) === '1'
            ? TeamContentAccess::SOURCE_ASSIGNMENT
            : TeamContentAccess::SOURCE_PURCHASE;

        $subordinate_ids = $this->get_subordinate_ids($leader_id);

        foreach ($this->get_downloadable_items($order) as [$product]) {
            $this->ledger->grant($leader_id, $order->get_id(), $product->get_id(), $leader_id, $source);

            foreach ($subordinate_ids as $subordinate_id) {
                $this->grant_one($order, $product, $leader_id, (int) $subordinate_id, $source);
            }
        }
    }

    /**
     * Revokes every ledger grant and matching WooCommerce download
     * permission tied to an order.
     */
    public function revoke_order(int $order_id): void
    {
        $grants = $this->ledger->get_active_grants_for_order($order_id);

        foreach ($grants as $grant) {
            $this->remove_wc_permissions($order_id, (int) $grant['product_id'], (int) $grant['subordinate_id']);
        }

        $this->ledger->revoke_by_order($order_id);
    }

    /**
     * Grants a single newly-added subordinate access to every downloadable
     * product their leader has already paid for. Bound to `emwtm_subordinate_added`.
     */
    public function grant_subordinate(int $leader_id, int $subordinate_id): void
    {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $orders = wc_get_orders([
            'customer_id' => $leader_id,
            'status'      => self::GRANTING_STATUSES,
            'limit'       => -1,
        ]);

        foreach ($orders as $order) {
            foreach ($this->get_downloadable_items($order) as [$product]) {
                $this->grant_one($order, $product, $leader_id, $subordinate_id);
            }
        }
    }

    /**
     * Revokes access granted to a subordinate through one specific team.
     * Bound to `emwtm_subordinate_removed_from_team` — the subordinate's
     * account, and any other team memberships they hold, are untouched.
     */
    public function revoke_subordinate(int $leader_id, int $subordinate_id): void
    {
        $grants = $this->ledger->get_active_grants_for_leader_and_subordinate($leader_id, $subordinate_id);

        foreach ($grants as $grant) {
            $this->remove_wc_permissions((int) $grant['order_id'], (int) $grant['product_id'], $subordinate_id);
        }

        $this->ledger->revoke_by_subordinate($leader_id, $subordinate_id);
    }

    /**
     * Revokes every grant tied to a user account, whether that user was the
     * subordinate receiving access or the leader whose purchase granted it.
     * Bound to WordPress's `delete_user` action.
     */
    public function revoke_all_for_user(int $user_id): void
    {
        foreach ($this->ledger->get_active_grants_for_subordinate($user_id) as $grant) {
            $this->remove_wc_permissions((int) $grant['order_id'], (int) $grant['product_id'], $user_id);
        }
        $this->ledger->revoke_all_by_subordinate($user_id);

        foreach ($this->ledger->get_active_grants_for_leader($user_id) as $grant) {
            $this->remove_wc_permissions((int) $grant['order_id'], (int) $grant['product_id'], (int) $grant['subordinate_id']);
        }
        $this->ledger->revoke_by_leader($user_id);
    }

    /**
     * Returns [product_id => subordinate_id] arrays as [product, item] pairs
     * for every downloadable line item on an order.
     *
     * @return array<int, array{0: \WC_Product}>
     */
    private function get_downloadable_items(\WC_Order $order): array
    {
        $downloadable = [];

        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            if ($product && $product->is_downloadable()) {
                $downloadable[] = [$product];
            }
        }

        return $downloadable;
    }

    private function get_subordinate_ids(int $leader_id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'emwtm_team_leaders_subordinates';

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT subordinate_id FROM {$table} WHERE leader_id = %d",
            $leader_id
        )));
    }

    /**
     * Records a ledger grant and, if not already present, a real WooCommerce
     * download permission for one subordinate/product pair.
     */
    private function grant_one(\WC_Order $order, \WC_Product $product, int $leader_id, int $subordinate_id, string $source = TeamContentAccess::SOURCE_PURCHASE): void
    {
        $grant_id = $this->ledger->grant($leader_id, $order->get_id(), $product->get_id(), $subordinate_id, $source);
        if (!$grant_id) {
            return;
        }

        $subordinate = get_user_by('id', $subordinate_id);
        if (!$subordinate || !class_exists('WC_Data_Store')) {
            return;
        }

        $data_store = \WC_Data_Store::load('customer-download');

        foreach ($product->get_downloads() as $download) {
            $existing = $data_store->get_downloads([
                'user_id'     => $subordinate_id,
                'order_id'    => $order->get_id(),
                'product_id'  => $product->get_id(),
                'download_id' => $download->get_id(),
            ]);
            if (!empty($existing)) {
                continue;
            }

            $permission = new \WC_Customer_Download();
            $permission->set_download_id($download->get_id());
            $permission->set_product_id($product->get_id());
            $permission->set_user_id($subordinate_id);
            $permission->set_order_id($order->get_id());
            $permission->set_order_key($order->get_order_key());
            $permission->set_user_email($subordinate->user_email);
            $permission->set_downloads_remaining('');
            $permission->set_access_granted(time());
            $permission->set_download_count(0);
            $permission->save();
        }
    }

    /**
     * Removes the WooCommerce download permissions behind a set of grants for
     * one leader/product pair. Used when an assignment is withdrawn.
     *
     * @param array<int, array{order_id:int, subordinate_id:int}> $grants
     */
    public function remove_product_permissions(int $leader_id, int $product_id, array $grants): void
    {
        foreach ($grants as $grant) {
            $this->remove_wc_permissions((int) $grant['order_id'], $product_id, (int) $grant['subordinate_id']);
        }
    }

    /**
     * Removes every WooCommerce download permission granted to a subordinate
     * for a product through a specific order.
     */
    private function remove_wc_permissions(int $order_id, int $product_id, int $user_id): void
    {
        if (!class_exists('WC_Data_Store')) {
            return;
        }

        $data_store = \WC_Data_Store::load('customer-download');
        $downloads  = $data_store->get_downloads([
            'user_id'    => $user_id,
            'order_id'   => $order_id,
            'product_id' => $product_id,
        ]);

        foreach ($downloads as $download) {
            $download->delete();
        }
    }
}
