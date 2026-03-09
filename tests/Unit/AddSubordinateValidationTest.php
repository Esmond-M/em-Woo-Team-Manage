<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;

/**
 * Tests for TeamAjaxHandler::add_single_subordinate() input validation.
 *
 * Brain Monkey stubs all WordPress functions so no WP installation is needed.
 * Each test drives the method to an early-exit validation path and captures
 * the HTML it echoes before calling wp_die().
 *
 * wp_die() is replaced by a stub that throws WpDieException (defined in
 * tests/bootstrap.php) so we can assert on echoed output without the PHP
 * process actually dying.
 */
class AddSubordinateValidationTest extends TestCase
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
     * Stub the WordPress functions needed to pass the auth + nonce guards so
     * tests can focus on downstream validation logic.
     */
    private function passAuthAndNonce(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->alias(fn($v) => (string) $v);
        Functions\when('wp_unslash')->alias(fn($v) => $v);
        Functions\when('wp_verify_nonce')->justReturn(true);
    }

    /**
     * Run $callback, capture its output, and return it.
     * wp_die() is expected to be called exactly once and will throw WpDieException.
     */
    private function captureOutput(callable $callback): string
    {
        Functions\expect('wp_die')
            ->once()
            ->andThrow(new \WpDieException());

        ob_start();
        try {
            $callback();
        } catch (\WpDieException $e) {
            // Expected – this is how wp_die() terminates the handler in tests.
        }
        return (string) ob_get_clean();
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    #[Test]
    public function rejects_request_from_unauthorized_user(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $output = $this->captureOutput(
            fn() => (new TeamAjaxHandler())->add_single_subordinate()
        );

        $this->assertStringContainsString('newpost-error', $output);
        $this->assertStringContainsString('Unauthorized', $output);
    }

    #[Test]
    public function rejects_request_with_invalid_nonce(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->alias(fn($v) => (string) $v);
        Functions\when('wp_unslash')->alias(fn($v) => $v);
        Functions\when('wp_verify_nonce')->justReturn(false);

        $_POST = ['_single_subordinate_nonce' => 'bad_nonce'];

        $output = $this->captureOutput(
            fn() => (new TeamAjaxHandler())->add_single_subordinate()
        );

        $this->assertStringContainsString('newpost-error', $output);
        $this->assertStringContainsString('Security check failed', $output);
    }

    #[Test]
    public function rejects_submission_with_all_fields_empty(): void
    {
        $this->passAuthAndNonce();
        Functions\when('sanitize_email')->alias(fn($v) => (string) $v);
        Functions\when('is_email')->justReturn(false);

        $_POST = [
            '_single_subordinate_nonce' => 'valid',
            'single_first_name'         => '',
            'single_last_name'          => '',
            'single_email'              => '',
            'teamLeaderID'              => '1',
        ];

        $output = $this->captureOutput(
            fn() => (new TeamAjaxHandler())->add_single_subordinate()
        );

        $this->assertStringContainsString('newpost-error', $output);
        $this->assertStringContainsString('fill in all fields', $output);
    }

    #[Test]
    public function rejects_submission_with_invalid_email(): void
    {
        $this->passAuthAndNonce();
        Functions\when('sanitize_email')->alias(fn($v) => (string) $v);
        Functions\when('is_email')->justReturn(false);

        $_POST = [
            '_single_subordinate_nonce' => 'valid',
            'single_first_name'         => 'John',
            'single_last_name'          => 'Doe',
            'single_email'              => 'not-an-email',
            'teamLeaderID'              => '1',
        ];

        $output = $this->captureOutput(
            fn() => (new TeamAjaxHandler())->add_single_subordinate()
        );

        $this->assertStringContainsString('newpost-error', $output);
        $this->assertStringContainsString('valid email', $output);
    }

    #[Test]
    public function rejects_submission_missing_first_name_only(): void
    {
        $this->passAuthAndNonce();
        Functions\when('sanitize_email')->alias(fn($v) => (string) $v);
        Functions\when('is_email')->justReturn(true);

        $_POST = [
            '_single_subordinate_nonce' => 'valid',
            'single_first_name'         => '',      // missing
            'single_last_name'          => 'Doe',
            'single_email'              => 'john@example.com',
            'teamLeaderID'              => '1',
        ];

        $output = $this->captureOutput(
            fn() => (new TeamAjaxHandler())->add_single_subordinate()
        );

        $this->assertStringContainsString('newpost-error', $output);
    }
}
