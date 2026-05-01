<?php
/**
 * Runs once when the plugin is activated.
 * Creates the "Compare Assignment" page with the [products_catalog] shortcode
 * if a page with that title does not already exist.
 */
class Products_Catalog_Activator {

    public static function activate() {
        $page_title = 'Compare Assignment';

        // Avoid creating duplicates on re-activation.
        $existing = get_posts( array(
            'post_type'   => 'page',
            'title'       => $page_title,
            'post_status' => 'any',
            'numberposts' => 1,
        ) );

        if ( ! empty( $existing ) ) {
            return;
        }

        wp_insert_post( array(
            'post_title'   => $page_title,
            'post_content' => '[products_catalog]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ) );
    }
}
