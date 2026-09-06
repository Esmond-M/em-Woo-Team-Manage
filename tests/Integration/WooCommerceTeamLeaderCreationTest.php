<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamManageCore;

class WooCommerceTeamLeaderCreationTest extends WP_UnitTestCase
{
    private array $user_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->user_ids as $user_id) {
            if (get_user_by('id', $user_id)) {
                wp_delete_user($user_id);
            }
        }
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function makeGuestOrder(string $email, string $status): WC_Order
    {
        $order = wc_create_order();
        $order->set_billing_email($email);
        $order->set_billing_first_name('Paid');
        $order->set_billing_last_name('Customer');
        $order->set_status($status);
        $order->save();

        return $order;
    }

    public function test_paid_guest_order_creates_team_leader_account(): void
    {
        $email = 'paid-leader@example.com';
        $order = $this->makeGuestOrder($email, 'processing');
        wp_set_current_user(0);

        (new TeamManageCore())->create_Team_Leader_After_Payment($order->get_id());

        $user = get_user_by('email', $email);
        $this->assertNotFalse($user);
        $this->user_ids[] = (int) $user->ID;
        $this->assertContains('team_leader', (array) $user->roles);
        $this->assertSame('yes', get_user_meta($user->ID, 'guest', true));
        $this->assertSame('Paid', $user->first_name);
        $this->assertSame('Customer', $user->last_name);
    }

    public function test_pending_guest_order_does_not_create_team_leader(): void
    {
        $email = 'pending-leader@example.com';
        $order = $this->makeGuestOrder($email, 'pending');
        wp_set_current_user(0);

        (new TeamManageCore())->create_Team_Leader_After_Payment($order->get_id());

        $this->assertFalse(get_user_by('email', $email));
    }

    public function test_logged_in_customer_order_does_not_create_second_account(): void
    {
        $existing_user_id = self::factory()->user->create([
            'role' => 'customer',
            'user_email' => 'existing-customer@example.com',
        ]);
        $this->user_ids[] = $existing_user_id;
        $order = wc_create_order(['customer_id' => $existing_user_id]);
        $order->set_billing_email('existing-customer@example.com');
        $order->set_status('processing');
        $order->save();
        wp_set_current_user($existing_user_id);

        (new TeamManageCore())->create_Team_Leader_After_Payment($order->get_id());

        $users = get_users(['search' => 'existing-customer@example.com', 'search_columns' => ['user_email']]);
        $this->assertCount(1, $users);
        $this->assertContains('customer', (array) $users[0]->roles);
        $this->assertNotContains('team_leader', (array) $users[0]->roles);
    }
}