<?php declare(strict_types=1);
namespace emWooTeamManage\init_plugin;

/**
 * Main plugin file for EM WooTeamManage.
 * 
 * Handles plugin initialization, text domain loading, and class autoloading.
 * Registers the main plugin class and ensures minimum PHP version.
 * 
 * PHP version 7.4.33
 *
 * @category  Wordpress_Plugin
 * @package   Esmond-M
 * @author    Esmond Mccain <esmondmccain@gmail.com>
 * @license   https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
 * @link      esmondmccain.com
 */

/**
 * Plugin Name:       EM WooTeamManage
 * Description:       This plugin adds a team management page for WooCommerce customers to import other users and manage those users.
 * Requires at least: 6.1
 * Requires PHP:      7.4.33
 * Requires Plugins: woocommerce
 * Version:           0.1.0
 * Author:            Esmond Mccain
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       em-Woo-Team-Manage
 *
 * @package emWooTeamManage
 */

define('EMWTM_VERSION', '0.1.0');
define('EMWTM_DB_VERSION', '2');
define('EMWTM_PLUGIN_FILE', __FILE__);

defined('ABSPATH') or die();

/**
 * Initializes the EM WooTeamManage plugin.
 * Loads text domain, main class, and ensures singleton instance.
 */
final class emWooTeamManageInit {

    const VERSION = '0.1.0';
    const PHP_MINIMUM_VERSION = '7.4.33';

    private static $_instance = null;

    /**
     * Registers hooks for plugin initialization and class loading.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'i18n' ] );        
        add_action( 'plugins_loaded', [ $this, 'init_class' ] );
        add_action( 'plugins_loaded', [ $this, 'emwtm_maybe_upgrade_tables' ] );
        add_action( 'activate_' . plugin_basename( __FILE__ ), [ $this, 'emwtm_create_team_table' ] );
        add_action( 'activate_' . plugin_basename( __FILE__ ), [ $this, 'emwtm_create_content_grants_table' ] );
        add_action( 'deactivate_' . plugin_basename( __FILE__ ), [ $this, 'emwtm_deactivate' ] );
    }

    /**
     * Loads plugin text domain for translations.
     */
    public function i18n() {
        load_plugin_textdomain( 'emWooTeamManage' );
    }

    /**
     * Loads the main plugin class.
     */
    public function init_class() {
        require_once __DIR__ . '/includes/classes/TeamManageCore.php';
    }

    /**
     * Creates the custom team leaders/subordinates table in the database.
     * Also registers the My Account endpoint and flushes rewrite rules.
     */
    public function emwtm_create_team_table() {
        // Register the endpoint before flushing so WP writes it to .htaccess/rewrite rules.
        add_rewrite_endpoint('team-manage', EP_ROOT | EP_PAGES);
        flush_rewrite_rules();

        global $wpdb;
        $table_name = $wpdb->prefix . 'emwtm_team_leaders_subordinates';
        $charset_collate = $wpdb->get_charset_collate();

        $existing_table = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($table_name)
        ));
        if (!empty($existing_table)) {
            $duplicate_groups = $wpdb->get_results(
                "SELECT leader_id, subordinate_id, MIN(id) AS retained_id
                 FROM {$table_name}
                 GROUP BY leader_id, subordinate_id
                 HAVING COUNT(*) > 1"
            );
            foreach ($duplicate_groups as $duplicate_group) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$table_name}
                     WHERE leader_id = %d AND subordinate_id = %d AND id <> %d",
                    (int) $duplicate_group->leader_id,
                    (int) $duplicate_group->subordinate_id,
                    (int) $duplicate_group->retained_id
                ));
            }
        }

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            leader_id bigint(20) unsigned NOT NULL,
            subordinate_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY leader_id (leader_id),
            KEY subordinate_id (subordinate_id),
            UNIQUE KEY leader_subordinate (leader_id, subordinate_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Creates the team content access grants table.
     * Tracks which users have access to a product because their team leader
     * purchased it or an administrator assigned it, independently of
     * WooCommerce's own download-permission records.
     */
    public function emwtm_create_content_grants_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'emwtm_team_content_grants';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            leader_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            subordinate_id bigint(20) unsigned NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'purchase',
            granted_at datetime DEFAULT CURRENT_TIMESTAMP,
            revoked_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY leader_id (leader_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY subordinate_id (subordinate_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Applies table schema changes to installs that were activated before the
     * current EMWTM_DB_VERSION, without requiring deactivation/reactivation.
     */
    public function emwtm_maybe_upgrade_tables(): void {
        if ( get_option( 'emwtm_db_version' ) === EMWTM_DB_VERSION ) {
            return;
        }

        $this->emwtm_create_content_grants_table();
        update_option( 'emwtm_db_version', EMWTM_DB_VERSION );
    }

    /**
     * Flushes plugin rewrite rules without deleting plugin data.
     */
    public function emwtm_deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Returns the singleton instance of the plugin initializer.
     */
    public static function get_instance() {
        if ( null == self::$_instance ) {
            self::$_instance = new Self();
        }
        return self::$_instance;
    }
}

emWooTeamManageInit::get_instance();
