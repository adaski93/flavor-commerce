<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Koszyk: zalogowani → user_meta (_fc_cart), goście → cookie (fc_cart)
 */
class FC_Cart {

    private static $cart_cache = null;

    public static function init() {
        add_action( 'wp_login', array( __CLASS__, 'merge_cookie_on_login' ), 10, 2 );
        add_action( 'init', array( __CLASS__, 'maybe_merge_cookie_cart' ), 2 );
    }

    /**
     * Przy init (priority 2, po session_start) — merge cookie cart dla zalogowanych
     * Trzeba to robić wcześnie, gdy headers jeszcze nie wysłane.
     */
    public static function maybe_merge_cookie_cart() {
        if ( ! is_user_logged_in() ) return;
        if ( ! isset( $_COOKIE['fc_cart'] ) ) return;

        $cookie_cart = json_decode( stripslashes( $_COOKIE['fc_cart'] ), true );
        if ( ! is_array( $cookie_cart ) || empty( $cookie_cart ) ) {
            // Nieprawidłowe dane — wyczyść cookie
            if ( ! headers_sent() ) {
                setcookie( 'fc_cart', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
            }
            unset( $_COOKIE['fc_cart'] );
            return;
        }

        $user_id   = get_current_user_id();
        $user_cart = get_user_meta( $user_id, '_fc_cart', true );
        $user_cart = is_array( $user_cart ) ? $user_cart : array();

        foreach ( $cookie_cart as $key => $item ) {
            if ( ! isset( $user_cart[ $key ] ) ) {
                $user_cart[ $key ] = $item;
            } else {
                $user_cart[ $key ]['quantity'] += $item['quantity'];
            }
        }

        update_user_meta( $user_id, '_fc_cart', $user_cart );
        self::$cart_cache = $user_cart;

        if ( ! headers_sent() ) {
            setcookie( 'fc_cart', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        }
        unset( $_COOKIE['fc_cart'] );
    }

    /* ───── Storage layer ───── */

    /**
     * Pobierz zawartość koszyka
     */
    public static function get_cart() {
        if ( self::$cart_cache !== null ) {
            return self::$cart_cache;
        }

        $user_id = get_current_user_id();

        if ( $user_id ) {
            $cart = get_user_meta( $user_id, '_fc_cart', true );
            $cart = is_array( $cart ) ? $cart : array();

            self::$cart_cache = $cart;
            return $cart;
        }

        // Guest — cookie
        if ( isset( $_COOKIE['fc_cart'] ) ) {
            $decoded = json_decode( stripslashes( $_COOKIE['fc_cart'] ), true );
            $cart = is_array( $decoded ) ? $decoded : array();
        } else {
            $cart = array();
        }

        self::$cart_cache = $cart;
        return $cart;
    }

    /**
     * Zapisz koszyk
     */
    private static function save_cart( $cart ) {
        self::$cart_cache = $cart;
        $user_id = get_current_user_id();

        if ( $user_id ) {
            update_user_meta( $user_id, '_fc_cart', $cart );
        } else {
            setcookie( 'fc_cart', wp_json_encode( $cart ), array(
                'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
                'path'     => COOKIEPATH,
                'domain'   => COOKIE_DOMAIN,
                'secure'   => is_ssl(),
                'httponly'  => true,
                'samesite' => 'Lax',
            ) );
            $_COOKIE['fc_cart'] = wp_json_encode( $cart );
        }
    }

    /**
     * Przy logowaniu — merge cookie → user_meta
     */
    public static function merge_cookie_on_login( $user_login, $user ) {
        if ( ! isset( $_COOKIE['fc_cart'] ) ) return;
        $cookie_cart = json_decode( stripslashes( $_COOKIE['fc_cart'] ), true );
        if ( ! is_array( $cookie_cart ) || empty( $cookie_cart ) ) return;

        $user_cart = get_user_meta( $user->ID, '_fc_cart', true );
        $user_cart = is_array( $user_cart ) ? $user_cart : array();

        foreach ( $cookie_cart as $key => $item ) {
            if ( ! isset( $user_cart[ $key ] ) ) {
                $user_cart[ $key ] = $item;
            } else {
                $user_cart[ $key ]['quantity'] += $item['quantity'];
            }
        }

        update_user_meta( $user->ID, '_fc_cart', $user_cart );
        setcookie( 'fc_cart', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
    }

    /**
     * Find variant by hash id (or fallback to array index for BC).
     */
    public static function find_variant( $variants, $variant_id ) {
        if ( ! is_array( $variants ) || $variant_id === '' ) return null;
        // Search by 'id' field first
        foreach ( $variants as $v ) {
            if ( isset( $v['id'] ) && $v['id'] === $variant_id ) return $v;
        }
        // Fallback: array index (backward compatibility with old cart keys)
        if ( isset( $variants[ $variant_id ] ) ) return $variants[ $variant_id ];
        return null;
    }

    /* ───── Cart operations ───── */

    /**
     * Dodaj produkt do koszyka
     */
    public static function add_item( $product_id, $quantity = 1, $variant_id = '' ) {
        $product_id = absint( $product_id );
        $quantity   = max( 1, intval( $quantity ) );
        $variant_id = sanitize_text_field( $variant_id );

        $post = get_post( $product_id );
        if ( ! $post || $post->post_type !== 'fc_product' ) {
            return false;
        }

        // Sprawdź czy produkt ma dozwolony status (opublikowany lub preorder)
        if ( ! in_array( $post->post_status, array( 'fc_published', 'fc_preorder' ), true ) ) {
            return false;
        }

        $product_type = get_post_meta( $product_id, '_fc_product_type', true ) ?: 'simple';

        // Klucz koszyka: product_id lub product_id_variant_idx
        $cart_key = $variant_id !== '' ? $product_id . '_v' . $variant_id : $product_id;

        $cart = self::get_cart();

        // Sprawdź stan magazynowy
        if ( $product_type === 'variable' && $variant_id !== '' ) {
            $variants = get_post_meta( $product_id, '_fc_variants', true );
            $variant = self::find_variant( $variants, $variant_id );
            if ( ! $variant ) return false;
            if ( ( $variant['status'] ?? 'active' ) !== 'active' ) return false;
            $v_stock = $variant['stock'] ?? '';
            if ( $v_stock !== '' ) {
                $current_in_cart = isset( $cart[ $cart_key ] ) ? $cart[ $cart_key ]['quantity'] : 0;
                if ( ( $current_in_cart + $quantity ) > intval( $v_stock ) ) {
                    $quantity = max( 0, intval( $v_stock ) - $current_in_cart );
                    if ( $quantity <= 0 ) return false;
                }
            }
        } else {
            $stock_status = get_post_meta( $product_id, '_fc_stock_status', true );
            if ( $stock_status === 'outofstock' ) {
                return false;
            }

            $manage_stock = get_post_meta( $product_id, '_fc_manage_stock', true );
            if ( $manage_stock === '1' ) {
                $stock = intval( get_post_meta( $product_id, '_fc_stock', true ) );
                $current_in_cart = isset( $cart[ $cart_key ] ) ? $cart[ $cart_key ]['quantity'] : 0;
                if ( ( $current_in_cart + $quantity ) > $stock ) {
                    $quantity = max( 0, $stock - $current_in_cart );
                    if ( $quantity <= 0 ) return false;
                }
            }
        }

        if ( isset( $cart[ $cart_key ] ) ) {
            $cart[ $cart_key ]['quantity'] += $quantity;
        } else {
            $cart[ $cart_key ] = array(
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity'   => $quantity,
            );
        }

        self::save_cart( $cart );
        return true;
    }

    /**
     * Aktualizuj ilość
     */
    public static function update_item( $cart_key, $quantity ) {
        $quantity = intval( $quantity );

        if ( $quantity <= 0 ) {
            self::remove_item( $cart_key );
            return true;
        }

        $cart = self::get_cart();

        if ( isset( $cart[ $cart_key ] ) ) {
            // Sprawdź stan magazynowy
            $item = $cart[ $cart_key ];
            $product_id = $item['product_id'];
            $variant_id = $item['variant_id'] ?? '';
            $product_type = get_post_meta( $product_id, '_fc_product_type', true ) ?: 'simple';

            if ( $product_type === 'variable' && $variant_id !== '' ) {
                $variants = get_post_meta( $product_id, '_fc_variants', true );
                $found = self::find_variant( $variants, $variant_id );
                if ( $found ) {
                    $v_stock = $found['stock'] ?? '';
                    if ( $v_stock !== '' && $quantity > intval( $v_stock ) ) {
                        $quantity = intval( $v_stock );
                    }
                }
            } else {
                $manage_stock = get_post_meta( $product_id, '_fc_manage_stock', true );
                if ( $manage_stock === '1' ) {
                    $stock = intval( get_post_meta( $product_id, '_fc_stock', true ) );
                    if ( $quantity > $stock ) {
                        $quantity = $stock;
                    }
                }
            }

            if ( $quantity <= 0 ) {
                self::remove_item( $cart_key );
                return true;
            }

            $cart[ $cart_key ]['quantity'] = $quantity;
            self::save_cart( $cart );
            return true;
        }

        return false;
    }

    /**
     * Usuń produkt z koszyka
     */
    public static function remove_item( $cart_key ) {
        $cart = self::get_cart();
        if ( isset( $cart[ $cart_key ] ) ) {
            unset( $cart[ $cart_key ] );
            self::save_cart( $cart );
            return true;
        }
        return false;
    }

    /**
     * Wyczyść koszyk
     */
    public static function clear() {
        self::save_cart( array() );
    }

    /**
     * Oblicz sumę koszyka
     */
    public static function get_total() {
        $total = 0;
        $cart = self::get_cart();

        foreach ( $cart as $item ) {
            $variant_id = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
            $price = self::get_product_price( $item['product_id'], $variant_id );
            $total += $price * $item['quantity'];
        }

        return $total;
    }

    /**
     * Liczba przedmiotów w koszyku
     */
    public static function get_count() {
        $count = 0;
        $cart = self::get_cart();
        foreach ( $cart as $item ) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Pobierz aktualną cenę produktu (z uwzględnieniem promocji i wariantów)
     */
    public static function get_product_price( $product_id, $variant_id = '' ) {
        if ( $variant_id !== '' ) {
            $variants = get_post_meta( $product_id, '_fc_variants', true );
            $v = self::find_variant( $variants, $variant_id );
            if ( $v ) {
                if ( ! empty( $v['sale_price'] ) && floatval( $v['sale_price'] ) > 0 ) {
                    return floatval( $v['sale_price'] );
                }
                return floatval( $v['price'] ?? 0 );
            }
        }

        $sale_price = get_post_meta( $product_id, '_fc_sale_price', true );
        $price      = get_post_meta( $product_id, '_fc_price', true );

        if ( $sale_price && floatval( $sale_price ) > 0 ) {
            return floatval( $sale_price );
        }

        return floatval( $price );
    }

    /**
     * Czy koszyk jest pusty
     */
    public static function is_empty() {
        return empty( self::get_cart() );
    }

    /**
     * Pobierz unikalne klasy wysyłkowe produktów w koszyku.
     *
     * @return array  Tablica term_id klas wysyłkowych (puste = brak klas w koszyku)
     */
    public static function get_cart_shipping_classes() {
        $cart = self::get_cart();
        $class_ids = array();
        foreach ( $cart as $item ) {
            $terms = wp_get_object_terms( $item['product_id'], 'fc_shipping_class', array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                foreach ( $terms as $tid ) {
                    $class_ids[ $tid ] = $tid;
                }
            }
        }
        return array_values( $class_ids );
    }

    /**
     * Oblicz efektywny koszt wysyłki dla danej metody na podstawie klas wysyłkowych w koszyku.
     *
     * Logika:
     * - Jeśli metoda ma class_costs i któryś produkt w koszyku ma klasę oznaczoną jako 'none' → metoda niedostępna
     * - Jeśli metoda ma class_costs → koszt = najwyższy class_cost z klas w koszyku (zastępuje bazowy)
     * - Jeśli żaden produkt nie ma klasy zdefiniowanej w class_costs → koszt bazowy
     *
     * @param array $method     Dane metody wysyłki (z fc_shipping_methods)
     * @param array $cart_classes  Wynik get_cart_shipping_classes()
     * @return array [ 'available' => bool, 'cost' => float ]
     */
    public static function get_effective_shipping( $method, $cart_classes = null ) {
        if ( $cart_classes === null ) {
            $cart_classes = self::get_cart_shipping_classes();
        }

        $base_cost    = floatval( $method['cost'] ?? 0 );
        $class_costs  = isset( $method['class_costs'] ) && is_array( $method['class_costs'] ) ? $method['class_costs'] : array();

        // Brak konfiguracji klas → zawsze bazowy koszt
        if ( empty( $class_costs ) ) {
            return array( 'available' => true, 'cost' => $base_cost );
        }

        // Brak klas w koszyku → bazowy koszt
        if ( empty( $cart_classes ) ) {
            return array( 'available' => true, 'cost' => $base_cost );
        }

        // Sprawdź klasy z koszyka
        $max_class_cost = null;
        foreach ( $cart_classes as $class_id ) {
            $key = strval( $class_id );
            if ( isset( $class_costs[ $key ] ) ) {
                if ( $class_costs[ $key ] === 'none' ) {
                    // Metoda zablokowana dla tej klasy
                    return array( 'available' => false, 'cost' => 0 );
                }
                $cc = floatval( $class_costs[ $key ] );
                if ( $max_class_cost === null || $cc > $max_class_cost ) {
                    $max_class_cost = $cc;
                }
            }
        }

        $effective_cost = $max_class_cost !== null ? $max_class_cost : $base_cost;

        return array( 'available' => true, 'cost' => $effective_cost );
    }
}
