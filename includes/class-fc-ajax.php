<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obsługa AJAX: koszyk (dodawanie, usuwanie, aktualizacja)
 */
class FC_Ajax {

    public static function init() {
        // Dodaj do koszyka
        add_action( 'wp_ajax_fc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_fc_add_to_cart', array( __CLASS__, 'add_to_cart' ) );

        // Usuń z koszyka
        add_action( 'wp_ajax_fc_remove_from_cart', array( __CLASS__, 'remove_from_cart' ) );
        add_action( 'wp_ajax_nopriv_fc_remove_from_cart', array( __CLASS__, 'remove_from_cart' ) );

        // Aktualizuj ilość
        add_action( 'wp_ajax_fc_update_cart', array( __CLASS__, 'update_cart' ) );
        add_action( 'wp_ajax_nopriv_fc_update_cart', array( __CLASS__, 'update_cart' ) );

        // Pobierz mini koszyk
        add_action( 'wp_ajax_fc_get_cart_count', array( __CLASS__, 'get_cart_count' ) );
        add_action( 'wp_ajax_nopriv_fc_get_cart_count', array( __CLASS__, 'get_cart_count' ) );

        // Mini koszyk (popup)
        add_action( 'wp_ajax_fc_get_mini_cart', array( __CLASS__, 'get_mini_cart' ) );
        add_action( 'wp_ajax_nopriv_fc_get_mini_cart', array( __CLASS__, 'get_mini_cart' ) );

        // Sprawdź e-mail (checkout)
        add_action( 'wp_ajax_fc_check_email', array( __CLASS__, 'check_email' ) );
        add_action( 'wp_ajax_nopriv_fc_check_email', array( __CLASS__, 'check_email' ) );

        // Sprawdź nazwę użytkownika (rejestracja)
        add_action( 'wp_ajax_fc_check_username', array( __CLASS__, 'check_username' ) );
        add_action( 'wp_ajax_nopriv_fc_check_username', array( __CLASS__, 'check_username' ) );

        // Podgląd e-mail — dane zamówienia (admin)
        add_action( 'wp_ajax_fc_get_order_preview_data', array( __CLASS__, 'get_order_preview_data' ) );
    }

    public static function add_to_cart() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $quantity   = isset( $_POST['quantity'] ) ? max( 1, intval( $_POST['quantity'] ) ) : 1;
        $variant_id = isset( $_POST['variant_id'] ) ? sanitize_text_field( $_POST['variant_id'] ) : '';

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => fc__( 'invalid_product' ) ) );
        }

        $result = FC_Cart::add_item( $product_id, $quantity, $variant_id );

        if ( $result ) {
            wp_send_json_success( array(
                'message'    => fc__( 'product_added_to_cart' ),
                'cart_count' => FC_Cart::get_count(),
                'cart_total' => fc_format_price( FC_Cart::get_total() ),
                'mini_cart'  => self::render_mini_cart_items(),
            ) );
        } else {
            wp_send_json_error( array( 'message' => fc__( 'cannot_add_product' ) ) );
        }
    }

    public static function remove_from_cart() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $cart_key = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        FC_Cart::remove_item( $cart_key );

        wp_send_json_success( array(
            'cart_count' => FC_Cart::get_count(),
            'cart_total' => fc_format_price( FC_Cart::get_total() ),
        ) );
    }

    public static function update_cart() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $cart_key   = isset( $_POST['product_id'] ) ? sanitize_text_field( $_POST['product_id'] ) : '';
        $quantity   = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;

        FC_Cart::update_item( $cart_key, $quantity );

        // Przelicz wiersz
        $cart = FC_Cart::get_cart();
        $line_total = 0;
        if ( isset( $cart[ $cart_key ] ) ) {
            $item = $cart[ $cart_key ];
            $variant_id = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
            $line_total = FC_Cart::get_product_price( $item['product_id'], $variant_id ) * max( 0, $quantity );
        }

        wp_send_json_success( array(
            'cart_count'  => FC_Cart::get_count(),
            'cart_total'  => fc_format_price( FC_Cart::get_total() ),
            'line_total'  => fc_format_price( $line_total ),
        ) );
    }

    public static function get_cart_count() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        wp_send_json_success( array(
            'cart_count' => FC_Cart::get_count(),
        ) );
    }

    public static function get_mini_cart() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        wp_send_json_success( array(
            'cart_count' => FC_Cart::get_count(),
            'cart_total' => fc_format_price( FC_Cart::get_total() ),
            'mini_cart'  => self::render_mini_cart_items(),
        ) );
    }

    /**
     * Renderuje HTML elementów mini-koszyka
     */
    public static function render_mini_cart_items() {
        $cart = FC_Cart::get_cart();
        if ( empty( $cart ) ) {
            return '<p class="fc-mini-cart-empty">' . fc__( 'cart_empty' ) . '</p>';
        }

        ob_start();
        foreach ( $cart as $cart_key => $item ) {
            $variant_id   = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
            $price        = FC_Cart::get_product_price( $item['product_id'], $variant_id );
            $line_total   = $price * $item['quantity'];
            $variant_name = '';
            $variant_attrs = array();
            $thumb_html   = '';

            if ( $variant_id !== '' ) {
                $variants = get_post_meta( $item['product_id'], '_fc_variants', true );
                $found_v = FC_Cart::find_variant( $variants, $variant_id );
                if ( $found_v ) {
                    $variant_name = $found_v['name'];
                    $variant_attrs = isset( $found_v['attribute_values'] ) && is_array( $found_v['attribute_values'] ) ? $found_v['attribute_values'] : array();
                    $v_img = 0;
                    if ( ! empty( $found_v['main_image'] ) ) {
                        $v_img = intval( $found_v['main_image'] );
                    } elseif ( ! empty( $found_v['images'] ) && is_array( $found_v['images'] ) ) {
                        $v_img = intval( $found_v['images'][0] );
                    }
                    if ( $v_img > 0 ) {
                        $thumb_html = wp_get_attachment_image( $v_img, 'thumbnail' );
                    }
                }
            }
            if ( empty( $thumb_html ) ) {
                $thumb_html = get_the_post_thumbnail( $item['product_id'], 'thumbnail' );
            }
            if ( empty( $thumb_html ) ) {
                $thumb_html = '<div class="fc-mini-cart-no-img"></div>';
            }

            // Oblicz max stock dla inputa ilości
            $max_stock = 99;
            if ( $variant_id !== '' && isset( $found_v ) && $found_v ) {
                if ( isset( $found_v['stock'] ) && $found_v['stock'] !== '' ) {
                    $max_stock = max( 1, intval( $found_v['stock'] ) );
                }
            } else {
                $manage_stock = get_post_meta( $item['product_id'], '_fc_manage_stock', true );
                if ( $manage_stock === '1' ) {
                    $product_stock = get_post_meta( $item['product_id'], '_fc_stock', true );
                    if ( $product_stock !== '' ) {
                        $max_stock = max( 1, intval( $product_stock ) );
                    }
                }
            }
            ?>
            <div class="fc-mini-cart-item" data-cart-key="<?php echo esc_attr( $cart_key ); ?>">
                <div class="fc-mini-cart-thumb"><?php echo $thumb_html; ?></div>
                <div class="fc-mini-cart-details">
                    <a href="<?php echo esc_url( get_permalink( $item['product_id'] ) ); ?>" class="fc-mini-cart-name">
                        <?php echo esc_html( get_the_title( $item['product_id'] ) ); ?>
                    </a>
                    <?php if ( ! empty( $variant_attrs ) ) : ?>
                        <span class="fc-mini-cart-variant"><?php
                            $vp = array();
                            foreach ( $variant_attrs as $an => $av ) {
                                $vp[] = esc_html( $an ) . ': ' . esc_html( $av );
                            }
                            echo implode( ', ', $vp );
                        ?></span>
                    <?php elseif ( $variant_name ) : ?>
                        <span class="fc-mini-cart-variant"><?php echo esc_html( $variant_name ); ?></span>
                    <?php endif; ?>
                    <span class="fc-mini-cart-qty-price">
                        <span class="fc-mini-cart-qty-controls">
                            <button type="button" class="fc-mini-qty-btn fc-mini-qty-minus" data-cart-key="<?php echo esc_attr( $cart_key ); ?>">−</button>
                            <input type="number" class="fc-mini-qty-input" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" max="<?php echo esc_attr( $max_stock ); ?>" data-cart-key="<?php echo esc_attr( $cart_key ); ?>">
                            <button type="button" class="fc-mini-qty-btn fc-mini-qty-plus" data-cart-key="<?php echo esc_attr( $cart_key ); ?>">+</button>
                        </span>
                        &times; <?php echo fc_format_price( $price ); ?>
                    </span>
                </div>
                <button class="fc-mini-cart-remove" data-cart-key="<?php echo esc_attr( $cart_key ); ?>" title="<?php fc_e( 'remove' ); ?>">&times;</button>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Sprawdź, czy e-mail jest przypisany do istniejącego konta (AJAX)
     */
    public static function check_email() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => fc__( 'invalid_email_address' ) ) );
        }

        $exists = email_exists( $email );
        wp_send_json_success( array( 'exists' => (bool) $exists ) );
    }

    /**
     * Sprawdź, czy nazwa użytkownika jest już zajęta (AJAX)
     */
    public static function check_username() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $username = sanitize_user( $_POST['username'] ?? '' );
        if ( empty( $username ) || strlen( $username ) < 3 ) {
            wp_send_json_error( array( 'message' => fc__( 'username_too_short' ) ) );
        }

        $exists = username_exists( $username );
        wp_send_json_success( array( 'exists' => (bool) $exists ) );
    }

    /**
     * AJAX: Pobierz dane zamówienia do podglądu e-mail
     */
    public static function get_order_preview_data() {
        check_ajax_referer( 'fc_email_preview', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => fc__( 'access_denied' ) ) );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $lang     = sanitize_key( $_POST['email_lang'] ?? get_option( 'fc_frontend_lang', 'pl' ) );
        $data = FC_Settings::get_order_preview_data( $order_id, $lang );
        wp_send_json_success( $data );
    }
}
