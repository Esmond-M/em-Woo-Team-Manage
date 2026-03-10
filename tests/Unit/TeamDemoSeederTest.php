<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamDemoSeeder;

/**
 * Tests for TeamDemoSeeder.
 *
 * Covers:
 *  - get_status()  — returns correct leader / subordinate counts.
 *  - ajax_seed()   — nonce and capability guards.
 *  - ajax_clear()  — nonce and capability guards.
 *
 * seed() and clear() delegate to core WP DB functions which require a live
 * WordPress installation; those are integration-level and not tested here.
 */
class TeamDemoSeederTest extends TestCase
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Stubs wp_send_json_error() to capture its arguments and then stop
     * execution via WpDieException.
     *
     * @return array  [$data, $status_code]  populated after the callable runs.
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
            // Expected – wp_send_json_error() was called.
        }

        return $captured;
    }

    // ── get_status ────────────────────────────────────────────────────────────

    #[Test]
    public function get_status_returns_correct_leader_and_subordinate_counts(): void
    {
        Functions\when('get_users')->alias(function (array $args): array {
            return match ($args['role'] ?? '') {
                'team_leader'      => [10, 11],       // 2 demo leaders
                'team_subordinate' => [20, 21, 22],   // 3 demo subs
                default            => [],
            };
        });

        $status = (new TeamDemoSeeder())->get_status();

        $this->assertSame(['leaders' => 2, 'subordinates' => 3], $status);
    }

    #[Test]
    public function get_status_returns_zeros_when_no_demo_users_exist(): void
    {
        Functions\when('get_users')->justReturn([]);

        $status = (new TeamDemoSeeder())->get_status();

        $this->assertSame(['leaders' => 0, 'subordinates' => 0], $status);
    }

    // ── ajax_seed ─────────────────────────────────────────────────────────────

    #[Test]
    public function ajax_seed_rejects_invalid_nonce(): void
    {
        Functions\when('check_ajax_referer')->justReturn(false);

        $args = $this->runAndCaptureJsonError(
            fn() => (new TeamDemoSeeder())->ajax_seed()
        );

        $this->assertSame(['message' => 'Invalid nonce.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function ajax_seed_rejects_user_without_manage_options_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $args = $this->runAndCaptureJsonError(
            fn() => (new TeamDemoSeeder())->ajax_seed()
        );

        $this->assertSame(['message' => 'Insufficient permissions.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    // ── ajax_clear ────────────────────────────────────────────────────────────

    #[Test]
    public function ajax_clear_rejects_invalid_nonce(): void
    {
        Functions\when('check_ajax_referer')->justReturn(false);

        $args = $this->runAndCaptureJsonError(
            fn() => (new TeamDemoSeeder())->ajax_clear()
        );

        $this->assertSame(['message' => 'Invalid nonce.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function ajax_clear_rejects_user_without_manage_options_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $args = $this->runAndCaptureJsonError(
            fn() => (new TeamDemoSeeder())->ajax_clear()
        );

        $this->assertSame(['message' => 'Insufficient permissions.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }
}
