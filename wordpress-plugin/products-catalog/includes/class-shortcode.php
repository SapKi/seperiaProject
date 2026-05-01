<?php
/**
 * Registers the [products_catalog] shortcode and enqueues assets.
 *
 * Usage: add [products_catalog] to any page or post content.
 */
class Products_Catalog_Shortcode {

    public static function register() {
        add_shortcode( 'products_catalog', array( __CLASS__, 'render' ) );
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'products-catalog',
            PRODUCTS_CATALOG_URL . 'assets/css/products-catalog.css',
            array(),
            PRODUCTS_CATALOG_VERSION
        );

        wp_enqueue_script(
            'products-catalog',
            PRODUCTS_CATALOG_URL . 'assets/js/products-catalog.js',
            array(),         // no dependencies
            PRODUCTS_CATALOG_VERSION,
            true             // load in footer
        );

        // Pass server-side config to the JS as a plain object.
        wp_localize_script( 'products-catalog', 'ProductsCatalogConfig', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => 'products_catalog_fetch',
        ) );
    }

    /**
     * Shortcode callback — outputs the HTML shell.
     * All data is loaded and rendered by products-catalog.js.
     */
    public static function render() {
        ob_start();
        ?>
        <div id="products-catalog-app">

          <div class="pc-toolbar">
            <form id="pc-search-form" class="pc-search-form" role="search">
              <input
                type="text"
                id="pc-search-input"
                placeholder="Search products&hellip;"
                aria-label="Search products"
                autocomplete="off"
              />
              <button type="submit" class="pc-btn pc-btn-primary">Search</button>
              <button type="button" id="pc-clear-btn" class="pc-btn pc-btn-secondary" hidden>Clear</button>
            </form>
            <div class="pc-meta-info" id="pc-meta-info" aria-live="polite"></div>
          </div>

          <div id="pc-error-banner" class="pc-alert-error" role="alert" hidden></div>

          <div class="pc-table-wrapper">
            <table aria-label="Product list">
              <thead>
                <tr>
                  <th>Thumbnail</th>
                  <th>Title</th>
                  <th>Description</th>
                  <th>Price</th>
                  <th>Rating</th>
                  <th>Stock</th>
                  <th>Brand</th>
                  <th>Category</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="pc-products-body">
                <tr><td colspan="9" class="pc-loading">Loading&hellip;</td></tr>
              </tbody>
            </table>
          </div>

          <nav class="pc-pagination" id="pc-pagination" aria-label="Page navigation"></nav>

        </div>
        <?php
        return ob_get_clean();
    }
}
