<?php
declare(strict_types=1);
/**
 * TeamContentAssignment
 *
 * Lets an administrator grant a team leader access to downloadable products
 * without a real purchase. Each assignment is backed by a hidden $0
 * WooCommerce order so that download links, My Account > Downloads, and the
 * existing grant/revoke flow all work exactly as they do for a real purchase.
 */
namespace emWooTeamManage\init_plugin\Classes;

class TeamContentAssignment
{
    /** Order meta flag marking an order as plugin-generated. */
    const ORDER_META_KEY = '_emwtm_assignment';

    private $ledger;
    private $sync;

    public function __construct(TeamContentAccess $ledger, TeamContentAccessSync $sync)
    {
        $this->ledger = $ledger;
        $this->sync   = $sync;
    }

    /**
     * Assigns one or more downloadable products to a team leader's team.
     *
     * @param int[] $product_ids
     * @return array{success:bool, message:string, order_id:int, assigned:int}
     */
    public function assign(int $leader_id, array $product_ids): array
    {
        if (!function_exists('wc_create_order')) {
            return ['success' => false, 'message' => 'WooCommerce must be active.', 'order_id' => 0, 'assigned' => 0];
        }

        $leader = get_user_by('id', $leader_id);
        if (!$leader || !in_array('team_leader', (array) $leader->roles, true)) {
            return ['success' => false, 'message' => 'Invalid team leader selected.', 'order_id' => 0, 'assigned' => 0];
        }

        $products = $this->collect_assignable_products($leader_id, $product_ids);
        if (empty($products)) {
            return [
                'success'  => false,
                'message'  => 'Select at least one downloadable product that is not already assigned.',
                'order_id' => 0,
                'assigned' => 0,
            ];
        }

        $order = wc_create_order(['customer_id' => $leader_id]);
        if (is_wp_error($order)) {
            return ['success' => false, 'message' => 'Could not create the assignment order.', 'order_id' => 0, 'assigned' => 0];
        }

        foreach ($products as $product) {
            $order->add_product($product, 1, ['subtotal' => 0, 'total' => 0]);
        }
        $order->set_total(0);
        $order->update_meta_data(self::ORDER_META_KEY, '1');
        $order->save();

        // Completing the order triggers the normal grant flow and WooCommerce's
        // own download permissions for the leader.
        $order->update_status('completed', 'Team content assigned by administrator.');

        return [
            'success'  => true,
            'message'  => sprintf('Assigned %d product(s) to %s.', count($products), $leader->display_name),
            'order_id' => $order->get_id(),
            'assigned' => count($products),
        ];
    }

    /**
     * Removes a product's access from a leader's whole team.
     *
     * @return array{success:bool, message:string, revoked:int}
     */
    public function unassign(int $leader_id, int $product_id): array
    {
        $grants = $this->ledger->get_active_grants_for_leader_and_product($leader_id, $product_id);
        if (empty($grants)) {
            return ['success' => false, 'message' => 'No active access found for that product.', 'revoked' => 0];
        }

        $this->sync->remove_product_permissions($leader_id, $product_id, $grants);
        $revoked = $this->ledger->revoke_by_leader_and_product($leader_id, $product_id);

        foreach ($this->collect_assignment_order_ids($grants) as $order_id) {
            $this->maybe_cancel_assignment_order($order_id, $product_id);
        }

        return ['success' => true, 'message' => 'Access removed.', 'revoked' => $revoked];
    }

    /**
     * Filters requested IDs down to downloadable products the team doesn't
     * already have access to.
     *
     * @param int[] $product_ids
     * @return \WC_Product[]
     */
    private function collect_assignable_products(int $leader_id, array $product_ids): array
    {
        $already_assigned = array_column($this->ledger->get_team_products($leader_id), 'product_id');
        $already_assigned = array_map('intval', $already_assigned);

        $products = [];
        foreach (array_unique(array_map('intval', $product_ids)) as $product_id) {
            if (!$product_id || in_array($product_id, $already_assigned, true)) {
                continue;
            }

            $product = wc_get_product($product_id);
            if ($product && $product->is_downloadable()) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * @param array<int, array{order_id:int}> $grants
     * @return int[]
     */
    private function collect_assignment_order_ids(array $grants): array
    {
        return array_unique(array_map(static fn($grant) => (int) $grant['order_id'], $grants));
    }

    /**
     * Cancels a plugin-generated order once none of its products are still
     * assigned. Real customer orders are never touched.
     */
    private function maybe_cancel_assignment_order(int $order_id, int $product_id): void
    {
        if (!$order_id || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta(self::ORDER_META_KEY) !== '1') {
            return;
        }

        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            if ((int) $item->get_product_id() !== $product_id) {
                return;
            }
        }

        $order->update_status('cancelled', 'Team content assignment removed by administrator.');
    }

    /**
     * Whether an order was generated by an assignment rather than a purchase.
     */
    public static function is_assignment_order(int $order_id): bool
    {
        if (!function_exists('wc_get_order')) {
            return false;
        }

        $order = wc_get_order($order_id);

        return $order ? $order->get_meta(self::ORDER_META_KEY) === '1' : false;
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /**
     * AJAX: assign products to a team leader.
     * Expects POST: _nonce, leader_id, product_ids[].
     */
    public function ajax_assign(): void
    {
        if (!check_ajax_referer('emwtm_content_assignment', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $leader_id   = isset($_POST['leader_id']) ? (int) $_POST['leader_id'] : 0;
        $product_ids = isset($_POST['product_ids']) ? (array) wp_unslash($_POST['product_ids']) : [];

        $result = $this->assign($leader_id, array_map('intval', $product_ids));
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: remove an assigned product from a team.
     * Expects POST: _nonce, leader_id, product_id.
     */
    public function ajax_unassign(): void
    {
        if (!check_ajax_referer('emwtm_content_assignment', '_nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $leader_id  = isset($_POST['leader_id']) ? (int) $_POST['leader_id'] : 0;
        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

        $result = $this->unassign($leader_id, $product_id);
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success($result);
    }

    /**
     * Hides plugin-generated assignment orders from the WooCommerce order list
     * when the corresponding setting is enabled.
     */
    public function filter_hide_assignment_orders(array $query_vars): array
    {
        if (!get_option('emwtm_hide_assignment_orders')) {
            return $query_vars;
        }

        $meta_query = $query_vars['meta_query'] ?? [];
        $meta_query[] = [
            'key'     => self::ORDER_META_KEY,
            'compare' => 'NOT EXISTS',
        ];
        $query_vars['meta_query'] = $meta_query;

        return $query_vars;
    }
}
