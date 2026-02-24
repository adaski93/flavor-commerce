<?php
/**
 * Szablon pojedynczego produktu
 *
 * @package Flavor Commerce
 */
get_header();

while ( have_posts() ) : the_post();
    $product_id   = get_the_ID();
    $price        = get_post_meta( $product_id, '_fc_price', true );
    $sale_price   = get_post_meta( $product_id, '_fc_sale_price', true );
    $sale_percent = get_post_meta( $product_id, '_fc_sale_percent', true );
    $sku          = get_post_meta( $product_id, '_fc_sku', true );
    $stock_status = get_post_meta( $product_id, '_fc_stock_status', true );
    $manage_stock = get_post_meta( $product_id, '_fc_manage_stock', true );
    $stock        = get_post_meta( $product_id, '_fc_stock', true );
    $gallery_ids  = get_post_meta( $product_id, '_fc_gallery', true );
    $categories   = get_the_terms( $product_id, 'fc_product_cat' );
    $brands       = get_the_terms( $product_id, 'fc_product_brand' );
    $product_type = get_post_meta( $product_id, '_fc_product_type', true ) ?: 'simple';
    $unit         = get_post_meta( $product_id, '_fc_unit', true ) ?: FC_Units_Admin::get_default();
    $variants     = get_post_meta( $product_id, '_fc_variants', true );
    if ( ! is_array( $variants ) ) $variants = array();
    $active_variants = array_filter( $variants, function( $v ) { return ( $v['status'] ?? 'active' ) === 'active'; } );
    $attributes   = get_post_meta( $product_id, '_fc_attributes', true );
    if ( ! is_array( $attributes ) ) $attributes = array();

    // Nowe meta (K1-N9)
    $specifications  = get_post_meta( $product_id, '_fc_specifications', true );
    if ( ! is_array( $specifications ) ) $specifications = array();
    $product_badges  = get_post_meta( $product_id, '_fc_badges', true );
    if ( ! is_array( $product_badges ) ) $product_badges = array();
    $upsell_ids      = get_post_meta( $product_id, '_fc_upsell_ids', true );
    if ( ! is_array( $upsell_ids ) ) $upsell_ids = array();
    $crosssell_ids   = get_post_meta( $product_id, '_fc_crosssell_ids', true );
    if ( ! is_array( $crosssell_ids ) ) $crosssell_ids = array();
    $purchase_note   = get_post_meta( $product_id, '_fc_purchase_note', true );
    $min_quantity    = get_post_meta( $product_id, '_fc_min_quantity', true );
    $max_quantity    = get_post_meta( $product_id, '_fc_max_quantity', true );

    // Oblicz tekst badge sprzedaży
    $has_sale   = false;
    $badge_sale = '';
    if ( $product_type === 'variable' && ! empty( $active_variants ) ) {
        $max_discount = 0;
        foreach ( $active_variants as $av ) {
            $sp = $av['sale_price'] ?? '';
            $vp = floatval( $av['price'] ?? 0 );
            if ( $sp !== '' && floatval( $sp ) > 0 ) {
                $has_sale = true;
                if ( ! empty( $av['sale_percent'] ) ) {
                    $d = floatval( $av['sale_percent'] );
                } elseif ( $vp > 0 ) {
                    $d = round( ( $vp - floatval( $sp ) ) / $vp * 100 );
                } else {
                    $d = 0;
                }
                if ( $d > $max_discount ) $max_discount = $d;
            }
        }
        if ( $max_discount > 0 ) {
            $badge_sale = sprintf( fc__( 'discount_badge' ), $max_discount );
        }
    } elseif ( $sale_price && floatval( $sale_price ) > 0 && floatval( $price ) > 0 ) {
        $has_sale = true;
        if ( $sale_percent ) {
            $disc = floatval( $sale_percent );
        } else {
            $disc = round( ( floatval( $price ) - floatval( $sale_price ) ) / floatval( $price ) * 100 );
        }
        if ( $disc > 0 ) {
            $badge_sale = sprintf( fc__( 'discount_badge' ), $disc );
        }
    }
    if ( $has_sale && empty( $badge_sale ) ) {
        $badge_sale = fc__( 'sale_badge' );
    }

    // Preorder: zmień badge rabatowy na "Preorder -X%" lub sam "Preorder"
    $is_preorder = ( get_post_status() === 'fc_preorder' );
    if ( $is_preorder && $has_sale && ! empty( $badge_sale ) ) {
        if ( preg_match( '/-(\d+)%/', $badge_sale, $m ) ) {
            $badge_sale = sprintf( fc__( 'preorder_discount_badge' ), $m[1] );
        } else {
            $badge_sale = fc__( 'preorder_badge' );
        }
    }
?>

<div class="fc-single-product">
    <div class="fc-product-top">
        <!-- Galeria -->
        <?php
        // Zbierz wszystkie zdjęcia: miniatura + galeria
        $all_images = array();
        if ( has_post_thumbnail() ) {
            $feat_id = get_post_thumbnail_id( $product_id );
            $all_images[] = array(
                'full'  => wp_get_attachment_image_url( $feat_id, 'large' ),
                'thumb' => wp_get_attachment_image( $feat_id, 'thumbnail' ),
            );
        }
        if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
            foreach ( $gallery_ids as $img_id ) {
                $full = wp_get_attachment_image_url( $img_id, 'large' );
                $th   = wp_get_attachment_image( $img_id, 'thumbnail' );
                if ( $full ) {
                    $all_images[] = array( 'full' => $full, 'thumb' => $th );
                }
            }
        }
        $total_images  = count( $all_images );
        $show_main_nav = $total_images > 1;
        $show_thumb_nav = $total_images > 6;
        ?>
        <div class="fc-product-gallery" data-total="<?php echo $total_images; ?>">
            <?php
            // Odznaki produktu + badge rabatowy — w jednym kontenerze
            $has_product_badges = get_option( 'fc_enable_badges', '1' ) && ! empty( $product_badges );
            $has_any_badge = $is_preorder || ( $has_sale && $badge_sale ) || $has_product_badges;
            if ( $has_any_badge ) :
                $badge_colors = array(
                    'bestseller' => '#e74c3c', 'new' => '#27ae60', 'recommended' => '#2980b9',
                    'free_shipping' => '#8e44ad', 'limited' => '#e67e22', 'last_items' => '#c0392b', 'eco' => '#16a085',
                );
                $badge_labels = array(
                    'bestseller' => fc__( 'badge_bestseller' ), 'new' => fc__( 'badge_new' ),
                    'recommended' => fc__( 'badge_recommended' ), 'free_shipping' => fc__( 'badge_free_shipping' ),
                    'limited' => fc__( 'badge_limited' ), 'last_items' => fc__( 'badge_last_items' ),
                    'eco' => fc__( 'badge_eco' ),
                );
            ?>
                <div class="fc-product-badges-wrap">
                    <?php if ( $has_product_badges ) : ?>
                        <?php foreach ( $product_badges as $b ) :
                            if ( isset( $badge_labels[ $b ] ) ) : ?>
                                <span class="fc-product-badge" style="background:<?php echo esc_attr( $badge_colors[ $b ] ?? '#333' ); ?>;"><?php echo esc_html( $badge_labels[ $b ] ); ?></span>
                            <?php endif;
                        endforeach; ?>
                    <?php endif; ?>
                    <?php if ( $is_preorder ) : ?>
                        <span class="fc-badge-sale fc-badge-inline"><?php echo $has_sale ? esc_html( $badge_sale ) : fc__( 'preorder_badge' ); ?></span>
                    <?php elseif ( $has_sale && $badge_sale ) : ?>
                        <span class="fc-badge-sale fc-badge-inline"><?php echo esc_html( $badge_sale ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="fc-main-image">
                <?php if ( $total_images > 0 ) : ?>
                    <img src="<?php echo esc_url( $all_images[0]['full'] ); ?>" alt="<?php the_title_attribute(); ?>">
                <?php else : ?>
                    <div class="fc-no-image fc-no-image-large"><?php fc_e( 'no_image' ); ?></div>
                <?php endif; ?>
                <?php if ( $show_main_nav ) : ?>
                    <button type="button" class="fc-main-nav fc-main-prev" aria-label="<?php echo esc_attr( fc__('previous') ); ?>">&#8249;</button>
                    <button type="button" class="fc-main-nav fc-main-next" aria-label="<?php echo esc_attr( fc__('next_slide') ); ?>">&#8250;</button>
                <?php endif; ?>
            </div>
            <?php if ( $total_images > 1 ) : ?>
                <div class="fc-thumbs-wrapper<?php echo $show_thumb_nav ? ' has-nav' : ''; ?>">
                    <?php if ( $show_thumb_nav ) : ?>
                        <button type="button" class="fc-thumbs-nav fc-thumbs-prev" aria-label="<?php echo esc_attr( fc__('scroll') ); ?>">&#8249;</button>
                    <?php endif; ?>
                    <div class="fc-gallery-thumbs">
                        <div class="fc-thumbs-track">
                            <?php foreach ( $all_images as $idx => $img ) : ?>
                                <div class="fc-thumb<?php echo $idx === 0 ? ' active' : ''; ?>" data-full="<?php echo esc_url( $img['full'] ); ?>" data-index="<?php echo $idx; ?>">
                                    <?php echo $img['thumb']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ( $show_thumb_nav ) : ?>
                        <button type="button" class="fc-thumbs-nav fc-thumbs-next" aria-label="<?php echo esc_attr( fc__('scroll') ); ?>">&#8250;</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Lightbox -->
        <div class="fc-lightbox" id="fc_lightbox">
            <div class="fc-lightbox-backdrop"></div>
            <div class="fc-lightbox-content">
                <button type="button" class="fc-lightbox-close" aria-label="<?php echo esc_attr( fc__('close') ); ?>">&times;</button>
                <button type="button" class="fc-lightbox-nav fc-lightbox-prev" aria-label="<?php echo esc_attr( fc__('previous') ); ?>">&#8249;</button>
                <img class="fc-lightbox-img" src="" alt="">
                <button type="button" class="fc-lightbox-nav fc-lightbox-next" aria-label="<?php echo esc_attr( fc__('next_slide') ); ?>">&#8250;</button>
            </div>
        </div>

        <!-- Info -->
        <div class="fc-product-details">
            <h1 class="fc-product-title"><?php the_title(); ?></h1>

            <?php
            $review_count = FC_Reviews::get_review_count( $product_id );
            $avg_rating   = $review_count > 0 ? FC_Reviews::get_average_rating( $product_id ) : 0;
            ?>
            <div class="fc-product-meta-row fc-single-meta-row">
                <div class="fc-product-brand">
                    <?php if ( ! empty( $brands ) && ! is_wp_error( $brands ) ) :
                        $brand = $brands[0]; ?>
                        <a href="<?php echo esc_url( add_query_arg( 'fc_brand', $brand->slug, get_permalink( get_option( 'fc_page_sklep', 0 ) ) ?: site_url( '/sklep/' ) ) ); ?>"><?php echo esc_html( $brand->name ); ?></a>
                    <?php else : ?>
                        &nbsp;
                    <?php endif; ?>
                </div>
                <?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
                    <div class="fc-product-category-inline">
                        <span><?php fc_e( 'category_label' ); ?></span>
                        <?php
                        $cat_links = array();
                        foreach ( $categories as $cat ) {
                            $cat_links[] = '<a href="' . esc_url( add_query_arg( 'fc_cat', $cat->slug, fc_get_shop_url() ) ) . '">' . esc_html( $cat->name ) . '</a>';
                        }
                        echo implode( ', ', $cat_links );
                        ?>
                    </div>
                <?php endif; ?>
                <?php if ( $review_count > 0 ) : ?>
                    <div class="fc-product-rating">
                        <?php FC_Reviews::render_stars( $avg_rating ); ?>
                        <span class="fc-rating-count">(<?php echo number_format( $avg_rating, 1, ',', '' ); ?> / <?php echo intval( $review_count ); ?>)</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="fc-product-price fc-price-large">
                <?php if ( $product_type === 'variable' && ! empty( $active_variants ) ) : ?>
                    <?php
                    $var_prices = array_filter( array_column( $active_variants, 'price' ), function( $p ) { return $p !== ''; } );
                    $var_sale_prices = array_filter( array_column( $active_variants, 'sale_price' ), function( $p ) { return $p !== '' && floatval( $p ) > 0; } );

                    $effective_prices = array();
                    foreach ( $active_variants as $av ) {
                        $vp = floatval( $av['price'] ?? 0 );
                        $vsp = floatval( $av['sale_price'] ?? 0 );
                        if ( $vsp > 0 && $vsp < $vp ) {
                            $effective_prices[] = $vsp;
                        } elseif ( $vp > 0 ) {
                            $effective_prices[] = $vp;
                        }
                    }
                    $min_eff = ! empty( $effective_prices ) ? min( $effective_prices ) : 0;
                    $max_eff = ! empty( $effective_prices ) ? max( $effective_prices ) : 0;
                    ?>
                    <?php if ( ! empty( $var_sale_prices ) ) : ?>
                        <?php if ( $min_eff == $max_eff ) : ?>
                            <span class="fc-price-sale"><?php echo fc_format_price( $min_eff, $product_id ); ?></span>
                        <?php else : ?>
                            <span class="fc-price-sale"><?php echo fc_format_price( $min_eff, $product_id ); ?> &ndash; <?php echo fc_format_price( $max_eff, $product_id ); ?></span>
                        <?php endif; ?>
                    <?php elseif ( $min_eff == $max_eff ) : ?>
                        <span><?php echo fc_format_price( $min_eff, $product_id ); ?></span>
                    <?php else : ?>
                        <span><?php echo fc_format_price( $min_eff, $product_id ); ?> &ndash; <?php echo fc_format_price( $max_eff, $product_id ); ?></span>
                    <?php endif; ?>
                    <span class="fc-price-selected" style="display:none;"></span>
                <?php elseif ( $sale_price && floatval( $sale_price ) > 0 ) : ?>
                    <del><?php echo fc_format_price( $price, $product_id ); ?></del>
                    <ins><?php echo fc_format_price( $sale_price, $product_id ); ?></ins>
                <?php elseif ( $price ) : ?>
                    <span><?php echo fc_format_price( $price, $product_id ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $unit ) && FC_Units_Admin::is_visible( 'product' ) ) : ?>
                    <span class="fc-price-unit">/ <?php echo esc_html( FC_Units_Admin::label( $unit ) ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( has_excerpt() ) : ?>
                <div class="fc-product-excerpt"><?php the_excerpt(); ?></div>
            <?php endif; ?>

            <!-- Stan magazynowy -->
            <div class="fc-stock-info" data-unit="<?php echo esc_attr( FC_Units_Admin::label( $unit ) ); ?>">
                <?php if ( $stock_status === 'outofstock' ) : ?>
                    <span class="fc-stock-badge out"><?php fc_e( 'out_of_stock_badge' ); ?></span>
                    <?php if ( get_option( 'fc_enable_stock_notify', '1' ) && class_exists( 'FC_Frontend_Features' ) ) FC_Frontend_Features::render_stock_notify_button( $product_id ); ?>
                <?php else : ?>
                    <span class="fc-stock-badge in"><?php fc_e( 'in_stock_badge' ); ?></span>
                    <?php if ( $manage_stock === '1' && $stock !== '' ) : ?>
                        <span class="fc-stock-qty">(<?php echo intval( $stock ); ?> <?php echo esc_html( FC_Units_Admin::label( $unit ) ); ?>)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Warianty atrybutowe -->
            <?php if ( $product_type === 'variable' && ! empty( $active_variants ) && ! empty( $attributes ) ) : ?>
                <div class="fc-variant-attributes" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                    <?php foreach ( $attributes as $attr ) :
                        // Zbierz unikalne wartości tego atrybutu z aktywnych wariantów
                        $attr_name = $attr['name'];
                        $attr_type = $attr['type'];
                        $attr_values = $attr['values'];
                    ?>
                        <div class="fc-attr-selector" data-attr-name="<?php echo esc_attr( $attr_name ); ?>">
                            <label class="fc-attr-label"><?php echo esc_html( $attr_name ); ?>:</label>
                            <div class="fc-attr-options fc-attr-type-<?php echo esc_attr( $attr_type ); ?>">
                                <?php foreach ( $attr_values as $val ) :
                                    $label = is_array( $val ) ? $val['label'] : $val;
                                ?>
                                    <?php if ( $attr_type === 'color' ) :
                                        $color_css = ( is_array( $val ) && isset( $val['value'] ) ) ? $val['value'] : $label;
                                    ?>
                                        <button type="button" class="fc-attr-tile fc-attr-color-tile" data-value="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>" style="background:<?php echo esc_attr( $color_css ); ?>;"></button>
                                    <?php elseif ( $attr_type === 'image' ) :
                                        $img_url = '';
                                        if ( is_array( $val ) && ! empty( $val['id'] ) ) {
                                            $img_url = wp_get_attachment_image_url( $val['id'], 'thumbnail' );
                                        } elseif ( is_array( $val ) && ! empty( $val['url'] ) ) {
                                            $img_url = $val['url'];
                                        }
                                    ?>
                                        <button type="button" class="fc-attr-tile fc-attr-image-tile" data-value="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">
                                            <?php if ( $img_url ) : ?>
                                                <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $label ); ?>">
                                            <?php else : ?>
                                                <span><?php echo esc_html( $label ); ?></span>
                                            <?php endif; ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button" class="fc-attr-tile fc-attr-text-tile" data-value="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <input type="hidden" class="fc-selected-variant-id" value="">
                </div>
                <!-- Variant data as JSON for JS matching -->
                <script type="application/json" class="fc-variants-data"><?php
                    $vdata = array();
                    foreach ( $active_variants as $vi => $v ) {
                        // Build image URLs for variant
                        $v_images = array();
                        $v_image_ids = array();
                        if ( ! empty( $v['images'] ) && is_array( $v['images'] ) ) {
                            $v_image_ids = array_map( 'intval', $v['images'] );
                        } elseif ( ! empty( $v['image'] ) && intval( $v['image'] ) > 0 ) {
                            $v_image_ids = array( intval( $v['image'] ) );
                        }
                        foreach ( $v_image_ids as $vid ) {
                            $vfull = wp_get_attachment_image_url( $vid, 'large' );
                            $vthumb = wp_get_attachment_image_url( $vid, 'thumbnail' );
                            if ( $vfull ) {
                                $v_images[] = array( 'id' => $vid, 'full' => $vfull, 'thumb' => $vthumb );
                            }
                        }
                        $vdata[] = array(
                            'index'            => $vi,
                            'id'               => isset( $v['id'] ) ? $v['id'] : $vi,
                            'name'             => $v['name'],
                            'attribute_values' => $v['attribute_values'] ?? array(),
                            'price'            => $v['price'] ?? '',
                            'sale_price'       => $v['sale_price'] ?? '',
                            'sale_percent'     => $v['sale_percent'] ?? '',
                            'sku'              => $v['sku'] ?? '',
                            'stock'            => $v['stock'] ?? '',
                            'images'           => $v_images,
                            'main_image'       => isset( $v['main_image'] ) ? intval( $v['main_image'] ) : ( ! empty( $v_images ) ? $v_images[0]['id'] : 0 ),
                        );
                    }
                    echo wp_json_encode( $vdata );
                ?></script>
            <?php endif; ?>

            <!-- Dodaj do koszyka -->
            <?php if ( $stock_status !== 'outofstock' || $product_type === 'variable' ) : ?>
                <div class="fc-add-to-cart-form">
                    <div class="fc-qty-wrapper"<?php echo ( $stock_status === 'outofstock' && $product_type === 'variable' ) ? ' style="display:none;"' : ''; ?>>
                        <button type="button" class="fc-qty-btn fc-qty-minus">−</button>
                        <input type="number" class="fc-qty-input-single" value="<?php echo esc_attr( $min_quantity ?: 1 ); ?>" min="<?php echo esc_attr( $min_quantity ?: 1 ); ?>" max="<?php echo esc_attr( $max_quantity ?: ( $manage_stock === '1' ? intval( $stock ) : 99 ) ); ?>">
                        <button type="button" class="fc-qty-btn fc-qty-plus">+</button>
                    </div>
                    <button class="fc-btn fc-btn-large fc-add-to-cart-single" data-product-id="<?php echo esc_attr( $product_id ); ?>"<?php echo $product_type === 'variable' ? ' disabled' : ''; ?><?php echo ( $stock_status === 'outofstock' && $product_type === 'variable' ) ? ' style="display:none;"' : ''; ?>>
                        <?php echo $product_type === 'variable' ? fc__( 'choose_variants' ) : fc__( 'add_to_cart' ); ?>
                    </button>
                    <?php if ( $product_type === 'variable' && get_option( 'fc_enable_stock_notify', '1' ) && class_exists( 'FC_Frontend_Features' ) ) : ?>
                        <button type="button" class="fc-btn fc-btn-large fc-btn-outline fc-stock-notify-btn fc-notify-variant-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>" style="display:none;width:100%;">
                            <span class="dashicons dashicons-email-alt" style="vertical-align:text-bottom;margin-right:4px;"></span>
                            <?php fc_e( 'notify_availability' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ( $min_quantity && intval( $min_quantity ) > 1 ) : ?>
                    <p class="fc-qty-hint">
                        <?php printf( fc__( 'min_order_quantity' ), intval( $min_quantity ) ); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Wishlist & Compare buttons -->
            <?php
                $btn_shape = get_theme_mod( 'fc_action_btn_shape', 'circle' );
                $btn_style = get_theme_mod( 'fc_action_btn_style', 'glass' );
                $single_cls = 'fc-single-action-btn fc-action-btn--' . esc_attr( $btn_shape ) . ' fc-single-btn--' . esc_attr( $btn_style );
            ?>
            <div class="fc-single-action-buttons">
                <?php if ( get_theme_mod( 'flavor_archive_wishlist', true ) && class_exists( 'FC_Wishlist' ) ) :
                    $is_fav = FC_Wishlist::is_in_wishlist( $product_id, get_current_user_id() );
                ?>
                    <button type="button" class="<?php echo esc_attr( $single_cls ); ?> fc-wishlist-btn<?php echo $is_fav ? ' active' : ''; ?>"
                            data-product-id="<?php echo esc_attr( $product_id ); ?>"
                            title="<?php echo $is_fav ? fc__( 'remove_from_wishlist' ) : fc__( 'add_to_wishlist' ); ?>">
                        <span class="fc-heart"><?php echo $is_fav ? '❤️' : '🤍'; ?></span>
                        <span><?php echo $is_fav ? fc__( 'in_wishlist' ) : fc__( 'to_wishlist' ); ?></span>
                    </button>
                <?php endif; ?>
                <?php if ( get_theme_mod( 'flavor_archive_compare', '1' ) && class_exists( 'FC_Frontend_Features' ) ) :
                    $is_compared = isset( $_SESSION['fc_compare'] ) && in_array( $product_id, $_SESSION['fc_compare'] );
                ?>
                    <button type="button" class="<?php echo esc_attr( $single_cls ); ?> fc-compare-btn<?php echo $is_compared ? ' active' : ''; ?>"
                            data-product-id="<?php echo esc_attr( $product_id ); ?>"
                            title="<?php fc_e( 'compare' ); ?>">
                        <span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg></span>
                        <span><?php echo $is_compared ? fc__( 'in_comparator' ) : fc__( 'compare' ); ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Meta -->
            <div class="fc-product-meta-info">
                <?php if ( $sku ) : ?>
                    <p><span><?php fc_e( 'sku_label' ); ?></span> <?php echo esc_html( $sku ); ?></p>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Zakładki: Opis / Specyfikacja / Opinie -->
    <?php
    $content = get_the_content();
    // $review_count i $avg_rating obliczone wyżej
    ?>
    <div class="fc-product-tabs">
        <div class="fc-tabs-nav">
            <?php if ( $content ) : ?>
                <button class="fc-tab-btn active" data-tab="description"><?php fc_e( 'description_tab' ); ?></button>
            <?php endif; ?>
            <?php if ( ! empty( $specifications ) ) : ?>
                <button class="fc-tab-btn<?php echo ! $content ? ' active' : ''; ?>" data-tab="specifications"><?php fc_e( 'specifications_tab' ); ?></button>
            <?php endif; ?>
            <button class="fc-tab-btn<?php echo ! $content && empty( $specifications ) ? ' active' : ''; ?>" data-tab="reviews">
                <?php fc_e( 'reviews_tab' ); ?>
                <?php if ( $review_count > 0 ) : ?>
                    <span class="fc-tab-badge"><?php echo intval( $review_count ); ?></span>
                <?php endif; ?>
            </button>
        </div>

        <?php if ( $content ) : ?>
            <div class="fc-tab-panel active" data-tab="description">
                <div class="fc-description-content">
                    <?php the_content(); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $specifications ) ) : ?>
            <div class="fc-tab-panel<?php echo ! $content ? ' active' : ''; ?>" data-tab="specifications">
                <table class="fc-specs-table">
                    <?php foreach ( $specifications as $i => $spec ) : ?>
                        <tr>
                            <th><?php echo esc_html( $spec['key'] ); ?></th>
                            <td><?php echo esc_html( $spec['value'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <div class="fc-tab-panel<?php echo ! $content && empty( $specifications ) ? ' active' : ''; ?>" data-tab="reviews">
            <?php FC_Reviews::render_reviews_section( $product_id ); ?>
        </div>
    </div>

    <?php
    // Notatka zakupowa (W3) — wyświetlana jako info
    if ( get_option( 'fc_enable_purchase_note', '1' ) && $purchase_note && $stock_status !== 'outofstock' ) : ?>
        <div class="fc-purchase-note">
            <strong><?php fc_e( 'purchase_note_heading' ); ?></strong>
            <?php echo wp_kses_post( wpautop( $purchase_note ) ); ?>
        </div>
    <?php endif; ?>

    <?php
    // Produkty powiązane — Up-sell (K3)
    if ( get_option( 'fc_enable_upsell', '1' ) && ! empty( $upsell_ids ) ) :
        $upsell_products = get_posts( array(
            'post_type'      => 'fc_product',
            'post__in'       => $upsell_ids,
            'post_status'    => 'fc_published',
            'posts_per_page' => 8,
        ) );
        if ( ! empty( $upsell_products ) ) : ?>
            <div class="fc-related-products">
                <div class="fc-related-header">
                    <h3><?php fc_e( 'you_may_also_like' ); ?></h3>
                    <div class="fc-related-nav">
                        <button type="button" class="fc-related-arrow fc-related-prev" aria-label="<?php echo esc_attr( fc__('previous') ); ?>">&#8249;</button>
                        <button type="button" class="fc-related-arrow fc-related-next" aria-label="<?php echo esc_attr( fc__('next_slide') ); ?>">&#8250;</button>
                    </div>
                </div>
                <div class="fc-related-track-wrapper">
                    <div class="fc-related-track">
                        <?php foreach ( $upsell_products as $up ) :
                            echo '<div class="fc-related-slide">';
                            FC_Shortcodes::render_product_card_static( $up->ID );
                            echo '</div>';
                        endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif;
    endif; ?>
</div>

<?php endwhile;

get_footer();
