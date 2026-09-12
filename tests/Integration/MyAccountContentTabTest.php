<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamContentAccess;
use emWooTeamManage\init_plugin\Classes\TeamManageCore;

class MyAccountContentTabTest extends WP_UnitTestCase
{
    private array $user_ids = [];

    private function baseItems(): array
    {
        return [
            'dashboard'       => 'Dashboard',
            'orders'          => 'Orders',
            'downloads'       => 'Downloads',
            'edit-account'    => 'Account details',
            'customer-logout' => 'Log out',
        ];
    }

    protected function tearDown(): void
    {
        global $wpdb;

        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        $wpdb->query("DELETE FROM {$wpdb->prefix}emwtm_team_content_grants");
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_subordinate_without_content_sees_no_extra_tabs(): void
    {
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$subordinate_id];
        wp_set_current_user($subordinate_id);

        $items = (new TeamManageCore())->add_myaccount_menu_item($this->baseItems());

        $this->assertArrayNotHasKey('team-content', $items);
        $this->assertArrayNotHasKey('team-manage', $items);
    }

    public function test_subordinate_with_granted_content_sees_my_content_tab(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        (new TeamContentAccess())->grant($leader_id, 4321, 8765, $subordinate_id, TeamContentAccess::SOURCE_ASSIGNMENT);
        wp_set_current_user($subordinate_id);

        $items = (new TeamManageCore())->add_myaccount_menu_item($this->baseItems());

        $this->assertArrayHasKey('team-content', $items);
        $this->assertSame('My Content', $items['team-content']);
        $this->assertArrayNotHasKey('team-manage', $items);
    }

    public function test_my_content_tab_is_placed_before_logout(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        (new TeamContentAccess())->grant($leader_id, 4321, 8765, $subordinate_id, TeamContentAccess::SOURCE_ASSIGNMENT);
        wp_set_current_user($subordinate_id);

        $items = (new TeamManageCore())->add_myaccount_menu_item($this->baseItems());
        $keys  = array_keys($items);

        $this->assertSame('customer-logout', end($keys));
        $this->assertLessThan(
            array_search('customer-logout', $keys, true),
            array_search('team-content', $keys, true)
        );
    }

    public function test_leader_keeps_my_team_tab_and_gains_my_content_when_granted(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id];

        (new TeamContentAccess())->grant($leader_id, 4321, 8765, $leader_id, TeamContentAccess::SOURCE_ASSIGNMENT);
        wp_set_current_user($leader_id);

        $items = (new TeamManageCore())->add_myaccount_menu_item($this->baseItems());

        $this->assertArrayHasKey('team-manage', $items);
        $this->assertArrayHasKey('team-content', $items);
    }

    public function test_revoked_content_removes_the_my_content_tab(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        $ledger = new TeamContentAccess();
        $ledger->grant($leader_id, 4321, 8765, $subordinate_id, TeamContentAccess::SOURCE_ASSIGNMENT);
        $ledger->revoke_by_order(4321);
        wp_set_current_user($subordinate_id);

        $items = (new TeamManageCore())->add_myaccount_menu_item($this->baseItems());

        $this->assertArrayNotHasKey('team-content', $items);
    }

    public function test_get_user_products_groups_duplicate_product_grants(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        $ledger = new TeamContentAccess();
        $ledger->grant($leader_id, 111, 999, $subordinate_id, TeamContentAccess::SOURCE_ASSIGNMENT);
        $ledger->grant($leader_id, 222, 999, $subordinate_id, TeamContentAccess::SOURCE_PURCHASE);

        $products = $ledger->get_user_products($subordinate_id);

        $this->assertCount(1, $products);
        $this->assertSame(999, (int) $products[0]['product_id']);
    }
}
