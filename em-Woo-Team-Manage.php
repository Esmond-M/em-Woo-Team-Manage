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
declare(strict_types=1);
namespace emWooTeamManage;
/**
 * Plugin Name:       EM WooTeamManage
 * Description:       This plugin adds a team management page for WooCommerce customers to import other users and manage those users.
 * Requires at least: 6.1
 * Requires PHP:      7.4.33
 * Version:           0.1.0
 * Author:            Esmond Mccain
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       em-Woo-Team-Manage
 *
 * @package emWooTeamManage
 */

defined('ABSPATH') or die();
/**
 * Define global constants

 * @param $constant_name
 * @param $value
 *
 * @return array
 */
function emWooTeamManageConstants($constant_name, $value)
{
    $constant_name_prefix = 'EM_Woo_Team_Manage_Constants_';
    $constant_name = $constant_name_prefix . $constant_name;
    if (!defined($constant_name))
        define($constant_name, $value);
}

emWooTeamManageConstants('DIR', dirname(plugin_basename(__FILE__)));
emWooTeamManageConstants('BASE', plugin_basename(__FILE__));
emWooTeamManageConstants('URL', plugin_dir_url(__FILE__));
emWooTeamManageConstants('PATH', plugin_dir_path(__FILE__));
emWooTeamManageConstants('SLUG', dirname(plugin_basename(__FILE__)));
require  EM_Woo_Team_Manage_Constants_PATH
    . 'includes/classes/emWooTeamManage.php';
use emWooTeamManage\emWooTeamManage;

new emWooTeamManage;