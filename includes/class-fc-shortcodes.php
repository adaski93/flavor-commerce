<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcodes: [fc_shop], [fc_cart], [fc_checkout], [fc_thank_you], [fc_retry_payment], [fc_add_to_cart]
 */
class FC_Shortcodes {

    public static function init() {
        add_shortcode( 'fc_shop', array( __CLASS__, 'shop' ) );
        add_shortcode( 'fc_cart', array( __CLASS__, 'cart' ) );
        add_shortcode( 'fc_checkout', array( __CLASS__, 'checkout' ) );
        add_shortcode( 'fc_thank_you', array( __CLASS__, 'thank_you' ) );
        add_shortcode( 'fc_retry_payment', array( __CLASS__, 'retry_payment' ) );
        add_shortcode( 'fc_add_to_cart', array( __CLASS__, 'add_to_cart_button' ) );

        // Szablon single produktu
        add_filter( 'single_template', array( __CLASS__, 'product_template' ) );

        // Ukryj tytuł strony podziękowania
        add_filter( 'the_title', array( __CLASS__, 'hide_thank_you_title' ), 10, 2 );
        add_filter( 'body_class', array( __CLASS__, 'thank_you_body_class' ) );
    }

    /**
     * Ukrywa tytuł strony podziękowania w nagłówku
     */
    public static function hide_thank_you_title( $title, $post_id = 0 ) {
        static $page_id = null;
        if ( $page_id === null ) $page_id = (int) get_option( 'fc_page_podziekowanie' );
        if ( $page_id && (int) $post_id === $page_id && in_the_loop() && is_main_query() ) {
            return '';
        }
        return $title;
    }

    /**
     * Dodaje klasę body na stronie podziękowania
     */
    public static function thank_you_body_class( $classes ) {
        static $page_id = null;
        if ( $page_id === null ) $page_id = (int) get_option( 'fc_page_podziekowanie' );
        if ( $page_id && is_page( $page_id ) ) {
            $classes[] = 'fc-page-thank-you';
        }
        return $classes;
    }

    /**
     * [fc_shop] — lista produktów
     */
    public static function shop( $atts ) {
        $atts = shortcode_atts( array(
            'per_page' => 12,
            'columns'  => 3,
            'category' => '',
            'orderby'  => 'date',
            'order'    => 'DESC',
        ), $atts );

        $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : ( get_query_var( 'page' ) ? get_query_var( 'page' ) : 1 );
        if ( isset( $_GET['paged'] ) ) {
            $paged = max( 1, intval( $_GET['paged'] ) );
        }

        // Sortowanie z URL
        $fc_sort = isset( $_GET['fc_sort'] ) ? sanitize_text_field( $_GET['fc_sort'] ) : '';
        $sort_orderby = $atts['orderby'];
        $sort_order   = $atts['order'];
        $sort_meta    = '';
        switch ( $fc_sort ) {
            case 'price_asc':
                $sort_orderby = 'meta_value_num';
                $sort_order   = 'ASC';
                $sort_meta    = '_fc_effective_price';
                break;
            case 'price_desc':
                $sort_orderby = 'meta_value_num';
                $sort_order   = 'DESC';
                $sort_meta    = '_fc_effective_price';
                break;
            case 'title_asc':
                $sort_orderby = 'title';
                $sort_order   = 'ASC';
                break;
            case 'title_desc':
                $sort_orderby = 'title';
                $sort_order   = 'DESC';
                break;
            case 'oldest':
                $sort_orderby = 'date';
                $sort_order   = 'ASC';
                break;
            default: // newest
                $sort_orderby = 'date';
                $sort_order   = 'DESC';
                break;
        }

        $args = array(
            'post_type'      => 'fc_product',
            'post_status'    => array( 'fc_published', 'fc_preorder' ),
            'posts_per_page' => intval( $atts['per_page'] ),
            'paged'          => $paged,
            'orderby'        => $sort_orderby,
            'order'          => $sort_order,
        );
        if ( $sort_meta ) {
            $args['meta_key'] = $sort_meta;
        }

        if ( ! empty( $atts['category'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'fc_product_cat',
                    'field'    => 'slug',
                    'terms'    => explode( ',', $atts['category'] ),
                ),
            );
        }

        // Filtrowanie po kategorii z URL
        if ( isset( $_GET['fc_cat'] ) && ! empty( $_GET['fc_cat'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'fc_product_cat',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $_GET['fc_cat'] ),
                ),
            );
        }

        // Filtrowanie po marce z URL
        if ( isset( $_GET['fc_brand'] ) && ! empty( $_GET['fc_brand'] ) ) {
            $brand_query = array(
                'taxonomy' => 'fc_product_brand',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $_GET['fc_brand'] ),
            );
            if ( isset( $args['tax_query'] ) ) {
                $args['tax_query']['relation'] = 'AND';
                $args['tax_query'][] = $brand_query;
            } else {
                $args['tax_query'] = array( $brand_query );
            }
        }

        // Filtrowanie po cenie z URL
        if ( isset( $_GET['fc_min_price'] ) || isset( $_GET['fc_max_price'] ) ) {
            $meta_price = array( 'relation' => 'AND' );
            if ( isset( $_GET['fc_min_price'] ) && $_GET['fc_min_price'] !== '' ) {
                $meta_price[] = array(
                    'key'     => '_fc_effective_price',
                    'value'   => floatval( $_GET['fc_min_price'] ),
                    'compare' => '>=',
                    'type'    => 'DECIMAL(10,2)',
                );
            }
            if ( isset( $_GET['fc_max_price'] ) && $_GET['fc_max_price'] !== '' ) {
                $meta_price[] = array(
                    'key'     => '_fc_effective_price',
                    'value'   => floatval( $_GET['fc_max_price'] ),
                    'compare' => '<=',
                    'type'    => 'DECIMAL(10,2)',
                );
            }
            if ( count( $meta_price ) > 1 ) {
                $args['meta_query'] = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
                $args['meta_query'][] = $meta_price;
            }
        }

        // Filtrowanie po atrybutach z URL (fc_attr_*)
        $attr_filters = array(); // nazwa atrybutu => wartość filtra
        foreach ( $_GET as $gk => $gv ) {
            if ( strpos( $gk, 'fc_attr_' ) !== 0 || $gv === '' ) continue;
            $attr_slug = substr( $gk, 8 ); // po 'fc_attr_'
            $filter_val = sanitize_text_field( $gv );
            $attr_filters[ $attr_slug ] = $filter_val;

            // Użyj formatu serializacji do LIKE aby ograniczyć false positives
            $serialized_val = serialize( $filter_val ); // np. s:1:"M";
            if ( ! isset( $args['meta_query'] ) ) {
                $args['meta_query'] = array();
            }
            $args['meta_query'][] = array(
                'key'     => '_fc_attributes',
                'value'   => $serialized_val,
                'compare' => 'LIKE',
            );
        }

        // Filtrowanie po wyszukiwarce
        if ( ! empty( $_GET['fc_search'] ) ) {
            $args['s'] = sanitize_text_field( $_GET['fc_search'] );
        }

        // Filtrowanie po dostępności
        if ( ! empty( $_GET['fc_availability'] ) ) {
            $avail_val = sanitize_text_field( $_GET['fc_availability'] );
            if ( in_array( $avail_val, array( 'instock', 'outofstock' ), true ) ) {
                if ( ! isset( $args['meta_query'] ) ) {
                    $args['meta_query'] = array();
                }
                $args['meta_query'][] = array(
                    'key'     => '_fc_stock_status',
                    'value'   => $avail_val,
                    'compare' => '=',
                );
            }
        }

        // Filtrowanie po minimalnej ocenie
        if ( ! empty( $_GET['fc_min_rating'] ) ) {
            $min_rating = intval( $_GET['fc_min_rating'] );
            if ( $min_rating >= 1 && $min_rating <= 5 ) {
                add_filter( 'posts_where', function( $where, $query ) use ( $min_rating ) {
                    if ( $query->get( '_fc_rating_filter' ) !== 'yes' ) return $where;
                    global $wpdb;
                    $where .= $wpdb->prepare(
                        " AND {$wpdb->posts}.ID IN (
                            SELECT comment_post_ID FROM {$wpdb->comments} c
                            INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                            WHERE cm.meta_key = '_fc_rating' AND cm.meta_value >= %d
                            GROUP BY comment_post_ID
                            HAVING AVG(cm.meta_value) >= %d
                        )",
                        $min_rating,
                        $min_rating
                    );
                    return $where;
                }, 10, 2 );
                $args['_fc_rating_filter'] = 'yes';
            }
        }

        $products = new WP_Query( $args );

        // Pobierz WSZYSTKIE przefiltrowane ID (do cross-filtrowania widgetów)
        $ids_args = $args;
        $ids_args['posts_per_page'] = -1;
        $ids_args['fields']         = 'ids';
        $ids_args['no_found_rows']  = true;
        unset( $ids_args['paged'] );
        $filtered_ids = get_posts( $ids_args );

        // Dodatkowa weryfikacja PHP: sprawdź, czy produkt naprawdę ma daną wartość atrybutu
        if ( ! empty( $attr_filters ) && ! empty( $filtered_ids ) ) {
            update_postmeta_cache( $filtered_ids );
            $filtered_ids = array_filter( $filtered_ids, function( $pid ) use ( $attr_filters ) {
                $attrs = get_post_meta( $pid, '_fc_attributes', true );
                if ( ! is_array( $attrs ) ) return false;
                foreach ( $attr_filters as $slug => $expected_val ) {
                    $found = false;
                    foreach ( $attrs as $a ) {
                        if ( sanitize_title( $a['name'] ) !== $slug ) continue;
                        if ( ! empty( $a['values'] ) && is_array( $a['values'] ) ) {
                            foreach ( $a['values'] as $v ) {
                                $label = is_array( $v ) ? ( $v['label'] ?? $v['value'] ?? '' ) : $v;
                                if ( trim( $label ) === $expected_val ) {
                                    $found = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    if ( ! $found ) return false;
                }
                return true;
            });
            $filtered_ids = array_values( $filtered_ids );

            // Zaktualizuj WP_Query o zweryfikowane ID
            if ( empty( $filtered_ids ) ) {
                $products->posts = array();
                $products->post_count = 0;
                $products->found_posts = 0;
            } else {
                $verified_args = array(
                    'post_type'      => 'fc_product',
                    'post_status'    => array( 'fc_published', 'fc_preorder' ),
                    'posts_per_page' => intval( $atts['per_page'] ),
                    'paged'          => $paged,
                    'post__in'       => $filtered_ids,
                    'orderby'        => $sort_orderby,
                    'order'          => $sort_order,
                );
                $products = new WP_Query( $verified_args );
            }
        }

        ob_start();

        $sidebar_pos = get_option( 'fc_shop_sidebar', 'none' );
        $blocks      = get_option( 'fc_sidebar_blocks', array() );
        $tablet_style = get_option( 'fc_tablet_sidebar', 'offcanvas' );
        $phone_style  = get_option( 'fc_phone_sidebar', 'bottom_sheet' );
        $has_blocks   = ! empty( $blocks );
        // Sidebar jest aktywny jeśli co najmniej jedno urządzenie ma go włączonego
        $desktop_sidebar = in_array( $sidebar_pos, array( 'left', 'right' ), true );
        $has_sidebar     = $has_blocks && ( $desktop_sidebar || $tablet_style !== 'none' || $phone_style !== 'none' );
        ?>
        <div class="fc-shop<?php echo $has_sidebar ? ' fc-shop-has-sidebar' : ''; ?><?php echo $desktop_sidebar && $has_blocks ? ' fc-sidebar-' . esc_attr( $sidebar_pos ) : ''; ?>"<?php echo $has_sidebar ? ' data-tablet-sidebar="' . esc_attr( $tablet_style ) . '" data-phone-sidebar="' . esc_attr( $phone_style ) . '"' : ''; ?>>
            <?php if ( $has_sidebar ) : ?>
                <div class="fc-mobile-sidebar-overlay"></div>
                <aside class="fc-shop-sidebar">
                    <div class="fc-mobile-sidebar-header">
                        <span class="fc-mobile-sidebar-title"><?php fc_e( 'filters' ); ?></span>
                        <button type="button" class="fc-mobile-sidebar-close" aria-label="<?php echo esc_attr( fc__( 'close' ) ); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <?php echo self::render_sidebar_blocks( $blocks, $filtered_ids ); ?>
                    <div class="fc-sidebar-footer">
                        <div class="fc-sidebar-pending-filters"></div>
                        <div class="fc-sidebar-footer-actions">
                            <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn fc-btn-sm fc-sidebar-reset"><?php fc_e( 'reset' ); ?></a>
                            <button type="button" class="fc-btn fc-btn-sm fc-btn-accent fc-sidebar-apply"><?php fc_e( 'filter_apply' ); ?></button>
                        </div>
                    </div>
                </aside>
            <?php endif; ?>

            <div class="fc-shop-content">
            <?php
            // Pasek aktywnych filtrów
            $active_filters = array();
            $shop_base_url  = fc_get_shop_url();
            $all_fc_params   = array();
            foreach ( $_GET as $k => $v ) {
                if ( strpos( $k, 'fc_' ) === 0 && $v !== '' && $k !== 'fc_sort' ) {
                    $all_fc_params[ $k ] = $v;
                }
            }

            if ( ! empty( $all_fc_params ) ) {
                // Kategoria
                if ( ! empty( $_GET['fc_cat'] ) ) {
                    $cat_term = get_term_by( 'slug', sanitize_text_field( $_GET['fc_cat'] ), 'fc_product_cat' );
                    $active_filters[] = array(
                        'label' => fc__( 'category' ) . ': ' . esc_html( $cat_term ? $cat_term->name : $_GET['fc_cat'] ),
                        'remove_url' => remove_query_arg( 'fc_cat', add_query_arg( $all_fc_params, $shop_base_url ) ),
                    );
                }
                // Marka
                if ( ! empty( $_GET['fc_brand'] ) ) {
                    $brand_term = get_term_by( 'slug', sanitize_text_field( $_GET['fc_brand'] ), 'fc_product_brand' );
                    $active_filters[] = array(
                        'label' => fc__( 'brand' ) . ': ' . esc_html( $brand_term ? $brand_term->name : $_GET['fc_brand'] ),
                        'remove_url' => remove_query_arg( 'fc_brand', add_query_arg( $all_fc_params, $shop_base_url ) ),
                    );
                }
                // Atrybuty
                foreach ( $_GET as $k => $v ) {
                    if ( strpos( $k, 'fc_attr_' ) === 0 && $v !== '' ) {
                        $attr_name = ucfirst( str_replace( '-', ' ', substr( $k, 8 ) ) );
                        $active_filters[] = array(
                            'label' => esc_html( $attr_name ) . ': ' . esc_html( sanitize_text_field( $v ) ),
                            'remove_url' => remove_query_arg( $k, add_query_arg( $all_fc_params, $shop_base_url ) ),
                        );
                    }
                }
                // Cena
                if ( isset( $_GET['fc_min_price'] ) || isset( $_GET['fc_max_price'] ) ) {
                    $currency = get_option( 'fc_currency_symbol', 'zł' );
                    $price_label = fc__( 'price' ) . ': ';
                    if ( ! empty( $_GET['fc_min_price'] ) && ! empty( $_GET['fc_max_price'] ) ) {
                        $price_label .= esc_html( $_GET['fc_min_price'] ) . ' – ' . esc_html( $_GET['fc_max_price'] ) . ' ' . esc_html( $currency );
                    } elseif ( ! empty( $_GET['fc_min_price'] ) ) {
                        $price_label .= fc__( 'from' ) . ' ' . esc_html( $_GET['fc_min_price'] ) . ' ' . esc_html( $currency );
                    } else {
                        $price_label .= fc__( 'to' ) . ' ' . esc_html( $_GET['fc_max_price'] ) . ' ' . esc_html( $currency );
                    }
                    $active_filters[] = array(
                        'label' => $price_label,
                        'remove_url' => remove_query_arg( array( 'fc_min_price', 'fc_max_price' ), add_query_arg( $all_fc_params, $shop_base_url ) ),
                    );
                }
                // Ocena
                if ( ! empty( $_GET['fc_min_rating'] ) ) {
                    $active_filters[] = array(
                        'label' => fc__( 'rating' ) . ': ' . intval( $_GET['fc_min_rating'] ) . '+ ★',
                        'remove_url' => remove_query_arg( 'fc_min_rating', add_query_arg( $all_fc_params, $shop_base_url ) ),
                    );
                }
                // Dostępność
                if ( ! empty( $_GET['fc_availability'] ) ) {
                    $avail_labels = array( 'instock' => fc__( 'in_stock' ), 'outofstock' => fc__( 'out_of_stock' ) );
                    $active_filters[] = array(
                        'label' => fc__( 'availability' ) . ': ' . esc_html( $avail_labels[ $_GET['fc_availability'] ] ?? $_GET['fc_availability'] ),
                        'remove_url' => remove_query_arg( 'fc_availability', add_query_arg( $all_fc_params, $shop_base_url ) ),
                    );
                }
                // Szukaj
                if ( ! empty( $_GET['fc_search'] ) ) {
                    $active_filters[] = array(
                        'label' => fc__( 'search' ) . ': „' . esc_html( sanitize_text_field( $_GET['fc_search'] ) ) . '"',
                        'remove_url' => remove_query_arg( 'fc_search', add_query_arg( $all_fc_params, $shop_base_url ) ),
                    );
                }
            }

            if ( ! empty( $active_filters ) ) :
            ?>
                <div class="fc-active-filters">
                    <span class="fc-active-filters-label"><?php fc_e( 'active_filters' ); ?></span>
                    <?php foreach ( $active_filters as $af ) : ?>
                        <a href="<?php echo esc_url( $af['remove_url'] ); ?>" class="fc-active-filter-tag" title="<?php echo esc_attr( fc__( 'remove_filter' ) ); ?>">
                            <?php echo esc_html( $af['label'] ); ?>
                            <span class="fc-active-filter-x">&times;</span>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?php echo esc_url( $shop_base_url ); ?>" class="fc-reset-filters"><?php fc_e( 'reset_filters' ); ?></a>
                </div>
            <?php endif; ?>

            <?php if ( $has_sidebar ) : ?>
            <div class="fc-mobile-bottom-bar">
                <div class="fc-mobile-bottom-bar-filters">
                    <?php if ( ! empty( $active_filters ) ) : ?>
                        <span class="fc-mobile-bottom-bar-label"><?php fc_e( 'active_filters' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="fc-mobile-bottom-bar-actions">
                    <?php if ( ! empty( $active_filters ) ) : ?>
                        <a href="<?php echo esc_url( $shop_base_url ); ?>" class="fc-mobile-reset-btn"><?php fc_e( 'reset_filters' ); ?></a>
                    <?php endif; ?>
                    <button type="button" class="fc-mobile-filters-btn" aria-expanded="false">
                        <span class="dashicons dashicons-filter"></span>
                        <?php fc_e( 'filters' ); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Preload all post meta & thumbnails in a single query
            if ( $products->have_posts() ) :
                $product_ids = wp_list_pluck( $products->posts, 'ID' );
                update_postmeta_cache( $product_ids );
                update_post_thumbnail_cache( $products );
            ?>
                <div class="fc-shop-toolbar">
                <p class="fc-products-count">
                    <?php
                    $n = $products->found_posts;
                    if ( $n === 1 ) {
                        $label = fc__( 'product_singular' );
                    } elseif ( $n % 10 >= 2 && $n % 10 <= 4 && ( $n % 100 < 10 || $n % 100 >= 20 ) ) {
                        $label = fc__( 'product_plural_few' );
                    } else {
                        $label = fc__( 'product_plural_many' );
                    }
                    /* translators: %1$s: count, %2$s: noun form */
                    printf( fc__( 'found_x_products' ), '<strong>' . $n . '</strong>', $label );
                    ?>
                </p>
                <?php
                $sort_options = array(
                    'newest'     => fc__( 'sort_newest' ),
                    'oldest'     => fc__( 'sort_oldest' ),
                    'price_asc'  => fc__( 'sort_price_asc' ),
                    'price_desc' => fc__( 'sort_price_desc' ),
                    'title_asc'  => fc__( 'sort_name_asc' ),
                    'title_desc' => fc__( 'sort_name_desc' ),
                );
                $current_sort = $fc_sort ?: 'newest';
                $current_label = $sort_options[ $current_sort ] ?? $sort_options['newest'];
                ?>
                <div class="fc-sort-wrap">
                    <div class="fc-sort-dropdown">
                        <button type="button" class="fc-sort-toggle">
                            <span class="dashicons dashicons-sort"></span>
                            <span class="fc-sort-label"><?php echo esc_html( $current_label ); ?></span>
                            <span class="fc-sort-arrow dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <ul class="fc-sort-list">
                            <?php foreach ( $sort_options as $val => $text ) : ?>
                                <li><a href="<?php echo esc_url( add_query_arg( array( 'fc_sort' => $val, 'paged' => false ) ) ); ?>" class="<?php echo $val === $current_sort ? 'active' : ''; ?>"><?php echo esc_html( $text ); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php if ( get_theme_mod( 'flavor_archive_view_toggle', true ) ) : ?>
                <div class="fc-view-toggles">
                    <button type="button" class="fc-view-toggle active" data-view="grid" title="<?php echo esc_attr( fc__( 'grid_view' ) ); ?>">
                        <span class="dashicons dashicons-grid-view"></span>
                    </button>
                    <button type="button" class="fc-view-toggle" data-view="list" title="<?php echo esc_attr( fc__( 'list_view' ) ); ?>">
                        <span class="dashicons dashicons-list-view"></span>
                    </button>
                </div>
                <?php endif; ?>
                </div><!-- .fc-shop-toolbar -->
                <div class="fc-products-grid fc-cols-<?php echo intval( $atts['columns'] ); ?>">
                    <?php while ( $products->have_posts() ) : $products->the_post(); ?>
                        <?php echo self::render_product_card( get_the_ID() ); ?>
                    <?php endwhile; ?>
                </div>

                <?php
                if ( $products->max_num_pages > 1 ) {
                    // Zachowaj wszystkie parametry filtrów w linkach stronicowania
                    $current_url = remove_query_arg( 'paged' );
                    echo '<div class="fc-pagination">';
                    echo paginate_links( array(
                        'base'    => $current_url . '%_%',
                        'format'  => ( strpos( $current_url, '?' ) !== false ? '&' : '?' ) . 'paged=%#%',
                        'total'   => $products->max_num_pages,
                        'current' => $paged,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ) );
                    echo '</div>';
                }
                ?>
            <?php else : ?>
                <p class="fc-no-products"><?php fc_e( 'no_products' ); ?></p>
            <?php endif; ?>
            </div><!-- .fc-shop-content -->
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Karta produktu
     */
    public static function render_product_card( $product_id ) {
        $price        = get_post_meta( $product_id, '_fc_price', true );
        $sale_price   = get_post_meta( $product_id, '_fc_sale_price', true );
        $sale_percent = get_post_meta( $product_id, '_fc_sale_percent', true );
        $stock_status = get_post_meta( $product_id, '_fc_stock_status', true );
        $product_type = get_post_meta( $product_id, '_fc_product_type', true ) ?: 'simple';
        $unit         = get_post_meta( $product_id, '_fc_unit', true ) ?: FC_Units_Admin::get_default();
        $is_variable  = ( $product_type === 'variable' );
        $has_sale      = ( $sale_price && floatval( $sale_price ) > 0 );

        $attributes     = array();
        $variants       = array();
        $active_variants = array();
        $badge_text      = '';
        if ( $is_variable ) {
            $variants = get_post_meta( $product_id, '_fc_variants', true );
            if ( ! is_array( $variants ) ) $variants = array();
            $active_variants = array_values( array_filter( $variants, function( $v ) { return ( $v['status'] ?? 'active' ) === 'active'; } ) );
            // Sprawdź czy choć jeden wariant ma cenę promocyjną + oblicz max procent
            $max_discount = 0;
            if ( ! empty( $active_variants ) ) {
                foreach ( $active_variants as $av ) {
                    $sp = $av['sale_price'] ?? '';
                    $vp = floatval( $av['price'] ?? 0 );
                    if ( $sp !== '' && floatval( $sp ) > 0 ) {
                        $has_sale = true;
                        // Oblicz procent rabatu
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
            }
            if ( $has_sale && $max_discount > 0 ) {
                $badge_text = sprintf( fc__( 'discount_badge' ), $max_discount );
            }
        } else {
            // Prosty produkt — badge z rabatem
            if ( $has_sale && floatval( $price ) > 0 ) {
                if ( $sale_percent ) {
                    $discount = floatval( $sale_percent );
                } else {
                    $discount = round( ( floatval( $price ) - floatval( $sale_price ) ) / floatval( $price ) * 100 );
                }
                if ( $discount > 0 ) {
                    $badge_text = sprintf( fc__( 'discount_badge' ), $discount );
                }
            }
        }
        if ( $has_sale && empty( $badge_text ) ) {
            $badge_text = fc__( 'sale_badge' );
        }

        // Preorder: zmień badge rabatowy na "Preorder -X%" lub sam "Preorder"
        $is_preorder = ( get_post_status( $product_id ) === 'fc_preorder' );
        if ( $is_preorder && $has_sale && ! empty( $badge_text ) ) {
            // Wyciągnij procent z badge_text i zamień "Rabat" na "Preorder"
            if ( preg_match( '/-(\d+)%/', $badge_text, $m ) ) {
                $badge_text = sprintf( fc__( 'preorder_discount_badge' ), $m[1] );
            } else {
                $badge_text = fc__( 'preorder_badge' );
            }
        }

        ob_start();
        ?>
        <div class="fc-product-card" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <div class="fc-product-image-wrap">
                <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-product-image fc-product-link" data-base-href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
                    <?php if ( has_post_thumbnail( $product_id ) ) : ?>
                        <?php echo get_the_post_thumbnail( $product_id, 'medium' ); ?>
                    <?php else : ?>
                        <div class="fc-no-image"><?php fc_e( 'no_image' ); ?></div>
                    <?php endif; ?>
                    <?php
                    // Odznaki produktu (N9) + badge rabatowy — w jednym kontenerze
                    $product_badges = get_post_meta( $product_id, '_fc_badges', true );
                    $has_product_badges = get_option( 'fc_enable_badges', '1' ) && is_array( $product_badges ) && ! empty( $product_badges );
                    $has_any_badge = $is_preorder || $has_sale || $has_product_badges;
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
                                <span class="fc-badge-sale fc-badge-inline"><?php echo $has_sale ? esc_html( $badge_text ) : esc_html( fc__( 'preorder_badge' ) ); ?></span>
                            <?php elseif ( $has_sale && $badge_text ) : ?>
                                <span class="fc-badge-sale fc-badge-inline"><?php echo esc_html( $badge_text ); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </a>


            </div>

            <div class="fc-card-actions">
                <?php
                // Serduszko wishlist (N5)
                if ( get_option( 'fc_enable_wishlist', '1' ) && class_exists( 'FC_Wishlist' ) ) {
                    FC_Wishlist::render_heart_button( $product_id );
                }
                // Quick View (N4)
                if ( class_exists( 'FC_Frontend_Features' ) ) {
                    if ( get_option( 'fc_enable_quick_view', '1' ) ) FC_Frontend_Features::render_quick_view_button( $product_id );
                    if ( get_option( 'fc_enable_compare', '1' ) ) FC_Frontend_Features::render_compare_button( $product_id );
                }
                ?>
            </div>

            <?php $card_brands = get_the_terms( $product_id, 'fc_product_brand' ); ?>
            <?php
                $review_count = FC_Reviews::get_review_count( $product_id );
                $avg_rating   = $review_count > 0 ? FC_Reviews::get_average_rating( $product_id ) : 0;
            ?>

            <div class="fc-product-info">
                <div class="fc-product-title-wrap" data-tooltip="<?php echo esc_attr( get_the_title( $product_id ) ); ?>">
                    <h3 class="fc-product-title">
                        <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-product-link" data-base-href="<?php echo esc_url( get_permalink( $product_id ) ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a>
                    </h3>
                </div>

                <div class="fc-product-meta-row">
                    <div class="fc-product-brand">
                        <?php if ( ! empty( $card_brands ) && ! is_wp_error( $card_brands ) ) :
                            $brand = $card_brands[0]; ?>
                            <a href="<?php echo esc_url( add_query_arg( 'fc_brand', $brand->slug, get_permalink( get_option( 'fc_page_sklep', 0 ) ) ?: site_url( '/sklep/' ) ) ); ?>"><?php echo esc_html( $brand->name ); ?></a>
                        <?php else : ?>
                            &nbsp;
                        <?php endif; ?>
                    </div>
                    <?php if ( $review_count > 0 ) : ?>
                        <div class="fc-product-rating">
                            <?php FC_Reviews::render_stars( $avg_rating ); ?>
                            <span class="fc-rating-count">(<?php echo number_format( $avg_rating, 1, ',', '' ); ?>/<?php echo $review_count; ?>)</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="fc-product-price">
                    <?php if ( $is_variable && ! empty( $active_variants ) ) : ?>
                        <?php
                        $var_prices = array_filter( array_column( $active_variants, 'price' ), function( $p ) { return $p !== ''; } );
                        $var_sale_prices = array_filter( array_column( $active_variants, 'sale_price' ), function( $p ) { return $p !== '' && floatval( $p ) > 0; } );

                        // Oblicz efektywne ceny (sale_price jeśli istnieje i mniejsza, inaczej price)
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
                        $min_reg = ! empty( $var_prices ) ? min( array_map( 'floatval', $var_prices ) ) : 0;
                        $max_reg = ! empty( $var_prices ) ? max( array_map( 'floatval', $var_prices ) ) : 0;
                        ?>
                        <?php if ( ! empty( $var_sale_prices ) ) : ?>
                            <?php if ( $min_eff == $max_eff ) : ?>
                                <span class="fc-price-sale"><?php echo fc_format_price( $min_eff, $product_id ); ?></span>
                            <?php else : ?>
                                <span class="fc-price-sale"><?php echo fc_format_price( $min_eff, $product_id ); ?> &ndash; <?php echo fc_format_price( $max_eff, $product_id ); ?></span>
                            <?php endif; ?>
                        <?php elseif ( $min_reg == $max_reg ) : ?>
                            <span><?php echo fc_format_price( $min_reg, $product_id ); ?></span>
                        <?php else : ?>
                            <span><?php echo fc_format_price( $min_reg, $product_id ); ?> &ndash; <?php echo fc_format_price( $max_reg, $product_id ); ?></span>
                        <?php endif; ?>
                    <?php elseif ( $sale_price && floatval( $sale_price ) > 0 ) : ?>
                        <del><?php echo fc_format_price( $price, $product_id ); ?></del>
                        <ins><?php echo fc_format_price( $sale_price, $product_id ); ?></ins>
                    <?php elseif ( $price ) : ?>
                        <span><?php echo fc_format_price( $price, $product_id ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $unit ) && FC_Units_Admin::is_visible( 'shop' ) ) : ?>
                        <span class="fc-price-unit">/ <?php echo esc_html( FC_Units_Admin::label( $unit ) ); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ( $stock_status !== 'outofstock' ) : ?>
                    <?php if ( $is_variable ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-btn fc-choose-options" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                            <?php fc_e( 'choose_variants' ); ?>
                        </a>
                    <?php else : ?>
                        <button class="fc-btn fc-add-to-cart" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                            <?php fc_e( 'add_to_cart' ); ?>
                        </button>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="fc-out-of-stock"><?php fc_e( 'unavailable' ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Publiczny proxy do renderowania karty produktu (dla szablonów)
     */
    public static function render_product_card_static( $product_id ) {
        echo self::render_product_card( $product_id );
    }

    /**
     * Renderowanie mini listy produktów (bestsellery, nowości, promocje)
     */
    private static function render_mini_product_list( $product_ids ) {
        $currency = get_option( 'fc_currency_symbol', 'zł' );
        // Preload meta for all mini-product IDs at once
        if ( ! empty( $product_ids ) ) {
            update_postmeta_cache( $product_ids );
        }
        $html = '<ul class="fc-sidebar-mini-products">';
        foreach ( $product_ids as $pid ) {
            $title     = get_the_title( $pid );
            $permalink = get_permalink( $pid );
            $thumb     = get_the_post_thumbnail_url( $pid, 'thumbnail' );
            $price     = floatval( get_post_meta( $pid, '_fc_price', true ) );
            $sale      = floatval( get_post_meta( $pid, '_fc_sale_price', true ) );
            $effective = $sale > 0 ? $sale : $price;

            $html .= '<li class="fc-sidebar-mini-product">';
            $html .= '<a href="' . esc_url( $permalink ) . '" class="fc-mini-product-link">';
            if ( $thumb ) {
                $html .= '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $title ) . '" class="fc-mini-product-img">';
            } else {
                $html .= '<span class="fc-mini-product-noimg dashicons dashicons-format-image"></span>';
            }
            $html .= '<span class="fc-mini-product-info">';
            $html .= '<span class="fc-mini-product-title">' . esc_html( $title ) . '</span>';
            if ( $sale > 0 ) {
                $html .= '<span class="fc-mini-product-price"><del>' . fc_format_price( $price ) . '</del> <ins>' . fc_format_price( $sale ) . '</ins></span>';
            } else {
                $html .= '<span class="fc-mini-product-price">' . fc_format_price( $price ) . '</span>';
            }
            $html .= '</span>';
            $html .= '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Renderowanie bloków sidebara na froncie
     */
    /**
     * Rekurencyjnie renderuje element kategorii z podkategoriami (dowolna głębokość)
     */
    private static function render_category_item( $term, $block, $current_cat, $shop_url, $has_filter, $filtered_ids, $cat_counts = array() ) {
        $active = $current_cat === $term->slug ? ' active' : '';
        $link   = esc_url( add_query_arg( 'fc_cat', $term->slug, $shop_url ) );

        if ( $has_filter && ! empty( $block['show_count'] ) && ! empty( $cat_counts ) ) {
            $cnt = $cat_counts[ $term->term_id ] ?? 0;
        } elseif ( ! $has_filter || empty( $block['show_count'] ) ) {
            $cnt = $term->count;
        } else {
            $cnt = 0;
        }
        $count_html = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $cnt . ')</span>' : '';

        $children = array();
        $has_children = false;
        if ( ! empty( $block['hierarchical'] ) ) {
            $children = get_terms( array( 'taxonomy' => 'fc_product_cat', 'hide_empty' => true, 'parent' => $term->term_id ) );
            if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
                $has_children = true;
            } else {
                $children = array();
            }
        }

        // Sprawdź czy aktywna kategoria jest w tej gałęzi (otwórz automatycznie)
        $branch_open = false;
        if ( $has_children && $current_cat ) {
            $branch_open = self::is_cat_in_branch( $term->term_id, $current_cat );
        }
        $is_open = $active || $branch_open;

        $html = '<li class="' . ( $has_children ? 'fc-has-children' : '' ) . ( $is_open ? ' fc-cat-open' : '' ) . '">';
        $html .= '<div class="fc-cat-row">';
        $html .= '<a href="' . $link . '" class="' . $active . '">' . esc_html( $term->name ) . $count_html . '</a>';
        if ( $has_children ) {
            $html .= '<button type="button" class="fc-cat-toggle" aria-label="' . esc_attr( fc__( 'expand' ) ) . '"><svg viewBox="0 0 24 24" width="16" height="16"><polyline points="6 9 12 15 18 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
        }
        $html .= '</div>';

        if ( $has_children ) {
            $html .= '<ul class="fc-sidebar-sublist"' . ( $is_open ? '' : ' style="display:none;"' ) . '>';
            foreach ( $children as $child ) {
                $html .= self::render_category_item( $child, $block, $current_cat, $shop_url, $has_filter, $filtered_ids, $cat_counts );
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }

    /**
     * Sprawdza czy kategoria o danym slug jest potomkiem danego term_id
     */
    private static function is_cat_in_branch( $parent_id, $cat_slug ) {
        $descendants = get_terms( array(
            'taxonomy'   => 'fc_product_cat',
            'hide_empty' => false,
            'child_of'   => $parent_id,
            'fields'     => 'id=>slug',
        ) );
        if ( is_wp_error( $descendants ) ) return false;
        return in_array( $cat_slug, $descendants, true );
    }

    private static function render_sidebar_blocks( $blocks, $filtered_ids = array() ) {
        $shop_base = fc_get_shop_url();
        // Zachowaj wszystkie aktywne filtry fc_* w linku bazowym
        $filter_params = array();
        foreach ( $_GET as $k => $v ) {
            if ( strpos( $k, 'fc_' ) === 0 && $v !== '' ) {
                $filter_params[ sanitize_key( $k ) ] = sanitize_text_field( $v );
            }
        }
        $shop_url = $filter_params ? add_query_arg( $filter_params, $shop_base ) : $shop_base;

        $output   = '';
        $has_filter = ! empty( $filtered_ids );

        foreach ( $blocks as $block ) {
            $type  = $block['type'] ?? '';
            $title = $block['title'] ?? '';

            $inner = '';
            switch ( $type ) {
                case 'categories':
                    $args = array( 'taxonomy' => 'fc_product_cat', 'hide_empty' => true );
                    if ( ! empty( $block['hierarchical'] ) ) {
                        $args['parent'] = 0;
                    }
                    $terms = get_terms( $args );
                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        $current_cat = isset( $_GET['fc_cat'] ) ? sanitize_text_field( $_GET['fc_cat'] ) : '';

                        // Precompute category counts in a single SQL query
                        $cat_counts = array();
                        if ( $has_filter && ! empty( $block['show_count'] ) && ! empty( $filtered_ids ) ) {
                            global $wpdb;
                            $ids_str = implode( ',', array_map( 'intval', $filtered_ids ) );
                            $cat_rows = $wpdb->get_results(
                                "SELECT tt.term_id, COUNT(DISTINCT tr.object_id) AS cnt
                                 FROM {$wpdb->term_relationships} tr
                                 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                 WHERE tt.taxonomy = 'fc_product_cat'
                                   AND tr.object_id IN ({$ids_str})
                                 GROUP BY tt.term_id"
                            );
                            foreach ( $cat_rows as $row ) {
                                $cat_counts[ (int) $row->term_id ] = (int) $row->cnt;
                            }
                        }

                        $inner .= '<ul class="fc-sidebar-list">';

                        // Liczba "Wszystkie" = suma przefiltrowanych
                        $all_count_str = $has_filter && ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . count( $filtered_ids ) . ')</span>' : '';
                        $inner .= '<li><a href="' . esc_url( remove_query_arg( 'fc_cat', $shop_url ) ) . '" class="' . ( empty( $current_cat ) ? 'active' : '' ) . '">' . fc__( 'all' ) . $all_count_str . '</a></li>';

                        foreach ( $terms as $term ) {
                            $inner .= self::render_category_item( $term, $block, $current_cat, $shop_url, $has_filter, $filtered_ids, $cat_counts );
                        }
                        $inner .= '</ul>';
                    }
                    break;

                case 'brands':
                    $terms = get_terms( array( 'taxonomy' => 'fc_product_brand', 'hide_empty' => true ) );
                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        $current_brand = isset( $_GET['fc_brand'] ) ? sanitize_text_field( $_GET['fc_brand'] ) : '';
                        $brand_style = $block['display_style'] ?? 'list';

                        // Oblicz liczby produktów dla każdej marki
                        $brand_counts = array();
                        if ( $has_filter && ! empty( $block['show_count'] ) && ! empty( $filtered_ids ) ) {
                            global $wpdb;
                            $ids_str = implode( ',', array_map( 'intval', $filtered_ids ) );
                            $brand_rows = $wpdb->get_results(
                                "SELECT tt.term_id, COUNT(DISTINCT tr.object_id) AS cnt
                                 FROM {$wpdb->term_relationships} tr
                                 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                                 WHERE tt.taxonomy = 'fc_product_brand'
                                   AND tr.object_id IN ({$ids_str})
                                 GROUP BY tt.term_id"
                            );
                            foreach ( $brand_rows as $row ) {
                                $brand_counts[ (int) $row->term_id ] = (int) $row->cnt;
                            }
                            foreach ( $terms as $term ) {
                                if ( ! isset( $brand_counts[ $term->term_id ] ) ) {
                                    $brand_counts[ $term->term_id ] = 0;
                                }
                            }
                        } else {
                            foreach ( $terms as $term ) {
                                $brand_counts[ $term->term_id ] = $term->count;
                            }
                        }

                        if ( $brand_style === 'tag_cloud' ) {
                            // Pastylki
                            $inner .= '<div class="fc-tag-cloud">';
                            $clear_url = esc_url( remove_query_arg( 'fc_brand', $shop_url ) );
                            $inner .= '<a href="' . $clear_url . '" class="fc-tag' . ( empty( $current_brand ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a>';
                            foreach ( $terms as $term ) {
                                $active = $current_brand === $term->slug ? ' active' : '';
                                $link   = esc_url( add_query_arg( 'fc_brand', $term->slug, $shop_url ) );
                                $cnt    = $brand_counts[ $term->term_id ];
                                $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $cnt . ')</span>' : '';
                                $inner .= '<a href="' . $link . '" class="fc-tag' . $active . '">' . esc_html( $term->name ) . $count . '</a>';
                            }
                            $inner .= '</div>';
                        } elseif ( $brand_style === 'logo' ) {
                            // Logo
                            $logo_size = intval( $block['logo_size'] ?? 60 ) ?: 60;
                            $logo_tint = $block['logo_tint'] ?? 'none';
                            $tint_class = ( $logo_tint && $logo_tint !== 'none' ) ? ' fc-logo-tint-' . esc_attr( $logo_tint ) : '';
                            $inner .= '<div class="fc-brand-logos' . $tint_class . '" style="--fc-logo-size:' . $logo_size . 'px;">';
                            $clear_url = esc_url( remove_query_arg( 'fc_brand', $shop_url ) );
                            $inner .= '<a href="' . $clear_url . '" class="fc-brand-logo-item' . ( empty( $current_brand ) ? ' active' : '' ) . '" title="' . esc_attr( fc__( 'all' ) ) . '"><span class="fc-brand-logo-all dashicons dashicons-screenoptions"></span></a>';
                            foreach ( $terms as $term ) {
                                $active   = $current_brand === $term->slug ? ' active' : '';
                                $link     = esc_url( add_query_arg( 'fc_brand', $term->slug, $shop_url ) );
                                $logo_id  = absint( get_term_meta( $term->term_id, '_fc_brand_logo', true ) );
                                $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
                                $inner .= '<a href="' . $link . '" class="fc-brand-logo-item' . $active . '" title="' . esc_attr( $term->name ) . '">';
                                if ( $logo_url && $logo_tint === 'accent' ) {
                                    $inner .= '<span class="fc-brand-logo-mask" style="-webkit-mask-image:url(' . esc_url( $logo_url ) . ');mask-image:url(' . esc_url( $logo_url ) . ')"></span>';
                                } elseif ( $logo_url ) {
                                    $inner .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $term->name ) . '">';
                                } else {
                                    $inner .= '<span class="fc-brand-logo-text">' . esc_html( $term->name ) . '</span>';
                                }
                                $inner .= '</a>';
                            }
                            $inner .= '</div>';
                        } else {
                            // Lista
                            $inner .= '<ul class="fc-sidebar-list">';
                            $all_count_str = $has_filter && ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . count( $filtered_ids ) . ')</span>' : '';
                            $inner .= '<li><a href="' . esc_url( remove_query_arg( 'fc_brand', $shop_url ) ) . '" class="' . ( empty( $current_brand ) ? 'active' : '' ) . '">' . fc__( 'all' ) . $all_count_str . '</a></li>';
                            foreach ( $terms as $term ) {
                                $active = $current_brand === $term->slug ? ' active' : '';
                                $link   = esc_url( add_query_arg( 'fc_brand', $term->slug, $shop_url ) );
                                $cnt    = $brand_counts[ $term->term_id ];
                                $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $cnt . ')</span>' : '';
                                $inner .= '<li><a href="' . $link . '" class="' . $active . '">' . esc_html( $term->name ) . $count . '</a></li>';
                            }
                            $inner .= '</ul>';
                        }
                    }
                    break;

                case 'attributes':
                    // Automatyczne odkrywanie wszystkich atrybutów w użyciu
                    $all_attrs = class_exists( 'FC_Attributes_Admin' ) ? FC_Attributes_Admin::get_all() : array();
                    if ( empty( $all_attrs ) ) break;

                    // Zbierz ID produktów do przeszukania
                    $attr_search_ids = $has_filter ? $filtered_ids : get_posts( array(
                        'post_type' => 'fc_product', 'posts_per_page' => -1,
                        'post_status' => array( 'fc_published', 'fc_preorder' ), 'fields' => 'ids',
                    ) );

                    // Preload meta for all attribute search IDs (single query instead of N)
                    if ( ! empty( $attr_search_ids ) ) {
                        update_postmeta_cache( $attr_search_ids );
                    }

                    // Zbierz dane atrybutów z produktów
                    $attrs_data = array(); // [attr_name => ['type' => ..., 'values' => [label => ['count' => N, 'data' => ...]]]]
                    foreach ( $attr_search_ids as $pid ) {
                        $prod_attrs = get_post_meta( $pid, '_fc_attributes', true );
                        if ( ! is_array( $prod_attrs ) ) continue;
                        foreach ( $prod_attrs as $a ) {
                            $a_name = $a['name'] ?? '';
                            if ( $a_name === '' ) continue;
                            if ( empty( $a['values'] ) || ! is_array( $a['values'] ) ) continue;
                            if ( ! isset( $attrs_data[ $a_name ] ) ) {
                                $attrs_data[ $a_name ] = array( 'type' => $a['type'] ?? 'text', 'values' => array() );
                            }
                            foreach ( $a['values'] as $v ) {
                                $val_label = is_array( $v ) ? ( $v['label'] ?? $v['value'] ?? '' ) : $v;
                                $val_label = trim( $val_label );
                                if ( $val_label === '' ) continue;
                                if ( ! isset( $attrs_data[ $a_name ]['values'][ $val_label ] ) ) {
                                    $attrs_data[ $a_name ]['values'][ $val_label ] = array( 'count' => 0, 'data' => $v );
                                }
                                $attrs_data[ $a_name ]['values'][ $val_label ]['count']++;
                            }
                        }
                    }

                    // Zachowaj kolejność z globalnych atrybutów
                    $tile_size = absint( $block['tile_size'] ?? 28 ) ?: 28;
                    foreach ( $all_attrs as $ga ) {
                        $a_name = $ga['name'];
                        if ( empty( $attrs_data[ $a_name ] ) || empty( $attrs_data[ $a_name ]['values'] ) ) continue;

                        $a_type = $attrs_data[ $a_name ]['type'];
                        $values_count = $attrs_data[ $a_name ]['values'];
                        $param_key = 'fc_attr_' . sanitize_title( $a_name );
                        $current_val = isset( $_GET[ $param_key ] ) ? sanitize_text_field( $_GET[ $param_key ] ) : '';

                        $attr_inner = '';

                        if ( $a_type === 'color' ) {
                            $color_display = $block['color_display'] ?? 'tiles';
                            $clear_url = esc_url( remove_query_arg( $param_key, $shop_url ) );

                            // Helper: swatch koloru inline
                            $color_swatch_fn = function( $info, $label ) {
                                if ( is_array( $info['data'] ) && ! empty( $info['data']['value'] ) ) {
                                    return '<span class="fc-attr-swatch fc-attr-swatch-color" style="background:' . esc_attr( $info['data']['value'] ) . ';"></span> ';
                                }
                                return '';
                            };

                            if ( $color_display === 'tiles' || $color_display === 'circles' ) {
                                // Kafelki / Koła
                                $shape_class = $color_display === 'circles' ? ' fc-attr-tiles-circle' : '';
                                $attr_inner .= '<div class="fc-attr-tiles' . $shape_class . '" style="--fc-tile-size:' . $tile_size . 'px;">';
                                $attr_inner .= '<a href="' . $clear_url . '" class="fc-attr-tile fc-attr-tile-all' . ( empty( $current_val ) ? ' active' : '' ) . '" data-tooltip="' . esc_attr( fc__( 'all' ) ) . '">';
                                $attr_inner .= '<span class="fc-attr-tile-x dashicons dashicons-screenoptions"></span>';
                                $attr_inner .= '</a>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $tip    = esc_attr( $label );
                                    if ( ! empty( $block['show_count'] ) ) $tip .= ' (' . $info['count'] . ')';
                                    $attr_inner .= '<a href="' . $link . '" class="fc-attr-tile' . $active . '" data-tooltip="' . $tip . '">';
                                    if ( is_array( $info['data'] ) && ! empty( $info['data']['value'] ) ) {
                                        $attr_inner .= '<span class="fc-attr-tile-color" style="background:' . esc_attr( $info['data']['value'] ) . ';"></span>';
                                    } else {
                                        $attr_inner .= '<span class="fc-attr-tile-label">' . esc_html( $label ) . '</span>';
                                    }
                                    $attr_inner .= '</a>';
                                }
                                $attr_inner .= '</div>';
                            } elseif ( $color_display === 'pills' ) {
                                // Pastylki kolorów
                                $attr_inner .= '<div class="fc-attr-pills">';
                                $attr_inner .= '<a href="' . $clear_url . '" class="fc-pill' . ( empty( $current_val ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $swatch = $color_swatch_fn( $info, $label );
                                    $attr_inner .= '<a href="' . $link . '" class="fc-pill' . $active . '">' . $swatch . esc_html( $label ) . $count . '</a>';
                                }
                                $attr_inner .= '</div>';
                            } elseif ( $color_display === 'dropdown' ) {
                                // Lista rozwijana kolorów
                                $current_label = $current_val ? $current_val : fc__( 'all' );
                                $attr_inner .= '<div class="fc-attr-dropdown">';
                                $attr_inner .= '<button type="button" class="fc-attr-dropdown-toggle" aria-expanded="false">';
                                $attr_inner .= '<span class="fc-attr-dropdown-label">' . esc_html( $current_label ) . '</span>';
                                $attr_inner .= '<span class="fc-attr-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>';
                                $attr_inner .= '</button>';
                                $attr_inner .= '<ul class="fc-attr-dropdown-list">';
                                $attr_inner .= '<li><a href="' . $clear_url . '" class="fc-attr-dropdown-item' . ( empty( $current_val ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $swatch = $color_swatch_fn( $info, $label );
                                    $attr_inner .= '<li><a href="' . $link . '" class="fc-attr-dropdown-item' . $active . '">' . $swatch . esc_html( $label ) . $count . '</a></li>';
                                }
                                $attr_inner .= '</ul></div>';
                            } else {
                                // Lista kolorów
                                $attr_inner .= '<ul class="fc-sidebar-list fc-attr-list fc-attr-type-color">';
                                $attr_inner .= '<li><a href="' . $clear_url . '" class="' . ( empty( $current_val ) ? 'active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $swatch = $color_swatch_fn( $info, $label );
                                    $attr_inner .= '<li><a href="' . $link . '" class="' . $active . '">' . $swatch . esc_html( $label ) . $count . '</a></li>';
                                }
                                $attr_inner .= '</ul>';
                            }
                        } elseif ( $a_type === 'image' ) {
                            // Styl wyświetlania dla atrybutów obrazkowych
                            $image_display = $block['image_display'] ?? 'list';
                            $clear_url = esc_url( remove_query_arg( $param_key, $shop_url ) );
                            $tile_size = intval( $block['image_tile_size'] ?? 48 );

                            // Helper: get thumbnail URL for image attr value
                            $img_thumb_fn = function( $info ) {
                                if ( ! is_array( $info['data'] ) ) return '';
                                // Image attributes store id + url, not value
                                $att_id = 0;
                                if ( ! empty( $info['data']['id'] ) ) {
                                    $att_id = intval( $info['data']['id'] );
                                } elseif ( ! empty( $info['data']['value'] ) ) {
                                    $att_id = intval( $info['data']['value'] );
                                }
                                if ( $att_id > 0 ) {
                                    return wp_get_attachment_image_url( $att_id, 'thumbnail' );
                                }
                                // Fallback: cached url from admin
                                if ( ! empty( $info['data']['url'] ) ) {
                                    return $info['data']['url'];
                                }
                                return '';
                            };

                            if ( $image_display === 'tiles' || $image_display === 'circles' ) {
                                $shape_class = ( $image_display === 'circles' ) ? ' fc-attr-img-circle' : '';
                                $attr_inner .= '<div class="fc-attr-img-tiles' . $shape_class . '">';
                                $attr_inner .= '<a href="' . $clear_url . '" class="fc-attr-img-tile fc-attr-img-tile-all' . ( empty( $current_val ) ? ' active' : '' ) . '" data-tooltip="' . esc_attr( fc__( 'all' ) ) . '" style="width:' . $tile_size . 'px;height:' . $tile_size . 'px;">';
                                $attr_inner .= '<span class="fc-attr-tile-x dashicons dashicons-screenoptions"></span>';
                                $attr_inner .= '</a>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $thumb  = $img_thumb_fn( $info );
                                    $tip    = esc_attr( $label );
                                    if ( ! empty( $block['show_count'] ) ) $tip .= ' (' . $info['count'] . ')';
                                    $attr_inner .= '<a href="' . $link . '" class="fc-attr-img-tile' . $active . '" data-tooltip="' . $tip . '" style="width:' . $tile_size . 'px;height:' . $tile_size . 'px;">';
                                    if ( $thumb ) {
                                        $attr_inner .= '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $label ) . '">';
                                    } else {
                                        $attr_inner .= '<span class="fc-attr-img-tile-fallback">' . esc_html( mb_substr( $label, 0, 2 ) ) . '</span>';
                                    }
                                    $attr_inner .= '</a>';
                                }
                                $attr_inner .= '</div>';
                            } elseif ( $image_display === 'pills' ) {
                                $attr_inner .= '<div class="fc-attr-pills">';
                                $attr_inner .= '<a href="' . $clear_url . '" class="fc-pill' . ( empty( $current_val ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $thumb  = $img_thumb_fn( $info );
                                    $swatch = $thumb ? '<img class="fc-attr-swatch fc-attr-swatch-img" src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $label ) . '"> ' : '';
                                    $attr_inner .= '<a href="' . $link . '" class="fc-pill' . $active . '">' . $swatch . esc_html( $label ) . $count . '</a>';
                                }
                                $attr_inner .= '</div>';
                            } elseif ( $image_display === 'dropdown' ) {
                                $current_label = $current_val ? $current_val : fc__( 'all' );
                                $attr_inner .= '<div class="fc-attr-dropdown">';
                                $attr_inner .= '<button type="button" class="fc-attr-dropdown-toggle" aria-expanded="false">';
                                $attr_inner .= '<span class="fc-attr-dropdown-label">' . esc_html( $current_label ) . '</span>';
                                $attr_inner .= '<span class="fc-attr-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>';
                                $attr_inner .= '</button>';
                                $attr_inner .= '<ul class="fc-attr-dropdown-list">';
                                $attr_inner .= '<li><a href="' . $clear_url . '" class="fc-attr-dropdown-item' . ( empty( $current_val ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $thumb  = $img_thumb_fn( $info );
                                    $swatch = $thumb ? '<img class="fc-attr-swatch fc-attr-swatch-img" src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $label ) . '"> ' : '';
                                    $attr_inner .= '<li><a href="' . $link . '" class="fc-attr-dropdown-item' . $active . '">' . $swatch . esc_html( $label ) . $count . '</a></li>';
                                }
                                $attr_inner .= '</ul></div>';
                            } else {
                                // Lista (domyślna)
                                $attr_inner .= '<ul class="fc-sidebar-list fc-attr-list fc-attr-type-image">';
                                $attr_inner .= '<li><a href="' . $clear_url . '" class="' . ( empty( $current_val ) ? 'active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $thumb  = $img_thumb_fn( $info );
                                    $swatch = $thumb ? '<img class="fc-attr-swatch fc-attr-swatch-img" src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $label ) . '"> ' : '';
                                    $attr_inner .= '<li><a href="' . $link . '" class="' . $active . '">' . $swatch . esc_html( $label ) . $count . '</a></li>';
                                }
                                $attr_inner .= '</ul>';
                            }
                        } else {
                            // Styl wyświetlania dla tekstu
                            $text_display = $block['text_display'] ?? 'list';
                            $clear_url = esc_url( remove_query_arg( $param_key, $shop_url ) );

                            if ( $text_display === 'pills' ) {
                                // Pastylki
                                $attr_inner .= '<div class="fc-attr-pills">';
                                $attr_inner .= '<a href="' . $clear_url . '" class="fc-pill' . ( empty( $current_val ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $attr_inner .= '<a href="' . $link . '" class="fc-pill' . $active . '">' . esc_html( $label ) . $count . '</a>';
                                }
                                $attr_inner .= '</div>';
                            } elseif ( $text_display === 'dropdown' ) {
                                // Lista rozwijana
                                $current_label = $current_val ? $current_val : fc__( 'all' );
                                $attr_inner .= '<div class="fc-attr-dropdown">';
                                $attr_inner .= '<button type="button" class="fc-attr-dropdown-toggle" aria-expanded="false">';
                                $attr_inner .= '<span class="fc-attr-dropdown-label">' . esc_html( $current_label ) . '</span>';
                                $attr_inner .= '<span class="fc-attr-dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>';
                                $attr_inner .= '</button>';
                                $attr_inner .= '<ul class="fc-attr-dropdown-list">';
                                $attr_inner .= '<li><a href="' . $clear_url . '" class="fc-attr-dropdown-item' . ( empty( $current_val ) ? ' active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $attr_inner .= '<li><a href="' . $link . '" class="fc-attr-dropdown-item' . $active . '">' . esc_html( $label ) . $count . '</a></li>';
                                }
                                $attr_inner .= '</ul></div>';
                            } else {
                                // Lista (domyślna)
                                $attr_inner .= '<ul class="fc-sidebar-list fc-attr-list fc-attr-type-text">';
                                $attr_inner .= '<li><a href="' . $clear_url . '" class="' . ( empty( $current_val ) ? 'active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                                foreach ( $values_count as $label => $info ) {
                                    $active = ( $current_val === $label ) ? ' active' : '';
                                    $link   = esc_url( add_query_arg( $param_key, urlencode( $label ), $shop_url ) );
                                    $count  = ! empty( $block['show_count'] ) ? ' <span class="fc-sidebar-count">(' . $info['count'] . ')</span>' : '';
                                    $attr_inner .= '<li><a href="' . $link . '" class="' . $active . '">' . esc_html( $label ) . $count . '</a></li>';
                                }
                                $attr_inner .= '</ul>';
                            }
                        }

                        // Owijka widgetu dla każdego atrybutu
                        $output .= '<div class="fc-sidebar-widget fc-sidebar-widget-attributes">';
                        $output .= '<h3 class="fc-sidebar-widget-title">' . esc_html( $a_name ) . '</h3>';
                        $output .= $attr_inner;
                        $output .= '</div>';
                    }
                    // Nie generuj domyślnego wrappera — już dodano powyżej
                    $inner = '';
                    break;

                case 'price_filter':
                    $step  = absint( $block['step'] ?? 10 ) ?: 10;
                    $style = $block['style'] ?? 'inputs';
                    $min_val = isset( $_GET['fc_min_price'] ) ? floatval( $_GET['fc_min_price'] ) : '';
                    $max_val = isset( $_GET['fc_max_price'] ) ? floatval( $_GET['fc_max_price'] ) : '';

                    // Zakres cen: z przefiltrowanych produktów lub z całej bazy
                    global $wpdb;
                    if ( $has_filter && ! empty( $filtered_ids ) ) {
                        $ids_str = implode( ',', array_map( 'intval', $filtered_ids ) );
                        $price_range = $wpdb->get_row(
                            "SELECT MIN( CAST(meta_value AS DECIMAL(10,2)) ) AS min_price,
                                    MAX( CAST(meta_value AS DECIMAL(10,2)) ) AS max_price
                             FROM {$wpdb->postmeta}
                             WHERE meta_key = '_fc_effective_price'
                               AND post_id IN ({$ids_str})
                               AND meta_value != ''"
                        );
                    } else {
                        $price_range = $wpdb->get_row(
                            "SELECT MIN( CAST(meta_value AS DECIMAL(10,2)) ) AS min_price,
                                    MAX( CAST(meta_value AS DECIMAL(10,2)) ) AS max_price
                             FROM {$wpdb->postmeta} pm
                             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                             WHERE pm.meta_key = '_fc_effective_price'
                               AND p.post_type = 'fc_product'
                               AND p.post_status IN ('fc_published','fc_preorder')
                               AND pm.meta_value != ''"
                        );
                    }
                    $abs_min = $price_range ? floor( floatval( $price_range->min_price ) ) : 0;
                    $abs_max = $price_range ? ceil( floatval( $price_range->max_price ) )  : 1000;
                    if ( $abs_min === $abs_max ) { $abs_max = $abs_min + $step; }
                    $cur_min = $min_val !== '' ? $min_val : $abs_min;
                    $cur_max = $max_val !== '' ? $max_val : $abs_max;

                    $inner .= '<form class="fc-price-filter-form" method="get" action="' . esc_url( $shop_base ) . '">';
                    foreach ( $_GET as $k => $v ) {
                        if ( in_array( $k, array( 'fc_min_price', 'fc_max_price' ), true ) ) continue;
                        $inner .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
                    }

                    if ( $style === 'slider' ) {
                        $currency = get_option( 'fc_currency_symbol', 'zł' );
                        $inner .= '<div class="fc-price-slider-wrap" data-min="' . esc_attr( $abs_min ) . '" data-max="' . esc_attr( $abs_max ) . '" data-step="' . esc_attr( $step ) . '">';
                        $inner .= '<div class="fc-price-slider-labels"><span class="fc-price-slider-val-min">' . esc_html( $currency ) . ' ' . esc_html( $cur_min ) . '</span><span class="fc-price-slider-val-max">' . esc_html( $currency ) . ' ' . esc_html( $cur_max ) . '</span></div>';
                        $inner .= '<div class="fc-price-slider-track"><div class="fc-price-slider-range"></div>';
                        $inner .= '<input type="range" name="fc_min_price" class="fc-range-min" min="' . esc_attr( $abs_min ) . '" max="' . esc_attr( $abs_max ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $cur_min ) . '">';
                        $inner .= '<input type="range" name="fc_max_price" class="fc-range-max" min="' . esc_attr( $abs_min ) . '" max="' . esc_attr( $abs_max ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $cur_max ) . '">';
                        $inner .= '</div></div>';
                    } else {
                        $inner .= '<div class="fc-price-filter-inputs">';
                        $inner .= '<input type="number" name="fc_min_price" placeholder="' . esc_attr( fc__( 'min' ) ) . '" value="' . esc_attr( $min_val ) . '" min="0" step="' . $step . '">';
                        $inner .= '<span class="fc-price-filter-sep">&ndash;</span>';
                        $inner .= '<input type="number" name="fc_max_price" placeholder="' . esc_attr( fc__( 'max' ) ) . '" value="' . esc_attr( $max_val ) . '" min="0" step="' . $step . '">';
                        $inner .= '</div>';
                    }

                    $inner .= '<div class="fc-price-filter-actions">';
                    $inner .= '<button type="submit" class="fc-btn fc-btn-sm">' . fc__( 'filter_apply' ) . '</button>';
                    if ( $min_val !== '' || $max_val !== '' ) {
                        $inner .= '<a href="' . esc_url( remove_query_arg( array( 'fc_min_price', 'fc_max_price' ), $shop_url ) ) . '" class="fc-btn fc-btn-sm fc-price-filter-reset">' . fc__( 'clear' ) . '</a>';
                    }
                    $inner .= '</div>';
                    $inner .= '</form>';
                    break;

                case 'custom_html':
                    $inner = wp_kses_post( $block['content'] ?? '' );
                    break;

                case 'rating_filter':
                    $current_rating = isset( $_GET['fc_min_rating'] ) ? intval( $_GET['fc_min_rating'] ) : 0;
                    $inner .= '<ul class="fc-sidebar-list fc-rating-list">';
                    $inner .= '<li><a href="' . esc_url( remove_query_arg( 'fc_min_rating', $shop_url ) ) . '" class="' . ( $current_rating === 0 ? 'active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                    for ( $r = 5; $r >= 1; $r-- ) {
                        $active = ( $current_rating === $r ) ? ' active' : '';
                        $link   = esc_url( add_query_arg( 'fc_min_rating', $r, $shop_url ) );
                        $stars  = str_repeat( '<span class="dashicons dashicons-star-filled fc-star-on"></span>', $r );
                        $stars .= str_repeat( '<span class="dashicons dashicons-star-empty fc-star-off"></span>', 5 - $r );
                        $label  = $r < 5 ? ' ' . fc__( 'and_more' ) : '';
                        $inner .= '<li><a href="' . $link . '" class="' . $active . '">' . $stars . $label . '</a></li>';
                    }
                    $inner .= '</ul>';
                    break;

                case 'availability':
                    $current_avail = isset( $_GET['fc_availability'] ) ? sanitize_text_field( $_GET['fc_availability'] ) : '';
                    $inner .= '<ul class="fc-sidebar-list">';
                    $inner .= '<li><a href="' . esc_url( remove_query_arg( 'fc_availability', $shop_url ) ) . '" class="' . ( empty( $current_avail ) ? 'active' : '' ) . '">' . fc__( 'all' ) . '</a></li>';
                    $inner .= '<li><a href="' . esc_url( add_query_arg( 'fc_availability', 'instock', $shop_url ) ) . '" class="' . ( $current_avail === 'instock' ? 'active' : '' ) . '">' . fc__( 'available' ) . '</a></li>';
                    $inner .= '<li><a href="' . esc_url( add_query_arg( 'fc_availability', 'outofstock', $shop_url ) ) . '" class="' . ( $current_avail === 'outofstock' ? 'active' : '' ) . '">' . fc__( 'unavailable_filter' ) . '</a></li>';
                    $inner .= '</ul>';
                    break;

                case 'search':
                    $search_val = isset( $_GET['fc_search'] ) ? sanitize_text_field( $_GET['fc_search'] ) : '';
                    $inner .= '<form class="fc-sidebar-search-form" method="get" action="' . esc_url( $shop_base ) . '">';
                    foreach ( $_GET as $k => $v ) {
                        if ( $k === 'fc_search' ) continue;
                        $inner .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
                    }
                    $inner .= '<div class="fc-sidebar-search-wrap">';
                    $inner .= '<input type="text" name="fc_search" value="' . esc_attr( $search_val ) . '" placeholder="' . esc_attr( fc__( 'search_products_placeholder' ) ) . '" class="fc-sidebar-search-input" autocomplete="off">';
                    $inner .= '<button type="submit" class="fc-sidebar-search-btn"><span class="dashicons dashicons-search"></span></button>';
                    $inner .= '</div>';
                    $inner .= '</form>';
                    break;

                case 'bestsellers':
                    $limit = absint( $block['limit'] ?? 5 ) ?: 5;
                    $cache_key = 'fc_bestsellers_' . $limit;
                    $top_ids = get_transient( $cache_key );
                    if ( false === $top_ids ) {
                        global $wpdb;
                        $order_ids = $wpdb->get_col(
                            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'fc_order' AND post_status = 'publish'"
                        );
                        if ( ! empty( $order_ids ) ) {
                            update_postmeta_cache( $order_ids );
                        }
                        $sales = array();
                        foreach ( $order_ids as $oid ) {
                            $items = get_post_meta( $oid, '_fc_order_items', true );
                            if ( ! is_array( $items ) ) continue;
                            foreach ( $items as $item ) {
                                $pid = intval( $item['product_id'] ?? 0 );
                                if ( ! $pid ) continue;
                                $qty = intval( $item['quantity'] ?? 1 );
                                $sales[ $pid ] = ( $sales[ $pid ] ?? 0 ) + $qty;
                            }
                        }
                        arsort( $sales );
                        $top_ids = array_slice( array_keys( $sales ), 0, $limit, true );
                        set_transient( $cache_key, $top_ids, HOUR_IN_SECONDS );
                    }
                    if ( ! empty( $top_ids ) ) {
                        update_postmeta_cache( $top_ids );
                        $inner .= self::render_mini_product_list( $top_ids );
                    }
                    break;

                case 'new_products':
                    $limit = absint( $block['limit'] ?? 5 ) ?: 5;
                    $new_ids = get_posts( array(
                        'post_type'      => 'fc_product',
                        'post_status'    => array( 'fc_published', 'fc_preorder' ),
                        'posts_per_page' => $limit,
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                        'fields'         => 'ids',
                    ) );
                    if ( ! empty( $new_ids ) ) {
                        $inner .= self::render_mini_product_list( $new_ids );
                    }
                    break;

                case 'on_sale':
                    $limit = absint( $block['limit'] ?? 5 ) ?: 5;
                    $sale_ids = get_posts( array(
                        'post_type'      => 'fc_product',
                        'post_status'    => array( 'fc_published', 'fc_preorder' ),
                        'posts_per_page' => $limit,
                        'meta_query'     => array(
                            array(
                                'key'     => '_fc_sale_price',
                                'value'   => '0',
                                'compare' => '>',
                                'type'    => 'DECIMAL(10,2)',
                            ),
                        ),
                        'fields' => 'ids',
                    ) );
                    if ( ! empty( $sale_ids ) ) {
                        $inner .= self::render_mini_product_list( $sale_ids );
                    }
                    break;

                case 'cta_banner':
                    $bg       = esc_attr( $block['bg_color'] ?? '#2271b1' );
                    $img_url  = $block['image_url'] ?? '';
                    $text     = $block['text'] ?? '';
                    $btn_text = $block['button_text'] ?? '';
                    $btn_url  = $block['button_url'] ?? '';
                    $inner .= '<div class="fc-cta-banner" style="background-color:' . $bg . ';">';
                    if ( $img_url ) {
                        $inner .= '<img src="' . esc_url( $img_url ) . '" alt="" class="fc-cta-banner-img">';
                    }
                    if ( $text ) {
                        $inner .= '<p class="fc-cta-banner-text">' . esc_html( $text ) . '</p>';
                    }
                    if ( $btn_text && $btn_url ) {
                        $inner .= '<a href="' . esc_url( $btn_url ) . '" class="fc-btn fc-cta-banner-btn">' . esc_html( $btn_text ) . '</a>';
                    }
                    $inner .= '</div>';
                    break;
            }

            if ( empty( $inner ) && $type !== 'custom_html' ) continue;

            $output .= '<div class="fc-sidebar-widget fc-sidebar-widget-' . esc_attr( $type ) . '">';
            if ( $title ) {
                $output .= '<h3 class="fc-sidebar-widget-title">' . esc_html( $title ) . '</h3>';
            }
            $output .= $inner;
            $output .= '</div>';
        }

        return $output;
    }

    /**
     * [fc_cart] — strona koszyka
     */
    public static function cart( $atts ) {
        $cart = FC_Cart::get_cart();

        ob_start();
        echo '<!--nocache-->';
        ?>
        <div class="fc-cart-page">
            <?php if ( empty( $cart ) ) : ?>
                <div class="fc-empty-cart">
                    <p><?php fc_e( 'cart_empty' ); ?></p>
                    <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'go_to_shop' ); ?></a>
                </div>
            <?php else : ?>
                <table class="fc-cart-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php fc_e( 'product' ); ?></th>
                            <th><?php fc_e( 'price' ); ?></th>
                            <th><?php fc_e( 'quantity' ); ?></th>
                            <th><?php fc_e( 'total' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $cart as $cart_key => $item ) :
                            $variant_id = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
                            $price = FC_Cart::get_product_price( $item['product_id'], $variant_id );
                            $line_total = $price * $item['quantity'];
                            $variant_name = '';
                            $variant_attrs = array();
                            $cart_thumb_html = '';
                            if ( $variant_id !== '' ) {
                                $variants = get_post_meta( $item['product_id'], '_fc_variants', true );
                                $found_v = FC_Cart::find_variant( $variants, $variant_id );
                                if ( $found_v ) {
                                    $variant_name = $found_v['name'];
                                    $variant_attrs = isset( $found_v['attribute_values'] ) && is_array( $found_v['attribute_values'] ) ? $found_v['attribute_values'] : array();
                                    $v_main_img = 0;
                                    if ( ! empty( $found_v['main_image'] ) ) {
                                        $v_main_img = intval( $found_v['main_image'] );
                                    } elseif ( ! empty( $found_v['images'] ) && is_array( $found_v['images'] ) ) {
                                        $v_main_img = intval( $found_v['images'][0] );
                                    }
                                    if ( $v_main_img > 0 ) {
                                        $cart_thumb_html = wp_get_attachment_image( $v_main_img, 'thumbnail' );
                                    }
                                }
                            }
                            if ( empty( $cart_thumb_html ) ) {
                                $cart_thumb_html = get_the_post_thumbnail( $item['product_id'], 'thumbnail' );
                            }
                        ?>
                            <tr class="fc-cart-row" data-product-id="<?php echo esc_attr( $cart_key ); ?>">
                                <td class="fc-cart-thumb">
                                    <?php echo $cart_thumb_html; ?>
                                </td>
                                <td class="fc-cart-product-name">
                                    <a href="<?php echo esc_url( get_permalink( $item['product_id'] ) ); ?>">
                                        <?php echo esc_html( get_the_title( $item['product_id'] ) ); ?>
                                    </a>
                                    <?php if ( ! empty( $variant_attrs ) ) : ?>
                                        <br><small class="fc-cart-variant-label"><?php
                                            $vp = array();
                                            foreach ( $variant_attrs as $an => $av ) {
                                                $vp[] = esc_html( $an ) . ': ' . esc_html( $av );
                                            }
                                            echo implode( ', ', $vp );
                                        ?></small>
                                    <?php elseif ( $variant_name ) : ?>
                                        <br><small class="fc-cart-variant-label"><?php echo esc_html( $variant_name ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="fc-cart-price"><?php echo fc_format_price( $price ); ?><?php if ( FC_Units_Admin::is_visible( 'cart' ) ) : ?><span class="fc-price-unit">/ <?php echo esc_html( FC_Units_Admin::label( get_post_meta( $item['product_id'], '_fc_unit', true ) ?: FC_Units_Admin::get_default() ) ); ?></span><?php endif; ?></td>
                                <td class="fc-cart-qty">
                                    <input type="number" class="fc-qty-input" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" max="99" data-product-id="<?php echo esc_attr( $cart_key ); ?>">
                                </td>
                                <td class="fc-cart-line-total"><?php echo fc_format_price( $line_total ); ?></td>
                                <td class="fc-cart-remove">
                                    <button class="fc-remove-item" data-product-id="<?php echo esc_attr( $cart_key ); ?>" title="<?php echo esc_attr( fc__( 'remove' ) ); ?>">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <?php
                        $session_coupons = class_exists( 'FC_Coupons' ) ? FC_Coupons::get_session_coupons() : array();
                        $total_discount  = 0;
                        foreach ( $session_coupons as $sc ) { $total_discount += floatval( $sc['discount'] ); }
                        ?>
                        <?php if ( get_option( 'fc_enable_coupons', '1' ) ) : ?>
                        <tr class="fc-cart-coupon-row">
                            <td colspan="6">
                                <div class="fc-cart-coupon">
                                    <div class="fc-coupon-form">
                                        <input type="text" id="fc-coupon-code" placeholder="<?php echo esc_attr( fc__( 'coupon_code' ) ); ?>" class="fc-coupon-input">
                                        <button type="button" id="fc-apply-coupon" class="fc-btn fc-btn-outline"><?php fc_e( 'apply_coupon' ); ?></button>
                                    </div>
                                    <div id="fc-coupon-message" class="fc-coupon-message"></div>
                                    <?php if ( ! empty( $session_coupons ) ) : ?>
                                    <div class="fc-coupon-applied" id="fc-coupon-applied">
                                        <?php foreach ( $session_coupons as $sc ) : ?>
                                        <span class="fc-coupon-badge" data-coupon-id="<?php echo esc_attr( $sc['coupon_id'] ); ?>">
                                            <code><?php echo esc_html( $sc['code'] ); ?></code>
                                            −<?php echo fc_format_price( $sc['discount'] ); ?>
                                            <button type="button" class="fc-remove-coupon" data-coupon-id="<?php echo esc_attr( $sc['coupon_id'] ); ?>">&times;</button>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="fc-cart-summary-row">
                            <td colspan="6">
                                <div class="fc-cart-summary">
                                    <?php if ( $total_discount > 0 ) : ?>
                                    <div class="fc-cart-summary-totals">
                                        <div class="fc-cart-subtotal">
                                            <span><?php fc_e( 'subtotal' ); ?></span>
                                            <span><?php echo fc_format_price( FC_Cart::get_total() ); ?></span>
                                        </div>
                                        <div class="fc-cart-discount">
                                            <span><?php fc_e( 'discount' ); ?></span>
                                            <span>−<?php echo fc_format_price( $total_discount ); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="fc-cart-total">
                                        <span><?php fc_e( 'total_label' ); ?></span>
                                        <strong class="fc-total-amount"><?php
                                            $cart_final = FC_Cart::get_total() - $total_discount;
                                            echo fc_format_price( max( 0, $cart_final ) );
                                        ?></strong>
                                    </div>
                                    <div class="fc-cart-actions">
                                        <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn fc-btn-outline"><?php fc_e( 'continue_shopping' ); ?></a>
                                        <a href="<?php echo esc_url( fc_get_checkout_url() ); ?>" class="fc-btn"><?php fc_e( 'place_order_short' ); ?></a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <script>
                (function(){
                    var applyBtn = document.getElementById('fc-apply-coupon');
                    var codeInput = document.getElementById('fc-coupon-code');
                    var msgBox = document.getElementById('fc-coupon-message');

                    if (applyBtn) {
                        applyBtn.addEventListener('click', function(){
                            var code = codeInput.value.trim();
                            if (!code) { codeInput.focus(); return; }
                            applyBtn.disabled = true;
                            applyBtn.textContent = '...';
                            var fd = new FormData();
                            fd.append('action', 'fc_apply_coupon');
                            fd.append('nonce', fc_ajax.nonce);
                            fd.append('coupon_code', code);
                            fetch(fc_ajax.url, { method:'POST', body: fd })
                                .then(function(r){ return r.json(); })
                                .then(function(res){
                                    msgBox.classList.add('fc-coupon-message-visible');
                                    if (res.success) {
                                        msgBox.classList.remove('fc-coupon-message-error');
                                        msgBox.classList.add('fc-coupon-message-success');
                                        msgBox.textContent = res.data.message;
                                        setTimeout(function(){ location.reload(); }, 800);
                                    } else {
                                        msgBox.classList.remove('fc-coupon-message-success');
                                        msgBox.classList.add('fc-coupon-message-error');
                                        msgBox.textContent = res.data;
                                        applyBtn.disabled = false;
                                        applyBtn.textContent = '<?php echo esc_js( fc__( 'apply_coupon' ) ); ?>';
                                    }
                                });
                        });
                        codeInput.addEventListener('keypress', function(e){ if(e.key==='Enter'){ e.preventDefault(); applyBtn.click(); } });
                    }
                    document.querySelectorAll('.fc-remove-coupon').forEach(function(btn){
                        btn.addEventListener('click', function(){
                            var couponId = this.getAttribute('data-coupon-id');
                            var fd = new FormData();
                            fd.append('action', 'fc_remove_coupon');
                            fd.append('nonce', fc_ajax.nonce);
                            fd.append('coupon_id', couponId);
                            fetch(fc_ajax.url, { method:'POST', body: fd })
                                .then(function(){ location.reload(); });
                        });
                    });
                })();
                </script>

                <?php
                // Cross-sell — zbierz ID z produktów w koszyku
                $crosssell_all = array();
                $cart_product_ids = array();
                foreach ( $cart as $ci ) {
                    $cart_product_ids[] = intval( $ci['product_id'] );
                    $cs = get_post_meta( $ci['product_id'], '_fc_crosssell_ids', true );
                    if ( is_array( $cs ) ) {
                        $crosssell_all = array_merge( $crosssell_all, $cs );
                    }
                }
                // Usuń duplikaty i produkty już w koszyku
                $crosssell_all = array_unique( array_map( 'intval', $crosssell_all ) );
                $crosssell_all = array_diff( $crosssell_all, $cart_product_ids );

                if ( ! empty( $crosssell_all ) ) :
                    $cs_products = get_posts( array(
                        'post_type'      => 'fc_product',
                        'post__in'       => $crosssell_all,
                        'post_status'    => 'fc_published',
                        'posts_per_page' => 4,
                        'orderby'        => 'post__in',
                    ) );
                    if ( ! empty( $cs_products ) ) : ?>
                        <div class="fc-crosssell-section">
                            <h3><?php fc_e( 'crosssell_title' ); ?></h3>
                            <div class="fc-products-grid fc-cols-4">
                                <?php foreach ( $cs_products as $csp ) :
                                    self::render_product_card_static( $csp->ID );
                                endforeach; ?>
                            </div>
                        </div>
                    <?php endif;
                endif;
                ?>

            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [fc_checkout] — formularz zamówienia
     */
    public static function checkout( $atts ) {
        if ( FC_Cart::is_empty() ) {
            ob_start();
            echo '<!--nocache-->';
            ?>
            <div class="fc-empty-cart">
                <p><?php fc_e( 'cart_empty_checkout' ); ?></p>
                <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'go_to_shop' ); ?></a>
            </div>
            <?php
            return ob_get_clean();
        }

        $errors = isset( $_SESSION['fc_checkout_errors'] ) ? $_SESSION['fc_checkout_errors'] : array();
        $data   = isset( $_SESSION['fc_checkout_data'] ) ? $_SESSION['fc_checkout_data'] : array();
        unset( $_SESSION['fc_checkout_errors'], $_SESSION['fc_checkout_data'] );

        // Pre-fill z danych konta użytkownika
        if ( empty( $data ) && is_user_logged_in() ) {
            $uid  = get_current_user_id();
            $user = wp_get_current_user();
            $data = array(
                'account_type'        => get_user_meta( $uid, 'fc_account_type', true ) ?: 'private',
                'billing_first_name'  => get_user_meta( $uid, 'fc_billing_first_name', true ) ?: $user->first_name,
                'billing_last_name'   => get_user_meta( $uid, 'fc_billing_last_name', true ) ?: $user->last_name,
                'billing_company'     => get_user_meta( $uid, 'fc_billing_company', true ),
                'billing_tax_no'      => get_user_meta( $uid, 'fc_billing_tax_no', true ),
                'billing_crn'         => get_user_meta( $uid, 'fc_billing_crn', true ),
                'billing_address'     => get_user_meta( $uid, 'fc_billing_address', true ),
                'billing_postcode'    => get_user_meta( $uid, 'fc_billing_postcode', true ),
                'billing_city'        => get_user_meta( $uid, 'fc_billing_city', true ),
                'billing_country'     => get_user_meta( $uid, 'fc_billing_country', true ) ?: get_option( 'fc_store_country', 'PL' ),
                'billing_email'       => $user->user_email,
                'billing_phone'       => get_user_meta( $uid, 'fc_billing_phone', true ),
                'billing_phone_prefix' => get_user_meta( $uid, 'fc_billing_phone_prefix', true ),
                'ship_to_different'   => get_user_meta( $uid, 'fc_ship_to_different', true ),
                'shipping_first_name' => get_user_meta( $uid, 'fc_shipping_first_name', true ),
                'shipping_last_name'  => get_user_meta( $uid, 'fc_shipping_last_name', true ),
                'shipping_company'    => get_user_meta( $uid, 'fc_shipping_company', true ),
                'shipping_address'    => get_user_meta( $uid, 'fc_shipping_address', true ),
                'shipping_postcode'   => get_user_meta( $uid, 'fc_shipping_postcode', true ),
                'shipping_city'       => get_user_meta( $uid, 'fc_shipping_city', true ),
                'shipping_country'    => get_user_meta( $uid, 'fc_shipping_country', true ) ?: get_option( 'fc_store_country', 'PL' ),
            );
        }

        $cart = FC_Cart::get_cart();

        ob_start();
        echo '<!--nocache-->';
        $checkout_layout = get_theme_mod( 'flavor_checkout_layout', 'steps' );
        ?>
        <div class="fc-checkout-page" data-layout="<?php echo esc_attr( $checkout_layout ); ?>">
            <?php if ( ! empty( $errors ) ) : ?>
                <div class="fc-errors">
                    <?php foreach ( $errors as $error ) : ?>
                        <p><?php echo esc_html( $error ); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! is_user_logged_in() ) :
                $checkout_url = get_permalink();
            ?>
            <div class="fc-checkout-login-notice">
                <span class="dashicons dashicons-info-outline"></span>
                <?php fc_e( 'have_account' ); ?> <a href="#" class="fc-checkout-login-toggle"><?php fc_e( 'log_in' ); ?></a><?php fc_e( 'to_speed_up_order' ); ?>
            </div>
            <div class="fc-checkout-login-form" style="display:none;">
                <form method="post" action="<?php echo esc_url( wp_login_url( $checkout_url ) ); ?>">
                    <div class="fc-checkout-login-fields">
                        <div class="fc-field">
                            <label for="fc_checkout_login_user"><?php fc_e( 'login_or_email' ); ?></label>
                            <input type="text" name="log" id="fc_checkout_login_user" required>
                        </div>
                        <div class="fc-field">
                            <label for="fc_checkout_login_pass"><?php fc_e( 'password' ); ?></label>
                            <input type="password" name="pwd" id="fc_checkout_login_pass" required>
                        </div>
                    </div>
                    <div class="fc-checkout-login-actions">
                        <label class="fc-checkout-remember"><input type="checkbox" name="rememberme" value="forever"> <?php fc_e( 'remember_me' ); ?></label>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url( $checkout_url ); ?>">
                        <button type="submit" class="fc-btn"><?php fc_e( 'log_in' ); ?></button>
                    </div>
                    <?php $account_url = get_permalink( get_option( 'fc_page_moje-konto' ) ); if ( $account_url ) : ?>
                    <p style="margin:8px 0 0;font-size:13px;"><a href="<?php echo esc_url( add_query_arg( 'action', 'forgot_password', $account_url ) ); ?>"><?php fc_e( 'forgot_password' ); ?></a></p>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <!-- Pasek kroków -->
            <div class="fc-steps">
                <div class="fc-step active" data-step="1">
                    <span class="fc-step-num">1</span>
                    <span class="fc-step-label"><?php fc_e( 'step_data' ); ?></span>
                </div>
                <div class="fc-step-line"></div>
                <div class="fc-step" data-step="2">
                    <span class="fc-step-num">2</span>
                    <span class="fc-step-label"><?php fc_e( 'step_shipping' ); ?></span>
                </div>
                <div class="fc-step-line"></div>
                <div class="fc-step" data-step="3">
                    <span class="fc-step-num">3</span>
                    <span class="fc-step-label"><?php fc_e( 'step_payment' ); ?></span>
                </div>
                <div class="fc-step-line"></div>
                <div class="fc-step" data-step="4">
                    <span class="fc-step-num">4</span>
                    <span class="fc-step-label"><?php fc_e( 'step_summary' ); ?></span>
                </div>
            </div>

            <form method="post" class="fc-checkout-form">
                <?php wp_nonce_field( 'fc_checkout', 'fc_checkout_nonce' ); ?>

                <!-- KROK 1: Dane -->
                <div class="fc-checkout-step active" data-step="1">
                    <h3><?php fc_e( 'billing_details' ); ?></h3>

                    <!-- Typ konta -->
                    <div class="fc-account-type">
                        <label class="fc-account-type-option">
                            <input type="radio" name="account_type" value="private" <?php checked( ( $data['account_type'] ?? 'private' ), 'private' ); ?>>
                            <span><?php fc_e( 'private_account' ); ?></span>
                        </label>
                        <label class="fc-account-type-option">
                            <input type="radio" name="account_type" value="company" <?php checked( ( $data['account_type'] ?? '' ), 'company' ); ?>>
                            <span><?php fc_e( 'company_account' ); ?></span>
                        </label>
                    </div>

                    <div class="fc-private-fields">
                        <div class="fc-field-group fc-two-cols">
                            <div class="fc-field">
                                <label for="billing_first_name"><?php fc_e( 'first_name' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="billing_first_name" id="billing_first_name" value="<?php echo esc_attr( $data['billing_first_name'] ?? '' ); ?>" required>
                            </div>
                            <div class="fc-field">
                                <label for="billing_last_name"><?php fc_e( 'last_name' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="billing_last_name" id="billing_last_name" value="<?php echo esc_attr( $data['billing_last_name'] ?? '' ); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="fc-field">
                        <label for="billing_country"><?php fc_e( 'country' ); ?> <span class="fc-required">*</span></label>
                        <?php self::render_country_select( 'billing_country', $data['billing_country'] ?? get_option( 'fc_store_country', 'PL' ) ); ?>
                    </div>

                    <div class="fc-company-fields" style="display:none;">
                        <div class="fc-field">
                            <label for="billing_company"><?php fc_e( 'company_name' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="billing_company" id="billing_company" value="<?php echo esc_attr( $data['billing_company'] ?? '' ); ?>">
                        </div>
                        <div class="fc-field-group fc-two-cols">
                            <div class="fc-field">
                                <label for="billing_tax_no" id="billing_tax_no_label"><?php fc_e( 'tax_id' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="billing_tax_no" id="billing_tax_no" value="<?php echo esc_attr( $data['billing_tax_no'] ?? '' ); ?>">
                            </div>
                            <div class="fc-field">
                                <label for="billing_crn" id="billing_crn_label"><?php fc_e( 'registration_number' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="billing_crn" id="billing_crn" value="<?php echo esc_attr( $data['billing_crn'] ?? '' ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="fc-field">
                        <label for="billing_address"><?php fc_e( 'address' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="billing_address" id="billing_address" value="<?php echo esc_attr( $data['billing_address'] ?? '' ); ?>" placeholder="<?php echo esc_attr( fc__( 'street_and_number' ) ); ?>" required>
                    </div>

                    <div class="fc-field-group fc-two-cols">
                        <div class="fc-field">
                            <label for="billing_postcode"><?php fc_e( 'postcode' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="billing_postcode" id="billing_postcode" value="<?php echo esc_attr( $data['billing_postcode'] ?? '' ); ?>" placeholder="00-000" required>
                        </div>
                        <div class="fc-field">
                            <label for="billing_city"><?php fc_e( 'city' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="billing_city" id="billing_city" value="<?php echo esc_attr( $data['billing_city'] ?? '' ); ?>" required>
                        </div>
                    </div>

                    <div class="fc-field-group fc-two-cols">
                        <div class="fc-field">
                            <label for="billing_email"><?php fc_e( 'email' ); ?> <span class="fc-required">*</span></label>
                            <input type="email" name="billing_email" id="billing_email" value="<?php echo esc_attr( $data['billing_email'] ?? '' ); ?>" required>
                        </div>
                        <div class="fc-field">
                            <label for="billing_phone"><?php fc_e( 'phone' ); ?> <span class="fc-required">*</span></label>
                            <?php self::render_phone_field( 'billing_phone', 'billing_phone_prefix', $data['billing_phone'] ?? '', $data['billing_phone_prefix'] ?? '' ); ?>
                        </div>
                    </div>

                    <?php if ( ! is_user_logged_in() ) : ?>
                    <div class="fc-checkout-register">
                        <label class="fc-checkout-register-toggle">
                            <input type="checkbox" name="fc_create_account" value="1">
                            <span><?php fc_e( 'create_account_prompt' ); ?></span>
                        </label>
                        <div class="fc-checkout-register-fields" style="display:none;">
                            <div class="fc-field">
                                <label for="fc_reg_display_name"><?php fc_e( 'display_name' ); ?></label>
                                <input type="text" name="fc_reg_display_name" id="fc_reg_display_name" autocomplete="nickname" placeholder="<?php echo esc_attr( fc__( 'display_name_example' ) ); ?>">
                                <p class="fc-field-desc"><?php fc_e( 'display_name_description' ); ?></p>
                            </div>
                            <div class="fc-checkout-register-row">
                                <div class="fc-field">
                                    <label for="fc_reg_password"><?php fc_e( 'password' ); ?> <span class="fc-required">*</span></label>
                                    <input type="password" name="fc_reg_password" id="fc_reg_password" minlength="6" autocomplete="new-password">
                                </div>
                                <div class="fc-field">
                                    <label for="fc_reg_password2"><?php fc_e( 'confirm_password' ); ?> <span class="fc-required">*</span></label>
                                    <input type="password" name="fc_reg_password2" id="fc_reg_password2" minlength="6" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="fc-step-actions">
                        <span></span>
                        <button type="button" class="fc-btn fc-step-next"><?php fc_e( 'next' ); ?> →</button>
                    </div>
                </div>

                <!-- KROK 2: Wysyłka -->
                <div class="fc-checkout-step" data-step="2">
                    <h3><?php fc_e( 'shipping_address' ); ?></h3>

                    <div class="fc-ship-different">
                        <label class="fc-ship-different-toggle">
                            <input type="checkbox" name="ship_to_different" value="1" <?php checked( ! empty( $data['ship_to_different'] ) ); ?>>
                            <span><?php fc_e( 'ship_to_different_address' ); ?></span>
                        </label>
                    </div>

                    <div class="fc-shipping-fields" style="display:none;">
                        <div class="fc-shipping-private-fields">
                            <div class="fc-field-group fc-two-cols">
                                <div class="fc-field">
                                    <label for="shipping_first_name"><?php fc_e( 'first_name' ); ?> <span class="fc-required">*</span></label>
                                    <input type="text" name="shipping_first_name" id="shipping_first_name" value="<?php echo esc_attr( $data['shipping_first_name'] ?? '' ); ?>">
                                </div>
                                <div class="fc-field">
                                    <label for="shipping_last_name"><?php fc_e( 'last_name' ); ?> <span class="fc-required">*</span></label>
                                    <input type="text" name="shipping_last_name" id="shipping_last_name" value="<?php echo esc_attr( $data['shipping_last_name'] ?? '' ); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="fc-shipping-company-field" style="display:none;">
                            <div class="fc-field">
                                <label for="shipping_company"><?php fc_e( 'company_name' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="shipping_company" id="shipping_company" value="<?php echo esc_attr( $data['shipping_company'] ?? '' ); ?>">
                            </div>
                        </div>

                        <div class="fc-field">
                            <label for="shipping_country"><?php fc_e( 'country' ); ?> <span class="fc-required">*</span></label>
                            <?php self::render_country_select( 'shipping_country', $data['shipping_country'] ?? get_option( 'fc_store_country', 'PL' ) ); ?>
                        </div>

                        <div class="fc-field">
                            <label for="shipping_address"><?php fc_e( 'address' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="shipping_address" id="shipping_address" value="<?php echo esc_attr( $data['shipping_address'] ?? '' ); ?>" placeholder="<?php echo esc_attr( fc__( 'street_and_number' ) ); ?>">
                        </div>

                        <div class="fc-field-group fc-two-cols">
                            <div class="fc-field">
                                <label for="shipping_postcode"><?php fc_e( 'postcode' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="shipping_postcode" id="shipping_postcode" value="<?php echo esc_attr( $data['shipping_postcode'] ?? '' ); ?>" placeholder="00-000">
                            </div>
                            <div class="fc-field">
                                <label for="shipping_city"><?php fc_e( 'city' ); ?> <span class="fc-required">*</span></label>
                                <input type="text" name="shipping_city" id="shipping_city" value="<?php echo esc_attr( $data['shipping_city'] ?? '' ); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="fc-field">
                        <label for="order_notes"><?php fc_e( 'order_notes' ); ?></label>
                        <textarea name="order_notes" id="order_notes" rows="3"><?php echo esc_textarea( $data['order_notes'] ?? '' ); ?></textarea>
                    </div>

                    <?php
                    $shipping_methods = get_option( 'fc_shipping_methods', array() );
                    if ( ! is_array( $shipping_methods ) ) $shipping_methods = array();
                    $enabled_methods = array_filter( $shipping_methods, function( $m ) { return ! empty( $m['enabled'] ); } );
                    $cart_total      = FC_Cart::get_total();
                    $currency_symbol = get_option( 'fc_currency_symbol', 'zł' );
                    $currency_pos    = get_option( 'fc_currency_position', 'after' );
                    $coupon_free_ship = false;
                    if ( class_exists( 'FC_Coupons' ) ) {
                        $coupon_free_ship = FC_Coupons::has_free_shipping();
                    }
                    // Klasy wysyłkowe w koszyku
                    $cart_classes = FC_Cart::get_cart_shipping_classes();
                    ?>
                    <?php if ( ! empty( $enabled_methods ) ) : ?>
                    <h3 class="fc-shipping-methods-heading" style="margin-top:1.5rem;"><?php fc_e( 'shipping_method' ); ?></h3>
                    <div class="fc-shipping-methods" data-cart-total="<?php echo esc_attr( $cart_total ); ?>" <?php if ( $coupon_free_ship ) echo 'data-coupon-free-shipping="1"'; ?>>
                        <?php foreach ( $enabled_methods as $i => $method ) :
                            // Wylicz efektywny koszt z uwzględnieniem klas wysyłkowych
                            $effective = FC_Cart::get_effective_shipping( $method, $cart_classes );
                            if ( ! $effective['available'] ) continue; // Metoda zablokowana przez klasę
                            $cost = $effective['cost'];
                            $method_free_threshold = isset( $method['free_threshold'] ) && $method['free_threshold'] !== '' ? floatval( $method['free_threshold'] ) : 0;
                            $is_free = $coupon_free_ship || ( $method_free_threshold > 0 && $cart_total >= $method_free_threshold );
                            $display_cost = $is_free ? 0 : $cost;
                            $m_countries = isset( $method['countries'] ) && is_array( $method['countries'] ) ? $method['countries'] : array();
                        ?>
                        <label class="fc-shipping-option" data-countries="<?php echo esc_attr( implode( ',', $m_countries ) ); ?>">
                            <input type="radio" name="shipping_method" value="<?php echo esc_attr( $i ); ?>" data-cost="<?php echo esc_attr( $is_free ? 0 : $cost ); ?>" data-name="<?php echo esc_attr( $method['name'] ); ?>" <?php checked( $i, intval( $data['shipping_method'] ?? 0 ) ); ?>>
                            <span class="fc-shipping-option-name"><?php echo esc_html( $method['name'] ); ?></span>
                            <span class="fc-shipping-option-cost">
                                <?php if ( $is_free && $cost > 0 ) : ?>
                                    <del><?php echo fc_format_price( $cost ); ?></del> <?php fc_e( 'free' ); ?>
                                <?php elseif ( $cost > 0 ) : ?>
                                    <?php echo fc_format_price( $cost ); ?>
                                <?php else : ?>
                                    <?php fc_e( 'free' ); ?>
                                <?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="fc-step-actions">
                        <button type="button" class="fc-btn fc-btn-outline fc-step-prev">← <?php fc_e( 'back' ); ?></button>
                        <button type="button" class="fc-btn fc-step-next"><?php fc_e( 'next' ); ?> →</button>
                    </div>
                </div>

                <!-- KROK 3: Płatność -->
                <div class="fc-checkout-step" data-step="3">
                    <h3><?php fc_e( 'payment_method' ); ?></h3>
                    <div class="fc-payment-methods">
                        <?php
                        $payment_methods = get_option( 'fc_payment_methods', array(
                            array( 'id' => 'transfer', 'name' => 'Przelew bankowy', 'description' => '', 'enabled' => 1 ),
                            array( 'id' => 'cod',      'name' => 'Płatność przy odbiorze', 'description' => '', 'enabled' => 1 ),
                        ) );
                        // Auto-add Stripe if enabled
                        if ( class_exists( 'FC_Stripe' ) && FC_Stripe::is_enabled() && FC_Stripe::get_publishable_key() ) {
                            $has_stripe = false;
                            foreach ( $payment_methods as $pm_check ) {
                                if ( $pm_check['id'] === 'stripe' ) { $has_stripe = true; break; }
                            }
                            if ( ! $has_stripe ) {
                                array_unshift( $payment_methods, array(
                                    'id'          => 'stripe',
                                    'name'        => fc__( 'stripe_online_payment' ),
                                    'description' => '',
                                    'enabled'     => 1,
                                ) );
                            }
                        }
                        $enabled_payments = array_filter( $payment_methods, function( $m ) { return ! empty( $m['enabled'] ); } );
                        $first = true;
                        foreach ( $enabled_payments as $pm ) :
                            $checked_val = $data['payment_method'] ?? '';
                            $is_checked = $checked_val ? ( $checked_val === $pm['id'] ) : $first;
                        ?>
                        <label class="fc-payment-option">
                            <input type="radio" name="payment_method" value="<?php echo esc_attr( $pm['id'] ); ?>" <?php checked( $is_checked ); ?> data-label="<?php echo esc_attr( $pm['name'] ); ?>">
                            <span><?php echo esc_html( $pm['name'] ); ?></span>
                            <?php if ( ! empty( $pm['description'] ) ) : ?>
                                <small class="fc-payment-desc"><?php echo esc_html( $pm['description'] ); ?></small>
                            <?php endif; ?>
                        </label>
                        <?php $first = false; endforeach; ?>
                    </div>

                    <?php if ( class_exists( 'FC_Stripe' ) && FC_Stripe::is_enabled() && FC_Stripe::get_publishable_key() ) : ?>
                    <div class="fc-stripe-payment-wrapper" id="fc-stripe-payment-wrapper">
                        <div class="fc-stripe-payment-label">
                            <?php fc_e( 'stripe_payment_methods' ); ?>
                            <?php if ( FC_Stripe::is_test_mode() ) : ?>
                                <span class="fc-stripe-test-badge">⚡ TEST</span>
                            <?php endif; ?>
                        </div>
                        <div id="fc-stripe-payment-element"></div>
                        <div id="fc-stripe-errors" class="fc-stripe-errors" role="alert"></div>
                        <div class="fc-stripe-powered">
                            <svg viewBox="0 0 60 25" xmlns="http://www.w3.org/2000/svg"><path fill="#635BFF" d="M59.64 14.28h-8.06c.19 1.93 1.6 2.55 3.2 2.55 1.64 0 2.96-.37 4.05-.95v3.32a12.3 12.3 0 0 1-4.56.88c-4.02 0-6.5-2.6-6.5-7.05 0-4.22 2.36-7.14 5.98-7.14 3.56 0 5.98 2.82 5.98 6.7 0 .57-.05 1.26-.09 1.69zm-5.89-5.52c-1.2 0-2.16 1.08-2.33 2.88h4.47c0-1.68-.79-2.88-2.14-2.88zM40.95 20.3c-1.44 0-2.32-.6-2.9-1.04l-.02 4.63-3.8.8V6.26h3.34l.2 1.08a4.7 4.7 0 0 1 3.36-1.38c2.96 0 5.13 2.82 5.13 7.02 0 4.61-2.26 7.32-5.31 7.32zm-.78-10.56c-1.05 0-1.74.44-2.15.98l.03 5.28c.38.48 1.05.94 2.12.94 1.67 0 2.68-1.68 2.68-3.63 0-1.9-1.03-3.57-2.68-3.57zM28.24 5.57h3.83V20h-3.83V5.57zm0-4.7L32.07 0v3.36l-3.83.8V.88zM24.37 6.55l.24-.29h3.33v13.74h-3.56l-.2-1.27a4.54 4.54 0 0 1-3.42 1.57c-2.9 0-5.07-2.82-5.07-7.02 0-4.56 2.22-7.32 5.25-7.32 1.26 0 2.3.49 3.43 1.59v-1zm-2.48 10.2c1.06 0 1.74-.47 2.14-.97l-.02-5.35c-.38-.48-1.04-.96-2.12-.96-1.65 0-2.67 1.68-2.67 3.62 0 1.91 1.03 3.66 2.67 3.66zM10.31 20a15.8 15.8 0 0 1-3.85-.48v-3.47c1.18.48 2.6.82 3.85.82 1.07 0 1.6-.32 1.6-.93 0-.63-.69-.87-1.78-1.25-1.83-.62-4.21-1.62-4.21-4.32 0-2.6 1.93-4.48 5.32-4.48 1.34 0 2.56.23 3.63.6v3.4c-1.02-.43-2.31-.76-3.55-.76-.93 0-1.44.27-1.44.85 0 .57.65.8 1.73 1.18 1.86.64 4.26 1.51 4.26 4.34 0 2.72-1.88 4.5-5.56 4.5z"/></svg>
                            <?php fc_e( 'stripe_secure_payment' ); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="fc-step-actions">
                        <button type="button" class="fc-btn fc-btn-outline fc-step-prev">← <?php fc_e( 'back' ); ?></button>
                        <button type="button" class="fc-btn fc-step-next"><?php fc_e( 'next' ); ?> →</button>
                    </div>
                </div>

                <!-- KROK 4: Podsumowanie -->
                <div class="fc-checkout-step" data-step="4">
                    <h3><?php fc_e( 'order_summary' ); ?></h3>

                    <!-- Podsumowanie danych — wypełniane przez JS -->
                    <div class="fc-summary-details">
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'billing_info' ); ?></h4>
                            <div class="fc-summary-billing-info"></div>
                        </div>
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'shipping_address' ); ?></h4>
                            <div class="fc-summary-shipping-info"></div>
                        </div>
                    </div>
                    <div class="fc-summary-details fc-summary-full">
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'step_shipping' ); ?></h4>
                            <div class="fc-summary-shipping-method-info"></div>
                        </div>
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'payment' ); ?></h4>
                            <div class="fc-summary-payment-info"></div>
                        </div>
                    </div>

                    <table class="fc-summary-table">
                        <thead>
                            <tr>
                                <th><?php fc_e( 'product' ); ?></th>
                                <th><?php fc_e( 'quantity' ); ?></th>
                                <th><?php fc_e( 'total' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $cart as $item ) :
                                $v_id = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
                                $price = FC_Cart::get_product_price( $item['product_id'], $v_id );
                                $v_attrs_html = '';
                                if ( $v_id !== '' ) {
                                    $vs = get_post_meta( $item['product_id'], '_fc_variants', true );
                                    $found_v = FC_Cart::find_variant( $vs, $v_id );
                                    if ( $found_v && ! empty( $found_v['attribute_values'] ) ) {
                                        $parts = array();
                                        foreach ( $found_v['attribute_values'] as $attr_name => $attr_val ) {
                                            $parts[] = esc_html( $attr_name ) . ': ' . esc_html( $attr_val );
                                        }
                                        $v_attrs_html = '<br><small style="color:var(--fc-text-light);">' . implode( ', ', $parts ) . '</small>';
                                    }
                                }
                                $unit_label = '';
                                if ( FC_Units_Admin::is_visible( 'checkout' ) ) {
                                    $unit_label = ' ' . esc_html( FC_Units_Admin::label( get_post_meta( $item['product_id'], '_fc_unit', true ) ?: FC_Units_Admin::get_default() ) );
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html( get_the_title( $item['product_id'] ) ); ?><?php echo $v_attrs_html; ?></td>
                                    <td><?php echo $item['quantity'] . $unit_label; ?></td>
                                    <td><?php echo fc_format_price( $price * $item['quantity'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $checkout_coupons = class_exists( 'FC_Coupons' ) ? FC_Coupons::get_session_coupons() : array();
                            $checkout_total_discount = 0;
                            foreach ( $checkout_coupons as $cc ) { $checkout_total_discount += floatval( $cc['discount'] ); }
                            foreach ( $checkout_coupons as $cc ) : ?>
                            <tr class="fc-summary-coupon-row" style="color:#27ae60;">
                                <td colspan="2"><?php printf( fc__( 'coupon_label' ), '<code>' . esc_html( $cc['code'] ) . '</code>' ); ?></td>
                                <td>−<?php echo fc_format_price( $cc['discount'] ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="fc-summary-shipping-row" style="display:none;">
                                <td colspan="2"><?php fc_e( 'step_shipping' ); ?></td>
                                <td class="fc-summary-shipping-cost"></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong><?php fc_e( 'grand_total' ); ?></strong></td>
                                <td><strong class="fc-summary-grand-total" data-cart-total="<?php echo esc_attr( FC_Cart::get_total() ); ?>" data-coupon-discount="<?php echo esc_attr( $checkout_total_discount ); ?>"><?php
                                    $checkout_subtotal = FC_Cart::get_total() - $checkout_total_discount;
                                    echo fc_format_price( max( 0, $checkout_subtotal ) );
                                ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="fc-step-actions">
                        <button type="button" class="fc-btn fc-btn-outline fc-step-prev">← <?php fc_e( 'back' ); ?></button>
                        <button type="submit" name="fc_checkout_submit" class="fc-btn fc-btn-checkout"><?php fc_e( 'place_order' ); ?></button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [fc_thank_you] — potwierdzenie zamówienia
     */
    public static function thank_you( $atts ) {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

        // Weryfikacja dostępu — zamówienie musi należeć do aktualnego użytkownika lub token musi pasować
        $valid_order = false;
        if ( $order_id && get_post_type( $order_id ) === 'fc_order' ) {
            $order_customer_id = get_post_meta( $order_id, '_fc_customer_id', true );
            if ( is_user_logged_in() && intval( $order_customer_id ) === get_current_user_id() ) {
                $valid_order = true;
            }
            // Token-based access (guest orders or when session is lost after Stripe redirect)
            if ( ! $valid_order ) {
                $order_token = get_post_meta( $order_id, '_fc_order_token', true );
                if ( ! empty( $order_token ) && isset( $_GET['token'] ) && hash_equals( $order_token, sanitize_text_field( $_GET['token'] ) ) ) {
                    $valid_order = true;
                }
            }
        }

        ob_start();
        ?>
        <div class="fc-thank-you">
            <?php if ( $valid_order ) :
                $number   = get_post_meta( $order_id, '_fc_order_number', true );
                $total    = get_post_meta( $order_id, '_fc_order_total', true );
                $customer = get_post_meta( $order_id, '_fc_customer', true );
                $payment  = get_post_meta( $order_id, '_fc_payment_method', true );
                $items    = get_post_meta( $order_id, '_fc_order_items', true );
                $shipping_method = get_post_meta( $order_id, '_fc_shipping_method', true );
                $shipping_cost   = floatval( get_post_meta( $order_id, '_fc_shipping_cost', true ) );
                $order_status    = get_post_meta( $order_id, '_fc_order_status', true );
            ?>

                <!-- ── NORMAL THANK-YOU VIEW ── -->
                <div class="fc-thank-you-header">
                    <span class="fc-check-icon">✓</span>
                    <h2><?php fc_e( 'thank_you_title' ); ?></h2>
                    <p><?php printf( fc__( 'order_number_label' ), esc_html( $number ) ); ?></p>
                </div>

                <div class="fc-order-confirmation">
                    <h3><?php fc_e( 'summary' ); ?></h3>
                    <table class="fc-summary-table">
                        <thead>
                            <tr>
                                <th><?php fc_e( 'product' ); ?></th>
                                <th><?php fc_e( 'quantity' ); ?></th>
                                <th><?php fc_e( 'total' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( is_array( $items ) ) : foreach ( $items as $item ) :
                                $item_unit = '';
                                if ( FC_Units_Admin::is_visible( 'thank_you' ) && ! empty( $item['product_id'] ) ) {
                                    $item_unit = FC_Units_Admin::label( get_post_meta( $item['product_id'], '_fc_unit', true ) ?: FC_Units_Admin::get_default() );
                                }
                                $v_attrs_html = '';
                                if ( ! empty( $item['attribute_values'] ) && is_array( $item['attribute_values'] ) ) {
                                    $parts = array();
                                    foreach ( $item['attribute_values'] as $attr_name => $attr_val ) {
                                        $parts[] = esc_html( $attr_name ) . ': ' . esc_html( $attr_val );
                                    }
                                    $v_attrs_html = '<br><small style="color:var(--fc-text-light);">' . implode( ', ', $parts ) . '</small>';
                                } elseif ( ! empty( $item['variant_name'] ) ) {
                                    $v_attrs_html = '<br><small style="color:var(--fc-text-light);">' . esc_html( $item['variant_name'] ) . '</small>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html( $item['product_name'] ); ?><?php echo $v_attrs_html; ?></td>
                                    <td><?php echo intval( $item['quantity'] ); ?><?php if ( $item_unit ) echo ' ' . esc_html( $item_unit ); ?></td>
                                    <td><?php echo fc_format_price( $item['line_total'] ); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $order_coupon_details = get_post_meta( $order_id, '_fc_coupon_details', true );
                            $order_coupon_discount = floatval( get_post_meta( $order_id, '_fc_coupon_discount', true ) );

                            // Rekonstrukcja szczegółów dla starych zamówień bez _fc_coupon_details
                            if ( empty( $order_coupon_details ) && $order_coupon_discount > 0 && class_exists( 'FC_Coupons' ) ) {
                                $order_coupon_code = get_post_meta( $order_id, '_fc_coupon_code', true );
                                $codes_arr = array_map( 'trim', explode( ',', $order_coupon_code ) );
                                if ( count( $codes_arr ) > 1 ) {
                                    $order_subtotal = 0;
                                    if ( is_array( $items ) ) {
                                        foreach ( $items as $it ) { $order_subtotal += floatval( $it['line_total'] ); }
                                    }
                                    $raw_discounts = array();
                                    $raw_total = 0;
                                    foreach ( $codes_arr as $c ) {
                                        $cp = FC_Coupons::find_by_code( $c );
                                        $d = 0;
                                        if ( $cp ) {
                                            $cdata = FC_Coupons::get_coupon_data( $cp->ID );
                                            $amt = floatval( $cdata['amount'] );
                                            if ( $cdata['discount_type'] === 'percent' ) {
                                                $d = $order_subtotal * ( $amt / 100 );
                                                if ( $cdata['max_discount'] && $d > floatval( $cdata['max_discount'] ) ) {
                                                    $d = floatval( $cdata['max_discount'] );
                                                }
                                            } else {
                                                $d = $amt;
                                            }
                                        }
                                        $raw_discounts[] = array( 'code' => $c, 'discount' => round( $d, 2 ) );
                                        $raw_total += $d;
                                    }
                                    // Skaluj proporcjonalnie jeśli suma odbiega od zapisanego rabatu
                                    if ( $raw_total > 0 && abs( $raw_total - $order_coupon_discount ) > 0.01 ) {
                                        $scale = $order_coupon_discount / $raw_total;
                                        foreach ( $raw_discounts as &$rd ) {
                                            $rd['discount'] = round( $rd['discount'] * $scale, 2 );
                                        }
                                        unset( $rd );
                                    }
                                    $order_coupon_details = $raw_discounts;
                                    update_post_meta( $order_id, '_fc_coupon_details', $order_coupon_details );
                                }
                            }

                            if ( ! empty( $order_coupon_details ) && is_array( $order_coupon_details ) ) :
                                foreach ( $order_coupon_details as $ocd ) :
                            ?>
                            <tr style="color:#27ae60;">
                                <td colspan="2"><?php printf( fc__( 'coupon_label' ), '<code>' . esc_html( $ocd['code'] ) . '</code>' ); ?></td>
                                <td>−<?php echo fc_format_price( $ocd['discount'] ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php elseif ( $order_coupon_discount > 0 ) :
                                $order_coupon_code = get_post_meta( $order_id, '_fc_coupon_code', true );
                            ?>
                            <tr style="color:#27ae60;">
                                <td colspan="2"><?php printf( fc__( 'coupon_label' ), '<code>' . esc_html( $order_coupon_code ) . '</code>' ); ?></td>
                                <td>−<?php echo fc_format_price( $order_coupon_discount ); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ( ! empty( $shipping_method ) ) : ?>
                            <tr>
                                <td colspan="2"><?php fc_e( 'shipping_label' ); ?> <?php echo esc_html( $shipping_method ); ?></td>
                                <td><?php echo $shipping_cost > 0 ? fc_format_price( $shipping_cost ) : fc__( 'free' ); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="2"><strong><?php fc_e( 'total_final' ); ?></strong></td>
                                <td><strong><?php echo fc_format_price( $total ); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>

                    <?php if ( is_array( $customer ) ) :
                        $billing_cc  = strtoupper( $customer['country'] ?? 'PL' );
                        $tax_labels  = self::get_country_tax_labels( $billing_cc );
                    ?>
                    <div class="fc-summary-details" style="margin-top:24px;">
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'billing_info' ); ?></h4>
                            <p><?php
                                if ( ( $customer['account_type'] ?? '' ) === 'company' && ! empty( $customer['company'] ) ) {
                                    echo '<strong>' . esc_html( $customer['company'] ) . '</strong><br>';
                                    $full_name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
                                    if ( $full_name !== '' ) {
                                        echo esc_html( $full_name ) . '<br>';
                                    }
                                } else {
                                    echo '<strong>' . esc_html( $customer['first_name'] . ' ' . $customer['last_name'] ) . '</strong><br>';
                                }
                                echo esc_html( $customer['address'] ) . '<br>';
                                echo esc_html( $customer['postcode'] . ' ' . $customer['city'] . ', ' . $billing_cc ) . '<br>';
                                echo esc_html( ( $customer['phone_prefix'] ?? '' ) . ' ' . $customer['phone'] ) . '<br>';
                                echo esc_html( $customer['email'] );
                                if ( ! empty( $customer['tax_no'] ) ) {
                                    echo '<br>' . esc_html( $tax_labels['tax_no'] ) . ': ' . esc_html( $customer['tax_no'] );
                                }
                                if ( ! empty( $customer['crn'] ) ) {
                                    echo '<br>' . esc_html( $tax_labels['crn'] ) . ': ' . esc_html( $customer['crn'] );
                                }
                            ?></p>
                        </div>
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'shipping_address' ); ?></h4>
                            <p><?php
                                if ( ! empty( $customer['shipping'] ) ) {
                                    $ship = $customer['shipping'];
                                    $ship_cc = strtoupper( $ship['country'] ?? $billing_cc );
                                    if ( ! empty( $ship['company'] ) ) {
                                        echo '<strong>' . esc_html( $ship['company'] ) . '</strong><br>';
                                    } elseif ( ! empty( $ship['first_name'] ) ) {
                                        echo '<strong>' . esc_html( $ship['first_name'] . ' ' . $ship['last_name'] ) . '</strong><br>';
                                    }
                                    echo esc_html( $ship['address'] ) . '<br>';
                                    echo esc_html( $ship['postcode'] . ' ' . $ship['city'] . ', ' . $ship_cc );
                                } else {
                                    fc_e( 'same_as_billing' );
                                }
                            ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="fc-summary-details fc-summary-full" style="margin-top:1rem;">
                        <?php if ( ! empty( $shipping_method ) ) : ?>
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'step_shipping' ); ?></h4>
                            <p><strong><?php echo esc_html( $shipping_method ); ?></strong> — <?php echo $shipping_cost > 0 ? fc_format_price( $shipping_cost ) : fc__( 'free' ); ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="fc-summary-section">
                            <h4><?php fc_e( 'payment' ); ?></h4>
                            <?php
                                $payment_labels = FC_Orders::get_payment_labels();
                                $payment_label = ( $payment === 'stripe' && class_exists( 'FC_Stripe' ) ) ? FC_Stripe::get_order_payment_label( $order_id ) : ( $payment_labels[ $payment ] ?? $payment );
                            ?>
                            <p><strong><?php echo esc_html( $payment_label ); ?></strong></p>
                        </div>
                    </div>

                    <?php if ( $payment === 'transfer' ) :
                        $bank_account = get_option( 'fc_bank_account', '' );
                    ?>
                        <div class="fc-payment-info">
                            <h4><?php fc_e( 'bank_transfer_details' ); ?></h4>
                            <p><?php fc_e( 'recipient' ); ?> <strong><?php echo esc_html( get_option( 'fc_store_name', get_bloginfo( 'name' ) ) ); ?></strong></p>
                            <?php if ( $bank_account ) : ?>
                                <p><?php fc_e( 'bank_account_number' ); ?> <strong><?php echo esc_html( $bank_account ); ?></strong></p>
                            <?php endif; ?>
                            <p><?php fc_e( 'amount' ); ?> <strong><?php echo fc_format_price( $total ); ?></strong></p>
                            <p><?php fc_e( 'transfer_title' ); ?> <strong><?php echo esc_html( $number ); ?></strong></p>
                        </div>
                    <?php endif; ?>

                    <p><?php fc_e( 'confirmation_sent' ); ?></p>
                </div>

                <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'back_to_shop' ); ?></a>

            <?php else : ?>
                <p><?php fc_e( 'order_not_found' ); ?></p>
                <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'go_to_shop' ); ?></a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [fc_retry_payment] — strona ponownej płatności
     */
    public static function retry_payment( $atts ) {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

        // Weryfikacja dostępu
        $valid_order = false;
        if ( $order_id && get_post_type( $order_id ) === 'fc_order' ) {
            $order_customer_id = get_post_meta( $order_id, '_fc_customer_id', true );
            if ( is_user_logged_in() && intval( $order_customer_id ) === get_current_user_id() ) {
                $valid_order = true;
            }
            // Token-based access (guest orders or when session is lost after Stripe redirect)
            if ( ! $valid_order ) {
                $order_token = get_post_meta( $order_id, '_fc_order_token', true );
                if ( ! empty( $order_token ) && isset( $_GET['token'] ) && hash_equals( $order_token, sanitize_text_field( $_GET['token'] ) ) ) {
                    $valid_order = true;
                }
            }
        }

        $payment   = $valid_order ? get_post_meta( $order_id, '_fc_payment_method', true ) : '';
        $is_payable = $valid_order && $payment === 'stripe' && class_exists( 'FC_Stripe' ) && FC_Stripe::is_order_payable( $order_id );

        ob_start();
        ?>
        <div class="fc-retry-page">
        <?php if ( $valid_order && $is_payable ) :
            $number   = get_post_meta( $order_id, '_fc_order_number', true );
            $total    = get_post_meta( $order_id, '_fc_order_total', true );
            $items    = get_post_meta( $order_id, '_fc_order_items', true );
            $deadline_remaining = FC_Stripe::get_deadline_remaining( $order_id );
            $account_url = is_user_logged_in() ? add_query_arg( array( 'tab' => 'orders', 'order_id' => $order_id ), get_permalink( get_option( 'fc_page_moje-konto' ) ) ) : '';
        ?>
            <div class="fc-retry-payment">
                <div class="fc-retry-header">
                    <span class="fc-retry-icon">!</span>
                    <h2><?php fc_e( 'retry_payment_title' ); ?></h2>
                    <p><?php printf( fc__( 'order_number_label' ), esc_html( $number ) ); ?></p>
                </div>

                <div class="fc-retry-info">
                    <p><?php fc_e( 'retry_payment_message' ); ?></p>
                    <div class="fc-retry-countdown" data-deadline="<?php echo esc_attr( $deadline_remaining ); ?>">
                        <span class="fc-countdown-icon">⏱</span>
                        <span class="fc-countdown-text">
                            <?php printf( fc__( 'retry_time_remaining' ), '<strong class="fc-countdown-timer"></strong>' ); ?>
                        </span>
                    </div>
                    <?php if ( $account_url ) : ?>
                        <p class="fc-retry-account-link">
                            <?php printf( fc__( 'retry_or_account' ), '<a href="' . esc_url( $account_url ) . '">' . fc__( 'my_account' ) . '</a>' ); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="fc-retry-summary">
                    <h3><?php fc_e( 'summary' ); ?></h3>
                    <table class="fc-summary-table">
                        <tbody>
                            <?php if ( is_array( $items ) ) : foreach ( $items as $item ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $item['product_name'] ); ?> × <?php echo intval( $item['quantity'] ); ?></td>
                                    <td style="text-align:right;"><?php echo fc_format_price( $item['line_total'] ); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong><?php fc_e( 'total_final' ); ?></strong></td>
                                <td style="text-align:right;"><strong><?php echo fc_format_price( $total ); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="fc-retry-payment-form">
                    <div id="fc-stripe-retry-element"></div>
                    <div id="fc-stripe-retry-errors" class="fc-stripe-errors"></div>
                    <button type="button" id="fc-stripe-retry-btn" class="fc-btn fc-btn-primary" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                        <?php fc_e( 'retry_pay_now' ); ?> — <?php echo fc_format_price( $total ); ?>
                    </button>
                </div>
            </div>

        <?php elseif ( $valid_order && ! $is_payable ) : ?>
            <div class="fc-retry-payment">
                <div class="fc-retry-header fc-retry-expired">
                    <span class="fc-retry-icon expired">✕</span>
                    <h2><?php fc_e( 'retry_expired_title' ); ?></h2>
                    <?php
                        $number = get_post_meta( $order_id, '_fc_order_number', true );
                        $status = get_post_meta( $order_id, '_fc_order_status', true );
                    ?>
                    <p><?php printf( fc__( 'order_number_label' ), esc_html( $number ) ); ?></p>
                </div>
                <div class="fc-retry-info">
                    <p><?php
                        if ( in_array( $status, array( 'processing', 'shipped', 'completed' ), true ) ) {
                            fc_e( 'retry_order_already_processed' );
                        } else {
                            fc_e( 'retry_expired_message' );
                        }
                    ?></p>
                </div>
                <?php if ( in_array( $status, array( 'processing', 'shipped', 'completed' ), true ) ) :
                    if ( is_user_logged_in() ) {
                        $view_url = get_permalink( get_option( 'fc_page_moje-konto' ) ) . '?tab=orders&order_id=' . $order_id;
                    } else {
                        $ty_url = get_permalink( get_option( 'fc_page_podziekowanie' ) );
                        $view_args = array( 'order_id' => $order_id );
                        $ot = get_post_meta( $order_id, '_fc_order_token', true );
                        if ( $ot ) $view_args['token'] = $ot;
                        $view_url = add_query_arg( $view_args, $ty_url );
                    }
                ?>
                    <a href="<?php echo esc_url( $view_url ); ?>" class="fc-btn"><?php fc_e( 'view_order' ); ?></a>
                <?php else : ?>
                    <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'back_to_shop' ); ?></a>
                <?php endif; ?>
            </div>

        <?php else : ?>
            <div class="fc-retry-payment">
                <div class="fc-retry-info">
                    <p><?php fc_e( 'order_not_found' ); ?></p>
                </div>
                <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'go_to_shop' ); ?></a>
            </div>
        <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [fc_add_to_cart id="123"] — przycisk dodaj do koszyka
     */
    public static function add_to_cart_button( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        $product_id = absint( $atts['id'] );

        if ( ! $product_id || get_post_type( $product_id ) !== 'fc_product' ) return '';

        ob_start();
        ?>
        <button class="fc-btn fc-add-to-cart" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <?php fc_e( 'add_to_cart' ); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Szablon single produktu
     */
    public static function product_template( $template ) {
        if ( is_singular( 'fc_product' ) ) {
            $plugin_template = FC_PLUGIN_DIR . 'templates/single-product.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }

    /**
     * Zwraca etykiety NIP/CRN dla danego kraju
     */
    public static function get_country_tax_labels( $country_code ) {
        $labels = array(
            'AL' => array( 'tax_no' => 'NIPT',                               'crn' => 'Numri i Regjistrimit (QKR)' ),
            'AT' => array( 'tax_no' => 'UID (ATU)',                           'crn' => 'Firmenbuchnummer (FN)' ),
            'BY' => array( 'tax_no' => 'УНП',                                'crn' => 'Рэгістрацыйны нумар' ),
            'BE' => array( 'tax_no' => 'BTW / TVA',                          'crn' => 'Ondernemingsnummer (KBO)' ),
            'BA' => array( 'tax_no' => 'PDV broj',                           'crn' => 'Registarski broj' ),
            'BG' => array( 'tax_no' => 'ИН по ДДС',                          'crn' => 'ЕИК (Булстат)' ),
            'HR' => array( 'tax_no' => 'OIB',                                'crn' => 'Matični broj subjekta (MBS)' ),
            'CY' => array( 'tax_no' => 'Αριθμός ΦΠΑ',                        'crn' => 'Αριθμός Εγγραφής (HE)' ),
            'ME' => array( 'tax_no' => 'PIB',                                'crn' => 'Registarski broj' ),
            'CZ' => array( 'tax_no' => 'DIČ',                                'crn' => 'Identifikační číslo (IČO)' ),
            'DK' => array( 'tax_no' => 'SE-nummer',                          'crn' => 'CVR-nummer' ),
            'EE' => array( 'tax_no' => 'KMKR number',                        'crn' => 'Registrikood' ),
            'FI' => array( 'tax_no' => 'ALV-numero',                         'crn' => 'Y-tunnus' ),
            'FR' => array( 'tax_no' => 'Numéro de TVA',                      'crn' => 'Numéro SIREN / SIRET' ),
            'GR' => array( 'tax_no' => 'ΑΦΜ',                                'crn' => 'Αριθμός ΓΕΜΗ' ),
            'ES' => array( 'tax_no' => 'NIF / CIF',                          'crn' => 'Registro Mercantil' ),
            'NL' => array( 'tax_no' => 'BTW-nummer',                         'crn' => 'KVK-nummer' ),
            'IE' => array( 'tax_no' => 'VAT Number',                         'crn' => 'Company Registration (CRO)' ),
            'IS' => array( 'tax_no' => 'Virðisaukaskattnúmer (VSK)',          'crn' => 'Kennitala' ),
            'LT' => array( 'tax_no' => 'PVM mokėtojo kodas',                 'crn' => 'Įmonės kodas' ),
            'LU' => array( 'tax_no' => 'Numéro TVA',                         'crn' => 'Numéro RCS' ),
            'LV' => array( 'tax_no' => 'PVN numurs',                         'crn' => 'Reģistrācijas Nr.' ),
            'MK' => array( 'tax_no' => 'ДДВ број',                           'crn' => 'ЕМБС' ),
            'MT' => array( 'tax_no' => 'VAT Number',                         'crn' => 'Company Number (C)' ),
            'MD' => array( 'tax_no' => 'Codul TVA',                          'crn' => 'IDNO (Cod fiscal)' ),
            'DE' => array( 'tax_no' => 'Umsatzsteuer-IdNr.',                 'crn' => 'Handelsregisternummer (HRB)' ),
            'NO' => array( 'tax_no' => 'MVA-nummer',                         'crn' => 'Organisasjonsnummer' ),
            'PL' => array( 'tax_no' => 'NIP',                                'crn' => 'KRS / REGON' ),
            'PT' => array( 'tax_no' => 'Número de contribuinte (NIF)',        'crn' => 'NIPC' ),
            'RO' => array( 'tax_no' => 'Cod de identificare fiscală (CIF)',   'crn' => 'Nr. Registrul Comerțului' ),
            'RS' => array( 'tax_no' => 'ПИБ',                                'crn' => 'Матични број' ),
            'SK' => array( 'tax_no' => 'IČ DPH',                             'crn' => 'Identifikačné číslo (IČO)' ),
            'SI' => array( 'tax_no' => 'Identifikacijska št. za DDV',         'crn' => 'Matična številka' ),
            'CH' => array( 'tax_no' => 'MWST-Nr. / Numéro TVA',              'crn' => 'Unternehmens-Id. (CHE/UID)' ),
            'SE' => array( 'tax_no' => 'Momsregistreringsnummer',             'crn' => 'Organisationsnummer' ),
            'UA' => array( 'tax_no' => 'ІПН',                                'crn' => 'Код ЄДРПОУ' ),
            'HU' => array( 'tax_no' => 'Adószám',                            'crn' => 'Cégjegyzékszám' ),
            'GB' => array( 'tax_no' => 'VAT Registration Number',            'crn' => 'Company Registration Number' ),
            'IT' => array( 'tax_no' => 'Partita IVA',                        'crn' => 'Numero REA' ),
        );
        $default = array( 'tax_no' => 'NIP', 'crn' => 'CRN' );
        return isset( $labels[ $country_code ] ) ? $labels[ $country_code ] : $default;
    }

    /**
     * Zwraca pełną listę krajów (bez filtrowania)
     */
    public static function get_all_countries( $context = null ) {
        return array(
            'AL' => fc__('country_AL', $context), 'AT' => fc__('country_AT', $context), 'BY' => fc__('country_BY', $context), 'BE' => fc__('country_BE', $context),
            'BA' => fc__('country_BA', $context), 'BG' => fc__('country_BG', $context), 'HR' => fc__('country_HR', $context),
            'CY' => fc__('country_CY', $context), 'ME' => fc__('country_ME', $context), 'CZ' => fc__('country_CZ', $context), 'DK' => fc__('country_DK', $context),
            'EE' => fc__('country_EE', $context), 'FI' => fc__('country_FI', $context), 'FR' => fc__('country_FR', $context), 'GR' => fc__('country_GR', $context),
            'ES' => fc__('country_ES', $context), 'NL' => fc__('country_NL', $context), 'IE' => fc__('country_IE', $context), 'IS' => fc__('country_IS', $context),
            'LT' => fc__('country_LT', $context), 'LU' => fc__('country_LU', $context), 'LV' => fc__('country_LV', $context), 'MK' => fc__('country_MK', $context),
            'MT' => fc__('country_MT', $context), 'MD' => fc__('country_MD', $context), 'DE' => fc__('country_DE', $context), 'NO' => fc__('country_NO', $context),
            'PL' => fc__('country_PL', $context), 'PT' => fc__('country_PT', $context), 'RO' => fc__('country_RO', $context), 'RS' => fc__('country_RS', $context),
            'SK' => fc__('country_SK', $context), 'SI' => fc__('country_SI', $context), 'CH' => fc__('country_CH', $context), 'SE' => fc__('country_SE', $context),
            'UA' => fc__('country_UA', $context), 'HU' => fc__('country_HU', $context), 'GB' => fc__('country_GB', $context), 'IT' => fc__('country_IT', $context),
        );
    }

    /**
     * Zwraca listę dozwolonych krajów (po uwzględnieniu wykluczeń)
     */
    public static function get_allowed_countries( $context = null ) {
        $all = self::get_all_countries( $context );
        $mode = get_option( 'fc_sell_to_mode', 'all' );
        if ( $mode === 'exclude' ) {
            $excluded = get_option( 'fc_sell_to_excluded', array() );
            if ( is_array( $excluded ) && ! empty( $excluded ) ) {
                $all = array_diff_key( $all, array_flip( $excluded ) );
            }
        } elseif ( $mode === 'include' ) {
            $included = get_option( 'fc_sell_to_included', array() );
            if ( is_array( $included ) && ! empty( $included ) ) {
                $all = array_intersect_key( $all, array_flip( $included ) );
            }
        }
        return $all;
    }

    /**
     * Renderuje select z dozwolonymi krajami (prywatna — checkout)
     */
    private static function render_country_select( $name, $selected = 'PL' ) {
        self::render_country_select_public( $name, $selected );
    }

    /**
     * Renderuje custom dropdown z flagami do wyboru kraju
     */
    public static function render_country_select_public( $name, $selected = 'PL' ) {
        $countries = self::get_allowed_countries();
        $flags     = self::get_country_flags();

        $current_code  = array_key_exists( $selected, $countries ) ? $selected : array_key_first( $countries );
        $current_label = $countries[ $current_code ] ?? $current_code;
        $current_flag  = $flags[ $current_code ] ?? '';

        ?>
        <div class="fc-country-select-wrap">
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $current_code ); ?>">
            <button type="button" class="fc-country-select-btn">
                <span class="fc-flag"><?php echo $current_flag; ?></span>
                <span class="fc-country-label"><?php echo esc_html( $current_label ); ?></span>
                <span class="fc-country-arrow">▼</span>
            </button>
            <div class="fc-country-dropdown">
                <div class="fc-country-dropdown-search">
                    <input type="text" placeholder="<?php echo esc_attr( fc__( 'search_country' ) ); ?>" autocomplete="off">
                </div>
                <ul class="fc-country-dropdown-list">
                    <?php foreach ( $countries as $code => $label ) : ?>
                        <li data-code="<?php echo esc_attr( $code ); ?>" data-flag="<?php echo esc_attr( $flags[ $code ] ?? '' ); ?>" class="<?php echo $code === $current_code ? 'active' : ''; ?>">
                            <span class="fc-flag"><?php echo $flags[ $code ] ?? ''; ?></span>
                            <span class="fc-dl-name"><?php echo esc_html( $label ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Mapa kodów krajów → emoji flagi
     */
    public static function get_country_flags() {
        return array(
            'AL' => '🇦🇱', 'AT' => '🇦🇹', 'BY' => '🇧🇾', 'BE' => '🇧🇪',
            'BA' => '🇧🇦', 'BG' => '🇧🇬', 'HR' => '🇭🇷', 'CY' => '🇨🇾',
            'ME' => '🇲🇪', 'CZ' => '🇨🇿', 'DK' => '🇩🇰', 'EE' => '🇪🇪',
            'FI' => '🇫🇮', 'FR' => '🇫🇷', 'GR' => '🇬🇷', 'ES' => '🇪🇸',
            'NL' => '🇳🇱', 'IE' => '🇮🇪', 'IS' => '🇮🇸', 'LT' => '🇱🇹',
            'LU' => '🇱🇺', 'LV' => '🇱🇻', 'MK' => '🇲🇰', 'MT' => '🇲🇹',
            'MD' => '🇲🇩', 'DE' => '🇩🇪', 'NO' => '🇳🇴', 'PL' => '🇵🇱',
            'PT' => '🇵🇹', 'RO' => '🇷🇴', 'RS' => '🇷🇸', 'SK' => '🇸🇰',
            'SI' => '🇸🇮', 'CH' => '🇨🇭', 'SE' => '🇸🇪', 'UA' => '🇺🇦',
            'HU' => '🇭🇺', 'GB' => '🇬🇧', 'IT' => '🇮🇹',
        );
    }

    /**
     * Mapa krajów → kierunkowe + flagi emoji
     */
    public static function get_phone_prefixes() {
        return array(
            'AL' => array( 'prefix' => '+355', 'flag' => '🇦🇱', 'name' => fc__('country_AL') ),
            'AT' => array( 'prefix' => '+43',  'flag' => '🇦🇹', 'name' => fc__('country_AT') ),
            'BY' => array( 'prefix' => '+375', 'flag' => '🇧🇾', 'name' => fc__('country_BY') ),
            'BE' => array( 'prefix' => '+32',  'flag' => '🇧🇪', 'name' => fc__('country_BE') ),
            'BA' => array( 'prefix' => '+387', 'flag' => '🇧🇦', 'name' => fc__('country_BA') ),
            'BG' => array( 'prefix' => '+359', 'flag' => '🇧🇬', 'name' => fc__('country_BG') ),
            'HR' => array( 'prefix' => '+385', 'flag' => '🇭🇷', 'name' => fc__('country_HR') ),
            'CY' => array( 'prefix' => '+357', 'flag' => '🇨🇾', 'name' => fc__('country_CY') ),
            'ME' => array( 'prefix' => '+382', 'flag' => '🇲🇪', 'name' => fc__('country_ME') ),
            'CZ' => array( 'prefix' => '+420', 'flag' => '🇨🇿', 'name' => fc__('country_CZ') ),
            'DK' => array( 'prefix' => '+45',  'flag' => '🇩🇰', 'name' => fc__('country_DK') ),
            'EE' => array( 'prefix' => '+372', 'flag' => '🇪🇪', 'name' => fc__('country_EE') ),
            'FI' => array( 'prefix' => '+358', 'flag' => '🇫🇮', 'name' => fc__('country_FI') ),
            'FR' => array( 'prefix' => '+33',  'flag' => '🇫🇷', 'name' => fc__('country_FR') ),
            'GR' => array( 'prefix' => '+30',  'flag' => '🇬🇷', 'name' => fc__('country_GR') ),
            'ES' => array( 'prefix' => '+34',  'flag' => '🇪🇸', 'name' => fc__('country_ES') ),
            'NL' => array( 'prefix' => '+31',  'flag' => '🇳🇱', 'name' => fc__('country_NL') ),
            'IE' => array( 'prefix' => '+353', 'flag' => '🇮🇪', 'name' => fc__('country_IE') ),
            'IS' => array( 'prefix' => '+354', 'flag' => '🇮🇸', 'name' => fc__('country_IS') ),
            'LT' => array( 'prefix' => '+370', 'flag' => '🇱🇹', 'name' => fc__('country_LT') ),
            'LU' => array( 'prefix' => '+352', 'flag' => '🇱🇺', 'name' => fc__('country_LU') ),
            'LV' => array( 'prefix' => '+371', 'flag' => '🇱🇻', 'name' => fc__('country_LV') ),
            'MK' => array( 'prefix' => '+389', 'flag' => '🇲🇰', 'name' => fc__('country_MK') ),
            'MT' => array( 'prefix' => '+356', 'flag' => '🇲🇹', 'name' => fc__('country_MT') ),
            'MD' => array( 'prefix' => '+373', 'flag' => '🇲🇩', 'name' => fc__('country_MD') ),
            'DE' => array( 'prefix' => '+49',  'flag' => '🇩🇪', 'name' => fc__('country_DE') ),
            'NO' => array( 'prefix' => '+47',  'flag' => '🇳🇴', 'name' => fc__('country_NO') ),
            'PL' => array( 'prefix' => '+48',  'flag' => '🇵🇱', 'name' => fc__('country_PL') ),
            'PT' => array( 'prefix' => '+351', 'flag' => '🇵🇹', 'name' => fc__('country_PT') ),
            'RO' => array( 'prefix' => '+40',  'flag' => '🇷🇴', 'name' => fc__('country_RO') ),
            'RS' => array( 'prefix' => '+381', 'flag' => '🇷🇸', 'name' => fc__('country_RS') ),
            'SK' => array( 'prefix' => '+421', 'flag' => '🇸🇰', 'name' => fc__('country_SK') ),
            'SI' => array( 'prefix' => '+386', 'flag' => '🇸🇮', 'name' => fc__('country_SI') ),
            'CH' => array( 'prefix' => '+41',  'flag' => '🇨🇭', 'name' => fc__('country_CH') ),
            'SE' => array( 'prefix' => '+46',  'flag' => '🇸🇪', 'name' => fc__('country_SE') ),
            'UA' => array( 'prefix' => '+380', 'flag' => '🇺🇦', 'name' => fc__('country_UA') ),
            'HU' => array( 'prefix' => '+36',  'flag' => '🇭🇺', 'name' => fc__('country_HU') ),
            'GB' => array( 'prefix' => '+44',  'flag' => '🇬🇧', 'name' => fc__('country_GB') ),
            'IT' => array( 'prefix' => '+39',  'flag' => '🇮🇹', 'name' => fc__('country_IT') ),
        );
    }

    /**
     * Renderuje pole telefonu z wyborem kierunkowego i flagą IMG — wersja dla WP Admin
     */
    public static function render_admin_phone_field( $phone_name, $prefix_name, $phone_value = '', $prefix_value = '', $phone_input_id = '' ) {
        $prefixes = self::get_phone_prefixes();
        $store_country = get_option( 'fc_store_country', 'PL' );

        if ( empty( $prefix_value ) ) {
            $prefix_value = isset( $prefixes[ $store_country ] ) ? $prefixes[ $store_country ]['prefix'] : '+48';
        }

        $current_code = $store_country;
        foreach ( $prefixes as $cc => $info ) {
            if ( $info['prefix'] === $prefix_value ) { $current_code = $cc; break; }
        }
        $current = $prefixes[ $current_code ] ?? reset( $prefixes );
        $wrap_id = 'fc-admin-phone-' . esc_attr( $prefix_name );

        // Inline CSS — raz
        static $admin_phone_css_rendered = false;
        if ( ! $admin_phone_css_rendered ) {
            $admin_phone_css_rendered = true;
            ?>
            <style>
            .fc-admin-phone-wrap{display:flex;align-items:stretch;position:relative;max-width:25em;}
            .fc-admin-phone-btn{display:flex;align-items:center;gap:6px;padding:0 10px;border:1px solid #8c8f94;border-right:none;background:#fff;cursor:pointer;font-size:14px;white-space:nowrap;min-width:110px;box-sizing:border-box;border-radius:4px 0 0 4px;line-height:2.15384615}
            .fc-admin-phone-btn:hover{border-color:#2271b1}
            .fc-admin-flag-img{width:20px;height:15px;object-fit:cover;border-radius:2px;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,.1)}
            .fc-admin-phone-btn .fc-admin-prefix{font-weight:500;color:#50575e}
            .fc-admin-phone-btn .fc-admin-arrow{font-size:.55rem;margin-left:2px;color:#50575e}
            .fc-admin-phone-wrap input[type="text"]{flex:1;min-width:0;border-radius:0 4px 4px 0;margin:0 !important}
            .fc-admin-phone-dd{display:none;position:absolute;top:100%;left:0;width:320px;max-height:260px;overflow-y:auto;background:#fff;border:1px solid #8c8f94;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:100000;border-radius:0 0 4px 4px}
            .fc-admin-phone-dd.open{display:block}
            .fc-admin-phone-dd-search{position:sticky;top:0;background:#fff;padding:6px;border-bottom:1px solid #dcdcde}
            .fc-admin-phone-dd-search input{width:100%;padding:4px 8px;border:1px solid #8c8f94;font-size:13px;box-sizing:border-box;border-radius:3px}
            .fc-admin-phone-dd-list{padding:0;margin:0;list-style:none}
            .fc-admin-phone-dd-list li{display:flex;align-items:center;gap:8px;padding:6px 10px;cursor:pointer;font-size:13px;transition:background .12s}
            .fc-admin-phone-dd-list li:hover,.fc-admin-phone-dd-list li.active{background:#f0f0f1}
            .fc-admin-phone-dd-list li .fc-admin-dl-name{flex:1}
            .fc-admin-phone-dd-list li .fc-admin-dl-prefix{color:#646970;font-size:12px}
            </style>
            <?php
        }

        // Inline JS — raz
        static $admin_phone_js_rendered = false;
        if ( ! $admin_phone_js_rendered ) {
            $admin_phone_js_rendered = true;
            ?>
            <script>
            jQuery(function($){
                function fcFlagUrl(code){ return 'https://flagcdn.com/w40/'+code.toLowerCase()+'.png'; }
                $(document).on('click','.fc-admin-phone-btn',function(e){
                    e.preventDefault();e.stopPropagation();
                    var $w=$(this).closest('.fc-admin-phone-wrap'),$dd=$w.find('.fc-admin-phone-dd');
                    $('.fc-admin-phone-dd.open').not($dd).removeClass('open');
                    $dd.toggleClass('open');
                    if($dd.hasClass('open'))$dd.find('.fc-admin-phone-dd-search input').val('').trigger('input').focus();
                });
                $(document).on('input','.fc-admin-phone-dd-search input',function(){
                    var q=$(this).val().toLowerCase(),$list=$(this).closest('.fc-admin-phone-dd').find('.fc-admin-phone-dd-list li');
                    $list.each(function(){
                        var t=($(this).data('name')||'')+' '+($(this).data('prefix')||'');
                        $(this).toggle(t.toLowerCase().indexOf(q)>-1);
                    });
                });
                $(document).on('click','.fc-admin-phone-dd-list li',function(){
                    var $li=$(this),$w=$li.closest('.fc-admin-phone-wrap'),
                        prefix=$li.data('prefix'),code=$li.data('code');
                    $w.find('.fc-admin-phone-btn .fc-admin-flag-img').attr('src',fcFlagUrl(code));
                    $w.find('.fc-admin-phone-btn .fc-admin-prefix').text(prefix);
                    $w.find('.fc-admin-phone-btn').attr('data-current',code);
                    $w.find('.fc-admin-phone-hidden').val(prefix);
                    $w.find('.fc-admin-phone-dd-list li').removeClass('active');
                    $li.addClass('active');
                    $w.find('.fc-admin-phone-dd').removeClass('open');
                });
                $(document).on('click',function(e){
                    if(!$(e.target).closest('.fc-admin-phone-wrap').length) $('.fc-admin-phone-dd.open').removeClass('open');
                });
                /* Globalny helper do aktualizacji phone-wrap z zewnątrz */
                window.fcAdminSetPhonePrefix = function(wrapId, countryCode, prefix){
                    var $w = $('#'+wrapId);
                    if(!$w.length) return;
                    $w.find('.fc-admin-phone-hidden').val(prefix);
                    $w.find('.fc-admin-phone-btn .fc-admin-prefix').text(prefix);
                    $w.find('.fc-admin-phone-btn .fc-admin-flag-img').attr('src',fcFlagUrl(countryCode));
                    $w.find('.fc-admin-phone-btn').attr('data-current',countryCode);
                    $w.find('.fc-admin-phone-dd-list li').removeClass('active');
                    $w.find('.fc-admin-phone-dd-list li[data-code="'+countryCode+'"]').addClass('active');
                };
            });
            </script>
            <?php
        }

        $input_id_attr = $phone_input_id ? ' id="' . esc_attr( $phone_input_id ) . '"' : '';
        $flag_url = 'https://flagcdn.com/w40/' . strtolower( $current_code ) . '.png';
        ?>
        <div class="fc-admin-phone-wrap" id="<?php echo $wrap_id; ?>">
            <button type="button" class="fc-admin-phone-btn" data-current="<?php echo esc_attr( $current_code ); ?>">
                <img class="fc-admin-flag-img" src="<?php echo esc_url( $flag_url ); ?>" alt="<?php echo esc_attr( $current_code ); ?>">
                <span class="fc-admin-prefix"><?php echo esc_html( $current['prefix'] ); ?></span>
                <span class="fc-admin-arrow">▼</span>
            </button>
            <input type="hidden" name="<?php echo esc_attr( $prefix_name ); ?>" value="<?php echo esc_attr( $current['prefix'] ); ?>" class="fc-admin-phone-hidden">
            <input type="text" name="<?php echo esc_attr( $phone_name ); ?>"<?php echo $input_id_attr; ?> value="<?php echo esc_attr( $phone_value ); ?>" style="vertical-align:middle;">
            <div class="fc-admin-phone-dd">
                <div class="fc-admin-phone-dd-search">
                    <input type="text" placeholder="<?php echo esc_attr( fc__( 'search_country' ) ); ?>" autocomplete="off">
                </div>
                <ul class="fc-admin-phone-dd-list">
                    <?php foreach ( $prefixes as $cc => $info ) : ?>
                        <li data-code="<?php echo esc_attr( $cc ); ?>" data-prefix="<?php echo esc_attr( $info['prefix'] ); ?>" data-name="<?php echo esc_attr( $info['name'] ); ?>" class="<?php echo $cc === $current_code ? 'active' : ''; ?>">
                            <img class="fc-admin-flag-img" src="https://flagcdn.com/w40/<?php echo esc_attr( strtolower( $cc ) ); ?>.png" alt="<?php echo esc_attr( $cc ); ?>">
                            <span class="fc-admin-dl-name"><?php echo esc_html( $info['name'] ); ?></span>
                            <span class="fc-admin-dl-prefix"><?php echo esc_html( $info['prefix'] ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje custom dropdown z flagami do wyboru kraju — wersja dla WP Admin (IMG zamiast emoji)
     */
    public static function render_admin_country_field( $name, $selected = 'PL', $input_id = '', $countries = null ) {
        if ( $countries === null ) {
            $countries = self::get_allowed_countries( 'admin' );
        }
        if ( ! isset( $countries[ $selected ] ) ) {
            $selected = array_key_first( $countries ) ?: 'PL';
        }
        $current_label = $countries[ $selected ];
        $wrap_id       = 'fc-admin-country-' . esc_attr( $name );
        $flag_url      = 'https://flagcdn.com/w40/' . strtolower( $selected ) . '.png';
        $id_attr       = $input_id ? ' id="' . esc_attr( $input_id ) . '"' : '';

        // CSS — raz
        static $admin_country_css_rendered = false;
        if ( ! $admin_country_css_rendered ) {
            $admin_country_css_rendered = true;
            ?>
            <style>
            .fc-admin-country-wrap{display:inline-block;position:relative;width:25em;vertical-align:middle}
            .fc-admin-country-btn{display:flex;align-items:center;gap:8px;padding:0 10px;border:1px solid #8c8f94;background:#fff;cursor:pointer;font-size:14px;white-space:nowrap;box-sizing:border-box;border-radius:4px;line-height:2.15384615;width:100%;text-align:left}
            .fc-admin-country-btn:hover{border-color:#2271b1}
            .fc-admin-country-flag{width:20px;height:15px;object-fit:cover;border-radius:2px;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,.1)}
            .fc-admin-country-btn .fc-admin-country-name{flex:1;font-weight:400;color:#1d2327}
            .fc-admin-country-btn .fc-admin-country-arrow{font-size:.55rem;color:#50575e}
            .fc-admin-country-dd{display:none;position:absolute;top:100%;left:0;width:100%;min-width:280px;max-height:260px;overflow-y:auto;background:#fff;border:1px solid #8c8f94;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:100000;border-radius:0 0 4px 4px}
            .fc-admin-country-dd.open{display:block}
            .fc-admin-country-dd-search{position:sticky;top:0;background:#fff;padding:6px;border-bottom:1px solid #dcdcde}
            .fc-admin-country-dd-search input{width:100%;padding:4px 8px;border:1px solid #8c8f94;font-size:13px;box-sizing:border-box;border-radius:3px}
            .fc-admin-country-dd-list{padding:0;margin:0;list-style:none}
            .fc-admin-country-dd-list li{display:flex;align-items:center;gap:8px;padding:6px 10px;cursor:pointer;font-size:13px;transition:background .12s}
            .fc-admin-country-dd-list li:hover,.fc-admin-country-dd-list li.active{background:#f0f0f1}
            .fc-admin-country-dd-list li .fc-admin-cl-name{flex:1}
            </style>
            <?php
        }

        // JS — raz
        static $admin_country_js_rendered = false;
        if ( ! $admin_country_js_rendered ) {
            $admin_country_js_rendered = true;
            ?>
            <script>
            jQuery(function($){
                function fcCFlagUrl(c){ return 'https://flagcdn.com/w40/'+c.toLowerCase()+'.png'; }
                $(document).on('click','.fc-admin-country-btn',function(e){
                    e.preventDefault();e.stopPropagation();
                    var $w=$(this).closest('.fc-admin-country-wrap'),$dd=$w.find('.fc-admin-country-dd');
                    $('.fc-admin-country-dd.open').not($dd).removeClass('open');
                    $('.fc-admin-phone-dd.open').removeClass('open');
                    $dd.toggleClass('open');
                    if($dd.hasClass('open'))$dd.find('.fc-admin-country-dd-search input').val('').trigger('input').focus();
                });
                $(document).on('input','.fc-admin-country-dd-search input',function(){
                    var q=$(this).val().toLowerCase(),$list=$(this).closest('.fc-admin-country-dd').find('.fc-admin-country-dd-list li');
                    $list.each(function(){
                        $(this).toggle(($(this).data('name')||'').toLowerCase().indexOf(q)>-1);
                    });
                });
                $(document).on('click','.fc-admin-country-dd-list li',function(){
                    var $li=$(this),$w=$li.closest('.fc-admin-country-wrap'),
                        code=$li.data('code'),name=$li.data('name');
                    $w.find('.fc-admin-country-btn .fc-admin-country-flag').attr('src',fcCFlagUrl(code));
                    $w.find('.fc-admin-country-btn .fc-admin-country-name').text(name);
                    $w.find('.fc-admin-country-btn').attr('data-current',code);
                    var $hidden=$w.find('.fc-admin-country-hidden');
                    $hidden.val(code).trigger('change');
                    $w.find('.fc-admin-country-dd-list li').removeClass('active');
                    $li.addClass('active');
                    $w.find('.fc-admin-country-dd').removeClass('open');
                });
                $(document).on('click',function(e){
                    if(!$(e.target).closest('.fc-admin-country-wrap').length) $('.fc-admin-country-dd.open').removeClass('open');
                });
                window.fcAdminSetCountry = function(wrapId, code){
                    var $w=$('#'+wrapId);
                    if(!$w.length) return;
                    var $li=$w.find('.fc-admin-country-dd-list li[data-code="'+code+'"]');
                    if(!$li.length) return;
                    $w.find('.fc-admin-country-btn .fc-admin-country-flag').attr('src',fcCFlagUrl(code));
                    $w.find('.fc-admin-country-btn .fc-admin-country-name').text($li.data('name'));
                    $w.find('.fc-admin-country-btn').attr('data-current',code);
                    $w.find('.fc-admin-country-hidden').val(code);
                    $w.find('.fc-admin-country-dd-list li').removeClass('active');
                    $li.addClass('active');
                };
            });
            </script>
            <?php
        }
        ?>
        <div class="fc-admin-country-wrap" id="<?php echo $wrap_id; ?>">
            <button type="button" class="fc-admin-country-btn" data-current="<?php echo esc_attr( $selected ); ?>">
                <img class="fc-admin-country-flag" src="<?php echo esc_url( $flag_url ); ?>" alt="<?php echo esc_attr( $selected ); ?>">
                <span class="fc-admin-country-name"><?php echo esc_html( $current_label ); ?></span>
                <span class="fc-admin-country-arrow">▼</span>
            </button>
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>"<?php echo $id_attr; ?> value="<?php echo esc_attr( $selected ); ?>" class="fc-admin-country-hidden">
            <div class="fc-admin-country-dd">
                <div class="fc-admin-country-dd-search">
                    <input type="text" placeholder="<?php echo esc_attr( fc__( 'search_country' ) ); ?>" autocomplete="off">
                </div>
                <ul class="fc-admin-country-dd-list">
                    <?php foreach ( $countries as $code => $label ) : ?>
                        <li data-code="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( $label ); ?>" class="<?php echo $code === $selected ? 'active' : ''; ?>">
                            <img class="fc-admin-country-flag" src="https://flagcdn.com/w40/<?php echo esc_attr( strtolower( $code ) ); ?>.png" alt="<?php echo esc_attr( $code ); ?>">
                            <span class="fc-admin-cl-name"><?php echo esc_html( $label ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Renderuje pole telefonu z wyborem kierunkowego i flagą
     */
    public static function render_phone_field( $name = 'billing_phone', $prefix_name = 'billing_phone_prefix', $phone_value = '', $prefix_value = '' ) {
        $prefixes = self::get_phone_prefixes();

        // Domyślny prefix wg kraju sklepu
        $store_country = get_option( 'fc_store_country', 'PL' );
        if ( empty( $prefix_value ) ) {
            $prefix_value = isset( $prefixes[ $store_country ] ) ? $prefixes[ $store_country ]['prefix'] : '+48';
        }

        // Znajdź aktualny kraj wg prefix_value
        $current_code = $store_country;
        foreach ( $prefixes as $cc => $info ) {
            if ( $info['prefix'] === $prefix_value ) { $current_code = $cc; break; }
        }
        $current = $prefixes[ $current_code ] ?? reset( $prefixes );

        // JSON data — wstrzykujemy raz
        static $json_rendered = false;
        if ( ! $json_rendered ) {
            echo '<script type="application/json" id="fc-phone-prefixes-data">' . wp_json_encode( array_values( array_map( function( $cc, $info ) {
                return array( 'code' => $cc, 'prefix' => $info['prefix'], 'flag' => $info['flag'], 'name' => $info['name'] );
            }, array_keys( $prefixes ), $prefixes ) ) ) . '</script>';
            $json_rendered = true;
        }

        ?>
        <div class="fc-phone-wrap">
            <button type="button" class="fc-phone-prefix-btn" data-current="<?php echo esc_attr( $current_code ); ?>">
                <span class="fc-flag"><?php echo $current['flag']; ?></span>
                <span class="fc-prefix-code"><?php echo esc_html( $current['prefix'] ); ?></span>
                <span class="fc-prefix-arrow">▼</span>
            </button>
            <input type="hidden" name="<?php echo esc_attr( $prefix_name ); ?>" value="<?php echo esc_attr( $current['prefix'] ); ?>" class="fc-phone-prefix-value">
            <input type="tel" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $phone_value ); ?>" required>
            <div class="fc-phone-dropdown">
                <div class="fc-phone-dropdown-search">
                    <input type="text" placeholder="<?php echo esc_attr( fc__( 'search_country' ) ); ?>" autocomplete="off">
                </div>
                <ul class="fc-phone-dropdown-list">
                    <?php foreach ( $prefixes as $cc => $info ) : ?>
                        <li data-code="<?php echo esc_attr( $cc ); ?>" data-prefix="<?php echo esc_attr( $info['prefix'] ); ?>" data-flag="<?php echo esc_attr( $info['flag'] ); ?>" class="<?php echo $cc === $current_code ? 'active' : ''; ?>">
                            <span class="fc-flag"><?php echo $info['flag']; ?></span>
                            <span class="fc-dl-name"><?php echo esc_html( $info['name'] ); ?></span>
                            <span class="fc-dl-prefix"><?php echo esc_html( $info['prefix'] ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }
}
