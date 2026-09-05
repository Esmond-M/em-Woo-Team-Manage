<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GetSubordinatesSecurityTest extends TestCase
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

    private function runAndCaptureJsonError(callable $callback): array
    {
        $captured = [];
        Functions\when('wp_send_json_error')->alias(function () use (&$captured) {
            $captured = func_get_args();
            throw new \WpDieException();
        });

        try {
            $callback();
        } catch (\WpDieException $exception) {
            // Expected termination from wp_send_json_error().
        }

        return $captured;
    }

    #[Test]
    public function rejects_request_with_invalid_nonce(): void
    {
        Functions\expect('check_ajax_referer')
            ->once()
            ->with('get_subordinates', '_nonce', false)
            ->andReturn(false);
        Functions\expect('current_user_can')->never();

        $args = $this->runAndCaptureJsonError(
            fn() => (new TeamAjaxHandler())->ajax_get_subordinates()
        );

        $this->assertSame(['message' => 'Invalid nonce.'], $args[0]);
        $this->assertSame(403, $args[1]);
    }

    #[Test]
    public function rejects_non_admin_with_valid_nonce(): void
    {
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        $args = $this->runAndCaptureJsonError(
            fn() => (new TeamAjaxHandler())->ajax_get_subordinates()
        );

        $this->assertSame(['message' => 'Unauthorized'], $args[0]);
        $this->assertSame(403, $args[1]);
    }
}