<?php
/**
 * Registers the [products_catalog] shortcode.
 *
 * The shortcode reads query parameters from the current request, calls the
 * DummyJSON API from PHP, and renders the complete HTML table server-side —
 * exactly the same approach as the Django implementation.
 *
 * Search uses a method="GET" form (standard page reload).
 * Pagination uses plain <a> links with backend-generated URLs.
 * JavaScript is used only to toggle the pre-rendered hidden gallery rows.
 */
class Products_Catalog_Shortcode {

    const DUMMYJSON_BASE = 'https://dummyjson.com';
    const DEFAULT_LIMIT  = 10;
    const MAX_LIMIT      = 100;
    const TIMEOUT        = 10;

    // Query-parameter names are prefixed with "pc_" to avoid conflicts
    // with WordPress's own reserved query variables (e.g. "page", "search").
    const PARAM_PAGE   = 'pc_page';
    const PARAM_SEARCH = 'pc_search';
    const PARAM_LIMIT  = 'pc_limit';

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

        // JS is gallery-toggle only — no configuration object needed.
        wp_enqueue_script(
            'products-catalog',
            PRODUCTS_CATALOG_URL . 'assets/js/products-catalog.js',
            array(),
            PRODUCTS_CATALOG_VERSION,
            true
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function get_params() {
        $page   = max( 1, intval( $_GET[ self::PARAM_PAGE ]   ?? 1 ) );
        $limit  = min( self::MAX_LIMIT, max( 1, intval( $_GET[ self::PARAM_LIMIT ] ?? self::DEFAULT_LIMIT ) ) );
        $search = sanitize_text_field( $_GET[ self::PARAM_SEARCH ] ?? '' );
        return array( $page, $limit, $search );
    }

    private static function fetch_products( $page, $limit, $search ) {
        $skip = ( $page - 1 ) * $limit;

        if ( $search ) {
            $url = self::DUMMYJSON_BASE . '/products/search?' . http_build_query( array(
                'q'     => $search,
                'limit' => $limit,
                'skip'  => $skip,
            ) );
        } else {
            $url = self::DUMMYJSON_BASE . '/products?' . http_build_query( array(
                'limit' => $limit,
                'skip'  => $skip,
            ) );
        }

        $response = wp_remote_get( $url, array( 'timeout' => self::TIMEOUT ) );

        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'Could not reach the products service. Please try again.' );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return array( 'error' => "Products service returned an error ({$code})." );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || ! isset( $body['products'] ) ) {
            return array( 'error' => 'Unexpected response from products service.' );
        }

        return $body;
    }

    private static function pagination_url( $page, $limit, $search ) {
        return esc_url( add_query_arg( array(
            self::PARAM_PAGE   => $page,
            self::PARAM_LIMIT  => $limit,
            self::PARAM_SEARCH => $search,
        ), get_permalink() ) );
    }

    // ── Shortcode renderer ────────────────────────────────────────────────────

    /**
     * Renders the full product catalog HTML.
     * Called by WordPress when [products_catalog] is encountered in post content.
     */
    public static function render() {
        list( $page, $limit, $search ) = self::get_params();

        $data  = self::fetch_products( $page, $limit, $search );
        $error = $data['error'] ?? null;

        $products    = $error ? array() : $data['products'];
        $total       = $error ? 0        : (int) $data['total'];
        $total_pages = $error ? 1        : max( 1, (int) ceil( $total / $limit ) );
        $has_prev    = $page > 1;
        $has_next    = $page < $total_pages;

        $form_action = esc_url( get_permalink() );

        ob_start();
        ?>
        <div id="products-catalog-app">

          <?php if ( $error ) : ?>
            <div class="pc-alert-error" role="alert"><?php echo esc_html( $error ); ?></div>
          <?php endif; ?>

          <!-- Search form — GET request, handled server-side on reload -->
          <div class="pc-toolbar">
            <form method="get" action="<?php echo $form_action; ?>" class="pc-search-form" role="search">
              <input
                type="text"
                name="<?php echo self::PARAM_SEARCH; ?>"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="Search products&hellip;"
                aria-label="Search products"
              />
              <input type="hidden" name="<?php echo self::PARAM_LIMIT; ?>" value="<?php echo esc_attr( $limit ); ?>" />
              <button type="submit" class="pc-btn pc-btn-primary">Search</button>
              <?php if ( $search ) : ?>
                <a href="<?php echo self::pagination_url( 1, $limit, '' ); ?>" class="pc-btn pc-btn-secondary">Clear</a>
              <?php endif; ?>
            </form>

            <div class="pc-meta-info">
              <?php if ( $search ) : ?>
                <strong><?php echo esc_html( $total ); ?></strong>
                result<?php echo $total !== 1 ? 's' : ''; ?>
                for &ldquo;<?php echo esc_html( $search ); ?>&rdquo;
              <?php else : ?>
                <strong><?php echo esc_html( $total ); ?></strong>
                product<?php echo $total !== 1 ? 's' : ''; ?>
                &mdash; page <?php echo esc_html( $page ); ?> of <?php echo esc_html( $total_pages ); ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Product table — fully server-rendered by PHP -->
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
              <tbody>
                <?php if ( empty( $products ) && ! $error ) : ?>
                  <tr>
                    <td colspan="9">
                      <div class="pc-empty-state">
                        <h3>No products found</h3>
                        <p>Try a different search term or clear the filter.</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>

                <?php foreach ( $products as $product ) :
                  $id       = intval( $product['id'] );
                  $stock    = intval( $product['stock'] );
                  $images   = array_slice( (array) ( $product['images'] ?? array() ), 0, 3 );
                  $gallery_id = 'pc-gallery-' . $id;

                  if ( $stock <= 10 )      $stock_class = 'pc-stock-low';
                  elseif ( $stock <= 30 )  $stock_class = 'pc-stock-mid';
                  else                     $stock_class = 'pc-stock-ok';
                ?>

                <!-- Product row -->
                <tr class="pc-product-row">
                  <td>
                    <img
                      class="pc-thumbnail"
                      src="<?php echo esc_url( $product['thumbnail'] ?? '' ); ?>"
                      alt="<?php echo esc_attr( $product['title'] ?? '' ); ?> thumbnail"
                      loading="lazy"
                    />
                  </td>
                  <td class="pc-td-title"><?php echo esc_html( $product['title']    ?? '' ); ?></td>
                  <td>
                    <span class="pc-td-desc" title="<?php echo esc_attr( $product['description'] ?? '' ); ?>">
                      <?php echo esc_html( $product['description'] ?? '' ); ?>
                    </span>
                  </td>
                  <td class="pc-td-price">$<?php echo esc_html( $product['price']  ?? '' ); ?></td>
                  <td class="pc-td-rating">
                    <span class="pc-stars" aria-hidden="true">&#9733;</span>
                    <?php echo esc_html( $product['rating'] ?? '' ); ?>
                  </td>
                  <td>
                    <span class="<?php echo esc_attr( $stock_class ); ?>">
                      <?php echo esc_html( $stock ); ?>
                    </span>
                  </td>
                  <td><?php echo esc_html( $product['brand']    ?? '' ); ?></td>
                  <td><span class="pc-badge"><?php echo esc_html( $product['category'] ?? '' ); ?></span></td>
                  <td>
                    <button
                      class="pc-btn pc-btn-gallery"
                      data-gallery-id="<?php echo esc_attr( $gallery_id ); ?>"
                      aria-expanded="false"
                      aria-controls="<?php echo esc_attr( $gallery_id ); ?>"
                    >
                      Gallery
                    </button>
                  </td>
                </tr>

                <!-- Gallery row: pre-rendered, hidden by default. JS only toggles visibility. -->
                <tr id="<?php echo esc_attr( $gallery_id ); ?>" class="pc-gallery-row" hidden>
                  <td colspan="9">
                    <div class="pc-gallery-strip">
                      <?php if ( empty( $images ) ) : ?>
                        <span class="pc-gallery-empty">No images available for this product.</span>
                      <?php else : ?>
                        <?php foreach ( $images as $i => $src ) : ?>
                          <img
                            src="<?php echo esc_url( $src ); ?>"
                            alt="<?php echo esc_attr( $product['title'] ?? '' ); ?> image <?php echo $i + 1; ?>"
                            loading="lazy"
                          />
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>

                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination — plain anchor links, page computed by PHP -->
          <?php if ( $total_pages > 1 ) : ?>
          <nav class="pc-pagination" aria-label="Page navigation">

            <?php if ( $has_prev ) : ?>
              <a href="<?php echo self::pagination_url( $page - 1, $limit, $search ); ?>">&laquo; Prev</a>
            <?php else : ?>
              <span class="pc-disabled">&laquo; Prev</span>
            <?php endif; ?>

            <?php
            $win_start = max( 1, $page - 2 );
            $win_end   = min( $total_pages, $page + 2 );
            for ( $p = $win_start; $p <= $win_end; $p++ ) :
            ?>
              <?php if ( $p === $page ) : ?>
                <span class="pc-current" aria-current="page"><?php echo esc_html( $p ); ?></span>
              <?php else : ?>
                <a href="<?php echo self::pagination_url( $p, $limit, $search ); ?>"><?php echo esc_html( $p ); ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ( $has_next ) : ?>
              <a href="<?php echo self::pagination_url( $page + 1, $limit, $search ); ?>">Next &raquo;</a>
            <?php else : ?>
              <span class="pc-disabled">Next &raquo;</span>
            <?php endif; ?>

          </nav>
          <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }
}
