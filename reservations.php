<?php

require_once "vendor/autoload.php";

/**
 * @wordpress-plugin
 * Plugin Name:       Reservations
 * Description:       Reservations for Wordpress
 * Version:           1.0.0
 * Author:            Martin Prokopič & Jiří Houšťava
 * Author URI:        http://www.houstava.eu/
 * Text Domain:       reservations
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined("WPINC")) {
    die;
}

/**
 * Define constants
 */
define("RESERVATIONS_VERSION", "1.0.0");
define("RESERVATIONS_SLUG", "reservations");
define("RESERVATIONS_MODE", "lubo");
define("RESERVATIONS_DEBUG", false);

/**
 * Enable Tracy
 */

require_once ABSPATH . 'wp-includes/pluggable.php';
if (RESERVATIONS_DEBUG && current_user_can("administrator")) {
    if (!session_id()) {
        session_start();
    }

    Tracy\Debugger::enable(Tracy\Debugger::DEVELOPMENT);
}

/**
 * Initialize RobotLoader
 */
$loader = new Nette\Loaders\RobotLoader;

$loader->addDirectory(__DIR__ . '/lib');

$loader->setTempDirectory(WP_CONTENT_DIR . '/cache');
$loader->register(); // Run the RobotLoader

if (!function_exists("reservations")) {
    /**
     * Function wrapper to get instance of plugin
     *
     * @return Reservations
     */
    function reservations()
    {
        return Reservations::instance();
    }
}

reservations()->run();
