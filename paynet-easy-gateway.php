<?php

/**
 * Paynet Easy Gateway plugin for WordPress
 *
 * @link              https://payneteasy.com/
 * @since             1.0.0
 * @package           PaynetEasy
 *
 * @wordpress-plugin
 * Plugin Name:       PaynetEasy Gateway
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       PaynetEasy Gateway.
 * Version:           1.0.0
 * Author:            Edmond Dantes
 * Author URI:        https://payneteasy.com/
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       paynet-easy-gateway
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use PaynetEasy\WoocommerceGateway\WordPressPlugin;

define('PAYNET_EASY_GATEWAY_VERSION', '1.0.0');
define('PAYNET_EASY_BASE', 'PaynetEasy\\');
define('PAYNET_EASY_GATEWAY', 'paynet-easy-gateway');
/**
 * Параметр для определения страницы прогресса
 */
define('PAYNET_EASY_PAGE', 'payneteasy-page');

/**
 * Autoload classes
 */
spl_autoload_register(function ($class) {

    if(strpos($class,PAYNET_EASY_BASE) !== 0)
    {
        return;
    }

    $file                           = __DIR__.'/'.str_replace('\\', '/', $class).'.php';

    if(is_file($file))
    {
        include_once $file;
    }
});

$plugin                             = new WordPressPlugin(__FILE__, PAYNET_EASY_GATEWAY_VERSION);
$plugin->init();
$plugin->run();