<?php
defined( 'ABSPATH' ) || exit;

class SPF_Filter_Ajax {

    public static function init() {
        add_action( 'wp_ajax_spf_filter',        [ __CLASS__, 'handle' ] );
        add_action( 'wp_ajax_nopriv_spf_filter', [ __CLASS__, 'handle' ] );
    }

    public static function handle() {
        check_ajax_referer( 'spf_nonce', 'nonce' );

        $params   = $_POST;
        $page_url = sanitize_url( $params['page_url'] ?? '' );
        $paged    = absint( $params['paged'] ?? 1 );

        // ساخت query
        $args  = SPF_Filter_Query::build_args( $params );
        $query = SPF_Filter_Query::run( $args );

        // رندر محصولات
        ob_start();
        if ( $query->have_posts() ) {
            wc_set_loop_prop( 'total',        $query->found_posts );
            wc_set_loop_prop( 'total_pages',  $query->max_num_pages );
            wc_set_loop_prop( 'current_page', $paged );

            while ( $query->have_posts() ) {
                $query->the_post();
                do_action( 'woocommerce_shop_loop' );
                // از فیلتر استفاده میکنیم تا قالب بتونه HTML wrapper رو تغییر بده
                echo apply_filters( 'spf_product_wrapper_open', "<div class='col-lg-6 col-xl-4 shop_item'>" );
                wc_get_template_part( 'content', 'product' );
                echo apply_filters( 'spf_product_wrapper_close', "</div>" );
            }
            wp_reset_postdata();
        } else {
            echo '<div class="spf-no-products">' . esc_html__( 'محصولی یافت نشد.', 'smart-product-filter' ) . '</div>';
        }
        $products_html = ob_get_clean();

        // رندر pagination
        ob_start();
        if ( $page_url ) {
            $base_url = trailingslashit( preg_replace( '/\/page\/\d+\/?/', '/', $page_url ) );
            add_filter( 'woocommerce_pagination_args', function( $a ) use ( $base_url, $paged, $query ) {
                $a['base']    = $base_url . '%_%';
                $a['format']  = 'page/%#%/';
                $a['current'] = $paged;
                $a['total']   = $query->max_num_pages;
                return $a;
            });
        }

        // set global wp_query برای pagination
        global $wp_query;
        $original_query = $wp_query;
        $wp_query = $query;

        woocommerce_pagination();

        $wp_query = $original_query;

        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'products'   => $products_html,
            'pagination' => $pagination_html,
            'found'      => $query->found_posts,
            'max_pages'  => $query->max_num_pages,
        ]);
    }
}
