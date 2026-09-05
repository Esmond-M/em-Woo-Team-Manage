<?php

declare(strict_types=1);

$tests_dir = getenv('WP_TESTS_DIR');

if (!$tests_dir || !is_dir($tests_dir)) {
    throw new RuntimeException('Integration tests require the disposable wp-env tests-cli environment.');
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
    require_once dirname(__DIR__, 2) . '/em-Woo-Team-Manage.php';
});

require_once $tests_dir . '/includes/bootstrap.php';