<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

class PermanentAccountDeletionTest extends WP_UnitTestCase
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
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
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
}