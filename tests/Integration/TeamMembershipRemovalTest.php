<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

class TeamMembershipRemovalTest extends WP_UnitTestCase
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
            wp_delete_user($user_id);
        }
        $wpdb->query("DROP TABLE IF EXISTS {$this->relationshipTable()}");
        $_POST = [];
        remove_all_filters('pre_wp_mail');
        remove_all_filters('wp_die_ajax_handler');
        parent::tearDown();
    }

    private function runRemoval(array $post): string
    {
        $_POST = $post;
        ob_start();
        try {
            (new TeamAjaxHandler())->team_Leader_Form_Submission();
        } catch (WPDieException $exception) {
        }

        return (string) ob_get_clean();
    }

    private function basePost(int $subordinate_id): array
    {
        return [
            'team_Leader_Form_Submission_nonce_field' => wp_create_nonce('team_Leader_Form_Submission'),
            'teamLeaderSelectOption' => 'delete',
            'userID' => [(string) $subordinate_id],
        ];
    }

    public function test_removal_requires_explicit_confirmation(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];
        $wpdb->insert(
            $this->relationshipTable(),
            ['leader_id' => $leader_id, 'subordinate_id' => $subordinate_id],
            ['%d', '%d']
        );
        wp_set_current_user($leader_id);

        $output = $this->runRemoval($this->basePost($subordinate_id));

        $this->assertStringContainsString('Please confirm', $output);
        $this->assertNotFalse(get_user_by('id', $subordinate_id));
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $leader_id,
            $subordinate_id
        )));
    }

    public function test_confirmed_removal_retains_user_and_other_team_relationships(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $other_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create([
            'role' => 'team_subordinate',
            'user_email' => 'retained-member@example.com',
        ]);
        $unaffected_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $other_leader_id, $subordinate_id, $unaffected_id];
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $subordinate_id], ['%d', '%d']);
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $other_leader_id, 'subordinate_id' => $subordinate_id], ['%d', '%d']);
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $unaffected_id], ['%d', '%d']);
        update_user_meta($subordinate_id, 'teamID', $leader_id);
        wp_set_current_user($leader_id);
        $mail_count = 0;
        add_filter('pre_wp_mail', static function () use (&$mail_count): bool {
            $mail_count++;
            return true;
        });

        $post = $this->basePost($subordinate_id);
        $post['confirm_removal'] = '1';
        $output = $this->runRemoval($post);

        $this->assertStringContainsString('removed from the team', $output);
        $this->assertNotFalse(get_user_by('id', $subordinate_id));
        $this->assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $leader_id,
            $subordinate_id
        )));
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $other_leader_id,
            $subordinate_id
        )));
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $leader_id,
            $unaffected_id
        )));
        $this->assertSame((string) $other_leader_id, get_user_meta($subordinate_id, 'teamID', true));
        $this->assertSame(1, $mail_count);
    }

    public function test_final_removal_restores_a_team_only_user_to_customer(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $subordinate_id], ['%d', '%d']);
        update_user_meta($subordinate_id, 'teamID', $leader_id);
        wp_set_current_user($leader_id);
        add_filter('pre_wp_mail', '__return_true');

        $post = $this->basePost($subordinate_id);
        $post['confirm_removal'] = '1';
        $this->runRemoval($post);

        $user = get_user_by('id', $subordinate_id);
        $this->assertNotFalse($user);
        $this->assertSame(['customer'], array_values((array) $user->roles));
        $this->assertSame('', get_user_meta($subordinate_id, 'teamID', true));
    }

    public function test_final_removal_preserves_team_leader_role(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $member_id = self::factory()->user->create(['role' => 'team_leader']);
        $member = new WP_User($member_id);
        $member->add_role('team_subordinate');
        $this->user_ids = [$leader_id, $member_id];
        $wpdb->insert($this->relationshipTable(), ['leader_id' => $leader_id, 'subordinate_id' => $member_id], ['%d', '%d']);
        update_user_meta($member_id, 'teamID', $leader_id);
        wp_set_current_user($leader_id);
        add_filter('pre_wp_mail', '__return_true');

        $post = $this->basePost($member_id);
        $post['confirm_removal'] = '1';
        $this->runRemoval($post);

        $user = get_user_by('id', $member_id);
        $this->assertContains('team_leader', (array) $user->roles);
        $this->assertNotContains('team_subordinate', (array) $user->roles);
    }
}