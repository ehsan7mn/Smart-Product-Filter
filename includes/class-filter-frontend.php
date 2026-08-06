<?php
defined( 'ABSPATH' ) || exit;

class SPF_Filter_Frontend {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_footer',          [ __CLASS__, 'render_modal' ] );
        add_action( 'woocommerce_product_query', [ __CLASS__, 'limit_products_by_admin_terms' ] );
    }

public static function limit_products_by_admin_terms( $q ) {
    if ( ! $q->is_main_query() ) return;
    if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_tax() ) ) return;

    $options      = SPF_Admin_Settings::get_options();
    $allowed_cats = array_map( 'urldecode', $options['taxonomy_terms']['product_cat'] ?? [] );

    if ( empty( $allowed_cats ) ) return;

    $q->set( 'tax_query', [
        [
            'taxonomy'         => 'product_cat',
            'field'            => 'slug',
            'terms'            => $allowed_cats,
            'operator'         => 'IN',
            'include_children' => false,
        ]
    ]);
}

    private static function is_filter_page() {
        return is_shop() || is_product_category() || is_product_tag() || is_tax();
    }

    /**
     * تشخیص اینکه الان توی آرشیو کدوم تاکسونومی/ترم هستیم
     * (مثلاً صفحه‌ی یک برند خاص یا یک دسته خاص)
     * خروجی: [ 'taxonomy' => 'product_brand', 'term_slug' => 'orbital-power-plus' ] یا آرایه خالی
     */
    public static function get_current_archive_context() {
        if ( is_product_category() || is_product_tag() || is_tax() ) {
            $queried = get_queried_object();
            if ( $queried instanceof WP_Term ) {
                return [
                    'taxonomy'  => $queried->taxonomy,
                    'term_slug' => $queried->slug,
                    'term_id'   => $queried->term_id,
                ];
            }
        }
        return [ 'taxonomy' => '', 'term_slug' => '', 'term_id' => 0 ];
    }

    public static function enqueue_assets() {
        if ( ! self::is_filter_page() ) return;

        $options     = SPF_Admin_Settings::get_options();
        $price_range = SPF_Filter_Query::get_price_range();
        $context     = self::get_current_archive_context();

        // jQuery UI Slider برای فیلتر قیمت
        wp_enqueue_script( 'jquery-ui-slider' );
        wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2' );

        wp_enqueue_style( 'spf-style', SPF_ASSETS . 'css/filter.css', [], SPF_VERSION );
        wp_enqueue_script( 'spf-filter', SPF_ASSETS . 'js/filter.js', [ 'jquery', 'jquery-ui-slider' ], SPF_VERSION, true );

        // پاس دادن داده به JS
        wp_localize_script( 'spf-filter', 'spfData', [
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'spf_nonce' ),
            'min_price'    => $price_range['min'],
            'max_price'    => $price_range['max'],
            'per_page'     => $options['per_page'],
            'page_url'     => self::get_base_url(),
            'taxonomies'   => $options['taxonomies'],
            'attributes'   => $options['attributes'],
            'current_tax'     => $context['taxonomy'],
            'current_term'    => $context['term_slug'],
            'current_term_id' => $context['term_id'],
        ]);
    }

    /**
     * گرفتن URL پایه صفحه بدون pagination
     */
    private static function get_base_url() {
        $url = get_pagenum_link(1);
        return trailingslashit( preg_replace( '/\/page\/\d+\/?/', '/', $url ) );
    }

    /**
     * رندر سایدبار فیلتر
     */
    public static function render_sidebar() {
        if ( ! self::is_filter_page() ) return;
        include SPF_PATH . 'templates/filter-sidebar.php';
    }

    /**
     * رندر دکمه‌های sort
     */
    public static function render_sort_bar() {
        if ( ! self::is_filter_page() ) return;
        include SPF_PATH . 'templates/sort-bar.php';
    }

    /**
     * رندر مودال موبایل (توی footer)
     */
    public static function render_modal() {
        if ( ! self::is_filter_page() ) return;
        ?>
        <div class="modal fade filter_modal" id="spf-filter-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">فیلترها</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="spf-modal-body">
                        <!-- محتوای سایدبار با JS کپی میشه -->
                    </div>
                    <div class="modal-footer">
                        <button class="spf-btn-apply-modal">اعمال فیلتر</button>
                        <button class="spf-btn-reset-modal">حذف فیلترها</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
