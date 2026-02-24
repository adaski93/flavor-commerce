<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Checkout: przetwarzanie zamówienia
 */
class FC_Checkout {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'process_checkout' ) );
        add_action( 'wp_ajax_fc_stripe_checkout', array( __CLASS__, 'ajax_stripe_checkout' ) );
        add_action( 'wp_ajax_nopriv_fc_stripe_checkout', array( __CLASS__, 'ajax_stripe_checkout' ) );
    }

    // ── Shared helpers ──────────────────────────────────────────────

    /**
     * Return field labels map for billing validation messages.
     */
    private static function get_field_labels() {
        return array(
            'billing_first_name' => fc__( 'first_name' ),
            'billing_last_name'  => fc__( 'last_name' ),
            'billing_email'      => fc__( 'email' ),
            'billing_phone'      => fc__( 'phone' ),
            'billing_address'    => fc__( 'street_address' ),
            'billing_city'       => fc__( 'city' ),
            'billing_postcode'   => fc__( 'postcode' ),
            'billing_company'    => fc__( 'company_name' ),
            'billing_tax_no'     => fc__( 'tax_id' ),
            'billing_crn'        => fc__( 'company_registration_number' ),
        );
    }

    /**
     * Validate checkout fields.
     *
     * @return array [ 'errors' => [], 'account_type' => '', 'ship_different' => bool, 'billing_country' => '' ]
     */
    private static function validate_checkout_fields() {
        $account_type = sanitize_text_field( $_POST['account_type'] ?? 'private' );
        $errors       = array();
        $field_labels = self::get_field_labels();

        // Required billing fields
        if ( $account_type === 'company' ) {
            $required = array( 'billing_company', 'billing_tax_no', 'billing_crn', 'billing_email', 'billing_phone', 'billing_address', 'billing_city', 'billing_postcode' );
        } else {
            $required = array( 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone', 'billing_address', 'billing_city', 'billing_postcode' );
        }

        foreach ( $required as $field ) {
            if ( empty( $_POST[ $field ] ) ) {
                $label = $field_labels[ $field ] ?? $field;
                $errors[] = sprintf( fc__( 'field_required' ), $label );
            }
        }

        // Email validation
        if ( ! is_email( sanitize_email( $_POST['billing_email'] ?? '' ) ) ) {
            $errors[] = fc__( 'invalid_email' );
        }

        // Check if email is already registered (guests only)
        if ( ! is_user_logged_in() && is_email( $_POST['billing_email'] ?? '' ) && email_exists( sanitize_email( $_POST['billing_email'] ) ) ) {
            $errors[] = fc__( 'email_already_registered_checkout' );
        }

        // Shipping address validation
        $ship_different = ! empty( $_POST['ship_to_different'] );
        if ( $ship_different ) {
            if ( $account_type === 'company' ) {
                $ship_required = array(
                    'shipping_company'  => fc__( 'shipping_company' ),
                    'shipping_address'  => fc__( 'shipping_address_field' ),
                    'shipping_city'     => fc__( 'shipping_city' ),
                    'shipping_postcode' => fc__( 'shipping_postcode' ),
                );
            } else {
                $ship_required = array(
                    'shipping_first_name' => fc__( 'shipping_first_name' ),
                    'shipping_last_name'  => fc__( 'shipping_last_name' ),
                    'shipping_address'    => fc__( 'shipping_address_field' ),
                    'shipping_city'       => fc__( 'shipping_city' ),
                    'shipping_postcode'   => fc__( 'shipping_postcode' ),
                );
            }
            foreach ( $ship_required as $field => $label ) {
                if ( empty( $_POST[ $field ] ) ) {
                    $errors[] = sprintf( fc__( 'field_required' ), $label );
                }
            }
        }

        // Country validation
        $allowed_countries = FC_Shortcodes::get_allowed_countries();
        $billing_country   = sanitize_text_field( $_POST['billing_country'] ?? 'PL' );
        if ( ! array_key_exists( $billing_country, $allowed_countries ) ) {
            $errors[] = fc__( 'billing_country_not_supported' );
        }
        if ( $ship_different ) {
            $shipping_country = sanitize_text_field( $_POST['shipping_country'] ?? 'PL' );
            if ( ! array_key_exists( $shipping_country, $allowed_countries ) ) {
                $errors[] = fc__( 'shipping_country_not_supported' );
            }
        }

        // Shipping method vs country validation + shipping class availability
        $shipping_method_index = intval( $_POST['shipping_method'] ?? -1 );
        $shipping_methods      = get_option( 'fc_shipping_methods', array() );
        if ( is_array( $shipping_methods ) && isset( $shipping_methods[ $shipping_method_index ] ) ) {
            $sm = $shipping_methods[ $shipping_method_index ];
            $sm_countries = isset( $sm['countries'] ) && is_array( $sm['countries'] ) ? $sm['countries'] : array();
            if ( ! empty( $sm_countries ) && ! in_array( $billing_country, $sm_countries, true ) ) {
                $errors[] = fc__( 'shipping_method_not_available' );
            }
            // Sprawdź dostępność metody wg klas wysyłkowych
            $cart_classes = FC_Cart::get_cart_shipping_classes();
            $effective    = FC_Cart::get_effective_shipping( $sm, $cart_classes );
            if ( ! $effective['available'] ) {
                $errors[] = fc__( 'shipping_method_not_available' );
            }
        }

        return array(
            'errors'          => $errors,
            'account_type'    => $account_type,
            'ship_different'  => $ship_different,
            'billing_country' => $billing_country,
        );
    }

    /**
     * Build customer data array from POST.
     *
     * @return array [ 'customer' => [...], 'notes' => '', 'payment_method' => '', 'shipping_method_name' => '', 'shipping_cost' => 0 ]
     */
    private static function prepare_checkout_data( $account_type, $ship_different ) {
        // Customer billing data
        $customer = array(
            'account_type' => $account_type,
            'first_name'   => sanitize_text_field( $_POST['billing_first_name'] ?? '' ),
            'last_name'    => sanitize_text_field( $_POST['billing_last_name'] ?? '' ),
            'email'        => sanitize_email( $_POST['billing_email'] ),
            'phone_prefix' => sanitize_text_field( $_POST['billing_phone_prefix'] ?? '+48' ),
            'phone'        => sanitize_text_field( $_POST['billing_phone'] ),
            'address'      => sanitize_text_field( $_POST['billing_address'] ),
            'city'         => sanitize_text_field( $_POST['billing_city'] ),
            'postcode'     => sanitize_text_field( $_POST['billing_postcode'] ),
            'country'      => sanitize_text_field( $_POST['billing_country'] ?? 'PL' ),
            'company'      => sanitize_text_field( $_POST['billing_company'] ?? '' ),
            'tax_no'       => sanitize_text_field( $_POST['billing_tax_no'] ?? '' ),
            'crn'          => sanitize_text_field( $_POST['billing_crn'] ?? '' ),
        );

        // Shipping address
        if ( $ship_different ) {
            $customer['shipping'] = array(
                'first_name' => sanitize_text_field( $_POST['shipping_first_name'] ?? '' ),
                'last_name'  => sanitize_text_field( $_POST['shipping_last_name'] ?? '' ),
                'company'    => sanitize_text_field( $_POST['shipping_company'] ?? '' ),
                'address'    => sanitize_text_field( $_POST['shipping_address'] ?? '' ),
                'city'       => sanitize_text_field( $_POST['shipping_city'] ?? '' ),
                'postcode'   => sanitize_text_field( $_POST['shipping_postcode'] ?? '' ),
                'country'    => sanitize_text_field( $_POST['shipping_country'] ?? 'PL' ),
            );
        }

        $notes          = sanitize_textarea_field( $_POST['order_notes'] ?? '' );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? 'cod' );

        // Shipping method cost
        $shipping_method_name  = '';
        $shipping_cost         = 0;
        $shipping_methods      = get_option( 'fc_shipping_methods', array() );
        $shipping_method_index = intval( $_POST['shipping_method'] ?? -1 );

        if ( is_array( $shipping_methods ) && isset( $shipping_methods[ $shipping_method_index ] ) ) {
            $sm = $shipping_methods[ $shipping_method_index ];
            $shipping_method_name = sanitize_text_field( $sm['name'] );

            // Wylicz efektywny koszt z uwzględnieniem klas wysyłkowych
            $cart_classes = FC_Cart::get_cart_shipping_classes();
            $effective    = FC_Cart::get_effective_shipping( $sm, $cart_classes );
            $shipping_cost = $effective['cost'];

            // Free shipping threshold (per method)
            $method_free_threshold = isset( $sm['free_threshold'] ) && $sm['free_threshold'] !== '' ? floatval( $sm['free_threshold'] ) : 0;
            if ( $method_free_threshold > 0 && FC_Cart::get_total() >= $method_free_threshold ) {
                $shipping_cost = 0;
            }

            // Coupon free shipping
            if ( class_exists( 'FC_Coupons' ) && FC_Coupons::has_free_shipping() ) {
                $shipping_cost = 0;
            }
        }

        return array(
            'customer'             => $customer,
            'notes'                => $notes,
            'payment_method'       => $payment_method,
            'shipping_method_name' => $shipping_method_name,
            'shipping_cost'        => $shipping_cost,
        );
    }

    /**
     * Handle optional account registration during checkout.
     *
     * @return array [ 'errors' => [], 'user_id' => int|null ]
     */
    private static function maybe_register_account() {
        if ( is_user_logged_in() || empty( $_POST['fc_create_account'] ) ) {
            return array( 'errors' => array(), 'user_id' => null );
        }

        $errors        = array();
        $reg_password  = $_POST['fc_reg_password'] ?? '';
        $reg_password2 = $_POST['fc_reg_password2'] ?? '';
        $reg_email     = sanitize_email( $_POST['billing_email'] );
        $display_name  = sanitize_text_field( $_POST['fc_reg_display_name'] ?? '' );

        if ( strlen( $reg_password ) < 6 ) {
            $errors[] = fc__( 'password_min_length' );
        }
        if ( $reg_password !== $reg_password2 ) {
            $errors[] = fc__( 'passwords_not_matching' );
        }

        if ( ! empty( $errors ) ) {
            return array( 'errors' => $errors, 'user_id' => null );
        }

        $username = sanitize_user( strstr( $reg_email, '@', true ) );
        if ( username_exists( $username ) ) {
            $username .= wp_rand( 10, 999 );
        }

        $user_id = wp_create_user( $username, $reg_password, $reg_email );
        if ( is_wp_error( $user_id ) ) {
            return array( 'errors' => array( $user_id->get_error_message() ), 'user_id' => null );
        }

        if ( ! empty( $display_name ) ) {
            wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
        }

        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        return array( 'errors' => array(), 'user_id' => $user_id );
    }

    /**
     * Post-order processing: save Stripe intent, reduce stock, sync meta, clear cart.
     */
    private static function finalize_order( $order_id, $payment_method, $customer, $ship_different ) {
        // Save Stripe Payment Intent ID
        if ( $payment_method === 'stripe' && ! empty( $_POST['fc_stripe_intent_id'] ) ) {
            update_post_meta( $order_id, '_fc_stripe_intent_id', sanitize_text_field( $_POST['fc_stripe_intent_id'] ) );
        }

        do_action( 'fc_order_created', $order_id, $payment_method );

        // Reduce stock
        self::reduce_stock( $order_id );

        // Sync user meta
        if ( is_user_logged_in() ) {
            self::sync_user_meta( $customer, $ship_different );
        }

        // Clear cart & coupons
        FC_Cart::clear();
        if ( class_exists( 'FC_Coupons' ) ) {
            if ( session_status() === PHP_SESSION_ACTIVE ) {
                unset( $_SESSION['fc_coupons'] );
                unset( $_SESSION['fc_coupon'] );
            }
        }
    }

    // ── Main checkout methods ───────────────────────────────────────

    /**
     * Przetwarzanie formularza zamówienia (POST redirect)
     */
    public static function process_checkout() {
        if ( ! isset( $_POST['fc_checkout_submit'] ) ) {
            return;
        }

        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) {
            session_start();
        }

        if ( ! wp_verify_nonce( $_POST['fc_checkout_nonce'] ?? '', 'fc_checkout' ) ) {
            wp_die( fc__( 'security_error' ) );
        }

        if ( FC_Cart::is_empty() ) {
            return;
        }

        // Validate
        $validation = self::validate_checkout_fields();
        if ( ! empty( $validation['errors'] ) ) {
            $_SESSION['fc_checkout_errors'] = $validation['errors'];
            $safe_post = $_POST;
            unset( $safe_post['fc_reg_password'], $safe_post['fc_reg_password2'] );
            $_SESSION['fc_checkout_data'] = array_map( 'sanitize_text_field', $safe_post );
            return;
        }

        // Prepare data
        $data = self::prepare_checkout_data( $validation['account_type'], $validation['ship_different'] );

        // Register account
        $reg = self::maybe_register_account();
        if ( ! empty( $reg['errors'] ) ) {
            $_SESSION['fc_checkout_errors'] = $reg['errors'];
            $safe_post = $_POST;
            unset( $safe_post['fc_reg_password'], $safe_post['fc_reg_password2'] );
            $_SESSION['fc_checkout_data'] = array_map( 'sanitize_text_field', $safe_post );
            return;
        }

        // Create order
        $order_id = self::create_order( $data['customer'], $data['notes'], $data['payment_method'], $data['shipping_method_name'], $data['shipping_cost'] );
        if ( ! $order_id ) {
            return;
        }

        // Finalize
        self::finalize_order( $order_id, $data['payment_method'], $data['customer'], $validation['ship_different'] );

        // Redirect to thank you page
        $thank_you_url = get_permalink( get_option( 'fc_page_podziekowanie' ) );
        $redirect_args = array( 'order_id' => $order_id );
        if ( $data['payment_method'] === 'stripe' ) {
            $redirect_args['stripe'] = '1';
        }
        // Always include token — needed for guest orders AND for guests who
        // registered during checkout (session/cookie may not persist through Stripe redirect)
        $order_token = get_post_meta( $order_id, '_fc_order_token', true );
        if ( $order_token ) {
            $redirect_args['token'] = $order_token;
        }

        // Non-Stripe: send email now
        if ( $data['payment_method'] !== 'stripe' ) {
            self::send_order_email( $order_id );
        }

        wp_redirect( add_query_arg( $redirect_args, $thank_you_url ) );
        exit;
    }

    /**
     * AJAX: Create order for Stripe payments (before payment confirmation)
     */
    public static function ajax_stripe_checkout() {
        if ( ! wp_verify_nonce( $_POST['fc_checkout_nonce'] ?? '', 'fc_checkout' ) ) {
            wp_send_json_error( array( 'message' => fc__( 'security_error' ) ) );
        }

        if ( FC_Cart::is_empty() ) {
            wp_send_json_error( array( 'message' => fc__( 'cart_empty' ) ) );
        }

        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) {
            session_start();
        }

        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );
        if ( $payment_method !== 'stripe' ) {
            wp_send_json_error( array( 'message' => fc__( 'invalid_payment_method' ) ) );
        }

        // Validate
        $validation = self::validate_checkout_fields();
        if ( ! empty( $validation['errors'] ) ) {
            wp_send_json_error( array( 'message' => implode( '<br>', $validation['errors'] ) ) );
        }

        // Prepare data
        $data = self::prepare_checkout_data( $validation['account_type'], $validation['ship_different'] );

        // Register account
        $reg = self::maybe_register_account();
        if ( ! empty( $reg['errors'] ) ) {
            wp_send_json_error( array( 'message' => implode( '<br>', $reg['errors'] ) ) );
        }

        // Create order
        $order_id = self::create_order( $data['customer'], $data['notes'], $data['payment_method'], $data['shipping_method_name'], $data['shipping_cost'] );
        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => fc__( 'stripe_generic_error' ) ) );
        }

        // Finalize
        self::finalize_order( $order_id, $data['payment_method'], $data['customer'], $validation['ship_different'] );

        // Return order data for JS (always include token — needed even when guest
        // registers account during checkout, because Stripe redirects may lose session)
        $response_data = array(
            'order_id' => $order_id,
            'token'    => get_post_meta( $order_id, '_fc_order_token', true ),
        );

        wp_send_json_success( $response_data );
    }

    /**
     * Tworzenie postu zamówienia
     */
    private static function create_order( $customer, $notes, $payment_method, $shipping_method_name = '', $shipping_cost = 0 ) {
        $cart  = FC_Cart::get_cart();
        $cart_subtotal = FC_Cart::get_total();

        // Kupony rabatowe (obsługa wielu)
        $coupon_discount = 0;
        $coupon_codes = array();
        $coupon_ids = array();
        $coupon_details = array();
        if ( class_exists( 'FC_Coupons' ) ) {
            $session_coupons = FC_Coupons::get_session_coupons();
            $running_total = $cart_subtotal;
            foreach ( $session_coupons as $sc ) {
                $result = FC_Coupons::validate( $sc['code'], $cart_subtotal, get_current_user_id() );
                if ( $result['valid'] ) {
                    $disc = FC_Coupons::calculate_discount( $result['coupon_id'], $running_total );
                    $coupon_discount += $disc;
                    $running_total = max( 0, $running_total - $disc );
                    $coupon_codes[] = $sc['code'];
                    $coupon_ids[] = $result['coupon_id'];
                    $coupon_details[] = array(
                        'code'     => $sc['code'],
                        'discount' => $disc,
                    );
                }
            }
        }

        $total = max( 0, $cart_subtotal - $coupon_discount ) + $shipping_cost;

        // Generowanie numeru zamówienia
        $order_number = 'FC-' . strtoupper( wp_generate_password( 6, false ) );

        $order_id = wp_insert_post( array(
            'post_type'   => 'fc_order',
            'post_title'  => $order_number,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $order_id ) ) {
            return false;
        }

        // Dane zamówienia
        update_post_meta( $order_id, '_fc_order_number', $order_number );
        update_post_meta( $order_id, '_fc_order_status', 'pending' );
        update_post_meta( $order_id, '_fc_order_total', $total );
        update_post_meta( $order_id, '_fc_payment_method', $payment_method );
        update_post_meta( $order_id, '_fc_shipping_method', $shipping_method_name );
        update_post_meta( $order_id, '_fc_shipping_cost', $shipping_cost );
        update_post_meta( $order_id, '_fc_order_notes', $notes );
        update_post_meta( $order_id, '_fc_customer', $customer );
        update_post_meta( $order_id, '_fc_order_date', current_time( 'mysql' ) );

        // Pozycje zamówienia
        $items = array();
        foreach ( $cart as $item ) {
            $variant_id = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
            $price = FC_Cart::get_product_price( $item['product_id'], $variant_id );
            $variant_name = '';
            $variant_attrs = array();
            if ( $variant_id !== '' ) {
                $variants = get_post_meta( $item['product_id'], '_fc_variants', true );
                $found_v = FC_Cart::find_variant( $variants, $variant_id );
                if ( $found_v ) {
                    $variant_name = $found_v['name'] ?? '';
                    $variant_attrs = $found_v['attribute_values'] ?? array();
                }
            }
            $items[] = array(
                'product_id'       => $item['product_id'],
                'variant_id'       => $variant_id,
                'variant_name'     => $variant_name,
                'attribute_values' => $variant_attrs,
                'product_name'     => get_the_title( $item['product_id'] ),
                'quantity'         => $item['quantity'],
                'price'            => $price,
                'line_total'       => $price * $item['quantity'],
            );
        }
        update_post_meta( $order_id, '_fc_order_items', $items );

        // Powiąż z użytkownikiem jeśli zalogowany
        if ( is_user_logged_in() ) {
            update_post_meta( $order_id, '_fc_customer_id', get_current_user_id() );
        }

        // Kupony rabatowe
        if ( $coupon_discount > 0 ) {
            update_post_meta( $order_id, '_fc_coupon_code', implode( ', ', $coupon_codes ) );
            update_post_meta( $order_id, '_fc_coupon_discount', $coupon_discount );
            update_post_meta( $order_id, '_fc_coupon_details', $coupon_details );
            foreach ( $coupon_ids as $cid ) {
                FC_Coupons::record_usage( $cid, get_current_user_id() );
            }
        }

        // Token do weryfikacji dostępu (zamówienia gości)
        $order_token = wp_generate_password( 32, false );
        update_post_meta( $order_id, '_fc_order_token', $order_token );

        return $order_id;
    }

    /**
     * Zmniejsz stan magazynowy i zwiększ licznik sprzedaży
     */
    private static function reduce_stock( $order_id ) {
        $items = get_post_meta( $order_id, '_fc_order_items', true );
        if ( ! is_array( $items ) ) return;

        foreach ( $items as $item ) {
            $product_id = $item['product_id'];
            $qty        = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
            $variant_id = ! empty( $item['variant_id'] ) ? $item['variant_id'] : '';

            // Zwiększ łączną liczbę sprzedaży produktu
            $current_sales = absint( get_post_meta( $product_id, '_fc_total_sales', true ) );
            update_post_meta( $product_id, '_fc_total_sales', $current_sales + $qty );

            if ( $variant_id ) {
                // Zmniejsz stan wariantu (warianty mają własne pole stock)
                $variants = get_post_meta( $item['product_id'], '_fc_variants', true );
                if ( is_array( $variants ) ) {
                    $all_zero = true;
                    foreach ( $variants as &$v ) {
                        if ( isset( $v['id'] ) && $v['id'] === $variant_id && $v['stock'] !== '' ) {
                            $v['stock'] = max( 0, intval( $v['stock'] ) - $item['quantity'] );
                        }
                        // Sprawdź czy wszystkie warianty mają stock=0
                        if ( $v['stock'] === '' || intval( $v['stock'] ) > 0 ) {
                            $all_zero = false;
                        }
                    }
                    unset( $v );
                    update_post_meta( $item['product_id'], '_fc_variants', $variants );

                    // Jeśli wszystkie warianty mają stock=0, ustaw produkt główny jako outofstock
                    if ( $all_zero ) {
                        update_post_meta( $item['product_id'], '_fc_stock_status', 'outofstock' );
                    }
                }
            } else {
                // Zmniejsz stan produktu prostego
                $manage = get_post_meta( $item['product_id'], '_fc_manage_stock', true );
                if ( $manage !== '1' ) continue;

                $stock = intval( get_post_meta( $item['product_id'], '_fc_stock', true ) );
                $new_stock = max( 0, $stock - $item['quantity'] );
                update_post_meta( $item['product_id'], '_fc_stock', $new_stock );

                if ( $new_stock <= 0 ) {
                    update_post_meta( $item['product_id'], '_fc_stock_status', 'outofstock' );
                }
            }
        }

        // Invalidate bestsellers cache
        delete_transient( 'fc_auto_bestseller_ids' );
    }

    /**
     * E-mail potwierdzający zamówienie
     */
    private static function send_order_email( $order_id ) {
        // Wysyłka maila przez system szablonów (do klienta + admin)
        FC_Settings::send_status_email( $order_id, 'pending' );
    }

    /**
     * Synchronizuj dane zamówienia z user meta
     */
    private static function sync_user_meta( $customer, $ship_different ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        // Typ konta
        update_user_meta( $user_id, 'fc_account_type', $customer['account_type'] ?? 'private' );

        // Dane rozliczeniowe
        update_user_meta( $user_id, 'fc_billing_first_name', $customer['first_name'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_last_name',  $customer['last_name'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_company',    $customer['company'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_tax_no',    $customer['tax_no'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_crn', $customer['crn'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_address',    $customer['address'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_postcode',   $customer['postcode'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_city',       $customer['city'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_country',    $customer['country'] ?? 'PL' );
        update_user_meta( $user_id, 'fc_billing_phone',        $customer['phone'] ?? '' );
        update_user_meta( $user_id, 'fc_billing_phone_prefix', $customer['phone_prefix'] ?? '+48' );

        // Imię i nazwisko w profilu WP
        wp_update_user( array(
            'ID'           => $user_id,
            'first_name'   => $customer['first_name'] ?? '',
            'last_name'    => $customer['last_name'] ?? '',
        ) );

        // Adres wysyłki
        update_user_meta( $user_id, 'fc_ship_to_different', $ship_different ? '1' : '0' );

        if ( $ship_different && ! empty( $customer['shipping'] ) ) {
            $s = $customer['shipping'];
            update_user_meta( $user_id, 'fc_shipping_first_name', $s['first_name'] ?? '' );
            update_user_meta( $user_id, 'fc_shipping_last_name',  $s['last_name'] ?? '' );
            update_user_meta( $user_id, 'fc_shipping_company',    $s['company'] ?? '' );
            update_user_meta( $user_id, 'fc_shipping_address',    $s['address'] ?? '' );
            update_user_meta( $user_id, 'fc_shipping_postcode',   $s['postcode'] ?? '' );
            update_user_meta( $user_id, 'fc_shipping_city',       $s['city'] ?? '' );
            update_user_meta( $user_id, 'fc_shipping_country',    $s['country'] ?? 'PL' );
        }
    }
}
