<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamContentAccess;
use emWooTeamManage\init_plugin\Classes\TeamContentAccessSync;
use emWooTeamManage\init_plugin\Classes\TeamContentAssignment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TeamContentAssignment authorization guards and the
 * assignment-order visibility filter.
 *
 * assign()/unassign() themselves need WooCommerce and $wpdb, so their
 * behavior is covered in tests/Integration/TeamContentAssignmentTest.php.
 */
class TeamContentAssignmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
        $_POST = [];
    }

    private function makeAssignment(): TeamContentAssignment
    {
        $ledger = new TeamContentAccess();

        return new TeamContentAssignment($ledger, new TeamContentAccessSync($ledger));
    }

    /**
     * @return array [$data, $status_code]
     */
    private function runAndCaptureJsonError(callable $callback): array
    {
        $captured = [];
        Functions\when('wp_send_json_error')->alias(function () use (&$captured) {
            $captured = func_get_args();
            throw new \WpDieException();
        });

        try {
            $callback();
        } catch (\WpDieException $e) {
            // Expected.
        }

        return $captured;
    }

    #[Test]
    public function assign_rejects_invalid_nonce(): void
    {
        Functions\when('check_ajax_referer')->justReturn(false);

        $args = $this->runAndCaptureJsonError(fn() => $this->makeAssignment()->ajax_assign());

        $this->assertSame(['message' => 'Invalid nonce.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function assign_rejects_user_without_manage_options_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $args = $this->runAndCaptureJsonError(fn() => $this->makeAssignment()->ajax_assign());

        $this->assertSame(['message' => 'Insufficient permissions.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function unassign_rejects_invalid_nonce(): void
    {
        Functions\when('check_ajax_referer')->justReturn(false);

        $args = $this->runAndCaptureJsonError(fn() => $this->makeAssignment()->ajax_unassign());

        $this->assertSame(['message' => 'Invalid nonce.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function unassign_rejects_user_without_manage_options_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $args = $this->runAndCaptureJsonError(fn() => $this->makeAssignment()->ajax_unassign());

        $this->assertSame(['message' => 'Insufficient permissions.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function assign_requires_woocommerce(): void
    {
        // wc_create_order genuinely does not exist in this WordPress-free suite.
        $result = $this->makeAssignment()->assign(1, [2]);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['order_id']);
        $this->assertStringContainsString('WooCommerce must be active', $result['message']);
    }

    #[Test]
    public function order_visibility_filter_is_inert_when_setting_is_disabled(): void
    {
        Functions\when('get_option')->justReturn(false);

        $query_vars = $this->makeAssignment()->filter_hide_assignment_orders(['limit' => 10]);

        $this->assertSame(['limit' => 10], $query_vars);
    }

    #[Test]
    public function order_visibility_filter_excludes_assignment_orders_when_enabled(): void
    {
        Functions\when('get_option')->justReturn(1);

        $query_vars = $this->makeAssignment()->filter_hide_assignment_orders(['limit' => 10]);

        $this->assertArrayHasKey('meta_query', $query_vars);
        $this->assertSame(TeamContentAssignment::ORDER_META_KEY, $query_vars['meta_query'][0]['key']);
        $this->assertSame('NOT EXISTS', $query_vars['meta_query'][0]['compare']);
    }
}
