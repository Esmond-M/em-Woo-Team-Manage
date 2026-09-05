<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamManageCore;
use emWooTeamManage\init_plugin\emWooTeamManageInit;

class PluginActivationTest extends WP_UnitTestCase
{
    private function relationshipTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    }

    protected function tearDown(): void
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        parent::tearDown();
    }

    public function test_plugins_load_and_activation_creates_relationship_table(): void
    {
        global $wpdb;

        $this->assertTrue(class_exists('WooCommerce'));
        $this->assertTrue(class_exists(TeamManageCore::class));

        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        emWooTeamManageInit::get_instance()->emwtm_create_team_table();

        $actual_table = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($this->relationshipTable())
        ));

        $this->assertSame($this->relationshipTable(), $actual_table);
    }

    public function test_plugin_registers_team_roles_with_limited_baseline_capabilities(): void
    {
        global $wp_roles;

        remove_role('team_leader');
        remove_role('team_subordinate');

        (new TeamManageCore())->user_import_inits();

        $leader = get_role('team_leader');
        $subordinate = get_role('team_subordinate');

        $this->assertNotNull($leader);
        $this->assertNotNull($subordinate);
        $this->assertTrue($leader->has_cap('read'));
        $this->assertTrue($subordinate->has_cap('read'));
        $this->assertFalse($leader->has_cap('edit_posts'));
        $this->assertFalse($subordinate->has_cap('edit_posts'));

        $this->assertArrayHasKey('team_leader', $wp_roles->roles);
        $this->assertArrayHasKey('team_subordinate', $wp_roles->roles);
    }

    public function test_team_leader_role_name_is_an_authorization_capability(): void
    {
        $user_id = self::factory()->user->create(['role' => 'team_leader']);
        wp_set_current_user($user_id);

        $this->assertTrue(current_user_can('team_leader'));
        $this->assertTrue(current_user_can('read'));
        $this->assertFalse(current_user_can('manage_options'));
    }

    public function test_only_team_leaders_and_site_admins_can_manage_team_pages(): void
    {
        $customer_id = self::factory()->user->create(['role' => 'customer']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $admin_id = self::factory()->user->create(['role' => 'administrator']);

        wp_set_current_user($customer_id);
        $this->assertFalse(TeamManageCore::can_manage_team_pages());

        wp_set_current_user($subordinate_id);
        $this->assertFalse(TeamManageCore::can_manage_team_pages());

        wp_set_current_user($leader_id);
        $this->assertTrue(TeamManageCore::can_manage_team_pages());

        wp_set_current_user($admin_id);
        $this->assertTrue(TeamManageCore::can_manage_team_pages());
    }
}