<?php
defined( 'ABSPATH' ) || exit;

class SPF_Filter_Query {

    public static function init() {
        add_filter( 'posts_clauses', [ __CLASS__, 'apply_product_sort_clauses' ], 20, 2 );
        add_action( 'woocommerce_product_query', [ __CLASS__, 'prepare_archive_product_query' ], 30 );
    }

    /**
     * ساخت آرگومان‌های WP_Query بر اساس فیلترهای دریافتی
     */
    public static function build_args( $params ) {
        $options  = SPF_Admin_Settings::get_options();
        $per_page = $options['per_page'] ?: 12;

        $orderby   = sanitize_text_field( $params['orderby']   ?? 'date' );
        $paged     = absint( $params['paged'] ?? 1 );
        $min_price = isset( $params['min_price'] ) ? floatval( $params['min_price'] ) : null;
        $max_price = isset( $params['max_price'] ) ? floatval( $params['max_price'] ) : null;

        // آرگومان پایه
        $args = [
            'post_type'               => 'product',
            'post_status'             => 'publish',
            'posts_per_page'          => $per_page,
            'paged'                   => $paged,
            'spf_availability_sort'   => true,
        ];

        // مرتب‌سازی
        $args = array_merge( $args, self::get_orderby_args( $orderby ) );

        // فیلتر قیمت
        if ( $min_price !== null || $max_price !== null ) {
            $args['meta_query'][] = self::get_price_meta_query( $min_price, $max_price );
        }

        // فیلتر «فقط آیتم‌های موجود»
        if ( ! empty( $params['instock'] ) ) {
            $args['meta_query'][] = [
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            ];
        }

        // فیلتر تاکسونومی‌ها و attribute ها
        $tax_query = self::get_tax_query( $params );

        // فیلتر «فقط آیتم‌های ویژه» (محصولات featured ووکامرس)
        if ( ! empty( $params['featured'] ) ) {
            $tax_query[] = [
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => 'featured',
            ];
        }

        // محدود کردن به آرشیو تاکسونومی فعلی (مثلاً صفحه یک برند یا دسته خاص)
        // تا مرتب‌سازی و فیلتر فقط روی همین صفحه اعمال بشه، نه کل فروشگاه
        // از term_id استفاده می‌کنیم نه اسلاگ، چون اسلاگ‌های فارسی توی رفت‌وبرگشت AJAX
        // مستعد خراب شدن encoding هستن و ممکنه با چیزی که توی دیتابیس ذخیره شده match نکنن.
        $current_tax     = isset( $params['current_tax'] )     ? sanitize_text_field( $params['current_tax'] ) : '';
        $current_term_id = isset( $params['current_term_id'] ) ? absint( $params['current_term_id'] )          : 0;
        if ( $current_tax && $current_term_id ) {
            $tax_query[] = [
                'taxonomy' => $current_tax,
                'field'    => 'term_id',
                'terms'    => $current_term_id,
            ];
        }

        if ( ! empty( $tax_query ) ) {
            $tax_query['relation'] = 'AND';
            $args['tax_query']     = $tax_query;
        }

        // فیلتر «فقط آیتم‌های تخفیف‌دار»
        if ( ! empty( $params['onsale'] ) ) {
            $sale_ids = wc_get_product_ids_on_sale();
            $args['post__in'] = ! empty( $sale_ids ) ? $sale_ids : [ 0 ];
        }

        return $args;
    }

    /**
     * هم‌تراز کردن query اولیه آرشیو با منطق AJAX (مرتب‌سازی + ترتیب موجود/قیمت)
     */
    public static function prepare_archive_product_query( $query ) {
        if ( is_admin() || ! $query instanceof WP_Query ) {
            return;
        }

        if ( ! self::is_product_archive_context() ) {
            return;
        }

        $query->set( 'spf_availability_sort', true );

        $options  = SPF_Admin_Settings::get_options();
        $per_page = ! empty( $options['per_page'] ) ? absint( $options['per_page'] ) : 12;
        $query->set( 'posts_per_page', $per_page );

        $orderby = 'date';
        if ( ! empty( $_GET['orderby'] ) ) {
            $orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
        }

        self::apply_orderby_to_query( $query, $orderby );
    }

    private static function apply_orderby_to_query( $query, $orderby ) {
        $args = self::get_orderby_args( $orderby );
        foreach ( $args as $key => $value ) {
            $query->set( $key, $value );
        }
    }

    private static function is_product_archive_context() {
        return is_shop() || is_product_category() || is_product_tag() || is_tax();
    }

    /**
     * آرگومان‌های مرتب‌سازی
     */
    private static function get_orderby_args( $orderby ) {
        $map = [
            'date'       => [ 'orderby' => 'date',           'order' => 'DESC' ],
            'price'      => [ 'orderby' => 'meta_value_num', 'order' => 'ASC',  'meta_key' => '_price' ],
            'price-desc' => [ 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_price' ],
            'popularity' => [ 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => 'total_sales' ],
            'rating'     => [ 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_wc_average_rating' ],
            'title'      => [ 'orderby' => 'title',          'order' => 'ASC' ],
        ];

        return $map[ $orderby ] ?? $map['date'];
    }

    /**
     * meta_query فیلتر قیمت
     */
    private static function get_price_meta_query( $min, $max ) {
        $query = [
            'key'     => '_price',
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN',
            'value'   => [
                $min ?? 0,
                $max ?? PHP_INT_MAX,
            ],
        ];
        return $query;
    }

    /**
     * tax_query برای تاکسونومی‌ها و attribute ها
     */
private static function get_tax_query( $params ) {
    $options     = SPF_Admin_Settings::get_options();
    $tax_query   = [];
    $all_filters = array_merge(
        $options['taxonomies'] ?? [],
        $options['attributes'] ?? []
    );

    foreach ( $all_filters as $tax_slug ) {
        $key    = 'filter_' . $tax_slug;
        $values = isset( $params[ $key ] ) ? (array) $params[ $key ] : [];
        $values = array_map( 'urldecode', array_map( 'sanitize_text_field', $values ) );
        $values = array_filter( $values );

        if ( empty( $values ) ) continue;

        $tax_query[] = [
            'taxonomy' => $tax_slug,
            'field'    => 'slug',
            'terms'    => $values,
            'operator' => 'IN',
        ];
    }

    return $tax_query;
}

    /**
     * اجرای query فیلتر AJAX
     */
    public static function run( $args ) {
        if ( ! isset( $args['spf_availability_sort'] ) ) {
            $args['spf_availability_sort'] = true;
        }
        return new WP_Query( $args );
    }

    /**
     * آیا ترتیب موجود/قیمت‌دار/ناموجود روی این query اعمال شود؟
     */
    private static function should_apply_product_sort( $query ) {
        if ( ! $query instanceof WP_Query ) {
            return false;
        }

        if ( $query->get( 'spf_availability_sort' ) ) {
            return true;
        }

        if ( ! $query->is_main_query() ) {
            return false;
        }

        $post_type = $query->get( 'post_type' );
        $is_product_query = ( 'product' === $post_type )
            || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
        if ( ! $is_product_query ) {
            return false;
        }

        return self::is_product_archive_context();
    }

    /**
     * ترتیب ثابت: موجود با قیمت → موجود بدون قیمت (تماس بگیرید) → ناموجود
     */
    public static function apply_product_sort_clauses( $clauses, $query ) {
        if ( ! self::should_apply_product_sort( $query ) ) {
            return $clauses;
        }

        global $wpdb;

        if ( strpos( $clauses['join'], 'spf_stock' ) === false ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS spf_stock ON ({$wpdb->posts}.ID = spf_stock.post_id AND spf_stock.meta_key = '_stock_status')";
        }
        if ( strpos( $clauses['join'], 'spf_price' ) === false ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS spf_price ON ({$wpdb->posts}.ID = spf_price.post_id AND spf_price.meta_key = '_price')";
        }

        $priority = "CASE
            WHEN COALESCE(spf_stock.meta_value, 'outofstock') <> 'instock' THEN 2
            WHEN spf_price.meta_value IS NOT NULL AND spf_price.meta_value <> '' AND CAST(spf_price.meta_value AS DECIMAL(10,4)) > 0 THEN 0
            ELSE 1
        END ASC";

        $clauses['orderby'] = $priority . ', ' . $clauses['orderby'];

        return $clauses;
    }

    /**
     * گرفتن min و max قیمت از دیتابیس
     */
    public static function get_price_range() {
        global $wpdb;
        $min = (float) $wpdb->get_var( "SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value != ''" );
        $max = (float) $wpdb->get_var( "SELECT MAX(CAST(meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} WHERE meta_key = '_price' AND meta_value != ''" );
        return [
            'min' => floor( $min ),
            'max' => ceil( $max ),
        ];
    }

    /**
     * ترم‌های یک تاکسونومی رو فقط محدود به محصولاتی که داخل یک آرشیو دیگه (مثلاً یک برند خاص) هستن برمی‌گردونه
     * و شمارش هر ترم (count) رو هم بر همون اساس (نه کل فروشگاه) محاسبه می‌کنه.
     * ترم‌هایی که هیچ محصولی توی این آرشیو ندارن اصلاً برگردونده نمی‌شن.
     */
    public static function get_scoped_terms( $tax_slug, $context_tax, $context_term_slug, $allowed_terms = [] ) {
        $terms_args = [
            'taxonomy'   => $tax_slug,
            'hide_empty' => false,
        ];
        if ( ! empty( $allowed_terms ) ) {
            $terms_args['slug'] = $allowed_terms;
        }
        $terms = get_terms( $terms_args );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return [];

        $context_term = get_term_by( 'slug', $context_term_slug, $context_tax );
        if ( ! $context_term ) return [];

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT tt2.term_id, COUNT(DISTINCT p.ID) as cnt
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr1 ON tr1.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt1 ON tt1.term_taxonomy_id = tr1.term_taxonomy_id AND tt1.taxonomy = %s AND tt1.term_id = %d
             INNER JOIN {$wpdb->term_relationships} tr2 ON tr2.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id AND tt2.taxonomy = %s
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
             GROUP BY tt2.term_id",
            $context_tax, $context_term->term_id, $tax_slug
        ), OBJECT_K );

        $result = [];
        foreach ( $terms as $term ) {
            if ( isset( $rows[ $term->term_id ] ) ) {
                $term->count = (int) $rows[ $term->term_id ]->cnt;
                $result[]    = $term;
            }
        }
        return $result;
    }

    /**
     * مرتب‌سازی طبیعی (عددی-آگاه) لیست ترم‌ها.
     * مثلا "۹ آمپر"، "۷۰ آمپر"، "۹۰ آمپر" رو به‌جای مرتب‌سازی حرفی (که ۹۰ رو قبل ۷۰ میاره)
     * به ترتیب عددی درست مرتب می‌کنه. اگه عددی توی اسم ترم نباشه، مرتب‌سازی حرفی معمولی انجام می‌شه.
     */
    public static function sort_terms_naturally( $terms ) {
        usort( $terms, function( $a, $b ) {
            $num_a = self::extract_leading_number( $a->name );
            $num_b = self::extract_leading_number( $b->name );
            if ( $num_a !== null && $num_b !== null && $num_a !== $num_b ) {
                return $num_a <=> $num_b;
            }
            return strcmp( $a->name, $b->name );
        });
        return $terms;
    }

    /**
     * get_terms() یه لیست مسطح و صرفاً الفبایی برمی‌گردونه، نه یه درخت مرتب‌شده بر اساس والد/فرزند.
     * این متد از روی همون لیست مسطح، ترتیب واقعی درختی می‌سازه: هر والد بلافاصله قبل از فرزندهای خودش
     * قرار می‌گیره (به‌صورت بازگشتی، برای هر تعداد سطح که باشه)، و داخل هر سطح هم مرتب‌سازی طبیعی اعمال می‌شه.
     * روی هر ترم یک property به اسم spf_depth ست می‌شه که عمق واقعیش رو نشون می‌ده (برای تورفتگی توی HTML).
     * اگه والد یک ترم توی همین لیست نباشه (مثلاً به‌خاطر hide_empty حذف شده)، خود اون ترم به‌عنوان ریشه نمایش داده می‌شه
     * تا از دست نره.
     */
    public static function build_term_tree( $terms, $parent_id = 0, $depth = 0 ) {
        $ids = array_map( function( $t ) { return (int) $t->term_id; }, $terms );

        $children = array_values( array_filter( $terms, function( $t ) use ( $parent_id, $ids ) {
            $parent = (int) $t->parent;
            if ( $parent === (int) $parent_id ) return true;
            if ( (int) $parent_id === 0 && ! in_array( $parent, $ids, true ) ) return true; // والد یتیم -> نمایش در ریشه
            return false;
        }));

        $children = self::sort_terms_naturally( $children );

        $result = [];
        foreach ( $children as $term ) {
            $term->spf_depth = $depth;
            $result[]        = $term;
            $result           = array_merge( $result, self::build_term_tree( $terms, $term->term_id, $depth + 1 ) );
        }
        return $result;
    }

    /**
     * اولین عدد داخل یک رشته رو برمی‌گردونه (ارقام فارسی/عربی هم پشتیبانی می‌شه)
     */
    private static function extract_leading_number( $str ) {
        static $digit_map = [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ];
        $normalized = strtr( $str, $digit_map );
        if ( preg_match( '/\d+(\.\d+)?/', $normalized, $m ) ) {
            return (float) $m[0];
        }
        return null;
    }
}
