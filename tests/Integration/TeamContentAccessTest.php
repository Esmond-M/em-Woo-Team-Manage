<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamContentAccess;
use emWooTeamManage\init_plugin\Classes\TeamContentAccessSync;

class TeamContentAccessTest extends WP_UnitTestCase
{
    private array $user_ids = [];
    private array $product_ids = [];

    private function relationshipTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    }

    private function addToTeam(int $leader_id, int $subordinate_id): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->relationshipTable(),
            ['leader_id' => $leader_id, 'subordinate_id' => $subordinate_id],
            ['%d', '%d']
        );
    }

    private function makeDownloadableProduct(): WC_Product_Simple
    {
        $download = new WC_Product_Download();
        $download->set_id(md5('team-ebook-file-' . uniqid('', true)));
        $download->set_name('Team eBook PDF');
        $download->set_file('http://example.com/team-ebook.pdf');

        $product = new WC_Product_Simple();
        $product->set_name('Team eBook');
        $product->set_regular_price('10');
        $product->set_virtual(true);
        $product->set_downloadable(true);
        $product->set_downloads([$download]);
        $product->save();
        $this->product_ids[] = $product->get_id();

        return $product;
    }

    private function hasDownloadPermission(int $order_id, int $product_id, int $user_id): bool
    {
        $data_store = WC_Data_Store::load('customer-download');

        return !empty($data_store->get_downloads([
            'user_id'    => $user_id,
            'order_id'   => $order_id,
            'product_id' => $product_id,
        ]));
    }

    protected function tearDown(): void
    {
        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        foreach ($this->product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->delete(true);
            }
        }
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->relationshipTable()}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}emwtm_team_content_grants");
        parent::tearDown();
    }

    public function test_order_completion_grants_downloadable_access_to_current_subordinates(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];
        $this->addToTeam($leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $order = wc_create_order(['customer_id' => $leader_id]);
        $order->add_product($product, 1);
        $order->save();

        $ledger = new TeamContentAccess();
        $sync = new TeamContentAccessSync($ledger);
        $sync->grant_order($order->get_id());

        $this->assertTrue($ledger->has_access($subordinate_id, $product->get_id()));
        $this->assertTrue($this->hasDownloadPermission($order->get_id(), $product->get_id(), $subordinate_id));
    }

    public function test_revoking_order_removes_access_and_ledger_grant(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];
        $this->addToTeam($leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $order = wc_create_order(['customer_id' => $leader_id]);
        $order->add_product($product, 1);
        $order->save();

        $ledger = new TeamContentAccess();
        $sync = new TeamContentAccessSync($ledger);
        $sync->grant_order($order->get_id());
        $sync->revoke_order($order->get_id());

        $this->assertFalse($ledger->has_access($subordinate_id, $product->get_id()));
        $this->assertFalse($this->hasDownloadPermission($order->get_id(), $product->get_id(), $subordinate_id));
    }

    public function test_new_subordinate_gets_access_to_leaders_existing_purchase(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        $product = $this->makeDownloadableProduct();
        $order = wc_create_order(['customer_id' => $leader_id]);
        $order->add_product($product, 1);
        $order->set_status('completed');
        $order->save();

        $ledger = new TeamContentAccess();
        $sync = new TeamContentAccessSync($ledger);

        // Subordinate is added to the team after the purchase already exists.
        $this->addToTeam($leader_id, $subordinate_id);
        $sync->grant_subordinate($leader_id, $subordinate_id);

        $this->assertTrue($ledger->has_access($subordinate_id, $product->get_id()));
        $this->assertTrue($this->hasDownloadPermission($order->get_id(), $product->get_id(), $subordinate_id));
    }

    public function test_removing_subordinate_from_one_team_keeps_access_from_another_team(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $other_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $other_leader_id, $subordinate_id];
        $this->addToTeam($leader_id, $subordinate_id);
        $this->addToTeam($other_leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $other_product = $this->makeDownloadableProduct();

        $order = wc_create_order(['customer_id' => $leader_id]);
        $order->add_product($product, 1);
        $order->save();

        $other_order = wc_create_order(['customer_id' => $other_leader_id]);
        $other_order->add_product($other_product, 1);
        $other_order->save();

        $ledger = new TeamContentAccess();
        $sync = new TeamContentAccessSync($ledger);
        $sync->grant_order($order->get_id());
        $sync->grant_order($other_order->get_id());

        $sync->revoke_subordinate($leader_id, $subordinate_id);

        $this->assertFalse($ledger->has_access($subordinate_id, $product->get_id()));
        $this->assertTrue($ledger->has_access($subordinate_id, $other_product->get_id()));
    }

    public function test_deleting_subordinate_account_revokes_all_their_access(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id];
        $this->addToTeam($leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $order = wc_create_order(['customer_id' => $leader_id]);
        $order->add_product($product, 1);
        $order->save();

        $ledger = new TeamContentAccess();
        $sync = new TeamContentAccessSync($ledger);
        $sync->grant_order($order->get_id());
        $this->assertTrue($ledger->has_access($subordinate_id, $product->get_id()));

        $sync->revoke_all_for_user($subordinate_id);

        $this->assertFalse($ledger->has_access($subordinate_id, $product->get_id()));
    }

    public function test_deleting_leader_account_revokes_access_for_all_subordinates(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$subordinate_id];
        $this->addToTeam($leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $order = wc_create_order(['customer_id' => $leader_id]);
        $order->add_product($product, 1);
        $order->save();

        $ledger = new TeamContentAccess();
        $sync = new TeamContentAccessSync($ledger);
        $sync->grant_order($order->get_id());
        $this->assertTrue($ledger->has_access($subordinate_id, $product->get_id()));

        $sync->revoke_all_for_user($leader_id);
        wp_delete_user($leader_id);

        $this->assertFalse($ledger->has_access($subordinate_id, $product->get_id()));
    }

    public function test_emwtm_user_has_team_access_filter_reflects_ledger(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        $ledger = new TeamContentAccess();
        add_filter('emwtm_user_has_team_access', [$ledger, 'filter_user_has_team_access'], 10, 3);

        $product_id = 99999;
        $order_id = 88888;

        $this->assertFalse(apply_filters('emwtm_user_has_team_access', false, $subordinate_id, $product_id));

        $ledger->grant($leader_id, $order_id, $product_id, $subordinate_id);
        $this->assertTrue(apply_filters('emwtm_user_has_team_access', false, $subordinate_id, $product_id));

        $ledger->revoke_by_order($order_id);
        $this->assertFalse(apply_filters('emwtm_user_has_team_access', false, $subordinate_id, $product_id));

        remove_filter('emwtm_user_has_team_access', [$ledger, 'filter_user_has_team_access'], 10);
    }
}
