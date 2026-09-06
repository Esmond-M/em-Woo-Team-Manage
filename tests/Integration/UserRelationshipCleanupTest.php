<?php

declare(strict_types=1);

class UserRelationshipCleanupTest extends WP_UnitTestCase
{
    private array $user_ids = [];

    private function relationshipTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    }

    protected function setUp(): void
    {
        parent::setUp();
        emWooTeamManage\init_plugin\emWooTeamManageInit::get_instance()->emwtm_create_team_table();
    }

    protected function tearDown(): void
    {
        global $wpdb;

        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        parent::tearDown();
    }

    public function test_deleting_leader_removes_all_relationships_for_that_leader(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $first_subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $second_subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $unaffected_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $unaffected_subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [
            $first_subordinate_id,
            $second_subordinate_id,
            $unaffected_leader_id,
            $unaffected_subordinate_id,
        ];

        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $first_subordinate_id], ['%d', '%d']);
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $second_subordinate_id], ['%d', '%d']);
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $unaffected_leader_id, 'subordinate_id' => $unaffected_subordinate_id], ['%d', '%d']);

        wp_delete_user($leader_id);

        $this->assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d OR subordinate_id = %d",
            $leader_id,
            $leader_id
        )));
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $unaffected_leader_id,
            $unaffected_subordinate_id
        )));
    }

    public function test_deleting_subordinate_removes_all_relationships_for_that_subordinate(): void
    {
        global $wpdb;

        $first_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $second_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $unaffected_subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$first_leader_id, $second_leader_id, $unaffected_subordinate_id];

        $wpdb->insert($this->relationshipTable(), ['leader_id' => $first_leader_id, 'subordinate_id' => $subordinate_id], ['%d', '%d']);
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $second_leader_id, 'subordinate_id' => $subordinate_id], ['%d', '%d']);
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $first_leader_id, 'subordinate_id' => $unaffected_subordinate_id], ['%d', '%d']);

        wp_delete_user($subordinate_id);

        $this->assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE subordinate_id = %d",
            $subordinate_id
        )));
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $first_leader_id,
            $unaffected_subordinate_id
        )));
    }
}