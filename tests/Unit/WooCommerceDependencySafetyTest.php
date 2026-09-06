<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamManageCore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WooCommerceDependencySafetyTest extends TestCase
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

    #[Test]
    public function does_not_enqueue_account_styles_when_woocommerce_is_unavailable(): void
    {
        $this->assertFalse(function_exists('is_account_page'));
        Functions\expect('wp_enqueue_style')->never();

        (new TeamManageCore())->load_myaccount_styles();
    }
}