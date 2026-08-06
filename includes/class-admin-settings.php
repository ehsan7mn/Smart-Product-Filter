<?php
defined( 'ABSPATH' ) || exit;

class SPF_Admin_Settings {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            'تنظیمات فیلتر محصولات',
            'فیلتر محصولات',
            'manage_options',
            'smart-product-filter',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings() {
        register_setting( 'spf_settings', 'spf_options', [
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
        ]);
    }

    public static function sanitize( $input ) {
        $clean = [];

        // تاکسونومی‌های فعال
        $clean['taxonomies'] = isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] )
            ? array_map( 'sanitize_text_field', $input['taxonomies'] )
            : [];

        // attribute های فعال
        $clean['attributes'] = isset( $input['attributes'] ) && is_array( $input['attributes'] )
            ? array_map( 'sanitize_text_field', $input['attributes'] )
            : [];

        // نمایش فیلتر قیمت
        $clean['show_price_filter'] = ! empty( $input['show_price_filter'] ) ? 1 : 0;

        // تعداد محصولات در هر صفحه
        $clean['per_page'] = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 12;

        // نمایش تعداد محصول کنار هر فیلتر
        $clean['show_count'] = ! empty( $input['show_count'] ) ? 1 : 0;

        // نمایش فیلتر «فقط آیتم‌های موجود»
        $clean['show_stock_filter'] = ! empty( $input['show_stock_filter'] ) ? 1 : 0;

        // نمایش فیلتر «فقط آیتم‌های تخفیف‌دار»
        $clean['show_sale_filter'] = ! empty( $input['show_sale_filter'] ) ? 1 : 0;

        // نمایش فیلتر «فقط آیتم‌های ویژه»
        $clean['show_featured_filter'] = ! empty( $input['show_featured_filter'] ) ? 1 : 0;

        // تاکسونومی‌هایی که نباید توی صفحه آرشیو خودشون نمایش داده بشن
        $clean['hide_on_own_archive'] = isset( $input['hide_on_own_archive'] ) && is_array( $input['hide_on_own_archive'] )
            ? array_map( 'sanitize_text_field', $input['hide_on_own_archive'] )
            : [];

        // ترم‌های انتخابی هر تاکسونومی
        $clean['taxonomy_terms'] = [];
        if ( isset( $input['taxonomy_terms'] ) && is_array( $input['taxonomy_terms'] ) ) {
            foreach ( $input['taxonomy_terms'] as $tax => $terms ) {
                $clean['taxonomy_terms'][ sanitize_text_field($tax) ] = array_map( function($term) {
                    return sanitize_text_field( urldecode( $term ) );
                }, (array) $terms );
            }
        }
        // ترم‌های انتخابی هر attribute
        $clean['attribute_terms'] = [];
        if ( isset( $input['attribute_terms'] ) && is_array( $input['attribute_terms'] ) ) {
            foreach ( $input['attribute_terms'] as $tax => $terms ) {
                $clean['attribute_terms'][ sanitize_text_field($tax) ] = array_map( function($term) {
                    return sanitize_text_field( urldecode( $term ) );
                }, (array) $terms );
            }
        }
        return $clean;
    }

    /**
     * رندر بازگشتی ترم‌های یک تاکسونومی (چک‌باکس‌ها) به همراه فرزندانشان
     */
    private static function render_term_recursive( $all_terms, $parent_id, $tax_slug, $saved_terms, $depth = 0 ) {
        $children = wp_list_filter( $all_terms, [ 'parent' => $parent_id ] );
        foreach ( $children as $term ) :
            $checked = empty( $saved_terms ) || in_array( $term->slug, $saved_terms ) || in_array( urldecode($term->slug), $saved_terms ) ? 'checked' : '';
            $prefix  = str_repeat( '— ', $depth );
            $has_children = ! empty( wp_list_filter( $all_terms, [ 'parent' => $term->term_id ] ) );
        ?>
        <label class="spf-checkbox-item spf-term-item spf-term-depth-<?php echo $depth; ?>">
            <input type="checkbox"
                class="spf-term-checkbox"
                name="spf_options[taxonomy_terms][<?php echo esc_attr( $tax_slug ); ?>][]"
                value="<?php echo esc_attr( urldecode( $term->slug ) ); ?>"
                <?php echo $checked; ?>
            >
            <span><?php echo $prefix . esc_html( $term->name ); ?></span>
            <small>(<?php echo $term->count; ?>)</small>
        </label>
        <?php if ( $has_children ) : ?>
            <?php self::render_term_recursive( $all_terms, $term->term_id, $tax_slug, $saved_terms, $depth + 1 ); ?>
        <?php endif; ?>
        <?php endforeach;
    }

    public static function get_options() {
        $defaults = [
            'taxonomies'        => [],
            'attributes'        => [],
            'show_price_filter'    => 1,
            'per_page'             => 12,
            'show_count'           => 1,
            'show_stock_filter'    => 0,
            'show_sale_filter'     => 0,
            'show_featured_filter' => 0,
            'hide_on_own_archive'  => [],
        ];
        return wp_parse_args( get_option( 'spf_options', [] ), $defaults );
    }

    public static function enqueue_assets( $hook ) {
        if ( $hook !== 'woocommerce_page_smart-product-filter' ) return;
        wp_enqueue_style( 'spf-admin', SPF_ASSETS . 'admin/admin.css', [], SPF_VERSION );
    }

    public static function render_page() {
        $options = self::get_options();

        // گرفتن همه تاکسونومی‌های محصول
        $all_taxonomies = get_object_taxonomies( 'product', 'objects' );
        $all_attributes = wc_get_attribute_taxonomies();
        $attribute_slugs = array_map( function($attr) {
            return 'pa_' . $attr->attribute_name;
        }, $all_attributes );
        
        $excluded_tax = array_merge(
            [ 'product_type', 'product_visibility', 'product_shipping_class' ],
            $attribute_slugs
        );

        // گرفتن همه attribute های ووکامرس
        $all_attributes = wc_get_attribute_taxonomies();

        ?>
        <div class="wrap spf-admin-wrap">
            <h1>⚙️ تنظیمات فیلتر محصولات</h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'spf_settings' ); ?>

                <div class="spf-admin-grid">

                    <!-- تاکسونومی‌ها -->
                    <div class="spf-admin-card">
    <h2>📂 تاکسونومی‌های فیلتر</h2>
    <p class="description">انتخاب کنید کدام تاکسونومی‌ها و ترم‌ها در سایدبار فیلتر نمایش داده شوند.</p>
    <div class="spf-checkbox-list spf-tax-grid">
        <?php foreach ( $all_taxonomies as $tax_slug => $tax_obj ) :
            if ( in_array( $tax_slug, $excluded_tax ) ) continue;

            $terms = get_terms([
                'taxonomy'   => $tax_slug,
                'hide_empty' => false,
            ]);

            $tax_checked = in_array( $tax_slug, $options['taxonomies'] ) ? 'checked' : '';
            $saved_terms = $options['taxonomy_terms'][ $tax_slug ] ?? [];
        ?>
        <div class="spf-tax-group">

            <!-- چک‌باکس اصلی تاکسونومی -->
            <label class="spf-checkbox-item spf-tax-parent">
                <input type="checkbox"
                    class="spf-tax-toggle"
                    name="spf_options[taxonomies][]"
                    value="<?php echo esc_attr( $tax_slug ); ?>"
                    <?php echo $tax_checked; ?>
                >
                <span class="spf-tax-name"><?php echo esc_html( $tax_obj->label ); ?></span>
                <span class="spf-tax-slug"><?php echo esc_html( $tax_slug ); ?></span>
                <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                <span class="spf-toggle-arrow <?php echo $tax_checked ? 'open' : ''; ?>">▼</span>
                <?php endif; ?>
            </label>

            <div class="spf-tax-suboption">
                <label>
                    <input type="checkbox"
                        name="spf_options[hide_on_own_archive][]"
                        value="<?php echo esc_attr( $tax_slug ); ?>"
                        <?php checked( in_array( $tax_slug, $options['hide_on_own_archive'] ) ); ?>
                    >
                    <span>عدم نمایش این فیلتر در صفحه آرشیو خودش</span>
                </label>
            </div>

            <!-- ترم‌های این تاکسونومی -->
            <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
            <div class="spf-term-list <?php echo $tax_checked ? 'open' : ''; ?>">
                <label class="spf-checkbox-item spf-term-all">
                    <input type="checkbox"
                        class="spf-select-all-terms"
                        data-tax="<?php echo esc_attr( $tax_slug ); ?>"
                        <?php echo empty( $saved_terms ) ? 'checked' : ''; ?>
                    >
                    <span>همه دسته‌ها</span>
                </label>
                <?php self::render_term_recursive( $terms, 0, $tax_slug, $saved_terms ); ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- attribute ها -->
<div class="spf-admin-card">
    <h2>🎨 ویژگی‌های محصول</h2>
    <p class="description">انتخاب کنید کدام ویژگی‌ها و مقادیرشان در سایدبار فیلتر نمایش داده شوند.</p>
    <?php if ( empty( $all_attributes ) ) : ?>
        <p class="spf-empty">هیچ ویژگی‌ای تعریف نشده است. از <a href="<?php echo admin_url('edit.php?post_type=product&page=product_attributes'); ?>">اینجا</a> ویژگی اضافه کنید.</p>
    <?php else : ?>
    <div class="spf-tax-grid">
        <?php foreach ( $all_attributes as $attr ) :
            $tax_slug     = 'pa_' . $attr->attribute_name;
            $tax_checked  = in_array( $tax_slug, $options['attributes'] ) ? 'checked' : '';
            $saved_terms  = $options['attribute_terms'][ $tax_slug ] ?? [];

            $terms = get_terms([
                'taxonomy'   => $tax_slug,
                'hide_empty' => false,
            ]);
            if ( is_wp_error( $terms ) ) continue;
        ?>
        <div class="spf-tax-group">

            <label class="spf-checkbox-item spf-tax-parent">
                <input type="checkbox"
                    class="spf-tax-toggle"
                    name="spf_options[attributes][]"
                    value="<?php echo esc_attr( $tax_slug ); ?>"
                    <?php echo $tax_checked; ?>
                >
                <span class="spf-tax-name"><?php echo esc_html( $attr->attribute_label ); ?></span>
                <?php if ( ! empty( $terms ) ) : ?>
                <span class="spf-toggle-arrow <?php echo $tax_checked ? 'open' : ''; ?>">▼</span>
                <?php endif; ?>
            </label>

            <div class="spf-tax-suboption">
                <label>
                    <input type="checkbox"
                        name="spf_options[hide_on_own_archive][]"
                        value="<?php echo esc_attr( $tax_slug ); ?>"
                        <?php checked( in_array( $tax_slug, $options['hide_on_own_archive'] ) ); ?>
                    >
                    <span>عدم نمایش این فیلتر در صفحه آرشیو خودش</span>
                </label>
            </div>

            <?php if ( ! empty( $terms ) ) : ?>
            <div class="spf-term-list <?php echo $tax_checked ? 'open' : ''; ?>">
                <label class="spf-checkbox-item spf-term-all">
                    <input type="checkbox"
                        class="spf-select-all-terms"
                        data-tax="<?php echo esc_attr( $tax_slug ); ?>"
                        <?php echo empty( $saved_terms ) ? 'checked' : ''; ?>
                    >
                    <span>همه موارد</span>
                </label>
                <?php foreach ( $terms as $term ) :
                    $term_checked = empty( $saved_terms ) || in_array( $term->slug, $saved_terms ) || in_array( urldecode($term->slug), $saved_terms ) ? 'checked' : '';
                ?>
                <label class="spf-checkbox-item spf-term-item">
                    <input type="checkbox"
                        class="spf-term-checkbox"
                        name="spf_options[attribute_terms][<?php echo esc_attr( $tax_slug ); ?>][]"
                        value="<?php echo esc_attr( urldecode( $term->slug ) ); ?>"
                        <?php echo $term_checked; ?>
                    >
                    <span><?php echo esc_html( $term->name ); ?></span>
                    <small>(<?php echo intval( $term->count ); ?>)</small>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

                    <!-- تنظیمات عمومی -->
                    <div class="spf-admin-card">
                        <h2>⚙️ تنظیمات عمومی</h2>

                        <div class="spf-field">
                            <label>تعداد محصولات در هر صفحه</label>
                            <input type="number"
                                name="spf_options[per_page]"
                                value="<?php echo esc_attr( $options['per_page'] ); ?>"
                                min="1" max="100"
                            >
                        </div>

                        <div class="spf-field">
                            <label class="spf-checkbox-item">
                                <input type="checkbox"
                                    name="spf_options[show_price_filter]"
                                    value="1"
                                    <?php checked( $options['show_price_filter'], 1 ); ?>
                                >
                                <span>نمایش فیلتر قیمت</span>
                            </label>
                        </div>

                        <div class="spf-field">
                            <label class="spf-checkbox-item">
                                <input type="checkbox"
                                    name="spf_options[show_count]"
                                    value="1"
                                    <?php checked( $options['show_count'], 1 ); ?>
                                >
                                <span>نمایش تعداد محصول کنار هر فیلتر</span>
                            </label>
                        </div>

                        <div class="spf-field">
                            <label class="spf-checkbox-item">
                                <input type="checkbox"
                                    name="spf_options[show_stock_filter]"
                                    value="1"
                                    <?php checked( $options['show_stock_filter'], 1 ); ?>
                                >
                                <span>نمایش فیلتر «فقط آیتم‌های موجود»</span>
                            </label>
                        </div>

                        <div class="spf-field">
                            <label class="spf-checkbox-item">
                                <input type="checkbox"
                                    name="spf_options[show_sale_filter]"
                                    value="1"
                                    <?php checked( $options['show_sale_filter'], 1 ); ?>
                                >
                                <span>نمایش فیلتر «فقط آیتم‌های تخفیف‌دار»</span>
                            </label>
                        </div>

                        <div class="spf-field">
                            <label class="spf-checkbox-item">
                                <input type="checkbox"
                                    name="spf_options[show_featured_filter]"
                                    value="1"
                                    <?php checked( $options['show_featured_filter'], 1 ); ?>
                                >
                                <span>نمایش فیلتر «فقط آیتم‌های ویژه»</span>
                            </label>
                        </div>
                    </div>

                </div>

                <?php submit_button( 'ذخیره تنظیمات' ); ?>
            </form>
            <script>
            jQuery(function($){
                // باز/بسته کردن ترم‌ها
                $(document).on('change', '.spf-tax-toggle', function(){
                    const $group  = $(this).closest('.spf-tax-group');
                    const $terms  = $group.find('.spf-term-list');
                    const $arrow  = $group.find('.spf-toggle-arrow');
                    if($(this).is(':checked')){
                        $terms.slideDown(200).addClass('open');
                        $arrow.addClass('open');
                    } else {
                        $terms.slideUp(200).removeClass('open');
                        $arrow.removeClass('open');
                    }
                });

                // همه دسته‌ها
                $(document).on('change', '.spf-select-all-terms', function(){
                    const $group = $(this).closest('.spf-term-list');
                    if($(this).is(':checked')){
                        $group.find('.spf-term-checkbox').prop('checked', true);
                    }
                });

                // اگه یکی untick شد، همه رو untick کن
                $(document).on('change', '.spf-term-checkbox', function(){
                    const $group = $(this).closest('.spf-term-list');
                    if(!$(this).is(':checked')){
                        $group.find('.spf-select-all-terms').prop('checked', false);
                    }
                });
            });
            </script>
        </div>
        <?php
    }
}
