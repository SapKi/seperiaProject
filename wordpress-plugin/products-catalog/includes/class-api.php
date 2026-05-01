<?php
/**
 * WordPress AJAX handler — proxies requests to the DummyJSON API.
 *
 * Endpoint (public, no login required):
 *   /wp-admin/admin-ajax.php
 *     ?action=products_catalog_fetch
 *     &page=1
 *     &limit=10
 *     &search=phone          (optional)
 *
 * Response (success):
 *   { "success": true, "data": { products, total, skip, limit, page, total_pages } }
 *
 * Response (error):
 *   { "success": false, "data": { "message": "..." } }
 */
class Products_Catalog_API {

    const DUMMYJSON_BASE = 'https://dummyjson.com';
    const DEFAULT_LIMIT  = 10;
    const MAX_LIMIT      = 100;
    const TIMEOUT        = 10; // seconds

    public static function handle() {
        $page   = max( 1, intval( $_GET['page']   ?? 1 ) );
        $limit  = min( self::MAX_LIMIT, max( 1, intval( $_GET['limit'] ?? self::DEFAULT_LIMIT ) ) );
        $search = sanitize_text_field( $_GET['search'] ?? '' );
        $skip   = ( $page - 1 ) * $limit;

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
            wp_send_json_error(
                array( 'message' => 'Could not reach the products service. Please try again.' ),
                503
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            wp_send_json_error(
                array( 'message' => "Products service returned an error ({$status_code})." ),
                503
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || ! isset( $body['products'] ) ) {
            wp_send_json_error( array( 'message' => 'Unexpected response from products service.' ), 502 );
        }

        $total_pages = max( 1, (int) ceil( $body['total'] / $limit ) );

        wp_send_json_success( array(
            'products'    => $body['products'],
            'total'       => $body['total'],
            'skip'        => $body['skip'],
            'limit'       => $body['limit'],
            'page'        => $page,
            'total_pages' => $total_pages,
        ) );
    }
}
