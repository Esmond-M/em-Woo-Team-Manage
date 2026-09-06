<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

class TeamEditOwnershipTest extends WP_UnitTestCase
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
            wp_delete_user($user_id);
        }
        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        $_POST = [];
        parent::tearDown();
    }

    public function test_team_leader_cannot_edit_a_subordinate_owned_by_another_team(): void
    {
        global $wpdb;

        $acting_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $owning_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create([
            'role' => 'team_subordinate',
            'display_name' => 'Original Name',
            'user_email' => 'original@example.com',
        ]);
        $this->user_ids = [$acting_leader_id, $owning_leader_id, $subordinate_id];
        $wpdb->insert(
            $this->relationshipTable(),
            ['leader_id' => $owning_leader_id, 'subordinate_id' => $subordinate_id],
            ['%d', '%d']
        );
        wp_set_current_user($acting_leader_id);
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', static function () {
            return static function ($message = '', $title = '', $args = []): void {
                throw new WPDieException((string) $message);
            };
        });

        $_POST = [
            'action' => 'edit_subordinate',
            'edit_user_id' => (string) $subordinate_id,
            'edit_user_email' => 'changed@example.com',
            'edit_user_name' => 'Changed Name',
            'edit_subordinate_nonce' => wp_create_nonce('edit_subordinate_action'),
        ];

        ob_start();
        try {
            (new TeamAjaxHandler())->handle_edit_subordinate();
        } catch (WPDieException $exception) {
        }
        ob_end_clean();
        $subordinate = get_user_by('id', $subordinate_id);

        $this->assertSame('Original Name', $subordinate->display_name);
        $this->assertSame('original@example.com', $subordinate->user_email);
    }
}