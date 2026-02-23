<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Meta boxy produktu: cena, cena promocyjna, SKU, stan magazynowy, galeria
 */
class FC_Product_Meta {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_fc_product', array( __CLASS__, 'save' ) );
        add_filter( 'manage_fc_product_posts_columns', array( __CLASS__, 'columns' ) );
        add_action( 'manage_fc_product_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
        add_filter( 'manage_edit-fc_product_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_modified' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'set_custom_statuses' ) );
        add_action( 'admin_head', array( __CLASS__, 'column_styles' ) );
        add_filter( 'views_edit-fc_product', array( __CLASS__, 'product_status_views' ) );
        add_filter( 'post_row_actions', array( __CLASS__, 'product_row_actions' ), 10, 2 );
        add_action( 'transition_post_status', array( __CLASS__, 'intercept_wp_trash' ), 10, 3 );
        add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'empty_trash_button' ) );
        add_filter( 'bulk_actions-edit-fc_product', array( __CLASS__, 'product_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-fc_product', array( __CLASS__, 'handle_product_bulk_actions' ), 10, 3 );
        add_action( 'admin_notices', array( __CLASS__, 'product_admin_notices' ) );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'fc_product_data',
            fc__( 'meta_product_data' ),
            array( __CLASS__, 'render' ),
            'fc_product',
            'normal',
            'high'
        );

        add_meta_box(
            'fc_product_gallery',
            fc__( 'prod_product_gallery' ),
            array( __CLASS__, 'render_gallery' ),
            'fc_product',
            'side',
            'default'
        );
    }

    public static function render( $post ) {
        wp_nonce_field( 'fc_product_meta', 'fc_product_nonce' );

        $price         = get_post_meta( $post->ID, '_fc_price', true );
        $sale_price    = get_post_meta( $post->ID, '_fc_sale_price', true );
        $sku           = get_post_meta( $post->ID, '_fc_sku', true );
        $stock         = get_post_meta( $post->ID, '_fc_stock', true );
        $manage_stock  = get_post_meta( $post->ID, '_fc_manage_stock', true );
        $stock_status  = get_post_meta( $post->ID, '_fc_stock_status', true );
        $weight        = get_post_meta( $post->ID, '_fc_weight', true );

        if ( '' === $stock_status ) $stock_status = 'instock';
        ?>
        <div class="fc-product-meta-wrap">
            <div class="fc-meta-tabs">
                <button type="button" class="fc-tab-btn active" data-tab="general"><?php fc_e( 'meta_general' ); ?></button>
                <button type="button" class="fc-tab-btn" data-tab="inventory"><?php fc_e( 'prod_inventory' ); ?></button>
                <button type="button" class="fc-tab-btn" data-tab="shipping"><?php fc_e( 'meta_shipping' ); ?></button>
            </div>

            <!-- Ogólne -->
            <div class="fc-tab-content active" data-tab="general">
                <div class="fc-field-row">
                    <label for="fc_price"><?php fc_e( 'meta_regular_price' ); ?> (<?php echo esc_html( get_option( 'fc_currency_symbol', 'zł' ) ); ?>)</label>
                    <input type="number" id="fc_price" name="fc_price" value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" placeholder="0,00">
                </div>
                <div class="fc-field-row">
                    <label for="fc_sale_price"><?php fc_e( 'meta_sale_price' ); ?> (<?php echo esc_html( get_option( 'fc_currency_symbol', 'zł' ) ); ?>)</label>
                    <input type="number" id="fc_sale_price" name="fc_sale_price" value="<?php echo esc_attr( $sale_price ); ?>" step="0.01" min="0" placeholder="0,00">
                </div>
                <div class="fc-field-row">
                    <label for="fc_sku"><?php fc_e( 'prod_sku_product_code' ); ?></label>
                    <input type="text" id="fc_sku" name="fc_sku" value="<?php echo esc_attr( $sku ); ?>" placeholder="<?php echo esc_attr( fc__( 'prod_eg_sku' ) ); ?>">
                </div>
            </div>

            <!-- Magazyn -->
            <div class="fc-tab-content" data-tab="inventory">
                <div class="fc-field-row">
                    <label for="fc_stock_status"><?php fc_e( 'prod_stock_status_2' ); ?></label>
                    <select id="fc_stock_status" name="fc_stock_status">
                        <option value="instock" <?php selected( $stock_status, 'instock' ); ?>><?php fc_e( 'prod_in_stock' ); ?></option>
                        <option value="outofstock" <?php selected( $stock_status, 'outofstock' ); ?>><?php fc_e( 'prod_out_of_stock' ); ?></option>
                    </select>
                </div>
                <div class="fc-field-row">
                    <label>
                        <input type="checkbox" name="fc_manage_stock" value="1" <?php checked( $manage_stock, '1' ); ?>>
                        <?php fc_e( 'prod_manage_stock' ); ?>
                    </label>
                </div>
                <div class="fc-field-row fc-stock-field" style="<?php echo $manage_stock ? '' : 'display:none;'; ?>">
                    <label for="fc_stock"><?php fc_e( 'prod_stock_quantity' ); ?></label>
                    <input type="number" id="fc_stock" name="fc_stock" value="<?php echo esc_attr( $stock ); ?>" min="0" step="1">
                </div>
            </div>

            <!-- Wysyłka -->
            <div class="fc-tab-content" data-tab="shipping">
                <div class="fc-field-row">
                    <label for="fc_weight"><?php fc_e( 'prod_weight_kg' ); ?></label>
                    <input type="number" id="fc_weight" name="fc_weight" value="<?php echo esc_attr( $weight ); ?>" step="0.01" min="0">
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_gallery( $post ) {
        $gallery_ids = get_post_meta( $post->ID, '_fc_gallery', true );
        if ( ! is_array( $gallery_ids ) ) {
            $gallery_ids = array();
        }
        ?>
        <div class="fc-gallery-wrap">
            <div class="fc-gallery-images">
                <?php foreach ( $gallery_ids as $img_id ) :
                    $img_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                    if ( $img_url ) : ?>
                        <div class="fc-gallery-item" data-id="<?php echo esc_attr( $img_id ); ?>">
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="">
                            <button type="button" class="fc-gallery-remove">&times;</button>
                        </div>
                    <?php endif;
                endforeach; ?>
            </div>
            <input type="hidden" name="fc_gallery" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
            <button type="button" class="button fc-gallery-add"><?php fc_e( 'prod_add_photos' ); ?></button>
        </div>
        <?php
    }

    public static function save( $post_id ) {
        if ( ! isset( $_POST['fc_product_nonce'] ) || ! wp_verify_nonce( $_POST['fc_product_nonce'], 'fc_product_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Ceny i inne pola (bez sale_price — osobna obsługa niżej)
        $fields = array(
            '_fc_price'        => 'fc_price',
            '_fc_sku'          => 'fc_sku',
            '_fc_stock'        => 'fc_stock',
            '_fc_stock_status' => 'fc_stock_status',
            '_fc_weight'       => 'fc_weight',
        );

        foreach ( $fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        // Obsługa ceny promocyjnej (procentowa np. "10%" lub bezwzględna)
        if ( isset( $_POST['fc_sale_price'] ) ) {
            $raw_sale = trim( $_POST['fc_sale_price'] );
            if ( $raw_sale === '' || $raw_sale === '0' ) {
                delete_post_meta( $post_id, '_fc_sale_price' );
            } elseif ( preg_match( '/^-?(\d+(?:[.,]\d+)?)\s*%$/', $raw_sale, $m ) ) {
                $percent = floatval( str_replace( ',', '.', $m[1] ) );
                $regular = floatval( get_post_meta( $post_id, '_fc_price', true ) );
                if ( $regular > 0 && $percent > 0 && $percent < 100 ) {
                    $calculated = round( $regular * ( 1 - $percent / 100 ), 2 );
                    update_post_meta( $post_id, '_fc_sale_price', $calculated );
                } else {
                    delete_post_meta( $post_id, '_fc_sale_price' );
                }
            } else {
                $sale_val = floatval( $raw_sale );
                $regular  = floatval( get_post_meta( $post_id, '_fc_price', true ) );
                if ( $sale_val > 0 && $regular > 0 && $sale_val < $regular ) {
                    update_post_meta( $post_id, '_fc_sale_price', sanitize_text_field( $raw_sale ) );
                } else {
                    delete_post_meta( $post_id, '_fc_sale_price' );
                }
            }
        }

        // Zarządzanie magazynem (checkbox)
        update_post_meta( $post_id, '_fc_manage_stock', isset( $_POST['fc_manage_stock'] ) ? '1' : '0' );

        // Galeria
        if ( isset( $_POST['fc_gallery'] ) ) {
            $gallery = array_filter( array_map( 'absint', explode( ',', $_POST['fc_gallery'] ) ) );
            update_post_meta( $post_id, '_fc_gallery', $gallery );
        }
    }

    /**
     * Kolumny w liście produktów
     */
    public static function columns( $columns ) {
        // Remove default date, comments and brand columns
        unset( $columns['date'], $columns['comments'], $columns['taxonomy-fc_product_brand'] );

        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            if ( $key === 'title' ) {
                $value = fc__( 'meta_title' );
            }
            $new_columns[ $key ] = $value;
            if ( $key === 'title' ) {
                $new_columns['fc_status'] = fc__( 'coupon_status' );
                $new_columns['fc_type']  = fc__( 'attr_type' );
                $new_columns['fc_price'] = fc__( 'meta_price' );
                $new_columns['fc_sku']   = fc__( 'prod_sku' );
                $new_columns['fc_stock'] = fc__( 'meta_status' );
                $new_columns['fc_comments'] = fc__( 'meta_reviews' );
            }
        }
        $new_columns['fc_date'] = fc__( 'order_date' );
        return $new_columns;
    }

    public static function column_styles() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-fc_product' ) return;
        echo '<style>
            .wp-list-table.fixed { table-layout: auto; }
            .fixed .column-title { width: 30%; }
        </style>';
    }

    public static function sortable_columns( $columns ) {
        $columns['fc_date']  = 'modified';
        $columns['fc_price'] = 'fc_price';
        $columns['fc_sku']   = 'fc_sku';
        $columns['fc_stock'] = 'fc_stock';
        return $columns;
    }

    public static function sort_by_modified( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'fc_product' ) return;

        $orderby = $query->get( 'orderby' );

        if ( $orderby === 'fc_price' ) {
            $query->set( 'meta_key', '_fc_price' );
            $query->set( 'orderby', 'meta_value_num' );
        } elseif ( $orderby === 'fc_sku' ) {
            $query->set( 'meta_key', '_fc_sku' );
            $query->set( 'orderby', 'meta_value' );
        } elseif ( $orderby === 'fc_stock' ) {
            $query->set( 'meta_key', '_fc_stock' );
            $query->set( 'orderby', 'meta_value_num' );
        } elseif ( $orderby === 'modified' || $orderby === '' ) {
            $query->set( 'orderby', 'modified' );
            if ( ! $query->get( 'order' ) ) {
                $query->set( 'order', 'DESC' );
            }
        }
    }

    /**
     * Ustawia niestandardowe statusy fc_ dla zapytań na liście produktów
     */
    public static function set_custom_statuses( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'fc_product' ) return;

        $status = $query->get( 'post_status' );
        if ( empty( $status ) || $status === 'all' ) {
            $query->set( 'post_status', array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private' ) );
        } elseif ( $status === 'fc_trash' ) {
            $query->set( 'post_status', array( 'fc_trash', 'trash' ) );
        }
    }

    public static function column_content( $column, $post_id ) {
        static $meta_cached = false;
        if ( ! $meta_cached ) {
            $meta_cached = true;
            global $wp_query;
            if ( ! empty( $wp_query->posts ) ) {
                update_postmeta_cache( wp_list_pluck( $wp_query->posts, 'ID' ) );
            }
        }
        switch ( $column ) {
            case 'fc_status':
                $status = get_post_status( $post_id );
                $labels = array(
                    'fc_published' => array( 'label' => fc__( 'pt_published' ), 'color' => '#27ae60' ),
                    'fc_draft'     => array( 'label' => fc__( 'pt_draft' ),         'color' => '#95a5a6' ),
                    'fc_hidden'    => array( 'label' => fc__( 'pt_hidden_2' ),        'color' => '#e67e22' ),
                    'fc_preorder'  => array( 'label' => fc__( 'pt_preorder' ),      'color' => '#2980b9' ),
                    'fc_private'   => array( 'label' => fc__( 'pt_private_2' ),      'color' => '#8e44ad' ),
                    'fc_trash'     => array( 'label' => fc__( 'pt_in_trash' ),       'color' => '#e74c3c' ),
                );
                $info = isset( $labels[ $status ] ) ? $labels[ $status ] : array( 'label' => $status, 'color' => '#666' );
                echo '<span class="fc-status-badge" style="background:' . esc_attr( $info['color'] ) . ';">' . esc_html( $info['label'] ) . '</span>';

                // Zaplanowana data publikacji
                if ( in_array( $status, array( 'fc_hidden', 'fc_preorder' ) ) ) {
                    $pub_date = get_post_meta( $post_id, '_fc_publish_date', true );
                    if ( $pub_date ) {
                        $formatted = date_i18n( 'j M Y, H:i', strtotime( $pub_date ) );
                        echo '<br><small style="color:#e67e22;" title="' . esc_attr( fc__( 'meta_scheduled_publication' ) ) . '"><span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;vertical-align:text-bottom;"></span> ' . esc_html( $formatted ) . '</small>';
                    }
                }
                // Data wysyłki (preorder)
                if ( $status === 'fc_preorder' ) {
                    $ship_date = get_post_meta( $post_id, '_fc_shipping_date', true );
                    if ( $ship_date ) {
                        $formatted = date_i18n( 'j M Y, H:i', strtotime( $ship_date ) );
                        echo '<br><small style="color:#2980b9;" title="' . esc_attr( fc__( 'meta_shipping_date' ) ) . '"><span class="dashicons dashicons-airplane" style="font-size:13px;width:13px;height:13px;vertical-align:text-bottom;"></span> ' . esc_html( $formatted ) . '</small>';
                    }
                }
                break;
            case 'fc_type':
                $type = get_post_meta( $post_id, '_fc_product_type', true ) ?: 'simple';
                $labels = array(
                    'simple'   => '<span class="dashicons dashicons-archive" style="font-size:20px;width:20px;height:20px;color:#666;" title="' . esc_attr( fc__( 'meta_type_simple' ) ) . '"></span>',
                    'variable' => '<span class="dashicons dashicons-networking" style="font-size:20px;width:20px;height:20px;color:#2271b1;" title="' . esc_attr( fc__( 'meta_type_variable' ) ) . '"></span>',
                );
                echo isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['simple'];
                break;
            case 'fc_price':
                $product_type = get_post_meta( $post_id, '_fc_product_type', true ) ?: 'simple';
                $price      = get_post_meta( $post_id, '_fc_price', true );
                $sale_price = get_post_meta( $post_id, '_fc_sale_price', true );

                if ( $product_type === 'variable' ) {
                    $variants = get_post_meta( $post_id, '_fc_variants', true );
                    if ( is_array( $variants ) && ! empty( $variants ) ) {
                        $active = array_filter( $variants, function( $v ) { return ( $v['status'] ?? 'active' ) === 'active'; } );
                        $reg_prices = array_filter( array_map( function( $v ) { return floatval( $v['price'] ?? 0 ); }, $active ), function( $p ) { return $p > 0; } );
                        $sale_prices = array_filter( array_map( function( $v ) { return floatval( $v['sale_price'] ?? 0 ); }, $active ), function( $p ) { return $p > 0; } );

                        $eff_prices = array();
                        foreach ( $active as $av ) {
                            $vp = floatval( $av['price'] ?? 0 );
                            $vsp = floatval( $av['sale_price'] ?? 0 );
                            if ( $vsp > 0 && $vsp < $vp ) {
                                $eff_prices[] = $vsp;
                            } elseif ( $vp > 0 ) {
                                $eff_prices[] = $vp;
                            }
                        }

                        if ( ! empty( $eff_prices ) ) {
                            $min = min( $eff_prices );
                            $max = max( $eff_prices );
                            $has_sale = ! empty( $sale_prices );
                            $style = $has_sale ? ' style="color:#e74c3c;font-weight:600;"' : '';

                            if ( $min == $max ) {
                                echo '<span' . $style . '>' . fc_format_price( $min ) . '</span>';
                            } else {
                                echo '<span' . $style . '>' . fc_format_price( $min ) . ' &ndash; ' . fc_format_price( $max ) . '</span>';
                            }
                        } else {
                            echo '—';
                        }
                    } else {
                        echo '—';
                    }
                } elseif ( $sale_price && floatval( $sale_price ) > 0 ) {
                    echo '<del>' . fc_format_price( $price ) . '</del> ';
                    echo '<ins style="text-decoration:none;color:#e74c3c;">' . fc_format_price( $sale_price ) . '</ins>';
                } elseif ( $price ) {
                    echo fc_format_price( $price );
                } else {
                    echo '—';
                }
                break;
            case 'fc_sku':
                echo esc_html( get_post_meta( $post_id, '_fc_sku', true ) ?: '—' );
                break;
            case 'fc_stock':
                $status = get_post_meta( $post_id, '_fc_stock_status', true );
                $manage = get_post_meta( $post_id, '_fc_manage_stock', true );
                $stock  = get_post_meta( $post_id, '_fc_stock', true );
                $unit   = get_post_meta( $post_id, '_fc_unit', true ) ?: FC_Units_Admin::get_default();
                if ( $status === 'outofstock' ) {
                    echo '<span style="color:#e74c3c;">✗ ' . fc__( 'meta_none' ) . '</span>';
                } elseif ( $manage === '1' && $stock !== '' ) {
                    $color = intval( $stock ) > 0 ? '#27ae60' : '#e74c3c';
                    echo '<span style="color:' . $color . ';">' . intval( $stock ) . ' ' . esc_html( FC_Units_Admin::label( $unit ) ) . '</span>';
                } else {
                    echo '<span style="color:#27ae60;">✓ ' . fc__( 'prod_in_stock' ) . '</span>';
                }
                break;
            case 'fc_comments':
                $post_obj = get_post( $post_id );
                $total_comments = (int) $post_obj->comment_count;
                $review_count = FC_Reviews::get_review_count( $post_id );
                $comments_url = admin_url( 'edit-comments.php?p=' . $post_id );

                echo '<a href="' . esc_url( $comments_url ) . '" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;outline:none;box-shadow:none;">';

                // Dymek z łączną liczbą komentarzy
                echo '<span style="display:inline-flex;align-items:center;gap:3px;" title="' . esc_attr( fc_n( 'meta_d_comment', 'meta_d_comments', $total_comments ) ) . '">';
                echo '<span class="dashicons dashicons-admin-comments" style="font-size:20px;width:20px;height:20px;color:' . ( $total_comments > 0 ? '#2271b1' : '#a7aaad' ) . ';"></span>';
                echo '<span style="color:' . ( $total_comments > 0 ? '#2271b1' : '#a7aaad' ) . ';font-size:13px;font-weight:600;">' . $total_comments . '</span>';
                echo '</span>';

                // Gwiazdka ze średnią oceną
                $avg = $review_count > 0 ? FC_Reviews::get_average_rating( $post_id ) : 0;
                echo '<span style="display:inline-flex;align-items:center;gap:3px;" title="' . esc_attr( fc_n( 'meta_d_review', 'meta_d_reviews', $review_count ) . ' — ' . sprintf( fc__( 'meta_average' ), number_format_i18n( $avg, 1 ) ) ) . '">';
                echo '<span class="dashicons dashicons-star-filled" style="font-size:20px;width:20px;height:20px;color:' . ( $review_count > 0 ? '#2271b1' : '#a7aaad' ) . ';"></span>';
                echo '<span style="color:' . ( $review_count > 0 ? '#2271b1' : '#a7aaad' ) . ';font-size:13px;font-weight:600;">' . ( $review_count > 0 ? number_format_i18n( $avg, 1 ) : '—' ) . '</span>';
                echo '</span>';

                echo '</a>';
                break;
            case 'fc_date':
                $post = get_post( $post_id );
                $status = get_post_status( $post_id );
                $pub_date  = get_the_date( 'Y/m/d', $post_id );
                $pub_time  = get_the_date( 'H:i', $post_id );
                $mod_date  = get_the_modified_date( 'Y/m/d', $post_id );
                $mod_time  = get_the_modified_date( 'H:i', $post_id );

                $status_labels = array(
                    'fc_published' => fc__( 'meta_published' ),
                    'fc_draft'     => fc__( 'meta_created' ),
                    'fc_hidden'    => fc__( 'meta_hidden_2' ),
                    'fc_preorder'  => fc__( 'pt_preorder' ),
                    'fc_private'   => fc__( 'meta_created' ),
                    'fc_trash'     => fc__( 'meta_deleted' ),
                );
                $date_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : fc__( 'meta_published' );

                echo '<span style="color:#50575e;">' . esc_html( $date_label ) . '</span><br>';
                echo esc_html( $pub_date . ' ' . $pub_time );

                if ( $post->post_modified !== $post->post_date ) {
                    echo '<br><br><span style="color:#50575e;">' . fc__( 'meta_edited' ) . '</span><br>';
                    echo esc_html( $mod_date . ' ' . $mod_time );
                }
                break;
        }
    }

    /**
     * Przechwytuje wbudowane trashowanie WP i konwertuje na fc_trash dla produktów
     */
    public static function intercept_wp_trash( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'fc_product' ) return;
        if ( $new_status === 'trash' && $old_status !== 'trash' ) {
            remove_action( 'transition_post_status', array( __CLASS__, 'intercept_wp_trash' ), 10 );
            wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'fc_trash' ) );
            add_action( 'transition_post_status', array( __CLASS__, 'intercept_wp_trash' ), 10, 3 );
        }
    }

    /**
     * Przycisk "Opróżnij kosz" obok filtrów — tak jak natywny WP Empty Trash
     */
    public static function empty_trash_button( $which ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'fc_product' ) return;
        if ( $which !== 'top' ) return;
        if ( ! isset( $_GET['post_status'] ) || $_GET['post_status'] !== 'fc_trash' ) return;
        if ( ! current_user_can( 'delete_posts' ) ) return;

        $empty_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_empty_trash' ), 'fc_empty_trash' );
        echo '<div class="alignleft actions"><a href="' . esc_url( $empty_url ) . '" class="button apply" onclick="return confirm(\'' . esc_js( fc__( 'meta_permanently_delete_all_products_from_trash' ) ) . '\');">'
            . esc_html( fc__( 'meta_empty_trash' ) ) . '</a></div>';
    }

    /**
     * Akcje masowe na liście produktów — dostosowane do aktualnego widoku
     */
    public static function product_bulk_actions( $actions ) {
        // Usuwamy domyślne
        unset( $actions['edit'], $actions['trash'] );

        $current = isset( $_GET['post_status'] ) ? $_GET['post_status'] : 'all';

        if ( $current === 'fc_trash' ) {
            $actions['fc_bulk_restore'] = fc__( 'meta_restore' );
            $actions['fc_bulk_delete']  = fc__( 'prod_delete_permanently' );
        } else {
            $actions['fc_bulk_publish'] = fc__( 'meta_publish' );
            $actions['fc_bulk_hide']    = fc__( 'meta_hide' );
            $actions['fc_bulk_draft']   = fc__( 'pt_draft' );
            $actions['fc_bulk_trash']   = fc__( 'prod_move_to_trash' );
        }

        return $actions;
    }

    /**
     * Obsługa akcji masowych produktów
     */
    public static function handle_product_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( ! current_user_can( 'delete_posts' ) ) return $redirect_to;
        $count = count( $post_ids );

        switch ( $action ) {
            case 'fc_bulk_publish':
                foreach ( $post_ids as $id ) {
                    wp_update_post( array( 'ID' => $id, 'post_status' => 'fc_published' ) );
                }
                $redirect_to = add_query_arg( array( 'saved' => 'published', 'count' => $count ), $redirect_to );
                break;

            case 'fc_bulk_hide':
                foreach ( $post_ids as $id ) {
                    wp_update_post( array( 'ID' => $id, 'post_status' => 'fc_hidden' ) );
                }
                $redirect_to = add_query_arg( array( 'saved' => 'hidden', 'count' => $count ), $redirect_to );
                break;

            case 'fc_bulk_draft':
                foreach ( $post_ids as $id ) {
                    wp_update_post( array( 'ID' => $id, 'post_status' => 'fc_draft' ) );
                }
                $redirect_to = add_query_arg( array( 'saved' => 'drafted', 'count' => $count ), $redirect_to );
                break;

            case 'fc_bulk_trash':
                foreach ( $post_ids as $id ) {
                    update_post_meta( $id, '_fc_pre_trash_status', get_post_status( $id ) );
                    wp_update_post( array( 'ID' => $id, 'post_status' => 'fc_trash' ) );
                }
                $redirect_to = add_query_arg( array( 'saved' => 'trashed', 'count' => $count ), $redirect_to );
                break;

            case 'fc_bulk_restore':
                foreach ( $post_ids as $id ) {
                    $prev = get_post_meta( $id, '_fc_pre_trash_status', true );
                    $restore_to = in_array( $prev, array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private' ), true ) ? $prev : 'fc_hidden';
                    wp_update_post( array( 'ID' => $id, 'post_status' => $restore_to ) );
                    delete_post_meta( $id, '_fc_pre_trash_status' );
                }
                $redirect_to = add_query_arg( array( 'post_status' => 'fc_trash', 'saved' => 'restored', 'count' => $count ), $redirect_to );
                break;

            case 'fc_bulk_delete':
                foreach ( $post_ids as $id ) {
                    wp_delete_post( $id, true );
                }
                $redirect_to = add_query_arg( array( 'post_status' => 'fc_trash', 'saved' => 'deleted', 'count' => $count ), $redirect_to );
                break;
        }

        return $redirect_to;
    }

    /**
     * Komunikaty po akcjach masowych i pojedynczych
     */
    public static function product_admin_notices() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-fc_product' ) return;
        if ( empty( $_GET['saved'] ) ) return;

        $saved = sanitize_text_field( $_GET['saved'] );
        $count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 1;
        $msg   = '';

        switch ( $saved ) {
            case 'trashed':
                $msg = fc_n( 'meta_d_product_trashed', 'meta_d_products_trashed', $count );
                break;
            case 'restored':
                $msg = fc_n( 'meta_d_product_restored', 'meta_d_products_restored', $count );
                break;
            case 'deleted':
                $msg = fc_n( 'meta_d_product_deleted', 'meta_d_products_deleted', $count );
                break;
            case 'published':
                $msg = fc_n( 'meta_d_product_published', 'meta_d_products_published', $count );
                break;
            case 'hidden':
                $msg = fc_n( 'meta_d_product_hidden', 'meta_d_products_hidden', $count );
                break;
            case 'drafted':
                $msg = fc_n( 'meta_d_product_drafted', 'meta_d_products_drafted', $count );
                break;
            case 'emptied':
                $msg = fc__( 'meta_trash_has_been_emptied' );
                break;
        }

        if ( $msg ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }
    }

    /**
     * Własne widoki statusów na liście produktów
     */
    public static function product_status_views( $views ) {
        $base_url = admin_url( 'edit.php?post_type=fc_product' );
        $current  = isset( $_GET['post_status'] ) ? $_GET['post_status'] : 'all';

        // Policz produkty wg statusu
        $counts = wp_count_posts( 'fc_product' );
        $all_count        = ( $counts->fc_published ?? 0 ) + ( $counts->fc_draft ?? 0 ) + ( $counts->fc_hidden ?? 0 ) + ( $counts->fc_preorder ?? 0 ) + ( $counts->fc_private ?? 0 );
        $publish_count    = $counts->fc_published ?? 0;
        $draft_count      = $counts->fc_draft ?? 0;
        $hidden_count     = $counts->fc_hidden ?? 0;
        $preorder_count   = $counts->fc_preorder ?? 0;
        $fc_private_count = $counts->fc_private ?? 0;
        $trash_count      = ( $counts->fc_trash ?? 0 ) + ( $counts->trash ?? 0 );

        $views = array();

        // Wszystkie (bez kosza)
        $class = ( $current === 'all' ) ? ' class="current"' : '';
        $views['all'] = '<a href="' . esc_url( $base_url ) . '"' . $class . '>'
            . fc__( 'order_all' ) . ' <span class="count">(' . $all_count . ')</span></a>';

        // Opublikowane
        $class = ( $current === 'fc_published' ) ? ' class="current"' : '';
        $views['fc_published'] = '<a href="' . esc_url( add_query_arg( 'post_status', 'fc_published', $base_url ) ) . '"' . $class . '>'
            . fc__( 'order_published' ) . ' <span class="count">(' . $publish_count . ')</span></a>';

        // Szkice
        $class = ( $current === 'fc_draft' ) ? ' class="current"' : '';
        $views['fc_draft'] = '<a href="' . esc_url( add_query_arg( 'post_status', 'fc_draft', $base_url ) ) . '"' . $class . '>'
            . fc__( 'order_drafts' ) . ' <span class="count">(' . $draft_count . ')</span></a>';

        // Ukryte
        $class = ( $current === 'fc_hidden' ) ? ' class="current"' : '';
        $views['fc_hidden'] = '<a href="' . esc_url( add_query_arg( 'post_status', 'fc_hidden', $base_url ) ) . '"' . $class . '>'
            . fc__( 'meta_hidden' ) . ' <span class="count">(' . $hidden_count . ')</span></a>';

        // Preorder
        $class = ( $current === 'fc_preorder' ) ? ' class="current"' : '';
        $views['fc_preorder'] = '<a href="' . esc_url( add_query_arg( 'post_status', 'fc_preorder', $base_url ) ) . '"' . $class . '>'
            . fc__( 'pt_preorder' ) . ' <span class="count">(' . $preorder_count . ')</span></a>';

        // Prywatne (użytkowników)
        $class = ( $current === 'fc_private' ) ? ' class="current"' : '';
        $views['fc_private'] = '<a href="' . esc_url( add_query_arg( 'post_status', 'fc_private', $base_url ) ) . '"' . $class . '>'
            . fc__( 'meta_private' ) . ' <span class="count">(' . $fc_private_count . ')</span></a>';

        // Kosz
        $class = ( $current === 'fc_trash' ) ? ' class="current"' : '';
        $views['fc_trash'] = '<a href="' . esc_url( add_query_arg( 'post_status', 'fc_trash', $base_url ) ) . '"' . $class . '>'
            . fc__( 'order_trash' ) . ' <span class="count">(' . $trash_count . ')</span></a>';

        return $views;
    }

    /**
     * Własne akcje wierszy (row actions) na liście produktów
     */
    public static function product_row_actions( $actions, $post ) {
        if ( $post->post_type !== 'fc_product' ) return $actions;

        $product_id = $post->ID;
        $status     = $post->post_status;

        // Customowe akcje
        $new_actions = array();

        if ( $status !== 'fc_trash' ) {
            // Edytuj
            $edit_url = admin_url( 'admin.php?page=fc-product-add&product_id=' . $product_id );
            $new_actions['edit'] = '<a href="' . esc_url( $edit_url ) . '">' . fc__( 'attr_edit' ) . '</a>';

            // Duplikuj
            $dup_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_duplicate_product&product_id=' . $product_id ), 'fc_duplicate_product_' . $product_id );
            $new_actions['duplicate'] = '<a href="' . esc_url( $dup_url ) . '">' . fc__( 'meta_duplicate' ) . '</a>';

            // Podgląd / Zobacz
            if ( $status === 'fc_published' ) {
                $new_actions['view'] = '<a href="' . esc_url( get_permalink( $product_id ) ) . '" target="_blank">' . fc__( 'meta_view' ) . '</a>';
            }

            // Autor (dla prywatnych)
            if ( $status === 'fc_private' ) {
                $author = get_userdata( $post->post_author );
                if ( $author ) {
                    $new_actions['author'] = '<span style="color:#8e44ad;"><span class="dashicons dashicons-admin-users" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom;"></span> ' . esc_html( $author->display_name ) . '</span>';
                }
            }

            // Do kosza
            $trash_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_delete_product&product_id=' . $product_id ), 'fc_delete_product_' . $product_id );
            $new_actions['trash'] = '<a href="' . esc_url( $trash_url ) . '" class="submitdelete" onclick="return confirm(\'' . esc_js( fc__( 'meta_move_to_trash' ) ) . '\');">'
                . fc__( 'order_trash' ) . '</a>';
        } else {
            // Przywróć
            $restore_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_restore_product&product_id=' . $product_id ), 'fc_restore_product_' . $product_id );
            $new_actions['untrash'] = '<a href="' . esc_url( $restore_url ) . '">' . fc__( 'meta_restore' ) . '</a>';

            // Usuń trwale
            $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_force_delete_product&product_id=' . $product_id ), 'fc_force_delete_product_' . $product_id );
            $new_actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" class="submitdelete" onclick="return confirm(\'' . esc_js( fc__( 'meta_delete_permanently' ) ) . '\');">'
                . fc__( 'prod_delete_permanently' ) . '</a>';
        }

        return $new_actions;
    }
}
