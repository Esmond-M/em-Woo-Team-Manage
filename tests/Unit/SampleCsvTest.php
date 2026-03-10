<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

/**
 * Tests for TeamAjaxHandler::sample_csv() security guards.
 *
 * Only the early-exit guard paths are tested here; the happy-path
 * calls exit() which cannot be intercepted in a CLI PHPUnit process.
 */
class SampleCsvTest extends TestCase
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
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Capture the arguments passed to wp_die() and halt execution via
     * WpDieException so the test can inspect the response code.
     *
     * @return array{0:mixed, 1:mixed, 2:mixed}  [$message, $title, $args]
     */
    private function runAndCaptureDie(callable $callback): array
    {
        $die_args = [];
        Functions\when('wp_die')->alias(function () use (&$die_args) {
            $die_args = func_get_args();
            throw new \WpDieException();
        });

        try {
            $callback();
        } catch (\WpDieException $e) {
            // Expected – wp_die() was called.
        }

        return $die_args;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function rejects_request_with_invalid_nonce(): void
    {
        Functions\when('check_ajax_referer')->justReturn(false);

        $args = $this->runAndCaptureDie(
            fn() => (new TeamAjaxHandler())->sample_csv()
        );

        $this->assertSame(403, $args[2]['response'] ?? null);
    }

    #[Test]
    public function rejects_user_without_team_leader_or_admin_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $args = $this->runAndCaptureDie(
            fn() => (new TeamAjaxHandler())->sample_csv()
        );

        $this->assertSame(403, $args[2]['response'] ?? null);
    }
}
