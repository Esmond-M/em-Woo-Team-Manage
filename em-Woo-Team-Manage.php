<?php

/**
* Main plugin file.
* PHP version 7.4.33

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
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

final class emWooTeamManageInit {

    const VERSION = '0.1.0';
    const PHP_MINIMUM_VERSION = '7.4.33';

    private static $_instance = null;

    public function __construct() {
    
       add_action( 'init', [ $this, 'i18n' ] );        
       add_action( 'plugins_loaded', [ $this, 'init_class' ] );
 
    }

    public function i18n() {
        load_plugin_textdomain( 'emWooTeamManage' );
    }

    public function init_class() {
        require_once __DIR__ . '/includes/classes/emWooTeamManage.php';
   
     
    }



    public static function get_instance() {

        if ( null == self::$_instance ) {
            self::$_instance = new Self();
        }

        return self::$_instance;

    }

}


emWooTeamManageInit::get_instance();
