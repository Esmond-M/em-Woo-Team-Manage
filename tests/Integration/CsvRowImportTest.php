<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamUserImporter;

class CsvRowImportTest extends WP_UnitTestCase
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
        add_filter('pre_wp_mail', '__return_true');
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
        remove_all_filters('pre_wp_mail');
        parent::tearDown();
    }

    public function test_import_row_creates_user_metadata_and_relationship(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id];
        $result = (new TeamUserImporter())->import_row(
            ['csv-user@example.com', 'CSV', 'User'],
            $leader_id
        );

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['user_id']);
        $this->user_ids[] = $result['user_id'];
        $this->assertSame((string) $leader_id, get_user_meta($result['user_id'], 'teamID', true));
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d AND subordinate_id = %d",
            $leader_id,
            $result['user_id']
        )));
    }

    public function test_import_row_rejects_invalid_and_repeated_rows_without_extra_records(): void
    {
        global $wpdb;

        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $other_leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id, $other_leader_id];
        $importer = new TeamUserImporter();

        $invalid = $importer->import_row(['', 'Missing', 'Email'], $leader_id);
        $first = $importer->import_row(['repeat-csv@example.com', 'Repeat', 'CSV'], $leader_id);
        $this->user_ids[] = $first['user_id'];
        $repeat = $importer->import_row(['repeat-csv@example.com', 'Repeat', 'CSV'], $leader_id);

        $this->assertFalse($invalid['success']);
        $this->assertStringContainsString('Missing required fields', $invalid['message']);
        $this->assertTrue($first['success']);
        $this->assertFalse($repeat['success']);
        $this->assertSame(1, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d",
            $leader_id
        )));
        $this->assertSame(0, (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->relationshipTable()} WHERE leader_id = %d",
            $other_leader_id
        )));
    }
}