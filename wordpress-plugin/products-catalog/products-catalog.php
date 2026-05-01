<?php
/**
 * Plugin Name:  Products Catalog
 * Plugin URI:   https://github.com/your-repo
 * Description:  Displays a dynamic, searchable product catalog sourced from DummyJSON.
 *               Activating the plugin auto-creates a "Compare Assignment" page with the
 *               [products_catalog] shortcode embedded. All DummyJSON API calls are made
 *               server-side (PHP); the frontend uses plain JavaScript with no framework.
 * Version:      1.0.0
 * Author:       Your Name
 * License:      MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct file access.
}

define( 'PRODUCTS_CATALOG_VERSION', '1.0.0' );
define( 'PRODUCTS_CATALOG_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PRODUCTS_CATALOG_URL',     plugin_dir_url( __FILE__ ) );

// ── Autoload classes ─────────────────────────────────────────────────────────
require_once PRODUCTS_CATALOG_PATH . 'includes/class-activator.php';
require_once PRODUCTS_CATALOG_PATH . 'includes/class-api.php';
require_once PRODUCTS_CATALOG_PATH . 'includes/class-shortcode.php';

// ── Hooks ─────────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, array( 'Products_Catalog_Activator', 'activate' ) );

add_action( 'init', array( 'Products_Catalog_Shortcode', 'register' ) );
add_action( 'wp_enqueue_scripts', array( 'Products_Catalog_Shortcode', 'enqueue_assets' ) );
add_action( 'wp_ajax_products_catalog_fetch',        array( 'Products_Catalog_API', 'handle' ) );
add_action( 'wp_ajax_nopriv_products_catalog_fetch', array( 'Products_Catalog_API', 'handle' ) );
