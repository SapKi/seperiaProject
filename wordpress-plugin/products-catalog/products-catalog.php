<?php
/**
 * Plugin Name:  Products Catalog
 * Plugin URI:   https://github.com/SapKi/seperiaProject
 * Description:  Displays a server-rendered, searchable product catalog sourced from DummyJSON.
 *               On activation, automatically creates a "Compare Assignment" page.
 *               Add [products_catalog] to any page or post to render the catalog.
 *               All API calls, search, and pagination are handled in PHP — JavaScript
 *               is used only for the gallery row toggle.
 * Version:      1.0.0
 * Author:       Sapir
 * License:      MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PRODUCTS_CATALOG_VERSION', '1.0.0' );
define( 'PRODUCTS_CATALOG_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PRODUCTS_CATALOG_URL',     plugin_dir_url( __FILE__ ) );

require_once PRODUCTS_CATALOG_PATH . 'includes/class-activator.php';
require_once PRODUCTS_CATALOG_PATH . 'includes/class-shortcode.php';

register_activation_hook( __FILE__, array( 'Products_Catalog_Activator', 'activate' ) );

add_action( 'init',             array( 'Products_Catalog_Shortcode', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Products_Catalog_Shortcode', 'enqueue_assets' ) );
