<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

class PermanentAccountDeletionTest extends WP_UnitTestCase
{
    private array $user_ids = [];
    private array $product_ids = [];

    private function relationshipTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    }

    protected function setUp(): void
    {
        parent::setUp();
        emWooTeamManage\init_plugin\emWooTeamManageInit::get_instance()->emwtm_create_team_table();
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', static function () {
            return static function ($message = '', $title = '', $args = []): void {
                throw new WPDieException((string) $message);
            };
        });
    }

    protected function tearDown(): void
    {
        global $wpdb;

        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        foreach ($this->product_ids as $product_id) {
            wp_delete_post($product_id, true);
        }
        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        $_POST = [];
        $_REQUEST = [];
        remove_all_filters('wp_die_ajax_handler');
        parent::tearDown();
    }

    private function runDelete(array $post, bool $is_admin = true): string
    {
        if (!current_user_can($is_admin ? 'manage_options' : 'team_leader')) {
            wp_set_current_user(self::factory()->user->create(['role' => $is_admin ? 'administrator' : 'team_leader']));
        }
        $_POST = $post;
        $_REQUEST = $post;
        ob_start();
        try {
            (new TeamAjaxHandler())->delete_user_account();
        } catch (WPDieException $exception) {
            return (string) ob_get_clean() . $exception->getMessage();
        }

        return (string) ob_get_clean();
    }

    private function post(int $user_id, string $nonce = null, ?string $confirmation = '1'): array
    {
        $post = ['user_id' => (string) $user_id];
        if ($nonce !== null) {
            $post['_nonce'] = $nonce;
        }
        if ($confirmation !== null) {
            $post['confirm_deletion'] = $confirmation;
        }
        return $post;
    }

    private function setAdmin(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function test_rejects_invalid_nonce(): void
    {
        $user_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$user_id];
        $this->setAdmin();

        $output = $this->runDelete($this->post($user_id, 'bad-nonce'));

        $this->assertStringContainsString('Security check failed', $output);
        $this->assertNotFalse(get_user_by('id', $user_id));
    }

    public function test_requires_explicit_confirmation(): void
    {
        $user_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$user_id];
        $this->setAdmin();

        $output = $this->runDelete($this->post($user_id, wp_create_nonce('emwtm_delete_user_account'), null));

        $this->assertStringContainsString('Please confirm', $output);
        $this->assertNotFalse(get_user_by('id', $user_id));
    }

    public function test_rejects_a_dual_role_account(): void
    {
        $user_id = self::factory()->user->create(['role' => 'team_leader']);
        (new WP_User($user_id))->add_role('team_subordinate');
        $this->user_ids = [$user_id];
        $this->setAdmin();

        $output = $this->runDelete($this->post($user_id, wp_create_nonce('emwtm_delete_user_account')));

        $this->assertStringContainsString('Only pure team subordinate', $output);
        $this->assertNotFalse(get_user_by('id', $user_id));
    }

    public function test_admin_can_permanently_delete_pure_subordinate_and_relationships(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $user_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id];
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $user_id], ['%d', '%d']);
        $this->setAdmin();

        $output = $this->runDelete($this->post($user_id, wp_create_nonce('emwtm_delete_user_account')));

        $this->assertStringContainsString('permanently deleted', $output);
        $this->assertFalse(get_user_by('id', $user_id));
        $this->assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE subordinate_id = %d",
            $user_id
        )));
    }

    public function test_permanent_account_deletion_retains_woocommerce_order_history(): void
    {
        $user_id = self::factory()->user->create([
            'role' => 'team_subordinate',
            'user_email' => 'order-history@example.com',
        ]);
        $this->user_ids = [];

        $product = new WC_Product_Simple();
        $product->set_name('Retained Product');
        $product->set_regular_price('125.50');
        $product->save();
        $this->product_ids[] = $product->get_id();

        $order = wc_create_order(['customer_id' => $user_id]);
        $order->set_billing_email('order-history@example.com');
        $order->add_product($product, 1);
        $order->set_status('completed');
        $order->set_total('125.50');
        $order->save();
        $order_id = $order->get_id();

        $this->setAdmin();
        $output = $this->runDelete($this->post($user_id, wp_create_nonce('emwtm_delete_user_account')));

        $this->assertStringContainsString('permanently deleted', $output);
        $this->assertFalse(get_user_by('id', $user_id));
        $retained_order = wc_get_order($order_id);
        $this->assertNotFalse($retained_order);
        $this->assertSame('completed', $retained_order->get_status());
        $this->assertSame('125.50', $retained_order->get_total());
        $items = $retained_order->get_items();
        $this->assertCount(1, $items);
        $this->assertSame($product->get_id(), (int) reset($items)->get_product_id());
    }
}