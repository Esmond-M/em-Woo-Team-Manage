<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use emWooTeamManage\init_plugin\Classes\TeamContentAccess;
use emWooTeamManage\init_plugin\Classes\TeamContentAccessSync;

/**
 * Tests for TeamContentAccessSync::handle_order_status_changed() dispatch logic.
 *
 * grant_order()/revoke_order() themselves touch $wpdb and WooCommerce objects,
 * so they're covered at the integration level (see
 * tests/Integration/TeamContentAccessTest.php). Here we only verify the
 * dispatch decides correctly which one to call for a given status, using a
 * partial mock so no WordPress or WooCommerce functions are needed.
 */
class TeamContentAccessSyncDispatchTest extends TestCase
{
    private function makeSyncMock(): TeamContentAccessSync
    {
        return $this->getMockBuilder(TeamContentAccessSync::class)
            ->setConstructorArgs([new TeamContentAccess()])
            ->onlyMethods(['grant_order', 'revoke_order'])
            ->getMock();
    }

    #[Test]
    public function processing_status_grants_access(): void
    {
        $sync = $this->makeSyncMock();
        $sync->expects($this->once())->method('grant_order')->with(42);
        $sync->expects($this->never())->method('revoke_order');

        $sync->handle_order_status_changed(42, 'pending', 'processing');
    }

    #[Test]
    public function completed_status_grants_access(): void
    {
        $sync = $this->makeSyncMock();
        $sync->expects($this->once())->method('grant_order')->with(42);
        $sync->expects($this->never())->method('revoke_order');

        $sync->handle_order_status_changed(42, 'processing', 'completed');
    }

    #[Test]
    public function refunded_status_revokes_access(): void
    {
        $sync = $this->makeSyncMock();
        $sync->expects($this->once())->method('revoke_order')->with(42);
        $sync->expects($this->never())->method('grant_order');

        $sync->handle_order_status_changed(42, 'completed', 'refunded');
    }

    #[Test]
    public function cancelled_status_revokes_access(): void
    {
        $sync = $this->makeSyncMock();
        $sync->expects($this->once())->method('revoke_order')->with(42);
        $sync->expects($this->never())->method('grant_order');

        $sync->handle_order_status_changed(42, 'pending', 'cancelled');
    }

    #[Test]
    public function failed_status_revokes_access(): void
    {
        $sync = $this->makeSyncMock();
        $sync->expects($this->once())->method('revoke_order')->with(42);
        $sync->expects($this->never())->method('grant_order');

        $sync->handle_order_status_changed(42, 'pending', 'failed');
    }

    #[Test]
    public function unrelated_status_does_neither(): void
    {
        $sync = $this->makeSyncMock();
        $sync->expects($this->never())->method('grant_order');
        $sync->expects($this->never())->method('revoke_order');

        $sync->handle_order_status_changed(42, 'pending', 'on-hold');
    }
}
