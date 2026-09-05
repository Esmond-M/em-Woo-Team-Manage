<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

class TeamMembershipMutationTest extends WP_UnitTestCase
{
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

        foreach (get_users(['role' => 'team_subordinate', 'fields' => 'ID']) as $user_id) {
            wp_delete_user((int) $user_id);
        }
        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        $_POST = [];
        remove_all_filters('pre_wp_mail');
        parent::tearDown();
    }

    public function test_team_leader_adds_subordinate_to_the_logged_in_team(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $other_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        wp_set_current_user($leader_id);
        add_filter('pre_wp_mail', '__return_true');

        $_POST = [
            '_single_subordinate_nonce' => wp_create_nonce('add_single_subordinate'),
            'single_first_name' => 'Team',
            'single_last_name' => 'Member',
            'single_email' => 'membership-test@example.com',
            'teamLeaderID' => (string) $other_leader_id,
        ];

        ob_start();
        try {
            (new TeamAjaxHandler())->add_single_subordinate();
        } catch (WPDieException $exception) {
        }
        $output = (string) ob_get_clean();

        $subordinate = get_user_by('email', 'membership-test@example.com');
        $this->assertStringContainsString('added successfully', $output);
        $this->assertNotFalse($subordinate);
        $this->assertTrue(in_array('team_subordinate', (array) $subordinate->roles, true));
        $this->assertSame((string) $leader_id, get_user_meta($subordinate->ID, 'teamID', true));

        global $wpdb;
        $this->assertSame(
            1,
            (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
                $leader_id,
                $subordinate->ID
            ))
        );
        $this->assertSame(
            0,
            (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
                $other_leader_id,
                $subordinate->ID
            ))
        );
    }
}