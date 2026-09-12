<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamContentAccess;
use emWooTeamManage\init_plugin\Classes\TeamContentAccessSync;
use emWooTeamManage\init_plugin\Classes\TeamContentAssignment;

class TeamContentAssignmentTest extends WP_UnitTestCase
{
    private array $user_ids = [];
    private array $product_ids = [];
    private array $download_file_paths = [];
    private $previous_approved_directories_mode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous_approved_directories_mode = get_option('wc_downloads_approved_directories_mode');
        update_option('wc_downloads_approved_directories_mode', 'disabled');
    }

    private function relationshipTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'emwtm_team_leaders_subordinates';
    }

    private function addToTeam(int $leader_id, int $subordinate_id): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->relationshipTable(),
            ['leader_id' => $leader_id, 'subordinate_id' => $subordinate_id],
            ['%d', '%d']
        );
    }

    private function makeDownloadableProduct(): WC_Product_Simple
    {
        $upload_dir = wp_upload_dir();
        $file_path  = $upload_dir['basedir'] . '/emwtm-assign-test-' . uniqid('', true) . '.txt';
        file_put_contents($file_path, 'assignment test content');
        $this->download_file_paths[] = $file_path;

        $download = new WC_Product_Download();
        $download->set_id(md5($file_path));
        $download->set_name('Assigned eBook');
        $download->set_file($file_path);

        $product = new WC_Product_Simple();
        $product->set_name('Assigned eBook');
        $product->set_regular_price('25');
        $product->set_virtual(true);
        $product->set_downloadable(true);
        $product->set_downloads([$download]);
        $product->save();
        $this->product_ids[] = $product->get_id();

        return $product;
    }

    private function makeAssignment(): TeamContentAssignment
    {
        $ledger = new TeamContentAccess();

        return new TeamContentAssignment($ledger, new TeamContentAccessSync($ledger));
    }

    private function hasDownloadPermission(int $product_id, int $user_id): bool
    {
        $data_store = WC_Data_Store::load('customer-download');

        return !empty($data_store->get_downloads([
            'user_id'    => $user_id,
            'product_id' => $product_id,
        ]));
    }

    protected function tearDown(): void
    {
        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        foreach ($this->product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->delete(true);
            }
        }
        foreach ($this->download_file_paths as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->relationshipTable()}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}emwtm_team_content_grants");
        update_option('wc_downloads_approved_directories_mode', $this->previous_approved_directories_mode);
        parent::tearDown();
    }

    public function test_assigning_grants_access_to_leader_and_existing_subordinates(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];
        $this->addToTeam($leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $result = $this->makeAssignment()->assign($leader_id, [$product->get_id()]);

        $ledger = new TeamContentAccess();
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['order_id']);
        $this->assertTrue($ledger->has_access($leader_id, $product->get_id()));
        $this->assertTrue($ledger->has_access($subordinate_id, $product->get_id()));
        $this->assertTrue($this->hasDownloadPermission($product->get_id(), $subordinate_id));
    }

    public function test_assignment_is_recorded_with_assignment_source(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id];

        $product = $this->makeDownloadableProduct();
        $this->makeAssignment()->assign($leader_id, [$product->get_id()]);

        $products = (new TeamContentAccess())->get_team_products($leader_id);

        $this->assertCount(1, $products);
        $this->assertSame(TeamContentAccess::SOURCE_ASSIGNMENT, $products[0]['source']);
    }

    public function test_assignment_order_is_flagged_and_zero_total(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id];

        $product = $this->makeDownloadableProduct();
        $result = $this->makeAssignment()->assign($leader_id, [$product->get_id()]);

        $order = wc_get_order($result['order_id']);

        $this->assertTrue(TeamContentAssignment::is_assignment_order($result['order_id']));
        $this->assertSame('0', (string) (float) $order->get_total());
        $this->assertSame($leader_id, $order->get_customer_id());
    }

    public function test_subordinate_added_after_assignment_receives_access(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];

        $product = $this->makeDownloadableProduct();
        $this->makeAssignment()->assign($leader_id, [$product->get_id()]);

        $ledger = new TeamContentAccess();
        $this->assertFalse($ledger->has_access($subordinate_id, $product->get_id()));

        // Mirrors what the add-subordinate flows fire once the relationship exists.
        $this->addToTeam($leader_id, $subordinate_id);
        do_action('emwtm_subordinate_added', $leader_id, $subordinate_id);

        $this->assertTrue($ledger->has_access($subordinate_id, $product->get_id()));
    }

    public function test_assigning_the_same_product_twice_does_not_duplicate(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id];

        $product = $this->makeDownloadableProduct();
        $assignment = $this->makeAssignment();
        $assignment->assign($leader_id, [$product->get_id()]);
        $second = $assignment->assign($leader_id, [$product->get_id()]);

        $this->assertFalse($second['success']);
        $this->assertCount(1, (new TeamContentAccess())->get_team_products($leader_id));
    }

    public function test_unassigning_revokes_access_for_whole_team(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $subordinate_id = self::factory()->user->create(['role' => 'team_subordinate']);
        $this->user_ids = [$leader_id, $subordinate_id];
        $this->addToTeam($leader_id, $subordinate_id);

        $product = $this->makeDownloadableProduct();
        $assignment = $this->makeAssignment();
        $assignment->assign($leader_id, [$product->get_id()]);

        $result = $assignment->unassign($leader_id, $product->get_id());

        $ledger = new TeamContentAccess();
        $this->assertTrue($result['success']);
        $this->assertFalse($ledger->has_access($leader_id, $product->get_id()));
        $this->assertFalse($ledger->has_access($subordinate_id, $product->get_id()));
        $this->assertFalse($this->hasDownloadPermission($product->get_id(), $subordinate_id));
    }

    public function test_assign_rejects_a_user_who_is_not_a_team_leader(): void
    {
        $customer_id = self::factory()->user->create(['role' => 'customer']);
        $this->user_ids = [$customer_id];

        $product = $this->makeDownloadableProduct();
        $result = $this->makeAssignment()->assign($customer_id, [$product->get_id()]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid team leader', $result['message']);
    }

    public function test_assign_rejects_non_downloadable_products(): void
    {
        $leader_id = self::factory()->user->create(['role' => 'team_leader']);
        $this->user_ids = [$leader_id];

        $product = new WC_Product_Simple();
        $product->set_name('Physical Book');
        $product->set_regular_price('30');
        $product->save();
        $this->product_ids[] = $product->get_id();

        $result = $this->makeAssignment()->assign($leader_id, [$product->get_id()]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['assigned']);
    }
}
