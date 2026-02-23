<?php
/**
 * Plugin Name: Flavor Commerce
 * Plugin URI: https://flavor-theme.dev
 * Description: Prosta, ale kompletna wtyczka eCommerce dla WordPress. Zarządzanie produktami, koszyk, zamówienia i płatności.
 * Version: 2.0.0
 * Author: Developer
 * Author URI: https://flavor-theme.dev
 * Text Domain: flavor-commerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Stałe wtyczki
define( 'FC_VERSION', '2.0.0' );
define( 'FC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Główna klasa wtyczki Flavor Commerce
 */
final class Flavor_Commerce {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        // i18n — must load before all other classes
        require_once FC_PLUGIN_DIR . 'includes/class-fc-i18n.php';

        // Core
        require_once FC_PLUGIN_DIR . 'includes/class-fc-post-types.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-product-meta.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-cart.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-checkout.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-orders.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-ajax.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-shortcodes.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-settings.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-product-admin.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-attributes-admin.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-units-admin.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-account.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-reviews.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-user-profile.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-invoices.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-coupons.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-import-export.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-wishlist.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-frontend-features.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-stripe.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-updater.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-smtp.php';
        require_once FC_PLUGIN_DIR . 'includes/class-fc-setup-wizard.php';
    }

    private function init_hooks() {
        // GitHub auto-updater.
        new FC_Updater( __FILE__, 'adaski93/flavor-commerce' );

        // SMTP configuration.
        FC_SMTP::instance();

        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'maybe_migrate_effective_price' ) );
        add_action( 'init', array( $this, 'maybe_create_retry_payment_page' ) );
        add_action( 'init', function() { FC_i18n::init(); } );
        add_action( 'init', array( $this, 'override_wp_core_translations' ) );
        add_action( 'admin_init', array( $this, 'maybe_recreate_store_pages' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_mini_cart' ) );
        add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_filetype' ), 10, 5 );
        add_filter( 'display_post_states', array( $this, 'label_store_pages' ), 10, 2 );

        // Zaplanowana publikacja produktów (cron)
        add_action( 'fc_scheduled_publish', array( $this, 'scheduled_publish_product' ) );

        // Inicjalizacja klas
        FC_Post_Types::init();
        FC_Product_Meta::init();
        FC_Cart::init();
        FC_Checkout::init();
        FC_Orders::init();
        FC_Ajax::init();
        FC_Shortcodes::init();
        FC_Settings::init();
        FC_Product_Admin::init();
        FC_Attributes_Admin::init();
        FC_Units_Admin::init();
        FC_Account::init();
        FC_Reviews::init();
        FC_User_Profile::init();
        FC_Invoices::init();
        FC_Coupons::init();
        FC_Import_Export::init();
        FC_Wishlist::init();
        FC_Frontend_Features::init();
        FC_Stripe::init();
        FC_Setup_Wizard::init();
    }

    /**
     * Static activation handler (called from file-level register_activation_hook)
     */
    public static function file_activate() {
        // Ensure classes are loaded
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        self::$instance->activate();

        // Redirect to setup wizard after activation
        set_transient( 'fc_activation_redirect', 1, 60 );
    }

    /**
     * Static deactivation handler (called from file-level register_deactivation_hook)
     */
    public static function file_deactivate() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        self::$instance->deactivate();
    }

    public function activate() {
        FC_Post_Types::register();
        flush_rewrite_rules();

        // Upewnij się że strony istnieją
        $pages = array(
            'sklep'    => array( 'title' => fc__( 'page_shop' ), 'content' => '[fc_shop]' ),
            'koszyk'   => array( 'title' => fc__( 'page_cart' ), 'content' => '[fc_cart]' ),
            'zamowienie' => array( 'title' => fc__( 'page_checkout' ), 'content' => '[fc_checkout]' ),
            'podziekowanie' => array( 'title' => fc__( 'page_thank_you' ), 'content' => '[fc_thank_you]' ),
            'moje-konto' => array( 'title' => fc__( 'page_my_account' ), 'content' => '[fc_account]' ),
            'lista-zyczen' => array( 'title' => fc__( 'page_wishlist' ), 'content' => '[fc_wishlist]', 'option' => 'fc_page_wishlist' ),
            'porownanie' => array( 'title' => fc__( 'page_comparison' ), 'content' => '[fc_compare]', 'option' => 'fc_page_porownanie' ),
            'platnosc-nieudana' => array( 'title' => fc__( 'page_retry_payment' ), 'content' => '[fc_retry_payment]', 'option' => 'fc_page_platnosc_nieudana' ),
        );

        foreach ( $pages as $slug => $page_data ) {
            $option_key = isset( $page_data['option'] ) ? $page_data['option'] : 'fc_page_' . $slug;
            $existing = get_page_by_path( $slug );
            if ( ! $existing ) {
                $page_id = wp_insert_post( array(
                    'post_title'   => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_name'    => $slug,
                ) );
                update_option( $option_key, $page_id );
            } else {
                update_option( $option_key, $existing->ID );
            }
        }
    }

    public function deactivate() {
        flush_rewrite_rules();
        wp_clear_scheduled_hook( 'fc_cron_expire_pending_orders' );
        wp_clear_scheduled_hook( 'fc_scheduled_publish' );
    }

    /**
     * On every admin page load, compare the current fc_frontend_lang
     * with the language the store pages were last built in.
     * If they differ, delete old pages and recreate in the new language.
     */
    public function maybe_recreate_store_pages() {
        $current_lang = get_option( 'fc_frontend_lang', 'pl' );
        $pages_lang   = get_option( 'fc_pages_language', '' );

        if ( $current_lang === $pages_lang ) {
            return;
        }

        // Load frontend translations for the current language directly (bypass cached FC_i18n).
        $strings = array();
        $file    = FC_PLUGIN_DIR . "languages/{$current_lang}/frontend.php";
        if ( file_exists( $file ) ) {
            $data = include $file;
            if ( is_array( $data ) ) {
                $strings = $data;
            }
        }

        // Fallback to PL if the language file is missing.
        if ( empty( $strings ) && $current_lang !== 'pl' ) {
            $fallback = FC_PLUGIN_DIR . 'languages/pl/frontend.php';
            if ( file_exists( $fallback ) ) {
                $data = include $fallback;
                if ( is_array( $data ) ) {
                    $strings = $data;
                }
            }
        }

        $t = function( $key ) use ( $strings ) {
            return isset( $strings[ $key ] ) ? $strings[ $key ] : $key;
        };

        // Page definitions: option_key => page data.
        $pages = array(
            'fc_page_sklep'             => array( 'title_key' => 'page_shop',          'content' => '[fc_shop]' ),
            'fc_page_koszyk'            => array( 'title_key' => 'page_cart',          'content' => '[fc_cart]' ),
            'fc_page_zamowienie'        => array( 'title_key' => 'page_checkout',      'content' => '[fc_checkout]' ),
            'fc_page_podziekowanie'     => array( 'title_key' => 'page_thank_you',     'content' => '[fc_thank_you]' ),
            'fc_page_moje-konto'        => array( 'title_key' => 'page_my_account',    'content' => '[fc_account]' ),
            'fc_page_wishlist'          => array( 'title_key' => 'page_wishlist',       'content' => '[fc_wishlist]' ),
            'fc_page_porownanie'        => array( 'title_key' => 'page_comparison',    'content' => '[fc_compare]' ),
            'fc_page_platnosc_nieudana' => array( 'title_key' => 'page_retry_payment', 'content' => '[fc_retry_payment]' ),
        );

        // Delete existing store pages (tracked by options).
        foreach ( $pages as $option_key => $page_data ) {
            $page_id = get_option( $option_key );
            if ( $page_id ) {
                wp_delete_post( (int) $page_id, true );
                delete_option( $option_key );
            }
        }

        // Delete any orphaned pages containing store shortcodes (not tracked by options).
        $shortcodes = array_column( $pages, 'content' );
        foreach ( $shortcodes as $sc ) {
            $orphans = get_posts( array(
                'post_type'      => 'page',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                's'              => $sc,
                'fields'         => 'ids',
            ) );
            foreach ( $orphans as $oid ) {
                wp_delete_post( (int) $oid, true );
            }
        }

        // Create new pages with titles in the current language.
        foreach ( $pages as $option_key => $page_data ) {
            $page_id = wp_insert_post( array(
                'post_title'   => $t( $page_data['title_key'] ),
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_option( $option_key, $page_id );
            }
        }

        // Reset sidebar widget titles to defaults for the new language.
        $sidebar_blocks = get_option( 'fc_sidebar_blocks', array() );
        if ( ! empty( $sidebar_blocks ) && is_array( $sidebar_blocks ) ) {
            // Map widget type → frontend translation key for default title.
            $title_keys = array(
                'categories'    => 'theme_widget_categories',
                'brands'        => 'theme_widget_brands',
                'attributes'    => 'theme_widget_attributes',
                'price_filter'  => 'theme_widget_price',
                'rating_filter' => 'theme_widget_rating',
                'availability'  => 'theme_widget_availability',
                'search'        => 'theme_widget_search',
                'bestsellers'   => 'theme_widget_bestsellers',
                'new_products'  => 'theme_widget_new_products',
                'on_sale'       => 'theme_widget_on_sale',
                'cta_banner'    => 'theme_widget_cta_banner',
                'custom_html'   => 'theme_widget_custom_html',
            );
            foreach ( $sidebar_blocks as &$block ) {
                $type = $block['type'] ?? '';
                if ( isset( $title_keys[ $type ] ) ) {
                    $block['title'] = $t( $title_keys[ $type ] );
                }
            }
            unset( $block );
            update_option( 'fc_sidebar_blocks', $sidebar_blocks );
        }

        // Reset menu item titles for special store elements.
        $menu_items = get_theme_mod( 'flavor_menu_items', array() );
        if ( is_string( $menu_items ) ) {
            $menu_items = json_decode( $menu_items, true );
        }
        if ( ! empty( $menu_items ) && is_array( $menu_items ) ) {
            $menu_title_keys = array(
                'fc_shop'     => 'page_shop',
                'fc_account'  => 'page_my_account',
                'fc_cart'     => 'page_cart',
                'fc_wishlist' => 'page_wishlist',
                'fc_compare'  => 'theme_compare',
            );
            foreach ( $menu_items as &$item ) {
                $itype = $item['type'] ?? '';
                if ( isset( $menu_title_keys[ $itype ] ) ) {
                    $item['title'] = $t( $menu_title_keys[ $itype ] );
                }
            }
            unset( $item );
            set_theme_mod( 'flavor_menu_items', $menu_items );
        }

        // Remember which language pages are now built in.
        update_option( 'fc_pages_language', $current_lang );

        // Allow retry-payment migration to re-run if needed.
        delete_option( 'fc_retry_payment_page_created' );
    }

    /**
     * Add "Flavor Commerce" label to store pages on the Pages list screen.
     *
     * @param array   $post_states Existing post states.
     * @param WP_Post $post        The current post object.
     * @return array
     */
    public function label_store_pages( $post_states, $post ) {
        static $fc_page_ids = null;

        if ( $fc_page_ids === null ) {
            $fc_page_ids = array();
            $option_keys = array(
                'fc_page_sklep'             => 'page_state_shop',
                'fc_page_koszyk'            => 'page_state_cart',
                'fc_page_zamowienie'        => 'page_state_checkout',
                'fc_page_podziekowanie'     => 'page_state_thank_you',
                'fc_page_moje-konto'        => 'page_state_my_account',
                'fc_page_wishlist'          => 'page_state_wishlist',
                'fc_page_porownanie'        => 'page_state_comparison',
                'fc_page_platnosc_nieudana' => 'page_state_retry_payment',
            );
            foreach ( $option_keys as $opt => $label_key ) {
                $pid = (int) get_option( $opt, 0 );
                if ( $pid ) {
                    $fc_page_ids[ $pid ] = $label_key;
                }
            }
        }

        if ( isset( $fc_page_ids[ $post->ID ] ) ) {
            $post_states[ 'fc_page' ] = fc__( $fc_page_ids[ $post->ID ], 'admin' );
        }

        return $post_states;
    }

    /**
     * Jednorazowa migracja: oblicz _fc_effective_price dla istniejących produktów
     */
    public function maybe_migrate_effective_price() {
        if ( get_option( 'fc_effective_price_migrated' ) ) return;

        $products = get_posts( array(
            'post_type'      => 'fc_product',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ) );

        foreach ( $products as $pid ) {
            FC_Product_Admin::update_effective_price( $pid );
        }

        update_option( 'fc_effective_price_migrated', '1' );
    }

    /**
     * Jednorazowo utwórz stronę „Płatność nieudana" jeśli jeszcze nie istnieje.
     */
    public function maybe_create_retry_payment_page() {
        if ( get_option( 'fc_retry_payment_page_created' ) ) {
            return;
        }

        $page_id = get_option( 'fc_page_platnosc_nieudana' );

        // Jeśli opcja jest ustawiona i strona istnieje — nic nie rób.
        if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
            return;
        }

        // Sprawdź czy strona z tym slugiem już istnieje.
        $existing = get_page_by_path( 'platnosc-nieudana' );
        if ( $existing && $existing->post_status === 'publish' ) {
            update_option( 'fc_page_platnosc_nieudana', $existing->ID );
            return;
        }

        // Utwórz nową stronę.
        $new_page_id = wp_insert_post( array(
            'post_title'   => fc__( 'page_retry_payment' ),
            'post_content' => '[fc_retry_payment]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'platnosc-nieudana',
        ) );

        if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
            update_option( 'fc_page_platnosc_nieudana', $new_page_id );
        }

        update_option( 'fc_retry_payment_page_created', '1' );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'flavor-commerce', false, dirname( FC_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Override WP core translations on FC admin pages when fc_admin_lang differs from WP locale.
     */
    public function override_wp_core_translations() {
        if ( ! is_admin() ) return;

        $admin_lang = get_option( 'fc_admin_lang', 'pl' );
        $wp_locale  = determine_locale();
        // Map fc_admin_lang to WP locale prefix
        $fc_locale_prefix = substr( $wp_locale, 0, 2 );
        if ( $admin_lang === $fc_locale_prefix ) return;

        add_filter( 'gettext', array( $this, 'filter_wp_core_gettext' ), 10, 3 );
        add_filter( 'ngettext', array( $this, 'filter_wp_core_ngettext' ), 10, 5 );
    }

    /**
     * Filter WP core gettext on FC admin screens.
     */
    public function filter_wp_core_gettext( $translation, $text, $domain ) {
        if ( $domain !== 'default' ) return $translation;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) return $translation;
        if ( strpos( $screen->id, 'fc_' ) === false && strpos( $screen->id, 'flavor' ) === false ) return $translation;

        // When FC admin lang = 'en', return original English text
        $admin_lang = get_option( 'fc_admin_lang', 'pl' );
        if ( $admin_lang === 'en' ) {
            return $text;
        }

        return $translation;
    }

    /**
     * Filter WP core ngettext (plurals) on FC admin screens.
     */
    public function filter_wp_core_ngettext( $translation, $single, $plural, $number, $domain ) {
        if ( $domain !== 'default' ) return $translation;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen ) return $translation;
        if ( strpos( $screen->id, 'fc_' ) === false && strpos( $screen->id, 'flavor' ) === false ) return $translation;

        $admin_lang = get_option( 'fc_admin_lang', 'pl' );
        if ( $admin_lang === 'en' ) {
            return ( $number === 1 ) ? $single : $plural;
        }

        return $translation;
    }

    /**
     * Pozwól na upload plików SVG (tylko administratorzy)
     */
    public function allow_svg_upload( $mimes ) {
        if ( ! current_user_can( 'manage_options' ) ) return $mimes;
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Napraw wykrywanie typu pliku SVG (WordPress może go blokować)
     */
    public function fix_svg_filetype( $data, $file, $filename, $mimes, $real_mime = '' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $data;
        }
        if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
            return $data;
        }
        $ext = pathinfo( $filename, PATHINFO_EXTENSION );
        if ( strtolower( $ext ) === 'svg' ) {
            // Sanitize SVG: strip dangerous tags/attributes
            $svg_content = file_get_contents( $file );
            if ( $svg_content !== false ) {
                // Remove script tags, event handlers, and external references
                $svg_content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $svg_content );
                $svg_content = preg_replace( '/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg_content );
                $svg_content = preg_replace( '/xlink:href\s*=\s*["\'](?!#|data:image)[^"\']*["\']/i', '', $svg_content );
                $svg_content = preg_replace( '/href\s*=\s*["\'](?!#|data:image)[^"\']*["\']/i', '', $svg_content );
                file_put_contents( $file, $svg_content );
            }
            $data['ext']  = 'svg';
            $data['type'] = 'image/svg+xml';
        }
        return $data;
    }

    /**
     * Automatyczna publikacja ukrytego produktu o zaplanowanej dacie
     */
    public function scheduled_publish_product( $product_id ) {
        $product_id = absint( $product_id );
        if ( ! $product_id ) return;

        $post = get_post( $product_id );
        if ( ! $post || $post->post_type !== 'fc_product' ) return;
        if ( $post->post_status !== 'fc_hidden' ) return;

        wp_update_post( array(
            'ID'          => $product_id,
            'post_status' => 'fc_published',
        ) );
        delete_post_meta( $product_id, '_fc_publish_date' );
    }

    public function frontend_assets() {
        wp_enqueue_style(
            'flavor-commerce',
            FC_PLUGIN_URL . 'assets/css/frontend.css',
            array( 'dashicons' ),
            FC_VERSION
        );

        wp_enqueue_script(
            'flavor-commerce',
            FC_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            FC_VERSION,
            true
        );

        wp_localize_script( 'flavor-commerce', 'fc_ajax', array(
            'url'       => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'fc_nonce' ),
            'cart_url'  => get_permalink( get_option( 'fc_page_koszyk' ) ),
            'currency'  => get_option( 'fc_currency_symbol', 'zł' ),
            'currency_pos' => get_option( 'fc_currency_pos', 'after' ),
            'open_minicart_on_add' => $this->get_open_on_add_flag( 'cart' ) ? '1' : '0',
            'open_wishlist_on_add' => $this->get_open_on_add_flag( 'wishlist' ) ? '1' : '0',
            'open_compare_on_add' => $this->get_open_on_add_flag( 'compare' ) ? '1' : '0',
            'account_url' => get_permalink( get_option( 'fc_page_moje-konto' ) ),
            'checkout_layout' => get_theme_mod( 'flavor_checkout_layout', 'steps' ),
            'i18n' => array(
                'generic_error'           => fc__( 'js_generic_error' ),
                'compare_in_list'         => fc__( 'js_compare_in_list' ),
                'compare_add'             => fc__( 'js_compare_add' ),
                'compare_error'           => fc__( 'js_compare_error' ),
                'compare_cleared'         => fc__( 'js_compare_cleared' ),
                'compare_min_products'    => fc__( 'js_compare_min_products' ),
                'added_to_cart'           => fc__( 'js_added_to_cart' ),
                'select_all_attributes'   => fc__( 'js_select_all_attributes' ),
                'scroll_label'            => fc__( 'js_scroll_label' ),
                'select_variants'         => fc__( 'js_select_variants' ),
                'add_to_cart'             => fc__( 'js_add_to_cart' ),
                'unavailable'             => fc__( 'js_unavailable' ),
                'out_of_stock_badge'      => fc__( 'out_of_stock_badge' ),
                'in_stock_badge'          => fc__( 'in_stock_badge' ),
                'filter_category'         => fc__( 'js_filter_category' ),
                'filter_brand'            => fc__( 'js_filter_brand' ),
                'filter_rating'           => fc__( 'js_filter_rating' ),
                'filter_availability'     => fc__( 'js_filter_availability' ),
                'filter_instock'          => fc__( 'js_filter_instock' ),
                'filter_search'           => fc__( 'js_filter_search' ),
                'filter_price'            => fc__( 'js_filter_price' ),
                'price_from'              => fc__( 'js_price_from' ),
                'price_to'                => fc__( 'js_price_to' ),
                'tax_no_default'          => fc__( 'js_tax_no_default' ),
                'reg_no_default'          => fc__( 'js_reg_no_default' ),
                'review_rating_required'  => fc__( 'js_review_rating_required' ),
                'review_content_required' => fc__( 'js_review_content_required' ),
                'select_shipping_method'  => fc__( 'js_select_shipping_method' ),
                'same_as_billing'         => fc__( 'js_same_as_billing' ),
                'free_shipping'           => fc__( 'js_free_shipping' ),
                'field_required'          => fc__( 'js_field_required' ),
                'username_min_length'     => fc__( 'js_username_min_length' ),
                'username_no_spaces'      => fc__( 'js_username_no_spaces' ),
                'invalid_email'           => fc__( 'js_invalid_email' ),
                'password_min_length'     => fc__( 'js_password_min_length' ),
                'passwords_mismatch'      => fc__( 'js_passwords_mismatch' ),
                'activation_code_format'  => fc__( 'js_activation_code_format' ),
                'username_taken'          => fc__( 'js_username_taken' ),
                'email_already_registered' => fc__( 'js_email_already_registered' ),
                'stock_limit'             => fc__( 'js_stock_limit' ),
                'email_exists_login'      => str_replace( '%s', get_permalink( get_option( 'fc_page_moje-konto' ) ), fc__( 'js_email_exists_login' ) ),
            ),
        ) );
    }

    public function admin_assets( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen ) return;

        if ( in_array( $screen->post_type, array( 'fc_product', 'fc_order' ) ) || $screen->id === 'toplevel_page_flavor-commerce' || strpos( $hook, 'fc-product-add' ) !== false || strpos( $hook, 'fc-attributes' ) !== false || strpos( $hook, 'fc-units' ) !== false ) {
            wp_enqueue_style(
                'flavor-commerce-admin',
                FC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                FC_VERSION
            );

            wp_enqueue_script(
                'flavor-commerce-admin',
                FC_PLUGIN_URL . 'assets/js/admin.js',
                array( 'jquery', 'jquery-ui-sortable' ),
                FC_VERSION,
                true
            );

            wp_localize_script( 'flavor-commerce-admin', 'fc_admin_vars', array(
                'nonce'    => wp_create_nonce( 'fc_admin_nonce' ),
                'currency' => get_option( 'fc_currency_symbol', 'zł' ),
                'i18n'     => array(
                    // Media picker
                    'media_select_images'       => fc__( 'main_select_photos' ),
                    'media_select_main_image'   => fc__( 'main_select_main_photo' ),
                    'media_add_to_gallery'      => fc__( 'main_add_photos_to_gallery' ),
                    'media_select_image_for'    => fc__( 'main_select_image_for' ),
                    'media_select_image'        => fc__( 'main_select_image' ),
                    'media_select_download'     => fc__( 'main_select_file_for_download' ),
                    'media_select_brand_logo'   => fc__( 'main_select_brand_logo' ),
                    'media_use_as_logo'         => fc__( 'main_use_as_logo' ),

                    // Gallery / thumbnails
                    'click_to_add_image'        => fc__( 'prod_click_to_add_photo' ),
                    'add_photos'                => fc__( 'main_photos' ),
                    'add_gallery_images_first'  => fc__( 'main_first_add_photos_to_the_product_gallery' ),
                    'select_images_from_gallery' => fc__( 'main_select_photos_from_gallery' ),
                    'confirm_selection'         => fc__( 'main_confirm' ),

                    // Category / brand / unit AJAX
                    'adding_progress'           => fc__( 'main_adding' ),
                    'add_category'              => fc__( 'prod_add_category' ),
                    'error_adding_category'     => fc__( 'main_error_adding_category' ),
                    'add_brand'                 => fc__( 'prod_add_brand' ),
                    'error_adding_brand'        => fc__( 'main_error_adding_brand' ),
                    'add_unit'                  => fc__( 'prod_add_unit' ),
                    'error_adding_unit'         => fc__( 'main_error_adding_unit' ),
                    'connection_error'          => fc__( 'main_connection_error' ),

                    // Product type switch
                    'confirm_type_change_lose'  => fc__( 'main_changing_the_product_type_will_delete_all_attribut' ),
                    'confirm_type_change_var'   => fc__( 'main_changing_to_a_variable_product_will_clear_the_pric' ),

                    // Attribute editor
                    'click_to_change_color'     => fc__( 'main_click_to_change_color' ),
                    'click_to_change'           => fc__( 'main_click_to_change' ),
                    'select_image'              => fc__( 'main_select_image_2' ),
                    'available'                 => fc__( 'main_available' ),
                    'attr_name_placeholder'     => fc__( 'main_attribute_name_e_g_color' ),
                    'type_text'                 => fc__( 'attr_text' ),
                    'type_color'                => fc__( 'attr_color' ),
                    'type_image'                => fc__( 'attr_image' ),
                    'remove_attribute'          => fc__( 'main_delete_attribute' ),
                    'type_value_enter'          => fc__( 'main_type_value_enter' ),
                    'this_attribute'            => fc__( 'main_this_attribute' ),
                    'confirm_remove_attribute'  => fc__( 'main_delete_attribute_2' ),

                    // Selection
                    'reset_selection'           => fc__( 'main_reset_selection' ),
                    'selected_count'            => fc__( 'main_selected' ),

                    // Variant table
                    'click_to_set_as_main'      => fc__( 'main_click_to_set_as_main' ),
                    'status_active'             => fc__( 'coupon_active' ),
                    'status_inactive'           => fc__( 'prod_inactive' ),
                    'remove'                    => fc__( 'attr_delete' ),

                    // Pricing
                    'discount_label'            => fc__( 'main_discount' ),

                    // Validation
                    'error_fill_variant_prices' => fc__( 'main_fill_in_the_price_for_all_active_combinations' ),

                    // Global attributes
                    'placeholder_color'         => fc__( 'main_e_g_red_green_blue' ),
                    'placeholder_image'         => fc__( 'main_e_g_oak_beech_walnut' ),
                    'placeholder_text'          => fc__( 'main_e_g_s_m_l_xl' ),
                    'generate'                  => fc__( 'inv_generate' ),
                    'value_used_in_variants'    => fc__( 'main_value_is_used_in_variants' ),
                    'change'                    => fc__( 'main_change' ),
                    'select'                    => fc__( 'main_select' ),
                    'attr_used_in_products'     => fc__( 'main_attribute_is_used_in_products' ),
                    'attr_blocked_explanation'  => fc__( 'main_cannot_edit_or_delete_this_attribute_because_it_is' ),
                    'attr_blocked_instructions' => fc__( 'main_to_edit_or_delete_an_attribute_first_remove_it_fro' ),

                    // Units page
                    'units_selected_count'      => fc__( 'main_selected_2' ),

                    // Save / general
                    'saved'                     => fc__( 'main_saved' ),
                    'save_error'                => fc__( 'main_an_error_occurred' ),
                    'export_csv'                => fc__( 'admin_export_csv' ),
                    'close'                     => fc__( 'main_close' ),
                ),
            ) );

            wp_enqueue_media();
        }
    }

    /**
     * Get open_on_add flag for a specific icon from the side panel icons JSON.
     */
    private function get_open_on_add_flag( $icon_key ) {
        $default_icons = array(
            array( 'key' => 'account',  'enabled' => true ),
            array( 'key' => 'cart',     'enabled' => true, 'open_on_add' => true ),
            array( 'key' => 'wishlist', 'enabled' => true, 'open_on_add' => false ),
            array( 'key' => 'compare',  'enabled' => true, 'open_on_add' => false ),
        );
        $icons_raw = get_theme_mod( 'flavor_side_panel_icons', wp_json_encode( $default_icons ) );
        $icons     = is_string( $icons_raw ) ? json_decode( $icons_raw, true ) : $icons_raw;
        if ( ! is_array( $icons ) ) $icons = $default_icons;
        foreach ( $icons as $icon ) {
            if ( ( $icon['key'] ?? '' ) === $icon_key ) {
                return ! empty( $icon['open_on_add'] );
            }
        }
        return false;
    }

    public function render_mini_cart() {
        if ( is_admin() ) return;
        if ( ! get_theme_mod( 'flavor_side_panel_enabled', true ) ) return;

        // Batch page IDs and prime post cache in one query
        $page_ids = array_filter( array_map( 'absint', array(
            get_option( 'fc_page_koszyk' ),
            get_option( 'fc_page_zamowienie' ),
            get_option( 'fc_page_moje-konto' ),
        ) ) );
        if ( $page_ids ) {
            _prime_post_caches( $page_ids, false, false );
        }

        $cart_url     = get_permalink( $page_ids[0] ?? 0 );
        $checkout_url = get_permalink( $page_ids[1] ?? 0 );
        $account_url  = get_permalink( $page_ids[2] ?? 0 );

        // Side panel icons — ordered list with enabled flag
        $default_icons = array(
            array( 'key' => 'account',  'enabled' => true ),
            array( 'key' => 'cart',     'enabled' => true ),
            array( 'key' => 'wishlist', 'enabled' => true ),
            array( 'key' => 'compare',  'enabled' => true ),
        );
        $icons_raw = get_theme_mod( 'flavor_side_panel_icons', wp_json_encode( $default_icons ) );
        $icons     = is_string( $icons_raw ) ? json_decode( $icons_raw, true ) : $icons_raw;
        if ( ! is_array( $icons ) ) $icons = $default_icons;

        // Build tabs — respect order & enabled flag + plugin-level toggles
        $tabs = array();
        foreach ( $icons as $icon ) {
            if ( empty( $icon['enabled'] ) ) continue;
            $key = $icon['key'] ?? '';
            if ( $key === 'compare' && ! get_option( 'fc_enable_compare', '1' ) ) continue;
            if ( $key === 'wishlist' && ! get_option( 'fc_enable_wishlist', '1' ) ) continue;
            if ( $key === 'wishlist' && ! class_exists( 'FC_Wishlist' ) ) continue;
            $tabs[] = $key;
        }

        // Wishlist data
        if ( in_array( 'wishlist', $tabs, true ) ) {
            $wishlist_items = FC_Wishlist::get_wishlist( get_current_user_id() );
            $wishlist_count = count( $wishlist_items );
            $wishlist_page  = get_option( 'fc_page_wishlist', '' );
            $wishlist_url   = $wishlist_page ? get_permalink( $wishlist_page ) : site_url( '/lista-zyczen/' );
        }

        // Compare data
        if ( in_array( 'compare', $tabs, true ) ) {
            if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) session_start();
            $compare_count = isset( $_SESSION['fc_compare'] ) ? count( $_SESSION['fc_compare'] ) : 0;
            $compare_page  = get_option( 'fc_page_porownanie', '' );
            $compare_url   = $compare_page ? get_permalink( $compare_page ) : site_url( '/porownanie/' );
        }
        ?>
        <div id="fc-mini-cart-overlay" class="fc-mini-cart-overlay"></div>
        <div id="fc-mini-cart" class="fc-mini-cart">
            <div class="fc-mini-cart-tabs">
            <?php foreach ( $tabs as $tab_key ) : ?>
                <?php if ( $tab_key === 'account' ) : ?>
                <a href="<?php echo esc_url( $account_url ); ?>" class="fc-mini-cart-tab fc-mini-account-tab" title="<?php fc_e( 'my_account' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
                <?php elseif ( $tab_key === 'cart' ) : ?>
                <button id="fc-mini-cart-tab" class="fc-mini-cart-tab" title="<?php fc_e( 'cart' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="fc-mini-cart-tab-count fc-cart-count"<?php if ( FC_Cart::get_count() < 1 ) echo ' style="display:none"'; ?>><?php echo FC_Cart::get_count(); ?></span>
                </button>
                <?php elseif ( $tab_key === 'wishlist' ) : ?>
                <button id="fc-mini-wishlist-tab" class="fc-mini-cart-tab fc-mini-wishlist-tab" title="<?php fc_e( 'wishlist' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    <span class="fc-mini-cart-tab-count fc-header-wishlist-count"<?php if ( $wishlist_count < 1 ) echo ' style="display:none"'; ?>><?php echo $wishlist_count; ?></span>
                </button>
                <?php elseif ( $tab_key === 'compare' ) : ?>
                <button id="fc-mini-compare-tab" class="fc-mini-cart-tab fc-mini-compare-tab" title="<?php fc_e( 'comparison' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
                    <span class="fc-mini-cart-tab-count fc-compare-count"<?php if ( $compare_count < 1 ) echo ' style="display:none"'; ?>><?php echo $compare_count; ?></span>
                </button>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
            <button class="fc-mini-cart-close" title="<?php fc_e( 'close' ); ?>">&times;</button>

            <!-- Cart panel -->
            <div class="fc-panel fc-panel-cart fc-panel-active">
                <div class="fc-mini-cart-header">
                    <h3><?php fc_e( 'cart' ); ?></h3>
                </div>
                <div class="fc-mini-cart-items">
                    <?php echo FC_Ajax::render_mini_cart_items(); ?>
                </div>
                <div class="fc-mini-cart-footer"<?php if ( FC_Cart::get_count() < 1 ) echo ' style="display:none"'; ?>>
                    <div class="fc-mini-cart-total">
                        <span><?php fc_e( 'total_label' ); ?></span>
                        <strong class="fc-mini-cart-total-value"><?php echo fc_format_price( FC_Cart::get_total() ); ?></strong>
                    </div>
                    <div class="fc-mini-cart-actions">
                        <a href="<?php echo esc_url( $cart_url ); ?>" class="fc-btn fc-btn-outline"><?php fc_e( 'cart' ); ?></a>
                        <a href="<?php echo esc_url( $checkout_url ); ?>" class="fc-btn"><?php fc_e( 'checkout' ); ?></a>
                    </div>
                </div>
            </div>

            <?php if ( in_array( 'wishlist', $tabs, true ) ) : ?>
            <!-- Wishlist panel -->
            <div class="fc-panel fc-panel-wishlist">
                <div class="fc-mini-cart-header">
                    <h3><?php fc_e( 'wishlist' ); ?></h3>
                </div>
                <div class="fc-wishlist-panel-items">
                    <?php echo self::render_wishlist_panel_items(); ?>
                </div>
                <div class="fc-wishlist-panel-footer"<?php if ( $wishlist_count < 1 ) echo ' style="display:none"'; ?>>
                    <div class="fc-mini-cart-actions">
                        <button type="button" class="fc-btn fc-btn-outline fc-wishlist-panel-clear"><?php fc_e( 'clear' ); ?></button>
                        <a href="<?php echo esc_url( $wishlist_url ); ?>" class="fc-btn"><?php fc_e( 'view_wishlist_full' ); ?></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( in_array( 'compare', $tabs, true ) ) : ?>
            <!-- Compare panel -->
            <div class="fc-panel fc-panel-compare">
                <div class="fc-mini-cart-header">
                    <h3><?php fc_e( 'comparison' ); ?></h3>
                </div>
                <div class="fc-compare-panel-items">
                    <?php echo self::render_compare_panel_items(); ?>
                </div>
                <div class="fc-compare-panel-footer"<?php if ( $compare_count < 1 ) echo ' style="display:none"'; ?>>
                    <div class="fc-mini-cart-actions">
                        <button type="button" class="fc-btn fc-btn-outline fc-compare-panel-clear"><?php fc_e( 'clear' ); ?></button>
                        <a href="<?php echo esc_url( $compare_url ); ?>" class="fc-btn fc-compare-go-btn<?php echo $compare_count < 2 ? ' fc-btn-disabled' : ''; ?>"><?php fc_e( 'compare' ); ?></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderuj elementy panelu porównania (do użycia w renderminiart i AJAX)
     */
    public static function render_compare_panel_items() {
        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) session_start();
        $ids = isset( $_SESSION['fc_compare'] ) ? $_SESSION['fc_compare'] : array();

        if ( empty( $ids ) ) {
            return '<p class="fc-compare-panel-empty">' . fc__( 'no_products_to_compare' ) . '</p>';
        }

        ob_start();
        foreach ( $ids as $pid ) {
            $post = get_post( $pid );
            if ( ! $post ) continue;
            $thumb = get_the_post_thumbnail( $pid, 'thumbnail' );
            if ( ! $thumb ) {
                $thumb = '<div class="fc-mini-cart-no-img"></div>';
            }
            $price = get_post_meta( $pid, '_fc_price', true );
            ?>
            <div class="fc-compare-panel-item" data-product-id="<?php echo esc_attr( $pid ); ?>">
                <div class="fc-mini-cart-thumb"><?php echo $thumb; ?></div>
                <div class="fc-mini-cart-details">
                    <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" class="fc-mini-cart-name">
                        <?php echo esc_html( get_the_title( $pid ) ); ?>
                    </a>
                    <span class="fc-compare-panel-price"><?php echo fc_format_price( $price, $pid ); ?></span>
                </div>
                <button type="button" class="fc-compare-panel-remove" data-product-id="<?php echo esc_attr( $pid ); ?>" title="<?php fc_e( 'remove' ); ?>">&times;</button>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Renderuj elementy panelu listy życzeń (do użycia w render_mini_cart i AJAX)
     */
    public static function render_wishlist_panel_items() {
        $ids = class_exists( 'FC_Wishlist' ) ? FC_Wishlist::get_wishlist( get_current_user_id() ) : array();

        if ( empty( $ids ) ) {
            return '<p class="fc-wishlist-panel-empty">' . fc__( 'wishlist_empty' ) . '</p>';
        }

        ob_start();
        foreach ( $ids as $pid ) {
            $post = get_post( $pid );
            if ( ! $post || $post->post_type !== 'fc_product' ) continue;
            if ( ! in_array( $post->post_status, array( 'fc_published', 'fc_preorder' ) ) ) continue;
            $thumb = get_the_post_thumbnail( $pid, 'thumbnail' );
            if ( ! $thumb ) {
                $thumb = '<div class="fc-mini-cart-no-img"></div>';
            }
            $price = get_post_meta( $pid, '_fc_price', true );
            ?>
            <div class="fc-wishlist-panel-item" data-product-id="<?php echo esc_attr( $pid ); ?>">
                <div class="fc-mini-cart-thumb"><?php echo $thumb; ?></div>
                <div class="fc-mini-cart-details">
                    <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" class="fc-mini-cart-name">
                        <?php echo esc_html( get_the_title( $pid ) ); ?>
                    </a>
                    <span class="fc-wishlist-panel-price"><?php echo fc_format_price( $price, $pid ); ?></span>
                </div>
                <button type="button" class="fc-wishlist-panel-remove" data-product-id="<?php echo esc_attr( $pid ); ?>" title="<?php fc_e( 'remove' ); ?>">&times;</button>
            </div>
            <?php
        }
        return ob_get_clean();
    }
}

/**
 * Helper: formatowanie ceny
 */
function fc_format_price( $price, $product_id = 0 ) {
    static $symbol = null;
    static $pos    = null;
    if ( $symbol === null ) {
        $symbol = get_option( 'fc_currency_symbol', 'zł' );
        $pos    = get_option( 'fc_currency_pos', 'after' );
    }
    // Zastosuj filtr ceny (m.in. role-based pricing)
    if ( $product_id ) {
        $price = apply_filters( 'fc_product_price', floatval( $price ), $product_id );
    }
    $formatted = number_format( floatval( $price ), 2, ',', ' ' );
    return $pos === 'before' ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
}

/**
 * Helper: URL sklepu
 */
function fc_get_shop_url() {
    static $url = null;
    if ( $url === null ) {
        $url = get_permalink( get_option( 'fc_page_sklep' ) );
    }
    return $url;
}

/**
 * Helper: URL checkout
 */
function fc_get_checkout_url() {
    static $url = null;
    if ( $url === null ) {
        $url = get_permalink( get_option( 'fc_page_zamowienie' ) );
    }
    return $url;
}

// Register activation/deactivation hooks at file level (before plugins_loaded)
register_activation_hook( __FILE__, array( 'Flavor_Commerce', 'file_activate' ) );
register_deactivation_hook( __FILE__, array( 'Flavor_Commerce', 'file_deactivate' ) );

// Uruchomienie wtyczki
function flavor_commerce() {
    return Flavor_Commerce::instance();
}
add_action( 'plugins_loaded', 'flavor_commerce' );
