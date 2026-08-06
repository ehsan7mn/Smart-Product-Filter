<?php
defined( 'ABSPATH' ) || exit;

global $wp_query;
$initial_found = ( $wp_query instanceof WP_Query ) ? intval( $wp_query->found_posts ) : 0;
?>

<div id="spf-sort-bar" class="d-flex justify-content-between">
    <div class="spf-sort-buttons">
        <span class="spf-sort-label">مرتب‌سازی:</span>
        <button class="spf-sort-btn active" data-orderby="date">جدیدترین</button>
        <button class="spf-sort-btn" data-orderby="price">ارزان‌ترین</button>
        <button class="spf-sort-btn" data-orderby="price-desc">گران‌ترین</button>
        <button class="spf-sort-btn" data-orderby="popularity">پرفروش‌ترین</button>
        <button class="spf-sort-btn" data-orderby="rating">بهترین امتیاز</button>
        <button class="spf-sort-btn" data-orderby="title">الفبایی</button>
    </div>

    <div class="spf-result-count">
        <span id="spf-result-count-num" data-count="<?php echo esc_attr( $initial_found ); ?>"><?php echo esc_html( $initial_found ); ?></span> نتیجه
    </div>

    <!-- دکمه باز کردن فیلتر در موبایل -->
    <button class="spf-mobile-filter-btn d-md-none" data-bs-toggle="modal" data-bs-target="#spf-filter-modal">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
        فیلترها
        <span class="spf-active-count" style="display:none">0</span>
    </button>
</div>
