<?php

declare(strict_types=1);

use emWooTeamManage\init_plugin\Classes\TeamManageCore;

class AdminMenuStructureTest extends WP_UnitTestCase
{
    private array $previous_menu = [];
    private array $previous_submenu = [];

    protected function setUp(): void
    {
        parent::setUp();
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        global $menu, $submenu;
        $this->previous_menu    = (array) $menu;
        $this->previous_submenu = (array) $submenu;
        $menu    = [];
        $submenu = [];
    }

    protected function tearDown(): void
    {
        global $menu, $submenu;
        $menu    = $this->previous_menu;
        $submenu = $this->previous_submenu;
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function registerMenusAsAdmin(): array
    {
        global $submenu;

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        (new TeamManageCore())->user_import_register_submenu_page();

        return $submenu['user-import-controls'] ?? [];
    }

    public function test_parent_slug_is_not_duplicated_in_the_submenu(): void
    {
        $items = $this->registerMenusAsAdmin();

        $parent_slug_entries = array_values(array_filter(
            $items,
            static fn($item) => $item[2] === 'user-import-controls'
        ));

        $this->assertCount(1, $parent_slug_entries);
        $this->assertSame('Add Subordinates', $parent_slug_entries[0][0]);
    }

    public function test_no_submenu_item_reuses_the_parent_menu_title(): void
    {
        $items = $this->registerMenusAsAdmin();

        $titles = array_column($items, 0);

        $this->assertNotContains('Team Manage', $titles);
    }

    public function test_admin_sees_expected_pages_in_display_order(): void
    {
        $items = $this->registerMenusAsAdmin();

        ksort($items);

        $this->assertSame(
            ['Team Leaders', 'View Subordinates', 'Add Subordinates', 'Content Access', 'Settings', 'Demo Data'],
            array_values(array_column($items, 0))
        );
    }
}
