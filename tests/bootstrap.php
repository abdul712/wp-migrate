<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for WP Migrate plugin
 *
 * @package WPMigrate
 * @subpackage Tests
 */

// Define test environment
define('WP_MIGRATE_TESTS', true);

// Determine WordPress test directory
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Check if WordPress test suite exists
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find WordPress test suite at $_tests_dir/includes/functions.php\n";
    echo "Please install the WordPress test suite or set WP_TESTS_DIR environment variable.\n";
    echo "See: https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WP Migrate plugin
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/wp-migrate.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load additional test helper classes
require_once __DIR__ . '/helpers/class-wp-migrate-test-case.php';
require_once __DIR__ . '/helpers/class-wp-migrate-database-helper.php';
require_once __DIR__ . '/helpers/class-wp-migrate-file-helper.php';