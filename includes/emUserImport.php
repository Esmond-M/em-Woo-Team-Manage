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

if (!class_exists('emUserImport')) {
/**
* Declaring class

* @category Wordpress_Plugin
* @package  Esmond-M
* @author   Esmond Mccain <esmondmccain@gmail.com>
* @license  https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License
* @link     esmondmccain.com
* @return
*/

defined('ABSPATH') or die();
/**
 * Define global constants

 * @param $constant_name
 * @param $value
 *
 * @return array
 */
function EmUserImportConstants($constant_name, $value)
{
    $constant_name_prefix = 'EM_User_Import_Constants_';
    $constant_name = $constant_name_prefix . $constant_name;
    if (!defined($constant_name))
        define($constant_name, $value);
}

EmUserImportConstants('DIR', dirname(plugin_basename(__FILE__)));
EmUserImportConstants('BASE', plugin_basename(__FILE__));
EmUserImportConstants('URL', plugin_dir_url(__FILE__));
EmUserImportConstants('PATH', plugin_dir_path(__FILE__));
EmUserImportConstants('SLUG', dirname(plugin_basename(__FILE__)));
require  EM_User_Import_Constants_PATH
    . 'includes/classes/emUserImport.php';
use emUserImport\emUserImport;

new emUserImport;