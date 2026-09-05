<?php

declare(strict_types=1);

namespace emWooTeamManage\Tests\Unit;

use Brain\Monkey\Functions;
use emWooTeamManage\init_plugin\Classes\TeamAjaxHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminAssetAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
        $GLOBALS['pagenow'] = 'admin.php';
        $_GET['page'] = 'team-leader-admin';
        if (!defined('EMWTM_PLUGIN_FILE')) {
            define('EMWTM_PLUGIN_FILE', __FILE__);
        }
        if (!defined('EMWTM_VERSION')) {
            define('EMWTM_VERSION', '0.1.0');
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pagenow'], $_GET['page']);
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    #[Test]
    public function does_not_enqueue_team_assets_for_unauthorized_users(): void
    {
        Functions\when('sanitize_key')->alias(fn($value) => (string) $value);
        Functions\expect('current_user_can')->once()->with('team_leader')->andReturn(false);
        Functions\expect('wp_enqueue_style')->never();
        Functions\expect('wp_enqueue_script')->never();

        (new TeamAjaxHandler())->load_Admin_Styles();
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function allows_team_leaders_to_receive_team_assets(): void
    {
        Functions\when('sanitize_key')->alias(fn($value) => (string) $value);
        Functions\expect('current_user_can')->once()->with('team_leader')->andReturn(true);
        Functions\when('plugins_url')->justReturn('https://example.com/asset');
        Functions\when('wp_enqueue_style')->justReturn(null);
        Functions\when('wp_enqueue_script')->justReturn(null);
        Functions\when('wp_localize_script')->justReturn(null);
        Functions\when('wp_create_nonce')->justReturn('nonce');
        Functions\when('admin_url')->justReturn('https://example.com/admin-ajax.php');

        (new TeamAjaxHandler())->load_Admin_Styles();

        $this->addToAssertionCount(1);
    }
}