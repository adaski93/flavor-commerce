<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Własna strona dodawania/edycji produktu w panelu admina
 * Zastępuje domyślny edytor blokowy WordPress
 */
class FC_Product_Admin {

    /**
     * Generate a deterministic hash ID for a variant based on its attribute values.
     */
    public static function variant_hash( $attr_vals ) {
        if ( ! is_array( $attr_vals ) || empty( $attr_vals ) ) return '';
        $keys = array_keys( $attr_vals );
        sort( $keys );
        $parts = array();
        foreach ( $keys as $k ) {
            $parts[] = $k . ':' . $attr_vals[ $k ];
        }
        return substr( md5( implode( '|', $parts ) ), 0, 8 );
    }

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
        add_action( 'admin_post_fc_save_product', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_fc_delete_product', array( __CLASS__, 'handle_delete' ) );
        add_action( 'admin_post_fc_restore_product', array( __CLASS__, 'handle_restore' ) );
        add_action( 'admin_post_fc_force_delete_product', array( __CLASS__, 'handle_force_delete' ) );
        add_action( 'admin_post_fc_empty_trash', array( __CLASS__, 'handle_empty_trash' ) );
        add_action( 'admin_init', array( __CLASS__, 'redirect_default_editor' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_badge_settings' ) );
        add_action( 'wp_ajax_fc_admin_add_category', array( __CLASS__, 'ajax_add_category' ) );
        add_action( 'wp_ajax_fc_admin_add_brand', array( __CLASS__, 'ajax_add_brand' ) );
        add_action( 'wp_ajax_fc_admin_add_unit', array( __CLASS__, 'ajax_add_unit' ) );
        add_action( 'admin_post_fc_duplicate_product', array( __CLASS__, 'handle_duplicate' ) );

        add_action( 'fc_sale_end', array( __CLASS__, 'handle_sale_end' ) );

        // Brand logo na stronie taksonomii
        add_action( 'fc_product_brand_add_form_fields', array( __CLASS__, 'brand_add_logo_field' ) );
        add_action( 'fc_product_brand_edit_form_fields', array( __CLASS__, 'brand_edit_logo_field' ), 10, 2 );
        add_action( 'created_fc_product_brand', array( __CLASS__, 'brand_save_logo' ) );
        add_action( 'edited_fc_product_brand', array( __CLASS__, 'brand_save_logo' ) );

        // Filtruj media library — pokaż tylko loga marek
        add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'filter_brand_logo_media' ) );
    }

    /**
     * Przekieruj domyślny edytor na własny formularz
     */
    public static function redirect_default_editor() {
        global $pagenow;

        if ( $pagenow === 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'fc_product' ) {
            wp_redirect( admin_url( 'admin.php?page=fc-product-add' ) );
            exit;
        }

        if ( $pagenow === 'post.php' && isset( $_GET['post'] ) && isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) {
            $post_type = get_post_type( absint( $_GET['post'] ) );
            if ( $post_type === 'fc_product' ) {
                wp_redirect( admin_url( 'admin.php?page=fc-product-add&product_id=' . absint( $_GET['post'] ) ) );
                exit;
            }
        }
    }

    /**
     * Strony menu
     */
    public static function add_menu_pages() {
        // Dodaj/Edytuj produkt
        add_submenu_page(
            'edit.php?post_type=fc_product',
            fc__( 'pt_add_product' ),
            fc__( 'pt_add_product' ),
            'edit_posts',
            'fc-product-add',
            array( __CLASS__, 'render_form' )
        );

        // Odznaki — ustawienia automatycznych odznak
        add_submenu_page(
            'edit.php?post_type=fc_product',
            fc__( 'badge_badges_settings', 'admin' ),
            fc__( 'badge_badges', 'admin' ),
            'manage_options',
            'fc-badges',
            array( __CLASS__, 'render_badges_page' )
        );

        // Recenzje w podmenu produktów
        add_submenu_page(
            'edit.php?post_type=fc_product',
            fc__( 'prod_reviews' ),
            fc__( 'prod_reviews' ),
            'moderate_comments',
            'edit-comments.php?comment_type=fc_review'
        );

        // Usuń domyślne "Dodaj nowy produkt" z menu
        remove_submenu_page( 'edit.php?post_type=fc_product', 'post-new.php?post_type=fc_product' );
    }

    /**
     * Rejestruj ustawienia odznak
     */
    public static function register_badge_settings() {
        register_setting( 'fc_badges_settings', 'fc_badge_bestseller_auto' );
        register_setting( 'fc_badges_settings', 'fc_badge_bestseller_min_sales', array(
            'type' => 'integer', 'default' => 10, 'sanitize_callback' => 'absint',
        ) );
        register_setting( 'fc_badges_settings', 'fc_badge_bestseller_max_products', array(
            'type' => 'integer', 'default' => 10, 'sanitize_callback' => 'absint',
        ) );
        register_setting( 'fc_badges_settings', 'fc_badge_new_auto' );
        register_setting( 'fc_badges_settings', 'fc_badge_new_duration', array(
            'type' => 'integer', 'default' => 14, 'sanitize_callback' => 'absint',
        ) );
        register_setting( 'fc_badges_settings', 'fc_badge_last_items_auto' );
        register_setting( 'fc_badges_settings', 'fc_badge_last_items_threshold', array(
            'type' => 'integer', 'default' => 5, 'sanitize_callback' => 'absint',
        ) );
    }

    /**
     * Strona ustawień odznak
     */
    public static function render_badges_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap fc-admin-wrap">
            <h1 class="fc-admin-title">
                <span class="dashicons dashicons-awards"></span>
                <?php fc_e( 'badge_badges_settings', 'admin' ); ?>
            </h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'fc_badges_settings' ); ?>

                <?php
                $bs_auto  = get_option( 'fc_badge_bestseller_auto', '0' );
                $bs_min   = get_option( 'fc_badge_bestseller_min_sales', 10 );
                $bs_max   = get_option( 'fc_badge_bestseller_max_products', 10 );
                $nw_auto  = get_option( 'fc_badge_new_auto', '0' );
                $nw_dur   = get_option( 'fc_badge_new_duration', 14 );
                $li_auto  = get_option( 'fc_badge_last_items_auto', '0' );
                $li_thr   = get_option( 'fc_badge_last_items_threshold', 5 );
                ?>

                <!-- Bestseller -->
                <div class="fc-form-card" style="max-width:720px;margin-bottom:20px;">
                    <div class="fc-card-header" style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#e74c3c;"></span>
                        <?php fc_e( 'badge_bestseller_title', 'admin' ); ?>
                    </div>
                    <div class="fc-card-body">
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:220px;">
                                    <label for="fc_badge_bestseller_auto"><?php fc_e( 'badge_auto_assign', 'admin' ); ?></label>
                                </th>
                                <td>
                                    <label class="fc-toggle-switch">
                                        <input type="hidden" name="fc_badge_bestseller_auto" value="0">
                                        <input type="checkbox" name="fc_badge_bestseller_auto" id="fc_badge_bestseller_auto" value="1" <?php checked( $bs_auto, '1' ); ?>>
                                        <span class="fc-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php fc_e( 'badge_bestseller_auto_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="fc_badge_bestseller_min_sales"><?php fc_e( 'badge_min_sales', 'admin' ); ?></label></th>
                                <td>
                                    <input type="number" name="fc_badge_bestseller_min_sales" id="fc_badge_bestseller_min_sales" value="<?php echo esc_attr( $bs_min ); ?>" min="1" class="small-text" style="width:80px;">
                                    <p class="description"><?php fc_e( 'badge_min_sales_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="fc_badge_bestseller_max_products"><?php fc_e( 'badge_max_products', 'admin' ); ?></label></th>
                                <td>
                                    <input type="number" name="fc_badge_bestseller_max_products" id="fc_badge_bestseller_max_products" value="<?php echo esc_attr( $bs_max ); ?>" min="1" class="small-text" style="width:80px;">
                                    <p class="description"><?php fc_e( 'badge_max_products_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Nowość -->
                <div class="fc-form-card" style="max-width:720px;margin-bottom:20px;">
                    <div class="fc-card-header" style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#27ae60;"></span>
                        <?php fc_e( 'badge_new_title', 'admin' ); ?>
                    </div>
                    <div class="fc-card-body">
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:220px;">
                                    <label for="fc_badge_new_auto"><?php fc_e( 'badge_auto_assign', 'admin' ); ?></label>
                                </th>
                                <td>
                                    <label class="fc-toggle-switch">
                                        <input type="hidden" name="fc_badge_new_auto" value="0">
                                        <input type="checkbox" name="fc_badge_new_auto" id="fc_badge_new_auto" value="1" <?php checked( $nw_auto, '1' ); ?>>
                                        <span class="fc-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php fc_e( 'badge_new_auto_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="fc_badge_new_duration"><?php fc_e( 'badge_new_duration', 'admin' ); ?></label></th>
                                <td>
                                    <input type="number" name="fc_badge_new_duration" id="fc_badge_new_duration" value="<?php echo esc_attr( $nw_dur ); ?>" min="1" max="365" class="small-text" style="width:80px;">
                                    <p class="description"><?php fc_e( 'badge_new_duration_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Ostatnie sztuki -->
                <div class="fc-form-card" style="max-width:720px;margin-bottom:20px;">
                    <div class="fc-card-header" style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#c0392b;"></span>
                        <?php fc_e( 'badge_last_items_title', 'admin' ); ?>
                    </div>
                    <div class="fc-card-body">
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:220px;">
                                    <label for="fc_badge_last_items_auto"><?php fc_e( 'badge_auto_assign', 'admin' ); ?></label>
                                </th>
                                <td>
                                    <label class="fc-toggle-switch">
                                        <input type="hidden" name="fc_badge_last_items_auto" value="0">
                                        <input type="checkbox" name="fc_badge_last_items_auto" id="fc_badge_last_items_auto" value="1" <?php checked( $li_auto, '1' ); ?>>
                                        <span class="fc-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php fc_e( 'badge_last_items_auto_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="fc_badge_last_items_threshold"><?php fc_e( 'badge_last_items_threshold', 'admin' ); ?></label></th>
                                <td>
                                    <input type="number" name="fc_badge_last_items_threshold" id="fc_badge_last_items_threshold" value="<?php echo esc_attr( $li_thr ); ?>" min="1" max="100" class="small-text" style="width:80px;">
                                    <p class="description"><?php fc_e( 'badge_last_items_threshold_desc', 'admin' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <style>
        .fc-toggle-switch {
            position: relative; display: inline-block; width: 46px; height: 24px; vertical-align: middle;
        }
        .fc-toggle-switch input[type="checkbox"] {
            opacity: 0; width: 0; height: 0; position: absolute;
        }
        .fc-toggle-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background: #ccc; border-radius: 24px; transition: .3s;
        }
        .fc-toggle-slider::before {
            content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background: #fff; border-radius: 50%; transition: .3s;
        }
        .fc-toggle-switch input:checked + .fc-toggle-slider {
            background: #2271b1;
        }
        .fc-toggle-switch input:checked + .fc-toggle-slider::before {
            transform: translateX(22px);
        }
        </style>
        <?php
    }

    /**
     * Pobierz automatyczne odznaki dla produktu
     *
     * @param int $product_id
     * @return array  Klucze odznak do dodania automatycznie
     */
    public static function get_auto_badges( $product_id ) {
        $auto = array();

        // --- Bestseller ---
        if ( get_option( 'fc_badge_bestseller_auto', '0' ) === '1' ) {
            $min_sales    = absint( get_option( 'fc_badge_bestseller_min_sales', 10 ) );
            $max_products = absint( get_option( 'fc_badge_bestseller_max_products', 10 ) );
            $total_sales  = absint( get_post_meta( $product_id, '_fc_total_sales', true ) );

            if ( $total_sales >= $min_sales ) {
                // Sprawdź czy produkt mieści się w limicie max_products (top N)
                $bestseller_ids = get_transient( 'fc_auto_bestseller_ids' );
                if ( false === $bestseller_ids ) {
                    global $wpdb;
                    $bestseller_ids = $wpdb->get_col( $wpdb->prepare(
                        "SELECT p.ID FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fc_total_sales'
                         WHERE p.post_type = 'fc_product' AND p.post_status = 'fc_published'
                           AND CAST(pm.meta_value AS UNSIGNED) >= %d
                         ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
                         LIMIT %d",
                        $min_sales,
                        $max_products
                    ) );
                    set_transient( 'fc_auto_bestseller_ids', $bestseller_ids, HOUR_IN_SECONDS );
                }
                if ( in_array( (string) $product_id, $bestseller_ids, true ) ) {
                    $auto[] = 'bestseller';
                }
            }
        }

        // --- Nowość ---
        if ( get_option( 'fc_badge_new_auto', '0' ) === '1' ) {
            $duration = absint( get_option( 'fc_badge_new_duration', 14 ) );
            $post     = get_post( $product_id );
            if ( $post ) {
                $publish_time = strtotime( $post->post_date );
                $cutoff       = time() - ( $duration * DAY_IN_SECONDS );
                if ( $publish_time >= $cutoff ) {
                    $auto[] = 'new';
                }
            }
        }

        // --- Ostatnie sztuki ---
        if ( get_option( 'fc_badge_last_items_auto', '0' ) === '1' ) {
            $threshold    = absint( get_option( 'fc_badge_last_items_threshold', 5 ) );
            $manage_stock = get_post_meta( $product_id, '_fc_manage_stock', true );
            $stock_status = get_post_meta( $product_id, '_fc_stock_status', true );

            if ( $manage_stock === '1' && $stock_status !== 'outofstock' ) {
                $stock = absint( get_post_meta( $product_id, '_fc_stock', true ) );
                if ( $stock > 0 && $stock <= $threshold ) {
                    $auto[] = 'last_items';
                }
            }
        }

        return $auto;
    }



    /**
     * Formularz dodawania/edycji
     */
    public static function render_form() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        $is_edit    = $product_id > 0;

        // Dane produktu
        $data = array(
            'title'        => '',
            'content'      => '',
            'excerpt'      => '',
            'post_status'  => 'fc_published',
            'product_type' => 'simple',
            'price'        => '',
            'sale_price'   => '',
            'sku'          => '',
            'stock'        => '',
            'manage_stock' => '0',
            'stock_status' => 'instock',
            'weight'       => '',
            'categories'   => array(),
            'brand'        => 0,
            'unit'         => '',
            'thumbnail_id' => 0,
            'gallery'      => array(),
            'attributes'   => array(),
            'variants'     => array(),
            'publish_date' => '',
            'shipping_date' => '',
            'length'          => '',
            'width'           => '',
            'height'          => '',
            'tax_class'       => '',
            'min_quantity'    => '',
            'max_quantity'    => '',
            'sale_date_from'  => '',
            'sale_date_to'    => '',
            'specifications'  => array(),
            'badges'          => array(),
            'upsell_ids'      => array(),
            'crosssell_ids'   => array(),
            'purchase_note'   => '',
            'tags'            => array(),
        );

        if ( $is_edit ) {
            $post = get_post( $product_id );
            if ( ! $post || $post->post_type !== 'fc_product' ) {
                echo '<div class="wrap"><h1>' . fc__( 'prod_product_does_not_exist' ) . '</h1></div>';
                return;
            }
            if ( $post->post_status === 'fc_trash' ) {
                echo '<div class="wrap"><h1>' . fc__( 'prod_this_product_is_in_the_trash' ) . '</h1>';
                echo '<p>';
                $restore_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_restore_product&product_id=' . $product_id ), 'fc_restore_product_' . $product_id );
                echo '<a href="' . esc_url( $restore_url ) . '" class="button button-primary">' . fc__( 'prod_restore_product' ) . '</a> ';
                $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=fc_force_delete_product&product_id=' . $product_id ), 'fc_force_delete_product_' . $product_id );
                echo '<a href="' . esc_url( $delete_url ) . '" class="button" onclick="return confirm(\'' . esc_js( fc__( 'prod_permanently_delete_this_product' ) ) . '\');">' . fc__( 'prod_delete_permanently' ) . '</a>';
                echo '</p></div>';
                return;
            }
            $data['title']        = $post->post_title;
            $data['content']      = $post->post_content;
            $data['excerpt']      = $post->post_excerpt;
            $data['post_status']  = $post->post_status;
            $data['product_type'] = get_post_meta( $product_id, '_fc_product_type', true ) ?: 'simple';
            $data['price']        = get_post_meta( $product_id, '_fc_price', true );
            $data['sale_price']   = get_post_meta( $product_id, '_fc_sale_price', true );
            $data['sku']          = get_post_meta( $product_id, '_fc_sku', true );
            $data['stock']        = get_post_meta( $product_id, '_fc_stock', true );
            $data['manage_stock'] = get_post_meta( $product_id, '_fc_manage_stock', true );
            $data['stock_status'] = get_post_meta( $product_id, '_fc_stock_status', true ) ?: 'instock';
            $data['weight']       = get_post_meta( $product_id, '_fc_weight', true );
            $data['thumbnail_id'] = get_post_thumbnail_id( $product_id );
            $data['gallery']      = get_post_meta( $product_id, '_fc_gallery', true );
            if ( ! is_array( $data['gallery'] ) ) $data['gallery'] = array();

            $data['attributes']     = get_post_meta( $product_id, '_fc_attributes', true );
            if ( ! is_array( $data['attributes'] ) ) $data['attributes'] = array();
            $data['variants']       = get_post_meta( $product_id, '_fc_variants', true );
            if ( ! is_array( $data['variants'] ) ) $data['variants'] = array();
            $data['publish_date']   = get_post_meta( $product_id, '_fc_publish_date', true );
            $data['shipping_date']  = get_post_meta( $product_id, '_fc_shipping_date', true );

            $terms = wp_get_object_terms( $product_id, 'fc_product_cat', array( 'fields' => 'ids' ) );
            $data['categories'] = is_array( $terms ) ? $terms : array();

            $brand_terms = wp_get_object_terms( $product_id, 'fc_product_brand', array( 'fields' => 'ids' ) );
            $data['brand'] = ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ? $brand_terms[0] : 0;

            $data['unit'] = get_post_meta( $product_id, '_fc_unit', true );

            $data['length']          = get_post_meta( $product_id, '_fc_length', true );
            $data['width']           = get_post_meta( $product_id, '_fc_width', true );
            $data['height']          = get_post_meta( $product_id, '_fc_height', true );
            $data['tax_class']       = get_post_meta( $product_id, '_fc_tax_class', true );
            $data['min_quantity']    = get_post_meta( $product_id, '_fc_min_quantity', true );
            $data['max_quantity']    = get_post_meta( $product_id, '_fc_max_quantity', true );
            $data['sale_date_from']  = get_post_meta( $product_id, '_fc_sale_date_from', true );
            $data['sale_date_to']    = get_post_meta( $product_id, '_fc_sale_date_to', true );
            $data['specifications']  = get_post_meta( $product_id, '_fc_specifications', true );
            if ( ! is_array( $data['specifications'] ) ) $data['specifications'] = array();
            $data['badges']          = get_post_meta( $product_id, '_fc_badges', true );
            if ( ! is_array( $data['badges'] ) ) $data['badges'] = array();
            $data['upsell_ids']      = get_post_meta( $product_id, '_fc_upsell_ids', true );
            if ( ! is_array( $data['upsell_ids'] ) ) $data['upsell_ids'] = array();
            $data['crosssell_ids']   = get_post_meta( $product_id, '_fc_crosssell_ids', true );
            if ( ! is_array( $data['crosssell_ids'] ) ) $data['crosssell_ids'] = array();
            $data['purchase_note']   = get_post_meta( $product_id, '_fc_purchase_note', true );

        }

        // Pobierz kategorie
        $all_categories = get_terms( array(
            'taxonomy'   => 'fc_product_cat',
            'hide_empty' => false,
        ) );

        // Pobierz marki
        $all_brands = get_terms( array(
            'taxonomy'   => 'fc_product_brand',
            'hide_empty' => false,
        ) );

        // Pobierz jednostki miary
        $all_units = get_option( 'fc_product_units', array( 'szt.', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'm²', 'm³', 'opak.', 'kpl.' ) );
        if ( ! is_array( $all_units ) ) $all_units = array();

        // Pobierz klasy wysyłkowe
        $all_shipping_classes = get_terms( array(
            'taxonomy'   => 'fc_shipping_class',
            'hide_empty' => false,
        ) );

        // Status zapisu
        $saved   = isset( $_GET['saved'] ) ? sanitize_text_field( $_GET['saved'] ) : '';
        $symbol  = get_option( 'fc_currency_symbol', 'zł' );

        wp_enqueue_media();
        ?>
        <div class="wrap fc-product-form-page">
            <h1 class="fc-form-title">
                <?php echo $is_edit ? fc__( 'pt_edit_product' ) : fc__( 'pt_add_new_product' ); ?>
                <?php if ( $is_edit ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="page-title-action" target="_blank"><?php fc_e( 'pt_view_product' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fc-product-add' ) ); ?>" class="page-title-action"><?php fc_e( 'prod_add_new' ); ?></a>
                <?php endif; ?>
            </h1>

            <?php if ( $saved === '1' ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'prod_product_has_been_saved' ); ?></p></div>
            <?php elseif ( $saved === 'deleted' ) : ?>
                <div class="notice notice-warning is-dismissible"><p><?php fc_e( 'prod_product_has_been_deleted' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fc-admin-form" enctype="multipart/form-data">
                <?php wp_nonce_field( 'fc_save_product', 'fc_product_nonce' ); ?>
                <input type="hidden" name="action" value="fc_save_product">
                <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">

                <div class="fc-form-grid">
                    <!-- Lewa kolumna -->
                    <div class="fc-form-main">

                        <!-- Typ produktu -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_product_type' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-product-type-selector">
                                    <label class="fc-type-option <?php echo $data['product_type'] === 'simple' ? 'active' : ''; ?>">
                                        <input type="radio" name="fc_product_type" value="simple" <?php checked( $data['product_type'], 'simple' ); ?>>
                                        <span class="dashicons dashicons-archive"></span>
                                        <span class="fc-type-label"><?php fc_e( 'prod_simple_product' ); ?></span>
                                        <span class="fc-type-desc"><?php fc_e( 'prod_standard_physical_product' ); ?></span>
                                    </label>
                                    <label class="fc-type-option <?php echo $data['product_type'] === 'variable' ? 'active' : ''; ?>">
                                        <input type="radio" name="fc_product_type" value="variable" <?php checked( $data['product_type'], 'variable' ); ?>>
                                        <span class="dashicons dashicons-networking"></span>
                                        <span class="fc-type-label"><?php fc_e( 'prod_variable_product' ); ?></span>
                                        <span class="fc-type-desc"><?php fc_e( 'prod_e_g_different_sizes_colors' ); ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Nazwa -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_basic_information' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-form-field">
                                    <label for="product_title"><?php fc_e( 'prod_product_name' ); ?> <span class="required">*</span></label>
                                    <input type="text" id="product_title" name="product_title" value="<?php echo esc_attr( $data['title'] ); ?>" required placeholder="<?php fc_e( 'prod_enter_product_name' ); ?>" class="fc-input-large">
                                </div>

                                <div class="fc-form-field">
                                    <label for="product_excerpt"><?php fc_e( 'prod_short_description' ); ?></label>
                                    <textarea id="product_excerpt" name="product_excerpt" rows="3" placeholder="<?php fc_e( 'prod_short_description_visible_on_the_product_list' ); ?>"><?php echo esc_textarea( $data['excerpt'] ); ?></textarea>
                                </div>

                                <div class="fc-form-field">
                                    <label for="product_content"><?php fc_e( 'prod_full_product_description' ); ?></label>
                                    <?php
                                    wp_editor( $data['content'], 'product_content', array(
                                        'textarea_name' => 'product_content',
                                        'textarea_rows' => 12,
                                        'media_buttons' => true,
                                        'teeny'         => false,
                                        'quicktags'     => true,
                                    ) );
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Ceny (simple) -->
                        <div class="fc-form-card fc-section-simple" style="<?php echo $data['product_type'] === 'variable' ? 'display:none;' : ''; ?>">
                            <div class="fc-card-header"><?php fc_e( 'prod_prices' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-form-row fc-two-cols">
                                    <div class="fc-form-field">
                                        <label for="fc_price"><?php printf( fc__( 'prod_regular_price' ), $symbol ); ?> <span class="required">*</span></label>
                                        <input type="number" id="fc_price" name="fc_price" value="<?php echo esc_attr( $data['price'] ); ?>" step="0.01" min="0" placeholder="0,00" class="fc-input-price">
                                    </div>
                                    <div class="fc-form-field">
                                        <label for="fc_sale_price"><?php printf( fc__( 'prod_sale_price' ), $symbol ); ?></label>
                                        <input type="text" id="fc_sale_price" name="fc_sale_price" value="<?php echo esc_attr( $data['sale_price'] ); ?>" placeholder="<?php echo esc_attr( fc__( 'prod_price_or_percent' ) ); ?>" class="fc-input-price fc-sale-input">
                                        <p class="fc-field-hint"><?php fc_e( 'prod_amount_or_percentage_discount_e_g_10' ); ?></p>
                                        <p class="fc-sale-preview" id="fc_sale_preview" style="display:none;"></p>
                                    </div>
                                </div>
                                <div class="fc-form-row fc-two-cols" style="margin-top:10px;">
                                    <div class="fc-form-field">
                                        <label for="fc_sale_date_from"><?php fc_e( 'prod_sale_from' ); ?></label>
                                        <input type="datetime-local" id="fc_sale_date_from" name="fc_sale_date_from" value="<?php echo esc_attr( $data['sale_date_from'] ); ?>" class="fc-input-price">
                                    </div>
                                    <div class="fc-form-field">
                                        <label for="fc_sale_date_to"><?php fc_e( 'prod_sale_until' ); ?></label>
                                        <input type="datetime-local" id="fc_sale_date_to" name="fc_sale_date_to" value="<?php echo esc_attr( $data['sale_date_to'] ); ?>" class="fc-input-price">
                                    </div>
                                </div>
                                <p class="fc-field-hint"><?php fc_e( 'prod_set_dates_to_automatically_enable_and_disable_the' ); ?></p>
                            </div>
                        </div>

                        <!-- Atrybuty i warianty (variable only) -->
                        <div class="fc-form-card fc-section-variable" style="<?php echo $data['product_type'] !== 'variable' ? 'display:none;' : ''; ?>">
                            <div class="fc-card-header"><?php fc_e( 'prod_product_attributes' ); ?></div>
                            <div class="fc-card-body">

                                <!-- Attribute Builder (Shopify-style) -->
                                <div class="fc-attr-builder" id="fc_attr_builder">
                                    <div class="fc-attr-list" id="fc_attr_list">
                                        <?php /* Rendered by JS from fcAttributes */ ?>
                                    </div>
                                    <button type="button" class="button" id="fc_add_attr_btn">
                                        <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:4px;"></span>
                                        <?php fc_e( 'attr_add_attribute' ); ?>
                                    </button>
                                </div>

                                <!-- Kombinacje wariantów (auto-generowane) -->
                                <div class="fc-attr-step fc-attr-step-3" id="fc_combinations_wrap" style="<?php echo empty( $data['variants'] ) ? 'display:none;' : ''; ?>">
                                    <h4 style="margin:20px 0 10px;"><?php fc_e( 'prod_variant_combinations' ); ?></h4>

                                    <!-- Globalne ustawienia kombinacji -->
                                    <div class="fc-comb-global-options">
                                        <div class="fc-comb-global-header">
                                            <span class="dashicons dashicons-admin-tools" style="margin-right:4px;"></span>
                                            <?php fc_e( 'prod_set_for_all_combinations' ); ?>
                                            <button type="button" class="fc-comb-global-toggle" id="fc_comb_global_toggle">
                                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                            </button>
                                        </div>
                                        <div class="fc-comb-global-body" id="fc_comb_global_body" style="display:none;">
                                            <div class="fc-comb-global-row">
                                                <div class="fc-comb-global-field">
                                                    <label><?php printf( fc__( 'prod_price' ), $symbol ); ?></label>
                                                    <div class="fc-comb-global-input-wrap">
                                                        <input type="number" id="fc_comb_global_price" step="0.01" min="0" placeholder="0.00" class="fc-comb-input">
                                                        <button type="button" class="button button-small fc-comb-global-apply" data-target="price"><?php fc_e( 'prod_apply' ); ?></button>
                                                    </div>
                                                </div>
                                                <div class="fc-comb-global-field">
                                                    <label><?php printf( fc__( 'prod_sale_price' ), $symbol ); ?></label>
                                                    <div class="fc-comb-global-input-wrap">
                                                        <input type="text" id="fc_comb_global_sale_price" placeholder="<?php echo esc_attr( fc__( 'prod_price_or_percent' ) ); ?>" class="fc-comb-input fc-sale-input">
                                                        <button type="button" class="button button-small fc-comb-global-apply" data-target="sale_price"><?php fc_e( 'prod_apply' ); ?></button>
                                                    </div>
                                                </div>
                                                <div class="fc-comb-global-field">
                                                    <label><?php fc_e( 'prod_stock_status' ); ?></label>
                                                    <div class="fc-comb-global-input-wrap">
                                                        <input type="number" id="fc_comb_global_stock" min="0" step="1" placeholder="∞" class="fc-comb-input">
                                                        <button type="button" class="button button-small fc-comb-global-apply" data-target="stock"><?php fc_e( 'prod_apply' ); ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="fc-comb-global-row" style="margin-top:8px;">
                                                <div class="fc-comb-global-field">
                                                    <label><?php fc_e( 'coupon_status' ); ?></label>
                                                    <div class="fc-comb-global-input-wrap">
                                                        <select id="fc_comb_global_status" class="fc-comb-input">
                                                            <option value="active"><?php fc_e( 'coupon_active' ); ?></option>
                                                            <option value="inactive"><?php fc_e( 'prod_inactive' ); ?></option>
                                                        </select>
                                                        <button type="button" class="button button-small fc-comb-global-apply" data-target="status"><?php fc_e( 'prod_apply' ); ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="fc-combinations-table-wrap">
                                        <table class="wp-list-table widefat striped fc-combinations-table" id="fc_combinations_table">
                                            <thead>
                                                <tr>
                                                    <th><?php fc_e( 'prod_combination' ); ?></th>
                                                    <th><?php fc_e( 'prod_sku' ); ?></th>
                                                    <th><?php printf( fc__( 'prod_price' ), $symbol ); ?> <span class="required">*</span></th>
                                                    <th><?php printf( fc__( 'prod_sale' ), $symbol ); ?></th>
                                                    <th><?php fc_e( 'prod_inventory' ); ?></th>
                                                    <th><?php fc_e( 'attr_image' ); ?></th>
                                                    <th><?php fc_e( 'coupon_status' ); ?></th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="fc_combinations_body">
                                                <?php /* Rows rendered by JS from fc_variants_json */ ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Hidden JSON fields for AJAX-free submission -->
                                <input type="hidden" name="fc_attributes_json" id="fc_attributes_json" value="<?php echo esc_attr( json_encode( $data['attributes'] ) ); ?>">
                                <input type="hidden" name="fc_variants_json" id="fc_variants_json" value="<?php echo esc_attr( json_encode( $data['variants'] ) ); ?>">
                                <?php
                                // Build thumbnail URL map for existing variant images
                                $fc_variant_thumbs = array();
                                foreach ( $data['variants'] as $variant ) {
                                    $imgs = array();
                                    if ( ! empty( $variant['images'] ) && is_array( $variant['images'] ) ) {
                                        $imgs = $variant['images'];
                                    } elseif ( ! empty( $variant['image'] ) ) {
                                        $imgs = array( intval( $variant['image'] ) );
                                    }
                                    foreach ( $imgs as $img_id ) {
                                        $img_id = intval( $img_id );
                                        if ( $img_id > 0 && ! isset( $fc_variant_thumbs[ $img_id ] ) ) {
                                            $url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                                            if ( $url ) {
                                                $fc_variant_thumbs[ $img_id ] = $url;
                                            }
                                        }
                                    }
                                }
                                ?>
                                <script>window.fcVariantThumbs = <?php echo json_encode( (object) $fc_variant_thumbs ); ?>;</script>
                            </div>
                        </div>


                        <!-- Magazyn (tylko simple) -->
                        <div class="fc-form-card fc-section-simple" style="<?php echo $data['product_type'] !== 'simple' ? 'display:none;' : ''; ?>">
                            <div class="fc-card-header"><?php fc_e( 'prod_inventory' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-form-field">
                                    <label for="fc_sku"><?php fc_e( 'prod_sku_product_code' ); ?></label>
                                    <input type="text" id="fc_sku" name="fc_sku" value="<?php echo esc_attr( $data['sku'] ); ?>" placeholder="<?php echo esc_attr( fc__( 'prod_eg_sku' ) ); ?>">
                                </div>

                                <div class="fc-form-row fc-two-cols">
                                    <div class="fc-form-field">
                                        <label for="fc_stock_status"><?php fc_e( 'prod_stock_status_2' ); ?></label>
                                        <select id="fc_stock_status" name="fc_stock_status">
                                            <option value="instock" <?php selected( $data['stock_status'], 'instock' ); ?>><?php fc_e( 'prod_in_stock' ); ?></option>
                                            <option value="outofstock" <?php selected( $data['stock_status'], 'outofstock' ); ?>><?php fc_e( 'prod_out_of_stock' ); ?></option>
                                        </select>
                                    </div>
                                    <div class="fc-form-field">
                                        <label>
                                            <input type="checkbox" name="fc_manage_stock" value="1" <?php checked( $data['manage_stock'], '1' ); ?> id="fc_manage_stock_cb">
                                            <?php fc_e( 'prod_manage_stock' ); ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="fc-form-field fc-stock-qty-field" style="<?php echo $data['manage_stock'] === '1' ? '' : 'display:none;'; ?>">
                                    <label for="fc_stock"><?php fc_e( 'prod_stock_quantity' ); ?></label>
                                    <input type="number" id="fc_stock" name="fc_stock" value="<?php echo esc_attr( $data['stock'] ); ?>" min="0" step="1" placeholder="0">
                                </div>

                                <div class="fc-form-field">
                                    <label for="fc_weight"><?php fc_e( 'prod_weight_kg' ); ?></label>
                                    <input type="number" id="fc_weight" name="fc_weight" value="<?php echo esc_attr( $data['weight'] ); ?>" step="0.01" min="0" placeholder="0,00">
                                </div>

                                <div class="fc-form-row fc-three-cols" style="margin-top:10px;">
                                    <div class="fc-form-field">
                                        <label for="fc_length"><?php fc_e( 'prod_length_cm' ); ?></label>
                                        <input type="number" id="fc_length" name="fc_length" value="<?php echo esc_attr( $data['length'] ); ?>" step="0.01" min="0" placeholder="0">
                                    </div>
                                    <div class="fc-form-field">
                                        <label for="fc_width"><?php fc_e( 'prod_width_cm' ); ?></label>
                                        <input type="number" id="fc_width" name="fc_width" value="<?php echo esc_attr( $data['width'] ); ?>" step="0.01" min="0" placeholder="0">
                                    </div>
                                    <div class="fc-form-field">
                                        <label for="fc_height"><?php fc_e( 'prod_height_cm' ); ?></label>
                                        <input type="number" id="fc_height" name="fc_height" value="<?php echo esc_attr( $data['height'] ); ?>" step="0.01" min="0" placeholder="0">
                                    </div>
                                </div>

                                <div class="fc-form-row fc-two-cols" style="margin-top:10px;">
                                    <div class="fc-form-field">
                                        <label for="fc_min_quantity"><?php fc_e( 'prod_min_order_quantity' ); ?></label>
                                        <input type="number" id="fc_min_quantity" name="fc_min_quantity" value="<?php echo esc_attr( $data['min_quantity'] ); ?>" min="1" step="1" placeholder="1">
                                    </div>
                                    <div class="fc-form-field">
                                        <label for="fc_max_quantity"><?php fc_e( 'prod_max_order_quantity' ); ?></label>
                                        <input type="number" id="fc_max_quantity" name="fc_max_quantity" value="<?php echo esc_attr( $data['max_quantity'] ); ?>" min="1" step="1" placeholder="<?php fc_e( 'coupon_no_limit' ); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Specyfikacja techniczna -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_specifications' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-specifications-builder" id="fc_specs_builder">
                                    <?php if ( ! empty( $data['specifications'] ) ) : ?>
                                        <?php foreach ( $data['specifications'] as $i => $spec ) : ?>
                                            <div class="fc-spec-row" style="display:flex;gap:8px;margin-bottom:6px;align-items:center;">
                                                <input type="text" name="fc_spec_key[]" value="<?php echo esc_attr( $spec['key'] ); ?>" placeholder="<?php fc_e( 'prod_parameter' ); ?>" style="flex:1;">
                                                <input type="text" name="fc_spec_value[]" value="<?php echo esc_attr( $spec['value'] ); ?>" placeholder="<?php fc_e( 'coupon_value' ); ?>" style="flex:1;">
                                                <button type="button" class="button fc-spec-remove" title="<?php fc_e( 'attr_delete' ); ?>" aria-label="<?php echo esc_attr( fc__( 'prod_delete_specification' ) ); ?>">&times;</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button" id="fc_add_spec_btn">
                                    <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-right:4px;"></span>
                                    <?php fc_e( 'prod_add_parameter' ); ?>
                                </button>
                                <script>
                                document.getElementById('fc_add_spec_btn').addEventListener('click', function() {
                                    var row = document.createElement('div');
                                    row.className = 'fc-spec-row';
                                    row.style.cssText = 'display:flex;gap:8px;margin-bottom:6px;align-items:center;';
                                    row.innerHTML = '<input type="text" name="fc_spec_key[]" placeholder="<?php echo esc_js( fc__( 'prod_parameter' ) ); ?>" style="flex:1;">'
                                        + '<input type="text" name="fc_spec_value[]" placeholder="<?php echo esc_js( fc__( 'coupon_value' ) ); ?>" style="flex:1;">'
                                        + '<button type="button" class="button fc-spec-remove" title="<?php echo esc_js( fc__( 'attr_delete' ) ); ?>" aria-label="<?php echo esc_js( fc__( 'prod_delete_specification' ) ); ?>">&times;</button>';
                                    document.getElementById('fc_specs_builder').appendChild(row);
                                    row.querySelector('.fc-spec-remove').addEventListener('click', function() { row.remove(); });
                                });
                                document.querySelectorAll('.fc-spec-remove').forEach(function(btn) {
                                    btn.addEventListener('click', function() { this.closest('.fc-spec-row').remove(); });
                                });
                                </script>
                            </div>
                        </div>

                        <!-- Notatka do zamówienia -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_order_note' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-form-field">
                                    <label for="fc_purchase_note"><?php fc_e( 'prod_note_visible_to_the_customer_after_purchase' ); ?></label>
                                    <textarea id="fc_purchase_note" name="fc_purchase_note" rows="3" placeholder="<?php fc_e( 'prod_e_g_user_manual_warranty_information' ); ?>"><?php echo esc_textarea( $data['purchase_note'] ); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Produkty powiązane -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_related_products' ); ?></div>
                            <div class="fc-card-body">
                                <?php
                                $avail_related = get_posts( array(
                                    'post_type'      => 'fc_product',
                                    'post_status'    => array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder' ),
                                    'posts_per_page' => 200,
                                    'exclude'        => $product_id ? array( $product_id ) : array(),
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                ) );
                                ?>
                                <div class="fc-form-field">
                                    <label><?php fc_e( 'prod_up_sell' ); ?></label>
                                    <p class="fc-field-hint"><?php fc_e( 'prod_products_displayed_as_better_alternatives' ); ?></p>
                                    <input type="text" class="fc-select-filter" placeholder="<?php echo esc_attr( fc__( 'prod_search_product' ) ); ?>" data-target="fc_upsell_ids" style="width:100%;margin-bottom:4px;">
                                    <select name="fc_upsell_ids[]" multiple class="fc-select-products" id="fc_upsell_ids" style="width:100%;min-height:80px;">
                                        <?php foreach ( $avail_related as $ap ) : ?>
                                            <option value="<?php echo esc_attr( $ap->ID ); ?>" <?php echo in_array( $ap->ID, $data['upsell_ids'] ) ? 'selected' : ''; ?>>
                                                <?php echo esc_html( $ap->post_title ); ?> (#<?php echo esc_html( $ap->ID ); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="fc-form-field" style="margin-top:15px;">
                                    <label><?php fc_e( 'prod_cross_sell' ); ?></label>
                                    <p class="fc-field-hint"><?php fc_e( 'prod_products_displayed_in_the_cart_as_complementary_it' ); ?></p>
                                    <input type="text" class="fc-select-filter" placeholder="<?php echo esc_attr( fc__( 'prod_search_product' ) ); ?>" data-target="fc_crosssell_ids" style="width:100%;margin-bottom:4px;">
                                    <select name="fc_crosssell_ids[]" multiple class="fc-select-products" id="fc_crosssell_ids" style="width:100%;min-height:80px;">
                                        <?php foreach ( $avail_related as $ap ) : ?>
                                            <option value="<?php echo esc_attr( $ap->ID ); ?>" <?php echo in_array( $ap->ID, $data['crosssell_ids'] ) ? 'selected' : ''; ?>>
                                                <?php echo esc_html( $ap->post_title ); ?> (#<?php echo esc_html( $ap->ID ); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Prawa kolumna -->
                    <div class="fc-form-sidebar">

                        <!-- Zapisz -->
                        <div class="fc-form-card fc-card-actions">
                            <div class="fc-card-header"><?php fc_e( 'prod_publication' ); ?></div>
                            <div class="fc-card-body">
                                <?php
                                $current_status = $data['post_status'];
                                $statuses = array(
                                    'fc_published' => fc__( 'pt_published' ),
                                    'fc_draft'     => fc__( 'pt_draft' ),
                                    'fc_hidden'    => fc__( 'pt_hidden_2' ),
                                    'fc_preorder'  => fc__( 'pt_preorder' ),
                                );
                                $status_icons = array(
                                    'fc_published' => 'dashicons-visibility',
                                    'fc_draft'     => 'dashicons-edit',
                                    'fc_hidden'    => 'dashicons-hidden',
                                    'fc_preorder'  => 'dashicons-clock',
                                    'fc_private'   => 'dashicons-lock',
                                );
                                ?>

                                <?php if ( $current_status === 'fc_private' ) : ?>
                                    <div class="fc-status-field">
                                        <label>
                                            <span class="dashicons dashicons-lock" id="fc_status_icon"></span>
                                            <?php fc_e( 'prod_status' ); ?>
                                        </label>
                                        <span class="fc-status-badge" style="background:#8e44ad;"><?php fc_e( 'pt_private_2' ); ?></span>
                                        <input type="hidden" name="fc_post_status" value="fc_private">
                                    </div>
                                    <?php
                                    $author = get_userdata( get_post_field( 'post_author', $product_id ) );
                                    if ( $author ) :
                                    ?>
                                        <div class="fc-status-field" style="border-bottom:none;padding-bottom:0;">
                                            <label>
                                                <span class="dashicons dashicons-admin-users"></span>
                                                <?php fc_e( 'prod_author' ); ?>
                                            </label>
                                            <span><?php echo esc_html( $author->display_name ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <div class="fc-status-field">
                                        <label for="fc_post_status">
                                            <span class="dashicons <?php echo esc_attr( $status_icons[ $current_status ] ?? 'dashicons-visibility' ); ?>" id="fc_status_icon"></span>
                                            <?php fc_e( 'prod_status' ); ?>
                                        </label>
                                        <select name="fc_post_status" id="fc_post_status">
                                            <?php foreach ( $statuses as $val => $label ) : ?>
                                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_status, $val ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="fc-publish-date-field" id="fc_publish_date_wrap" style="<?php echo in_array( $current_status, array( 'fc_hidden', 'fc_preorder' ) ) ? '' : 'display:none;'; ?>">
                                        <label for="fc_publish_date">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php fc_e( 'prod_publication_date' ); ?>
                                        </label>
                                        <input type="datetime-local" name="fc_publish_date" id="fc_publish_date"
                                               value="<?php echo esc_attr( $data['publish_date'] ); ?>"
                                               min="<?php echo wp_date( 'Y-m-d\TH:i' ); ?>">
                                        <?php if ( ! empty( $data['publish_date'] ) ) : ?>
                                            <button type="button" class="fc-publish-date-clear" id="fc_publish_date_clear" title="<?php echo esc_attr( fc__( 'prod_clear' ) ); ?>" aria-label="<?php echo esc_attr( fc__( 'prod_clear_date' ) ); ?>">&times;</button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="fc-publish-date-field" id="fc_shipping_date_wrap" style="<?php echo $current_status === 'fc_preorder' ? '' : 'display:none;'; ?>">
                                        <label for="fc_shipping_date">
                                            <span class="dashicons dashicons-airplane"></span>
                                            <?php fc_e( 'prod_shipping_date' ); ?>
                                        </label>
                                        <input type="datetime-local" name="fc_shipping_date" id="fc_shipping_date"
                                               value="<?php echo esc_attr( $data['shipping_date'] ); ?>"
                                               min="<?php echo wp_date( 'Y-m-d\TH:i' ); ?>">
                                        <?php if ( ! empty( $data['shipping_date'] ) ) : ?>
                                            <button type="button" class="fc-publish-date-clear" id="fc_shipping_date_clear" title="<?php echo esc_attr( fc__( 'prod_clear' ) ); ?>" aria-label="<?php echo esc_attr( fc__( 'prod_clear_date' ) ); ?>">&times;</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="button button-primary button-large fc-btn-save">
                                    <?php echo $is_edit ? fc__( 'attr_save_changes' ) : fc__( 'pt_add_product' ); ?>
                                </button>

                                <?php if ( $is_edit ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fc_delete_product&product_id=' . $product_id ), 'fc_delete_product_' . $product_id ) ); ?>"
                                       class="fc-btn-delete"
                                       onclick="return confirm('<?php fc_e( 'prod_are_you_sure_you_want_to_move_this_product_to_tras' ); ?>');">
                                        <?php fc_e( 'prod_move_to_trash' ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Kategorie -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'pt_categories' ); ?> <span class="fc-checked-count" id="fc_cat_count"><?php
                                $checked_count = count( $data['categories'] );
                                if ( $checked_count > 0 ) echo '(' . $checked_count . ')';
                            ?></span></div>
                            <div class="fc-card-body">
                                <div class="fc-categories-list">
                                    <?php if ( ! empty( $all_categories ) && ! is_wp_error( $all_categories ) ) : ?>
                                        <?php self::render_category_tree( $all_categories, $data['categories'] ); ?>
                                    <?php else : ?>
                                        <p class="fc-field-hint"><?php fc_e( 'prod_no_categories' ); ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="#" class="fc-add-cat-toggle" id="fc_add_cat_toggle" role="button" aria-expanded="false"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php fc_e( 'prod_add_category' ); ?></a>
                                <div class="fc-add-cat-form" id="fc_add_cat_form" style="display:none;">
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_cat_name"><?php fc_e( 'attr_name' ); ?> <span class="required">*</span></label>
                                        <input type="text" id="fc_new_cat_name" class="fc-input-sm" placeholder="<?php fc_e( 'prod_category_name' ); ?>">
                                    </div>
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_cat_slug"><?php fc_e( 'prod_slug' ); ?></label>
                                        <input type="text" id="fc_new_cat_slug" class="fc-input-sm" placeholder="<?php fc_e( 'prod_category_slug' ); ?>">
                                    </div>
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_cat_parent"><?php fc_e( 'pt_parent_category' ); ?></label>
                                        <select id="fc_new_cat_parent" class="fc-input-sm">
                                            <option value="0"><?php fc_e( 'prod_none' ); ?></option>
                                            <?php if ( ! empty( $all_categories ) && ! is_wp_error( $all_categories ) ) : ?>
                                                <?php foreach ( $all_categories as $cat ) : ?>
                                                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html( $cat->name ); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_cat_desc"><?php fc_e( 'coupon_description' ); ?></label>
                                        <textarea id="fc_new_cat_desc" class="fc-input-sm" rows="3" placeholder="<?php fc_e( 'prod_category_description_optional' ); ?>"></textarea>
                                    </div>
                                    <div class="fc-add-cat-actions">
                                        <button type="button" id="fc_add_category_btn" class="button button-primary button-small"><?php fc_e( 'prod_add_category' ); ?></button>
                                        <button type="button" id="fc_add_cat_cancel" class="button button-small"><?php fc_e( 'coupon_cancel' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Marka -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'pt_brand' ); ?></div>
                            <div class="fc-card-body">
                                <select name="product_brand" id="fc_product_brand" class="fc-select-brand">
                                    <option value="0"><?php fc_e( 'prod_select_brand' ); ?></option>
                                    <?php if ( ! empty( $all_brands ) && ! is_wp_error( $all_brands ) ) : ?>
                                        <?php foreach ( $all_brands as $brand ) : ?>
                                            <option value="<?php echo $brand->term_id; ?>" <?php selected( $data['brand'], $brand->term_id ); ?>><?php echo esc_html( $brand->name ); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <a href="#" class="fc-add-cat-toggle" id="fc_add_brand_toggle" role="button" aria-expanded="false"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php fc_e( 'prod_add_brand' ); ?></a>
                                <div class="fc-add-cat-form" id="fc_add_brand_form" style="display:none;">
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_brand_name"><?php fc_e( 'attr_name' ); ?> <span class="required">*</span></label>
                                        <input type="text" id="fc_new_brand_name" class="fc-input-sm" placeholder="<?php fc_e( 'prod_brand_name' ); ?>">
                                    </div>
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_brand_slug"><?php fc_e( 'prod_slug' ); ?></label>
                                        <input type="text" id="fc_new_brand_slug" class="fc-input-sm" placeholder="<?php fc_e( 'prod_brand_slug' ); ?>">
                                    </div>
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_brand_desc"><?php fc_e( 'coupon_description' ); ?></label>
                                        <textarea id="fc_new_brand_desc" class="fc-input-sm" rows="3" placeholder="<?php fc_e( 'prod_brand_description_optional' ); ?>"></textarea>
                                    </div>
                                    <div class="fc-add-cat-field">
                                        <label><?php fc_e( 'prod_logo' ); ?></label>
                                        <div class="fc-brand-logo-field">
                                            <input type="hidden" id="fc_new_brand_logo" value="">
                                            <div class="fc-brand-logo-preview" id="fc_new_brand_logo_preview" style="display:none;"></div>
                                            <button type="button" class="button button-small fc-brand-logo-upload-btn" id="fc_new_brand_logo_btn"><?php fc_e( 'inv_select_logo' ); ?></button>
                                            <button type="button" class="button button-small fc-brand-logo-remove-btn" id="fc_new_brand_logo_remove" style="display:none;"><?php fc_e( 'attr_delete' ); ?></button>
                                        </div>
                                    </div>
                                    <div class="fc-add-cat-actions">
                                        <button type="button" id="fc_add_brand_btn" class="button button-primary button-small"><?php fc_e( 'prod_add_brand' ); ?></button>
                                        <button type="button" id="fc_add_brand_cancel" class="button button-small"><?php fc_e( 'coupon_cancel' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Jednostka miary -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_unit_of_measure' ); ?></div>
                            <div class="fc-card-body">
                                <select name="fc_unit" id="fc_unit" class="fc-select-unit">
                                    <option value=""><?php fc_e( 'prod_select_unit' ); ?></option>
                                    <?php foreach ( $all_units as $unit ) : ?>
                                        <option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $data['unit'], $unit ); ?>><?php echo esc_html( FC_Units_Admin::label( $unit ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="#" class="fc-add-cat-toggle" id="fc_add_unit_toggle" role="button" aria-expanded="false"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php fc_e( 'prod_add_unit' ); ?></a>
                                <div class="fc-add-cat-form" id="fc_add_unit_form" style="display:none;">
                                    <div class="fc-add-cat-field">
                                        <label for="fc_new_unit_name"><?php fc_e( 'prod_unit_name' ); ?> <span class="required">*</span></label>
                                        <input type="text" id="fc_new_unit_name" class="fc-input-sm" placeholder="<?php fc_e( 'prod_e_g_pcs_kg_pack' ); ?>">
                                    </div>
                                    <div class="fc-add-cat-actions">
                                        <button type="button" id="fc_add_unit_btn" class="button button-primary button-small"><?php fc_e( 'prod_add_unit' ); ?></button>
                                        <button type="button" id="fc_add_unit_cancel" class="button button-small"><?php fc_e( 'coupon_cancel' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Zdjęcie główne -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_main_photo' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-thumbnail-preview" id="fc_thumbnail_preview">
                                    <?php if ( $data['thumbnail_id'] ) : ?>
                                        <?php echo wp_get_attachment_image( $data['thumbnail_id'], 'medium' ); ?>
                                    <?php else : ?>
                                        <div class="fc-upload-placeholder">
                                            <span class="dashicons dashicons-format-image"></span>
                                            <p><?php fc_e( 'prod_click_to_add_photo' ); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="product_thumbnail" id="product_thumbnail" value="<?php echo esc_attr( $data['thumbnail_id'] ); ?>">
                                <div class="fc-thumbnail-actions">
                                    <button type="button" class="button" id="fc_set_thumbnail"><?php fc_e( 'prod_select_photo' ); ?></button>
                                    <button type="button" class="button fc-btn-remove-thumb" id="fc_remove_thumbnail" style="<?php echo $data['thumbnail_id'] ? '' : 'display:none;'; ?>"><?php fc_e( 'attr_delete' ); ?></button>
                                </div>
                            </div>
                        </div>

                        <!-- Galeria -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_product_gallery' ); ?></div>
                            <div class="fc-card-body">
                                <div class="fc-gallery-grid" id="fc_gallery_grid">
                                    <?php foreach ( $data['gallery'] as $img_id ) :
                                        $img_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                                        if ( $img_url ) : ?>
                                            <div class="fc-gallery-thumb" data-id="<?php echo esc_attr( $img_id ); ?>">
                                                <img src="<?php echo esc_url( $img_url ); ?>" alt="">
                                                <button type="button" class="fc-gallery-remove-btn" aria-label="<?php echo esc_attr( fc__( 'prod_delete_photo' ) ); ?>">&times;</button>
                                            </div>
                                        <?php endif;
                                    endforeach; ?>
                                </div>
                                <input type="hidden" name="fc_gallery" id="fc_gallery_input" value="<?php echo esc_attr( implode( ',', $data['gallery'] ) ); ?>">
                                <button type="button" class="button" id="fc_add_gallery"><?php fc_e( 'prod_add_photos' ); ?></button>
                            </div>
                        </div>

                        <!-- Klasa podatkowa -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_tax_class' ); ?></div>
                            <div class="fc-card-body">
                                <?php
                                $saved_tc = get_option( 'fc_tax_classes', array() );
                                $tax_classes = array( '' => fc__( 'prod_standard' ) . ' (' . get_option( 'fc_tax_rate', '23' ) . '%)' );
                                if ( ! empty( $saved_tc ) && is_array( $saved_tc ) ) {
                                    foreach ( $saved_tc as $tc_key => $tc_data ) {
                                        $tax_classes[ $tc_key ] = $tc_data['label'] . ' (' . $tc_data['rate'] . '%)';
                                    }
                                } else {
                                    $tax_classes['reduced']       = fc__( 'prod_reduced_8' );
                                    $tax_classes['super_reduced'] = fc__( 'prod_super_reduced_5' );
                                    $tax_classes['zero']          = fc__( 'prod_zero_0' );
                                }
                                $tax_classes = apply_filters( 'fc_tax_classes', $tax_classes );
                                ?>
                                <select name="fc_tax_class" id="fc_tax_class">
                                    <?php foreach ( $tax_classes as $tc_val => $tc_label ) : ?>
                                        <option value="<?php echo esc_attr( $tc_val ); ?>" <?php selected( $data['tax_class'], $tc_val ); ?>><?php echo esc_html( $tc_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="fc-field-hint"><?php fc_e( 'prod_default_rate' ); ?><?php echo esc_html( get_option( 'fc_tax_name', 'VAT' ) . ' ' . get_option( 'fc_tax_rate', '23' ) . '%' ); ?></p>
                            </div>
                        </div>

                        <!-- Klasa wysyłkowa -->
                        <div class="fc-form-card fc-section-simple fc-section-variable">
                            <div class="fc-card-header"><?php fc_e( 'pt_shipping_class' ); ?></div>
                            <div class="fc-card-body">
                                <?php
                                $current_sc = 0;
                                if ( $is_edit ) {
                                    $sc_terms = wp_get_object_terms( $product_id, 'fc_shipping_class', array( 'fields' => 'ids' ) );
                                    $current_sc = ! empty( $sc_terms ) && ! is_wp_error( $sc_terms ) ? $sc_terms[0] : 0;
                                }
                                ?>
                                <?php
                                $sc_rules = get_option( 'fc_shipping_class_rules', array() );
                                $has_rules = ! empty( $sc_rules ) && is_array( $sc_rules );
                                ?>
                                <select name="fc_shipping_class" id="fc_shipping_class">
                                    <option value="0"><?php echo $has_rules ? fc__( 'prod_automatic_by_rules' ) : fc__( 'prod_no_shipping_class' ); ?></option>
                                    <?php if ( ! empty( $all_shipping_classes ) && ! is_wp_error( $all_shipping_classes ) ) : ?>
                                        <?php foreach ( $all_shipping_classes as $sc ) : ?>
                                            <option value="<?php echo $sc->term_id; ?>" <?php selected( $current_sc, $sc->term_id ); ?>><?php echo esc_html( $sc->name ); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Odznaki produktu -->
                        <div class="fc-form-card">
                            <div class="fc-card-header"><?php fc_e( 'prod_product_badges' ); ?></div>
                            <div class="fc-card-body">
                                <?php
                                $available_badges = array(
                                    'bestseller'    => array( 'label' => fc__( 'prod_bestseller' ), 'color' => '#e74c3c' ),
                                    'new'           => array( 'label' => fc__( 'prod_new' ), 'color' => '#27ae60' ),
                                    'recommended'   => array( 'label' => fc__( 'prod_featured' ), 'color' => '#2980b9' ),
                                    'free_shipping' => array( 'label' => fc__( 'coupon_free_shipping' ), 'color' => '#8e44ad' ),
                                    'limited'       => array( 'label' => fc__( 'prod_limited_edition' ), 'color' => '#e67e22' ),
                                    'last_items'    => array( 'label' => fc__( 'prod_last_items' ), 'color' => '#c0392b' ),
                                    'eco'           => array( 'label' => fc__( 'prod_eco' ), 'color' => '#16a085' ),
                                    'handmade'      => array( 'label' => fc__( 'prod_handmade' ), 'color' => '#d35400' ),
                                );
                                foreach ( $available_badges as $badge_key => $badge_info ) : ?>
                                    <label style="display:inline-flex;align-items:center;gap:6px;margin-right:10px;margin-bottom:6px;">
                                        <input type="checkbox" name="fc_badges[]" value="<?php echo esc_attr( $badge_key ); ?>"
                                            <?php checked( in_array( $badge_key, $data['badges'] ) ); ?>>
                                        <span style="background:<?php echo esc_attr( $badge_info['color'] ); ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;"><?php echo esc_html( $badge_info['label'] ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </form>
        </div>
        <?php
    }

    /**
     * Zapisywanie produktu
     */
    /**
     * Renderuj hierarchiczne drzewo kategorii z checkboxami
     */
    public static function render_category_tree( $categories, $selected, $parent_id = 0, $depth = 0 ) {
        $children = array_filter( $categories, function( $cat ) use ( $parent_id ) {
            return (int) $cat->parent === (int) $parent_id;
        } );

        if ( empty( $children ) ) return;

        foreach ( $children as $cat ) {
            $indent = str_repeat( '&mdash; ', $depth );
            ?>
            <label class="fc-category-item" style="<?php echo $depth > 0 ? 'padding-left:' . ( $depth * 18 ) . 'px;' : ''; ?>">
                <input type="checkbox" name="product_categories[]" value="<?php echo $cat->term_id; ?>"
                    <?php checked( in_array( $cat->term_id, $selected ) ); ?>>
                <?php echo $indent . esc_html( $cat->name ); ?>
            </label>
            <?php
            self::render_category_tree( $categories, $selected, $cat->term_id, $depth + 1 );
        }
    }

    /**
     * Zapisywanie produktu
     */
    public static function handle_save() {
        if ( ! isset( $_POST['fc_product_nonce'] ) || ! wp_verify_nonce( $_POST['fc_product_nonce'], 'fc_save_product' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        // Sprawdź uprawnienia do edycji konkretnego produktu
        if ( $product_id && ! current_user_can( 'edit_post', $product_id ) ) {
            wp_die( fc__( 'prod_no_permissions_to_edit_this_product' ) );
        }

        $post_data = array(
            'post_type'    => 'fc_product',
            'post_title'   => sanitize_text_field( $_POST['product_title'] ),
            'post_content' => wp_kses_post( $_POST['product_content'] ),
            'post_excerpt' => sanitize_textarea_field( $_POST['product_excerpt'] ),
            'post_status'  => isset( $_POST['fc_post_status'] ) && in_array( $_POST['fc_post_status'], array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private' ) ) ? $_POST['fc_post_status'] : 'fc_published',
        );

        if ( $product_id ) {
            $post_data['ID'] = $product_id;
            wp_update_post( $post_data );
        } else {
            $product_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $product_id ) || ! $product_id ) {
            wp_die( fc__( 'prod_product_save_error' ) );
        }

        // Zaplanowana data publikacji (dla statusu ukryty lub preorder)
        $publish_date = '';
        if ( in_array( $post_data['post_status'], array( 'fc_hidden', 'fc_preorder' ) ) && ! empty( $_POST['fc_publish_date'] ) ) {
            $publish_date = sanitize_text_field( $_POST['fc_publish_date'] );
            update_post_meta( $product_id, '_fc_publish_date', $publish_date );

            // Zaplanuj event crona
            $timestamp = strtotime( $publish_date );
            if ( $timestamp && $timestamp > time() ) {
                wp_clear_scheduled_hook( 'fc_scheduled_publish', array( $product_id ) );
                wp_schedule_single_event( $timestamp, 'fc_scheduled_publish', array( $product_id ) );
            }
        } else {
            delete_post_meta( $product_id, '_fc_publish_date' );
            wp_clear_scheduled_hook( 'fc_scheduled_publish', array( $product_id ) );
        }

        // Data wysyłki (tylko dla statusu preorder)
        if ( $post_data['post_status'] === 'fc_preorder' && ! empty( $_POST['fc_shipping_date'] ) ) {
            update_post_meta( $product_id, '_fc_shipping_date', sanitize_text_field( $_POST['fc_shipping_date'] ) );
        } else {
            delete_post_meta( $product_id, '_fc_shipping_date' );
        }

        // Typ produktu
        $product_type = isset( $_POST['fc_product_type'] ) ? sanitize_text_field( $_POST['fc_product_type'] ) : 'simple';
        if ( ! in_array( $product_type, array( 'simple', 'variable' ) ) ) $product_type = 'simple';
        update_post_meta( $product_id, '_fc_product_type', $product_type );

        // Meta
        $meta_fields = array(
            '_fc_price'        => 'fc_price',
            '_fc_sale_price'   => 'fc_sale_price',
            '_fc_sku'          => 'fc_sku',
            '_fc_stock'        => 'fc_stock',
            '_fc_stock_status' => 'fc_stock_status',
            '_fc_weight'       => 'fc_weight',
        );

        foreach ( $meta_fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $product_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        // Obsługa procentowej ceny promocyjnej (np. "10%" lub "-10%")
        if ( isset( $_POST['fc_sale_price'] ) ) {
            $raw_sale = trim( $_POST['fc_sale_price'] );
            if ( preg_match( '/^-?(\d+(?:[.,]\d+)?)\s*%$/', $raw_sale, $m ) ) {
                $percent = floatval( str_replace( ',', '.', $m[1] ) );
                $regular = floatval( get_post_meta( $product_id, '_fc_price', true ) );
                if ( $regular > 0 && $percent > 0 && $percent < 100 ) {
                    $calculated = round( $regular * ( 1 - $percent / 100 ), 2 );
                    update_post_meta( $product_id, '_fc_sale_price', $calculated );
                    update_post_meta( $product_id, '_fc_sale_percent', $percent );
                } else {
                    delete_post_meta( $product_id, '_fc_sale_percent' );
                }
            } else {
                // Kwota bezpośrednia — wyczyść procent
                delete_post_meta( $product_id, '_fc_sale_percent' );

                // Walidacja: cena promocyjna nie może być wyższa niż regularna
                $sale_val = floatval( $raw_sale );
                $regular  = floatval( get_post_meta( $product_id, '_fc_price', true ) );
                if ( $sale_val > 0 && $regular > 0 && $sale_val >= $regular ) {
                    delete_post_meta( $product_id, '_fc_sale_price' );
                }
            }
        }

        // Walidacja unikalności SKU
        $sku = get_post_meta( $product_id, '_fc_sku', true );
        if ( ! empty( $sku ) ) {
            $existing = get_posts( array(
                'post_type'      => 'fc_product',
                'meta_key'       => '_fc_sku',
                'meta_value'     => $sku,
                'exclude'        => array( $product_id ),
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'post_status'    => array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private' ),
            ) );
            if ( ! empty( $existing ) ) {
                // SKU nie jest unikalne — dodaj sufiks
                update_post_meta( $product_id, '_fc_sku', $sku . '-' . $product_id );
            }
        }

        update_post_meta( $product_id, '_fc_manage_stock', isset( $_POST['fc_manage_stock'] ) ? '1' : '0' );

        // Atrybuty i warianty
        if ( $product_type === 'variable' ) {
            // Zapisz atrybuty z JSON
            $attributes = array();
            if ( ! empty( $_POST['fc_attributes_json'] ) ) {
                $decoded = json_decode( stripslashes( $_POST['fc_attributes_json'] ), true );
                if ( is_array( $decoded ) ) {
                    foreach ( $decoded as $attr ) {
                        // Sanityzacja zagnieżdżonych wartości atrybutów
                        $clean_values = array();
                        if ( isset( $attr['values'] ) && is_array( $attr['values'] ) ) {
                            foreach ( $attr['values'] as $val ) {
                                if ( is_array( $val ) ) {
                                    $clean_val = array();
                                    foreach ( $val as $vk => $vv ) {
                                        $clean_val[ sanitize_text_field( $vk ) ] = is_numeric( $vv ) ? $vv : sanitize_text_field( $vv );
                                    }
                                    $clean_values[] = $clean_val;
                                } else {
                                    $clean_values[] = sanitize_text_field( $val );
                                }
                            }
                        }
                        $attributes[] = array(
                            'name'   => sanitize_text_field( $attr['name'] ?? '' ),
                            'type'   => in_array( $attr['type'] ?? 'text', array( 'text', 'color', 'image' ) ) ? $attr['type'] : 'text',
                            'values' => $clean_values,
                        );
                    }
                }
            }
            update_post_meta( $product_id, '_fc_attributes', $attributes );

            // Sync atrybuty do globalnych
            if ( class_exists( 'FC_Attributes_Admin' ) ) {
                FC_Attributes_Admin::sync_from_product( $attributes );
            }

            // Zapisz warianty (kombinacje) — odczyt z pojedynczego pola JSON
            $variants = array();
            if ( ! empty( $_POST['fc_variants_json'] ) ) {
                $raw_variants = json_decode( stripslashes( $_POST['fc_variants_json'] ), true );
                if ( is_array( $raw_variants ) ) {
                    foreach ( $raw_variants as $v ) {
                        if ( empty( $v['name'] ) ) continue;
                        $attr_vals = array();
                        if ( ! empty( $v['attribute_values'] ) && is_array( $v['attribute_values'] ) ) {
                            $attr_vals = $v['attribute_values'];
                        }
                        // Handle images — array of IDs
                        $images = array();
                        if ( ! empty( $v['images'] ) && is_array( $v['images'] ) ) {
                            $images = array_filter( array_map( 'absint', $v['images'] ) );
                        }
                        $raw_price = isset( $v['price'] ) ? trim( $v['price'] ) : '';
                        if ( $raw_price === '' ) $raw_price = '0';

                        // Obsługa procentowej ceny promocyjnej wariantu (np. "15%" lub "-15%")
                        $variant_sale_price = sanitize_text_field( $v['sale_price'] ?? '' );
                        $variant_sale_percent = '';
                        if ( preg_match( '/^-?(\d+(?:[.,]\d+)?)\s*%$/', $variant_sale_price, $m ) ) {
                            $pct = floatval( str_replace( ',', '.', $m[1] ) );
                            $reg = floatval( $raw_price );
                            if ( $reg > 0 && $pct > 0 && $pct < 100 ) {
                                $variant_sale_price = strval( round( $reg * ( 1 - $pct / 100 ), 2 ) );
                                $variant_sale_percent = $pct;
                            }
                        }

                        $variants[] = array(
                            'id'               => ! empty( $v['id'] ) ? sanitize_text_field( $v['id'] ) : self::variant_hash( $attr_vals ),
                            'name'             => sanitize_text_field( $v['name'] ),
                            'attribute_values' => $attr_vals,
                            'sku'              => sanitize_text_field( $v['sku'] ?? '' ),
                            'price'            => sanitize_text_field( $raw_price ),
                            'sale_price'       => $variant_sale_price,
                            'sale_percent'     => $variant_sale_percent,
                            'stock'            => sanitize_text_field( $v['stock'] ?? '' ),
                            'images'           => array_values( $images ),
                            'main_image'       => ! empty( $v['main_image'] ) ? absint( $v['main_image'] ) : ( ! empty( $images ) ? reset( $images ) : 0 ),
                            'status'           => sanitize_text_field( $v['status'] ?? 'active' ),
                        );
                    }
                }
            }
            update_post_meta( $product_id, '_fc_variants', $variants );

            // Ustaw cenę główną = najniższa cena wariantu (do wyświetlania na liście)
            if ( ! empty( $variants ) ) {
                $prices = array_filter( array_column( $variants, 'price' ), function( $p ) { return $p !== ''; } );
                if ( ! empty( $prices ) ) {
                    update_post_meta( $product_id, '_fc_price', min( $prices ) );
                }
                // Wyczyść cenę promocyjną produktu — warianty mają własne ceny promocyjne
                delete_post_meta( $product_id, '_fc_sale_price' );
            }
        } else {
            delete_post_meta( $product_id, '_fc_variants' );
            delete_post_meta( $product_id, '_fc_attributes' );
        }

        // Thumbnail
        if ( ! empty( $_POST['product_thumbnail'] ) ) {
            set_post_thumbnail( $product_id, absint( $_POST['product_thumbnail'] ) );
        } else {
            delete_post_thumbnail( $product_id );
        }

        // Galeria
        if ( isset( $_POST['fc_gallery'] ) ) {
            $gallery = array_filter( array_map( 'absint', explode( ',', $_POST['fc_gallery'] ) ) );
            update_post_meta( $product_id, '_fc_gallery', $gallery );
        }

        // Kategorie
        if ( isset( $_POST['product_categories'] ) ) {
            $cat_ids = array_map( 'absint', $_POST['product_categories'] );
            wp_set_object_terms( $product_id, $cat_ids, 'fc_product_cat' );
        } else {
            wp_set_object_terms( $product_id, array(), 'fc_product_cat' );
        }

        // Marka
        if ( ! empty( $_POST['product_brand'] ) ) {
            $brand_id = absint( $_POST['product_brand'] );
            wp_set_object_terms( $product_id, array( $brand_id ), 'fc_product_brand' );
        } else {
            wp_set_object_terms( $product_id, array(), 'fc_product_brand' );
        }

        // Jednostka miary
        if ( isset( $_POST['fc_unit'] ) ) {
            update_post_meta( $product_id, '_fc_unit', sanitize_text_field( $_POST['fc_unit'] ) );
        }

        // Wymiary (K1)
        $dim_fields = array( '_fc_length' => 'fc_length', '_fc_width' => 'fc_width', '_fc_height' => 'fc_height' );
        foreach ( $dim_fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $product_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        // Klasa podatkowa (K2)
        if ( isset( $_POST['fc_tax_class'] ) ) {
            update_post_meta( $product_id, '_fc_tax_class', sanitize_text_field( $_POST['fc_tax_class'] ) );
        }

        // Min/Max ilość (W4)
        if ( isset( $_POST['fc_min_quantity'] ) ) {
            update_post_meta( $product_id, '_fc_min_quantity', sanitize_text_field( $_POST['fc_min_quantity'] ) );
        }
        if ( isset( $_POST['fc_max_quantity'] ) ) {
            update_post_meta( $product_id, '_fc_max_quantity', sanitize_text_field( $_POST['fc_max_quantity'] ) );
        }

        // Daty promocji (W6)
        $sale_from = sanitize_text_field( $_POST['fc_sale_date_from'] ?? '' );
        $sale_to   = sanitize_text_field( $_POST['fc_sale_date_to'] ?? '' );
        update_post_meta( $product_id, '_fc_sale_date_from', $sale_from );
        update_post_meta( $product_id, '_fc_sale_date_to', $sale_to );
        if ( $sale_to ) {
            $ts = strtotime( $sale_to );
            if ( $ts && $ts > time() ) {
                wp_clear_scheduled_hook( 'fc_sale_end', array( $product_id ) );
                wp_schedule_single_event( $ts, 'fc_sale_end', array( $product_id ) );
            }
        } else {
            wp_clear_scheduled_hook( 'fc_sale_end', array( $product_id ) );
        }

        // Klasa wysyłkowa (W8)
        if ( isset( $_POST['fc_shipping_class'] ) ) {
            $sc_id = absint( $_POST['fc_shipping_class'] );
            if ( $sc_id > 0 ) {
                wp_set_object_terms( $product_id, array( $sc_id ), 'fc_shipping_class' );
            } else {
                // Brak ręcznego wyboru — próba auto-przypisania wg reguł
                $auto_sc = self::auto_assign_shipping_class( $product_id );
                if ( $auto_sc > 0 ) {
                    wp_set_object_terms( $product_id, array( $auto_sc ), 'fc_shipping_class' );
                } else {
                    wp_set_object_terms( $product_id, array(), 'fc_shipping_class' );
                }
            }
        }

        // Specyfikacja (W9)
        $specs = array();
        if ( ! empty( $_POST['fc_spec_key'] ) && is_array( $_POST['fc_spec_key'] ) ) {
            $keys = $_POST['fc_spec_key'];
            $vals = $_POST['fc_spec_value'] ?? array();
            for ( $i = 0; $i < count( $keys ); $i++ ) {
                $k = sanitize_text_field( $keys[ $i ] );
                $v = sanitize_text_field( $vals[ $i ] ?? '' );
                if ( $k !== '' ) {
                    $specs[] = array( 'key' => $k, 'value' => $v );
                }
            }
        }
        update_post_meta( $product_id, '_fc_specifications', $specs );

        // Notatka zakupowa (W3)
        if ( isset( $_POST['fc_purchase_note'] ) ) {
            update_post_meta( $product_id, '_fc_purchase_note', sanitize_textarea_field( $_POST['fc_purchase_note'] ) );
        }

        // Odznaki (N9)
        $badges = isset( $_POST['fc_badges'] ) && is_array( $_POST['fc_badges'] ) ? array_map( 'sanitize_text_field', $_POST['fc_badges'] ) : array();
        update_post_meta( $product_id, '_fc_badges', $badges );

        // Produkty powiązane (K3)
        $upsell_ids = isset( $_POST['fc_upsell_ids'] ) && is_array( $_POST['fc_upsell_ids'] ) ? array_map( 'absint', $_POST['fc_upsell_ids'] ) : array();
        update_post_meta( $product_id, '_fc_upsell_ids', $upsell_ids );
        $crosssell_ids = isset( $_POST['fc_crosssell_ids'] ) && is_array( $_POST['fc_crosssell_ids'] ) ? array_map( 'absint', $_POST['fc_crosssell_ids'] ) : array();
        update_post_meta( $product_id, '_fc_crosssell_ids', $crosssell_ids );

        // Cena efektywna (promo jeśli istnieje, inaczej regularna)
        self::update_effective_price( $product_id );

        wp_redirect( admin_url( 'admin.php?page=fc-product-add&product_id=' . $product_id . '&saved=1' ) );
        exit;
    }

    /**
     * Oblicz i zapisz cenę efektywną produktu (do filtrowania)
     */
    public static function update_effective_price( $product_id ) {
        $type = get_post_meta( $product_id, '_fc_product_type', true );

        if ( $type === 'variable' ) {
            $variants = get_post_meta( $product_id, '_fc_variants', true );
            $effective_prices = array();
            if ( is_array( $variants ) ) {
                foreach ( $variants as $v ) {
                    if ( ( $v['status'] ?? '' ) !== 'active' ) continue;
                    $sale    = isset( $v['sale_price'] ) && $v['sale_price'] !== '' ? floatval( $v['sale_price'] ) : 0;
                    $regular = isset( $v['price'] ) && $v['price'] !== '' ? floatval( $v['price'] ) : 0;
                    $effective_prices[] = ( $sale > 0 && $sale < $regular ) ? $sale : $regular;
                }
            }
            $eff = ! empty( $effective_prices ) ? min( $effective_prices ) : 0;
        } else {
            $regular = floatval( get_post_meta( $product_id, '_fc_price', true ) );
            $sale    = floatval( get_post_meta( $product_id, '_fc_sale_price', true ) );
            $eff     = ( $sale > 0 && $sale < $regular ) ? $sale : $regular;
        }

        update_post_meta( $product_id, '_fc_effective_price', $eff );
    }

    /**
     * Usuwanie produktu
     */
    public static function handle_delete() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_die( fc__( 'prod_missing_product_id' ) );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_delete_product_' . $product_id ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        // Zapamiętaj oryginalny status przed przeniesieniem do kosza
        update_post_meta( $product_id, '_fc_pre_trash_status', get_post_status( $product_id ) );
        wp_update_post( array( 'ID' => $product_id, 'post_status' => 'fc_trash' ) );
        wp_redirect( admin_url( 'edit.php?post_type=fc_product&saved=trashed' ) );
        exit;
    }

    /**
     * Przywracanie produktu z kosza
     */
    public static function handle_restore() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_die( fc__( 'prod_missing_product_id' ) );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_restore_product_' . $product_id ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        // Przywróć oryginalny status sprzed przeniesienia do kosza
        $prev = get_post_meta( $product_id, '_fc_pre_trash_status', true );
        $restore_to = in_array( $prev, array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private' ), true ) ? $prev : 'fc_hidden';
        wp_update_post( array( 'ID' => $product_id, 'post_status' => $restore_to ) );
        delete_post_meta( $product_id, '_fc_pre_trash_status' );
        wp_redirect( admin_url( 'edit.php?post_type=fc_product&post_status=fc_trash&saved=restored' ) );
        exit;
    }

    /**
     * Trwałe usunięcie produktu
     */
    public static function handle_force_delete() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_die( fc__( 'prod_missing_product_id' ) );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_force_delete_product_' . $product_id ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        wp_delete_post( $product_id, true );
        wp_redirect( admin_url( 'edit.php?post_type=fc_product&post_status=fc_trash&saved=deleted' ) );
        exit;
    }

    /**
     * Opróżnij kosz — trwale usuwa wszystkie produkty fc_trash i trash
     */
    public static function handle_empty_trash() {
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'fc_empty_trash' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $trashed = get_posts( array(
            'post_type'      => 'fc_product',
            'post_status'    => array( 'fc_trash', 'trash' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        foreach ( $trashed as $id ) {
            wp_delete_post( $id, true );
        }

        wp_redirect( admin_url( 'edit.php?post_type=fc_product&saved=emptied' ) );
        exit;
    }

    /**
     * AJAX: dodaj kategorię
     */
    public static function ajax_add_category() {
        check_ajax_referer( 'fc_admin_nonce' );

        if ( ! current_user_can( 'manage_categories' ) ) {
            wp_send_json_error( fc__( 'access_denied' ) );
        }

        $name = sanitize_text_field( $_POST['category_name'] );
        if ( empty( $name ) ) {
            wp_send_json_error( fc__( 'empty_name' ) );
        }

        $args = array();

        // Slug
        $slug = isset( $_POST['category_slug'] ) ? sanitize_title( $_POST['category_slug'] ) : '';
        if ( ! empty( $slug ) ) {
            $args['slug'] = $slug;
        }

        // Opis
        $desc = isset( $_POST['category_description'] ) ? sanitize_textarea_field( $_POST['category_description'] ) : '';
        if ( ! empty( $desc ) ) {
            $args['description'] = $desc;
        }

        // Kategoria nadrzędna
        $parent = isset( $_POST['category_parent'] ) ? absint( $_POST['category_parent'] ) : 0;
        if ( $parent > 0 ) {
            $args['parent'] = $parent;
        }

        $term = wp_insert_term( $name, 'fc_product_cat', $args );
        if ( is_wp_error( $term ) ) {
            wp_send_json_error( $term->get_error_message() );
        }

        $term_obj = get_term( $term['term_id'], 'fc_product_cat' );
        $parent_name = '';
        if ( $parent > 0 ) {
            $parent_term = get_term( $parent, 'fc_product_cat' );
            if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                $parent_name = $parent_term->name;
            }
        }

        wp_send_json_success( array(
            'term_id'     => $term['term_id'],
            'name'        => $term_obj->name,
            'slug'        => $term_obj->slug,
            'parent'      => $parent,
            'parent_name' => $parent_name,
        ) );
    }

    /**
     * AJAX: dodaj markę
     */
    public static function ajax_add_brand() {
        check_ajax_referer( 'fc_admin_nonce' );

        if ( ! current_user_can( 'manage_categories' ) ) {
            wp_send_json_error( fc__( 'access_denied' ) );
        }

        $name = sanitize_text_field( $_POST['brand_name'] );
        if ( empty( $name ) ) {
            wp_send_json_error( fc__( 'empty_name' ) );
        }

        $args = array();

        $slug = isset( $_POST['brand_slug'] ) ? sanitize_title( $_POST['brand_slug'] ) : '';
        if ( ! empty( $slug ) ) {
            $args['slug'] = $slug;
        }

        $desc = isset( $_POST['brand_description'] ) ? sanitize_textarea_field( $_POST['brand_description'] ) : '';
        if ( ! empty( $desc ) ) {
            $args['description'] = $desc;
        }

        $term = wp_insert_term( $name, 'fc_product_brand', $args );
        if ( is_wp_error( $term ) ) {
            wp_send_json_error( $term->get_error_message() );
        }

        // Logo
        $logo_id = isset( $_POST['brand_logo'] ) ? absint( $_POST['brand_logo'] ) : 0;
        if ( $logo_id > 0 ) {
            update_term_meta( $term['term_id'], '_fc_brand_logo', $logo_id );
            update_post_meta( $logo_id, '_fc_is_brand_logo', 1 );
        }

        $term_obj = get_term( $term['term_id'], 'fc_product_brand' );

        wp_send_json_success( array(
            'term_id' => $term['term_id'],
            'name'    => $term_obj->name,
            'slug'    => $term_obj->slug,
        ) );
    }

    /**
     * AJAX: Dodaj nową jednostkę miary
     */
    public static function ajax_add_unit() {
        check_ajax_referer( 'fc_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( fc__( 'access_denied' ) );
        }

        $name = sanitize_text_field( $_POST['unit_name'] ?? '' );
        if ( empty( $name ) ) {
            wp_send_json_error( fc__( 'empty_unit_name' ) );
        }

        $units = FC_Units_Admin::get_all();

        // Sprawdź czy już istnieje
        if ( in_array( $name, $units, true ) ) {
            wp_send_json_error( fc__( 'unit_already_exists' ) );
        }

        $units[] = $name;
        sort( $units );
        update_option( 'fc_product_units', $units );

        wp_send_json_success( array( 'name' => $name, 'label' => FC_Units_Admin::label( $name ) ) );
    }

    /* ================================================================
       Brand logo — pola na stronie taksonomii
       ================================================================ */

    /**
     * Pole logo na formularzu dodawania marki (lewa strona)
     */
    public static function brand_add_logo_field() {
        ?>
        <div class="form-field">
            <label><?php fc_e( 'prod_brand_logo' ); ?></label>
            <input type="hidden" name="fc_brand_logo" id="fc_brand_logo" value="">
            <div id="fc_brand_logo_preview" class="fc-brand-logo-preview" style="display:none;"></div>
            <p>
                <button type="button" class="button fc-brand-logo-upload-btn" id="fc_brand_logo_btn"><?php fc_e( 'inv_select_logo' ); ?></button>
                <button type="button" class="button fc-brand-logo-remove-btn" id="fc_brand_logo_remove" style="display:none;"><?php fc_e( 'attr_delete' ); ?></button>
            </p>
        </div>
        <?php
    }

    /**
     * Pole logo na formularzu edycji marki (tabela)
     */
    public static function brand_edit_logo_field( $term ) {
        $logo_id  = absint( get_term_meta( $term->term_id, '_fc_brand_logo', true ) );
        $logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
        ?>
        <tr class="form-field">
            <th scope="row"><label><?php fc_e( 'prod_brand_logo' ); ?></label></th>
            <td>
                <input type="hidden" name="fc_brand_logo" id="fc_brand_logo" value="<?php echo esc_attr( $logo_id ); ?>">
                <div id="fc_brand_logo_preview" class="fc-brand-logo-preview" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:120px;height:auto;">
                    <?php endif; ?>
                </div>
                <p>
                    <button type="button" class="button fc-brand-logo-upload-btn" id="fc_brand_logo_btn"><?php fc_e( 'inv_select_logo' ); ?></button>
                    <button type="button" class="button fc-brand-logo-remove-btn" id="fc_brand_logo_remove" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>><?php fc_e( 'attr_delete' ); ?></button>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Zapis logo marki
     */
    public static function brand_save_logo( $term_id ) {
        if ( isset( $_POST['fc_brand_logo'] ) ) {
            $logo_id = absint( $_POST['fc_brand_logo'] );
            update_term_meta( $term_id, '_fc_brand_logo', $logo_id );
            // Oznacz attachment jako logo marki
            if ( $logo_id > 0 ) {
                update_post_meta( $logo_id, '_fc_is_brand_logo', 1 );
            }
        }
    }

    /**
     * Filtruj media library — pokaż tylko loga marek gdy fc_brand_logos=true
     */
    public static function filter_brand_logo_media( $query ) {
        if ( empty( $_REQUEST['query']['fc_brand_logos'] ) ) {
            return $query;
        }

        // Pobierz wszystkie attachment IDs używane jako loga marek
        $brand_terms = get_terms( array(
            'taxonomy'   => 'fc_product_brand',
            'hide_empty' => false,
            'fields'     => 'ids',
        ) );

        $logo_ids = array();
        if ( ! is_wp_error( $brand_terms ) ) {
            foreach ( $brand_terms as $tid ) {
                $lid = absint( get_term_meta( $tid, '_fc_brand_logo', true ) );
                if ( $lid > 0 ) {
                    $logo_ids[] = $lid;
                }
            }
        }

        // Dodaj też attachmenty oznaczone meta _fc_is_brand_logo
        $meta_ids = get_posts( array(
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => array(
                array(
                    'key'   => '_fc_is_brand_logo',
                    'value' => '1',
                ),
            ),
        ) );
        $logo_ids = array_unique( array_merge( $logo_ids, $meta_ids ) );

        if ( empty( $logo_ids ) ) {
            // Brak logotypów — wymuś pusty wynik
            $query['post__in'] = array( 0 );
        } else {
            $query['post__in'] = $logo_ids;
        }

        return $query;
    }

    /**
     * Duplikowanie produktu (N1)
     */
    public static function handle_duplicate() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_die( fc__( 'prod_missing_product_id' ) );
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_duplicate_product_' . $product_id ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $post = get_post( $product_id );
        if ( ! $post || $post->post_type !== 'fc_product' ) {
            wp_die( fc__( 'prod_product_does_not_exist' ) );
        }

        $new_post = array(
            'post_type'    => 'fc_product',
            'post_title'   => $post->post_title . ' (' . fc__( 'prod_copy' ) . ')',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'fc_draft',
        );
        $new_id = wp_insert_post( $new_post );
        if ( is_wp_error( $new_id ) || ! $new_id ) {
            wp_die( fc__( 'prod_duplication_error' ) );
        }

        // Kopiuj wszystkie meta (pomijając stanowe meta, które nie powinny być kopiowane)
        $meta = get_post_meta( $product_id );
        $skip_meta = array( '_fc_pre_trash_status', '_fc_total_sales', '_fc_publish_date' );
        foreach ( $meta as $key => $values ) {
            if ( in_array( $key, $skip_meta, true ) ) continue;
            foreach ( $values as $value ) {
                $value = maybe_unserialize( $value );
                update_post_meta( $new_id, $key, $value );
            }
        }

        // Wyczyść SKU (musi być unikalne)
        $sku = get_post_meta( $new_id, '_fc_sku', true );
        if ( $sku ) {
            update_post_meta( $new_id, '_fc_sku', $sku . '-copy-' . $new_id );
        }

        // Kopiuj taksonomie
        $taxonomies = array( 'fc_product_cat', 'fc_product_brand', 'fc_shipping_class' );
        foreach ( $taxonomies as $tax ) {
            $terms = wp_get_object_terms( $product_id, $tax, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $terms ) ) {
                wp_set_object_terms( $new_id, $terms, $tax );
            }
        }

        // Kopiuj thumbnail
        $thumb_id = get_post_thumbnail_id( $product_id );
        if ( $thumb_id ) {
            set_post_thumbnail( $new_id, $thumb_id );
        }

        wp_redirect( admin_url( 'admin.php?page=fc-product-add&product_id=' . $new_id . '&saved=1' ) );
        exit;
    }

    /**
     * Cron: Zakończenie promocji (W6)
     */
    public static function handle_sale_end( $product_id ) {
        delete_post_meta( $product_id, '_fc_sale_price' );
        delete_post_meta( $product_id, '_fc_sale_percent' );
        delete_post_meta( $product_id, '_fc_sale_date_from' );
        delete_post_meta( $product_id, '_fc_sale_date_to' );
        self::update_effective_price( $product_id );
    }

    /**
     * Automatyczne przypisanie klasy wysyłkowej wg reguł.
     * Działa jako fallback — tylko gdy użytkownik nie wybrał klasy ręcznie.
     *
     * @param int $product_id
     * @return int ID przypisanej klasy lub 0
     */
    public static function auto_assign_shipping_class( $product_id ) {
        $rules = get_option( 'fc_shipping_class_rules', array() );
        if ( empty( $rules ) || ! is_array( $rules ) ) return 0;

        // Pobierz dane produktu (z POST jeśli dostępne, inaczej z meta)
        $weight = isset( $_POST['fc_weight'] ) && $_POST['fc_weight'] !== '' ? floatval( $_POST['fc_weight'] ) : floatval( get_post_meta( $product_id, '_fc_weight', true ) );
        $length = isset( $_POST['fc_length'] ) && $_POST['fc_length'] !== '' ? floatval( $_POST['fc_length'] ) : floatval( get_post_meta( $product_id, '_fc_length', true ) );
        $width  = isset( $_POST['fc_width'] )  && $_POST['fc_width']  !== '' ? floatval( $_POST['fc_width'] )  : floatval( get_post_meta( $product_id, '_fc_width', true ) );
        $height = isset( $_POST['fc_height'] ) && $_POST['fc_height'] !== '' ? floatval( $_POST['fc_height'] ) : floatval( get_post_meta( $product_id, '_fc_height', true ) );

        // Sortuj wg priorytetu (niższy = ważniejszy)
        usort( $rules, function( $a, $b ) { return ( $a['priority'] ?? 10 ) - ( $b['priority'] ?? 10 ); } );

        foreach ( $rules as $rule ) {
            $match = true;

            // Zakres wagi: produkt musi mieścić się w podanym zakresie
            if ( $rule['min_weight'] !== '' && $weight < floatval( $rule['min_weight'] ) ) {
                $match = false;
            }
            if ( $match && $rule['max_weight'] !== '' && $weight > floatval( $rule['max_weight'] ) ) {
                $match = false;
            }

            // Wymiary: produkt musi PRZEKROCZYĆ KTÓRYKOLWIEK podany limit
            // np. max_length=60 → klasa pasuje gdy długość produktu > 60cm
            $has_dim_rule = ( $rule['max_length'] !== '' || $rule['max_width'] !== '' || $rule['max_height'] !== '' );
            if ( $match && $has_dim_rule ) {
                $exceeds_any = false;
                if ( $rule['max_length'] !== '' && $length > floatval( $rule['max_length'] ) ) $exceeds_any = true;
                if ( $rule['max_width'] !== ''  && $width  > floatval( $rule['max_width'] ) )  $exceeds_any = true;
                if ( $rule['max_height'] !== '' && $height > floatval( $rule['max_height'] ) ) $exceeds_any = true;
                if ( ! $exceeds_any ) $match = false;
            }

            if ( $match ) {
                return intval( $rule['class_id'] );
            }
        }

        return 0;
    }
}
