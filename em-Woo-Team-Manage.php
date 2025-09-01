<?php

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

declare(strict_types=1);
namespace emWooTeamManage\init_plugin;

use emWooTeamManage\init_plugin\Classes\emWooTeamManage;

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
        require_once __DIR__ . '/includes/classes/emWooTeamManage.php';
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
