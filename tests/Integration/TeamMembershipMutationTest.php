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
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', static function () {
            return static function ($message = '', $title = '', $args = []): void {
                throw new WPDieException((string) $message);
            };
        });

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

    public function test_repeated_single_add_does_not_create_duplicate_user_or_membership(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        wp_set_current_user($leader_id);
        add_filter('pre_wp_mail', '__return_true');
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', static function () {
            return static function ($message = '', $title = '', $args = []): void {
                throw new WPDieException((string) $message);
            };
        });

        $post = [
            '_single_subordinate_nonce' => wp_create_nonce('add_single_subordinate'),
            'single_first_name' => 'Repeat',
            'single_last_name' => 'Submit',
            'single_email' => 'repeat-submit@example.com',
            'teamLeaderID' => (string) $leader_id,
        ];

        $responses = [];
        foreach ([$post, $post] as $request) {
            $_POST = $request;
            ob_start();
            try {
                (new TeamAjaxHandler())->add_single_subordinate();
            } catch (WPDieException $exception) {
            }
            $responses[] = (string) ob_get_clean();
        }

        $subordinate = get_user_by('email', 'repeat-submit@example.com');
        $this->assertStringContainsString('added successfully', $responses[0]);
        $this->assertStringContainsString('already exists', $responses[1]);
        $this->assertNotFalse($subordinate);
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $leader_id,
            $subordinate->ID
        )));
        $this->assertSame(1, count(get_users([
            'search' => 'repeat-submit@example.com',
            'search_columns' => ['user_email'],
        ])));
    }

    public function test_admin_cannot_add_a_subordinate_to_an_invalid_leader_id(): void
    {
        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        $non_leader_id = self::factory()->user->create(['role' => 'customer']);
        wp_set_current_user($admin_id);
        add_filter('pre_wp_mail', '__return_true');
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', static function () {
            return static function ($message = '', $title = '', $args = []): void {
                throw new WPDieException((string) $message);
            };
        });

        $_POST = [
            '_single_subordinate_nonce' => wp_create_nonce('add_single_subordinate'),
            'single_first_name' => 'Invalid',
            'single_last_name' => 'Leader',
            'single_email' => 'invalid-leader@example.com',
            'teamLeaderID' => (string) $non_leader_id,
        ];

        ob_start();
        try {
            (new TeamAjaxHandler())->add_single_subordinate();
        } catch (WPDieException $exception) {
        }
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Invalid team leader selected', $output);
        $this->assertFalse(get_user_by('email', 'invalid-leader@example.com'));
    }
}