<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamDemoSeeder;
use emWooTeamManage\init_plugin\Classes\TeamManageCore;

class DemoProductProvisioningTest extends WP_UnitTestCase
{
    private array $user_ids = [];

    protected function tearDown(): void
    {
        (new TeamDemoSeeder())->remove_demo_product();
        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function test_create_demo_product_is_idempotent(): void
    {
        $seeder = new TeamDemoSeeder();

        $first  = $seeder->create_demo_product();
        $second = $seeder->create_demo_product();

        $this->assertTrue($first['success']);
        $this->assertGreaterThan(0, $first['product_id']);
        $this->assertSame($first['product_id'], $second['product_id']);
        $this->assertSame('Demo product already exists.', $second['message']);
    }

    public function test_guest_checkout_with_demo_product_creates_team_leader_account(): void
    {
        $seeder = new TeamDemoSeeder();
        $created = $seeder->create_demo_product();
        $product = wc_get_product($created['product_id']);

        $email = 'demo-purchase-leader@example.com';
        $order = wc_create_order();
        $order->set_billing_email($email);
        $order->set_billing_first_name('Demo');
        $order->set_billing_last_name('Buyer');
        $order->add_product($product, 1);
        $order->set_status('processing');
        $order->save();
        wp_set_current_user(0);

        (new TeamManageCore())->create_Team_Leader_After_Payment($order->get_id());

        $user = get_user_by('email', $email);
        $this->assertNotFalse($user);
        $this->user_ids[] = (int) $user->ID;
        $this->assertContains('team_leader', (array) $user->roles);
    }

    public function test_remove_demo_product_deletes_it_and_status_reflects_removal(): void
    {
        $seeder = new TeamDemoSeeder();
        $created = $seeder->create_demo_product();

        $removed = $seeder->remove_demo_product();
        $status  = $seeder->get_product_status();

        $this->assertTrue($removed);
        $this->assertSame(0, $status['product_id']);
        $this->assertFalse(wc_get_product($created['product_id']));
    }
}
