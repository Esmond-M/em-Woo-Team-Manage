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
}