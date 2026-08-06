<?php
defined( 'ABSPATH' ) || exit;

$options     = SPF_Admin_Settings::get_options();
$price_range = SPF_Filter_Query::get_price_range();
$context     = SPF_Filter_Frontend::get_current_archive_context();

// خواندن فیلترهای فعال از URL
$url_filters = [];
foreach ( array_merge( $options['taxonomies'], $options['attributes'] ) as $tax_slug ) {
    $key = 'filter_' . $tax_slug;
    $url_filters[ $tax_slug ] = isset( $_GET[ $key ] ) ? explode( ',', sanitize_text_field( $_GET[ $key ] ) ) : [];
}
$active_min = isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : $price_range['min'];
$active_max = isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : $price_range['max'];

$active_instock  = ! empty( $_GET['instock'] );
$active_onsale   = ! empty( $_GET['onsale'] );
$active_featured = ! empty( $_GET['featured'] );

// مرتب‌سازی الفبایی تاکسونومی‌ها و attribute ها بر اساس عنوان، برای نمایش یکنواخت در سایدبار
$sorted_taxonomies = $options['taxonomies'];
usort( $sorted_taxonomies, function( $a, $b ) {
    $label_a = ( $tax = get_taxonomy( $a ) ) ? $tax->label : $a;
    $label_b = ( $tax = get_taxonomy( $b ) ) ? $tax->label : $b;
    return strcmp( $label_a, $label_b );
});

$sorted_attributes = $options['attributes'];
usort( $sorted_attributes, function( $a, $b ) {
    return strcmp( wc_attribute_label( $a ), wc_attribute_label( $b ) );
});
?>

<div id="spf-sidebar">

    <div class="spf-sidebar-header">
        <span>فیلترها</span>
        <button type="button" id="spf-reset-all" class="spf-btn-reset">حذف همه فیلترها</button>
    </div>

    <?php
    // ========== تاکسونومی‌ها ==========
    foreach ( $sorted_taxonomies as $tax_slug ) :
        $tax_obj = get_taxonomy( $tax_slug );
        if ( ! $tax_obj ) continue;

        // اگه ادمین گفته این تاکسونومی توی صفحه آرشیو خودش نمایش داده نشه، رد شو
        if ( $context['taxonomy'] === $tax_slug && in_array( $tax_slug, $options['hide_on_own_archive'] ?? [] ) ) continue;

        $allowed_terms = array_map( 'urldecode', $options['taxonomy_terms'][ $tax_slug ] ?? [] );

        // اگه توی آرشیو یک تاکسونومی دیگه هستیم (مثلاً صفحه یک برند خاص)،
        // فقط ترم‌هایی از این تاکسونومی رو نشون بده که توی همون آرشیو محصول دارن،
        // و تعداد داخل پرانتز هم مخصوص همون آرشیو باشه
        if ( $context['taxonomy'] && $context['term_slug'] && $context['taxonomy'] !== $tax_slug ) {
            $terms = SPF_Filter_Query::get_scoped_terms( $tax_slug, $context['taxonomy'], $context['term_slug'], $allowed_terms );
        } else {
            $terms_args = [
                'taxonomy'   => $tax_slug,
                'hide_empty' => true,
            ];
            if ( ! empty( $allowed_terms ) ) {
                $terms_args['slug'] = $allowed_terms;
            }
            $terms = get_terms( $terms_args );
        }
        if ( empty( $terms ) || is_wp_error( $terms ) ) continue;
        $terms = SPF_Filter_Query::build_term_tree( $terms );

        $active_terms = $url_filters[ $tax_slug ] ?? [];
    ?>
    <div class="spf-filter-section" data-tax="<?php echo esc_attr( $tax_slug ); ?>">
        <div class="spf-filter-title">
            <span><?php echo esc_html( apply_filters( 'spf_taxonomy_label', $tax_obj->label, $tax_slug ) ); ?></span>
            <svg class="spf-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="spf-filter-options">
            <?php if ( count($terms) > 2 ) : ?>
            <div class="spf-search-box">
                <input type="text" class="spf-term-search" placeholder="جستجو...">
            </div>
            <?php endif; ?>
            <div class="spf-terms-list">
            <?php foreach ( $terms as $term ) :
                $slug    = $term->slug;
                $checked = in_array( urldecode($slug), array_map('urldecode', $active_terms) ) ? 'checked' : '';
                $depth   = isset( $term->spf_depth ) ? $term->spf_depth : 0;
            ?>
            <label class="spf-option" style="padding-right: <?php echo $depth * 16; ?>px;">
                <input type="checkbox"
                    class="spf-checkbox"
                    data-tax="<?php echo esc_attr( $tax_slug ); ?>"
                    value="<?php echo esc_attr( $slug ); ?>"
                    <?php echo $checked; ?>
                >
                <span class="spf-option-name"><?php echo esc_html( $term->name ); ?></span>
                <?php if ( $options['show_count'] ) : ?>
                <span class="spf-option-count">(<?php echo intval( $term->count ); ?>)</span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php
    // ========== Attribute ها ==========
    foreach ( $sorted_attributes as $tax_slug ) :
        $tax_obj = get_taxonomy( $tax_slug );
        if ( ! $tax_obj ) continue;

        // اگه ادمین گفته این attribute توی صفحه آرشیو خودش نمایش داده نشه، رد شو
        if ( $context['taxonomy'] === $tax_slug && in_array( $tax_slug, $options['hide_on_own_archive'] ?? [] ) ) continue;

        $allowed_terms = array_map( 'urldecode', $options['attribute_terms'][ $tax_slug ] ?? [] );

        if ( $context['taxonomy'] && $context['term_slug'] && $context['taxonomy'] !== $tax_slug ) {
            $terms = SPF_Filter_Query::get_scoped_terms( $tax_slug, $context['taxonomy'], $context['term_slug'], $allowed_terms );
        } else {
            $terms_args = [
                'taxonomy'   => $tax_slug,
                'hide_empty' => true,
            ];
            if ( ! empty( $allowed_terms ) ) {
                $terms_args['slug'] = $allowed_terms;
            }
            $terms = get_terms( $terms_args );
        }
        if ( empty( $terms ) || is_wp_error( $terms ) ) continue;
        $terms = SPF_Filter_Query::build_term_tree( $terms );

        $active_terms = $url_filters[ $tax_slug ] ?? [];
        $attr_name    = str_replace( 'pa_', '', $tax_slug );
        $is_color     = in_array( strtolower($attr_name), ['color', 'colour', 'رنگ'] );
    ?>
    <div class="spf-filter-section" data-tax="<?php echo esc_attr( $tax_slug ); ?>">
        <div class="spf-filter-title">
            <span><?php echo esc_html( apply_filters( 'spf_taxonomy_label', wc_attribute_label( $tax_slug ), $tax_slug ) ); ?></span>
            <svg class="spf-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>

        <?php if ( $is_color ) : ?>
        <!-- رنگ‌ها بدون scroll و search -->
        <div class="spf-filter-options spf-color-options">
            <?php foreach ( $terms as $term ) :
                $slug       = $term->slug;
                $checked    = in_array( $slug, $active_terms ) ? 'checked' : '';
                $color_code = get_term_meta( $term->term_id, 'product_attribute_color', true ) ?: '#000000';
            ?>
            <label class="spf-option spf-color-option <?php echo $checked ? 'selected' : ''; ?>">
                <input type="checkbox"
                    class="spf-checkbox"
                    data-tax="<?php echo esc_attr( $tax_slug ); ?>"
                    value="<?php echo esc_attr( urldecode($slug) ); ?>"
                    <?php echo $checked; ?>
                >
                <span class="spf-color-swatch-wrap">
                    <span class="spf-color-swatch" style="background:<?php echo esc_attr( $color_code ); ?>"></span>
                    <?php if ( $checked ) : ?>
                    <svg class="spf-color-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php endif; ?>
                </span>
                <span class="spf-option-name"><?php echo esc_html( $term->name ); ?></span>
                <?php if ( $options['show_count'] ) : ?>
                <span class="spf-option-count">(<?php echo intval( $term->count ); ?>)</span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>

        <?php else : ?>
        <!-- سایر ویژگی‌ها با search و scroll -->
        <div class="spf-filter-options">
            <?php if ( count($terms) > 2 ) : ?>
            <div class="spf-search-box">
                <input type="text" class="spf-term-search" placeholder="جستجو...">
            </div>
            <?php endif; ?>
            <div class="spf-terms-list">
            <?php foreach ( $terms as $term ) :
                $slug    = $term->slug;
                $checked = in_array( $slug, $active_terms ) ? 'checked' : '';
            ?>
            <label class="spf-option">
                <input type="checkbox"
                    class="spf-checkbox"
                    data-tax="<?php echo esc_attr( $tax_slug ); ?>"
                    value="<?php echo esc_attr( $slug ); ?>"
                    <?php echo $checked; ?>
                >
                <span class="spf-option-name"><?php echo esc_html( $term->name ); ?></span>
                <?php if ( $options['show_count'] ) : ?>
                <span class="spf-option-count">(<?php echo intval( $term->count ); ?>)</span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>

    <?php if ( $options['show_price_filter'] ) : ?>
    <!-- ========== فیلتر قیمت ========== -->
    <div class="spf-filter-section">
        <div class="spf-filter-title">
            <span>قیمت</span>
            <svg class="spf-arrow" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="spf-filter-options">
            <div class="spf-price-slider-wrap">
                <div id="spf-price-slider"></div>
                <div class="spf-price-inputs">
                    <div class="spf-price-input-group">
                        <label>محدوده قیمت از</label>
                        <input type="text" id="spf-min-price" value="<?php echo esc_attr( $active_min ); ?>">
                    </div>
                    <div class="spf-price-input-group">
                        <label>محدوده قیمت تا</label>
                        <input type="text" id="spf-max-price" value="<?php echo esc_attr( $active_max ); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $options['show_stock_filter'] || $options['show_sale_filter'] || $options['show_featured_filter'] ) : ?>
    <!-- ========== toggle های سریع ========== -->
    <div class="spf-filter-section spf-toggles-section">
        <?php if ( $options['show_stock_filter'] ) : ?>
        <label class="spf-toggle-item">
            <span class="spf-toggle-label">فقط آیتم‌های موجود</span>
            <span class="spf-toggle-switch">
                <input type="checkbox" class="spf-toggle-input" data-toggle="instock" <?php checked( $active_instock ); ?>>
                <span class="spf-toggle-slider"></span>
            </span>
        </label>
        <?php endif; ?>

        <?php if ( $options['show_sale_filter'] ) : ?>
        <label class="spf-toggle-item">
            <span class="spf-toggle-label">فقط آیتم‌های تخفیف‌دار</span>
            <span class="spf-toggle-switch">
                <input type="checkbox" class="spf-toggle-input" data-toggle="onsale" <?php checked( $active_onsale ); ?>>
                <span class="spf-toggle-slider"></span>
            </span>
        </label>
        <?php endif; ?>

        <?php if ( $options['show_featured_filter'] ) : ?>
        <label class="spf-toggle-item">
            <span class="spf-toggle-label">فقط آیتم‌های ویژه</span>
            <span class="spf-toggle-switch">
                <input type="checkbox" class="spf-toggle-input" data-toggle="featured" <?php checked( $active_featured ); ?>>
                <span class="spf-toggle-slider"></span>
            </span>
        </label>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>