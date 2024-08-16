<?php

/**
* Main plugin file.
* PHP version 7.3

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
*/
declare(strict_types=1);
namespace emUserImport;
/**
 * Plugin Name:       EM User Import
 * Description:       Import user information
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            Esmond Mccain
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       em-block-posts-grid
 *
 * @package EmBlockPostsGrid
 */

defined('ABSPATH') or die();
/**
 * Define global constants

 * @param $constant_name
 * @param $value
 *
 * @return array
 */
function emUserImportConstants($constant_name, $value)
{
    $constant_name_prefix = 'EM_User_Import_Constants_';
    $constant_name = $constant_name_prefix . $constant_name;
    if (!defined($constant_name))
        define($constant_name, $value);
}

emUserImportConstants('DIR', dirname(plugin_basename(__FILE__)));
emUserImportConstants('BASE', plugin_basename(__FILE__));
emUserImportConstants('URL', plugin_dir_url(__FILE__));
emUserImportConstants('PATH', plugin_dir_path(__FILE__));
emUserImportConstants('SLUG', dirname(plugin_basename(__FILE__)));
require  EM_User_Import_Constants_PATH
    . 'includes/classes/emUserImport.php';
use emUserImport\emUserImport;

new emUserImport;