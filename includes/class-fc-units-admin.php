<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Zarządzanie jednostkami miary
 * Przechowywane w wp_options jako 'fc_product_units'
 */
class FC_Units_Admin {

    const OPTION_KEY = 'fc_product_units';

    /**
     * Domyślne slugi jednostek (tłumaczone kluczami unit_<slug>)
     */
    const DEFAULTS = array( 'pcs', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'sqm', 'cbm', 'pack', 'set' );

    /**
     * Mapa migracji ze starych zlokalizowanych nazw na slugi
     */
    const LEGACY_MAP = array(
        'szt.'  => 'pcs',
        'm²'    => 'sqm',
        'm³'    => 'cbm',
        'opak.' => 'pack',
        'kpl.'  => 'set',
    );

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_post_fc_save_unit', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_fc_delete_unit', array( __CLASS__, 'handle_delete' ) );
        add_action( 'admin_post_fc_save_default_unit', array( __CLASS__, 'handle_save_default' ) );
        add_action( 'admin_post_fc_save_unit_visibility', array( __CLASS__, 'handle_save_visibility' ) );
        add_action( 'admin_post_fc_edit_unit', array( __CLASS__, 'handle_edit' ) );
        add_action( 'admin_post_fc_bulk_units', array( __CLASS__, 'handle_bulk' ) );
        self::maybe_migrate();
    }

    /**
     * Migracja starych zlokalizowanych nazw na slugi (jednorazowo)
     */
    public static function maybe_migrate() {
        if ( get_option( 'fc_units_migrated_v2' ) ) return;

        // Domyślna jednostka — migruj ZAWSZE (nawet gdy lista pusta)
        $default = get_option( 'fc_default_unit', '' );
        if ( isset( self::LEGACY_MAP[ $default ] ) ) {
            update_option( 'fc_default_unit', self::LEGACY_MAP[ $default ] );
        }

        // Meta produktów — migruj ZAWSZE
        global $wpdb;
        foreach ( self::LEGACY_MAP as $old => $new ) {
            $wpdb->update(
                $wpdb->postmeta,
                array( 'meta_value' => $new ),
                array( 'meta_key' => '_fc_unit', 'meta_value' => $old )
            );
        }

        // Lista jednostek
        $units = get_option( self::OPTION_KEY );
        if ( is_array( $units ) && ! empty( $units ) ) {
            $migrated = array_map( function( $u ) {
                return isset( FC_Units_Admin::LEGACY_MAP[ $u ] ) ? FC_Units_Admin::LEGACY_MAP[ $u ] : $u;
            }, $units );
            $migrated = array_unique( $migrated );
            sort( $migrated );
            update_option( self::OPTION_KEY, $migrated );
        }

        // Seeduj domyślne jeśli brak
        self::seed_defaults();

        update_option( 'fc_units_migrated_v2', '1' );
    }

    /**
     * Seedowanie domyślnych jednostek (wywoływane z activate() i migrate)
     */
    public static function seed_defaults() {
        $units = get_option( self::OPTION_KEY );
        if ( ! is_array( $units ) || empty( $units ) ) {
            update_option( self::OPTION_KEY, self::DEFAULTS );
        }
        $default = get_option( 'fc_default_unit', '' );
        if ( empty( $default ) || ! in_array( $default, self::DEFAULTS, true ) ) {
            update_option( 'fc_default_unit', 'pcs' );
        }
    }

    /**
     * Tłumaczenie slugu jednostki na zlokalizowaną etykietę
     */
    public static function label( $slug ) {
        if ( empty( $slug ) ) return '';
        $key = 'unit_' . $slug;
        $translated = fc__( $key );
        return $translated !== $key ? $translated : $slug;
    }

    /**
     * Pobierz domyślną jednostkę (slug)
     */
    public static function get_default() {
        return get_option( 'fc_default_unit', 'pcs' );
    }

    /**
     * Sprawdź widoczność jednostki na danej stronie
     */
    public static function is_visible( $context ) {
        $visibility = get_option( 'fc_unit_visibility', array( 'shop' => '1', 'cart' => '1', 'checkout' => '1', 'product' => '1', 'thank_you' => '1', 'account' => '1' ) );
        return ! empty( $visibility[ $context ] );
    }

    /**
     * Pobierz wszystkie jednostki
     */
    public static function get_all() {
        $units = get_option( self::OPTION_KEY, self::DEFAULTS );
        return is_array( $units ) ? $units : self::DEFAULTS;
    }

    /**
     * Menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=fc_product',
            fc__( 'unit_units_of_measure' ),
            fc__( 'unit_units_of_measure' ),
            'manage_options',
            'fc-units',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Zapis nowej jednostki
     */
    public static function handle_save() {
        if ( ! isset( $_POST['fc_unit_nonce'] ) || ! wp_verify_nonce( $_POST['fc_unit_nonce'], 'fc_save_unit' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $name = isset( $_POST['unit_name'] ) ? sanitize_text_field( trim( $_POST['unit_name'] ) ) : '';
        if ( empty( $name ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&error=empty' ) );
            exit;
        }

        $units = self::get_all();

        if ( in_array( $name, $units, true ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&error=exists' ) );
            exit;
        }

        $units[] = $name;
        sort( $units );
        update_option( self::OPTION_KEY, $units );

        wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&saved=1' ) );
        exit;
    }

    /**
     * Usuwanie jednostki
     */
    public static function handle_delete() {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_delete_unit' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $unit_name = isset( $_GET['unit'] ) ? sanitize_text_field( $_GET['unit'] ) : '';
        if ( empty( $unit_name ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units' ) );
            exit;
        }

        $units = self::get_all();
        $units = array_values( array_filter( $units, function( $u ) use ( $unit_name ) {
            return $u !== $unit_name;
        } ) );

        update_option( self::OPTION_KEY, $units );

        wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&deleted=1' ) );
        exit;
    }

    /**
     * Zapis domyślnej jednostki
     */
    public static function handle_save_default() {
        if ( ! isset( $_POST['fc_unit_nonce'] ) || ! wp_verify_nonce( $_POST['fc_unit_nonce'], 'fc_save_unit' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $default = isset( $_POST['fc_default_unit'] ) ? sanitize_text_field( $_POST['fc_default_unit'] ) : 'pcs';
        update_option( 'fc_default_unit', $default );

        wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&default_saved=1' ) );
        exit;
    }

    /**
     * Zapis widoczności jednostek
     */
    public static function handle_save_visibility() {
        if ( ! isset( $_POST['fc_unit_nonce'] ) || ! wp_verify_nonce( $_POST['fc_unit_nonce'], 'fc_save_unit' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $visibility = array(
            'shop'          => isset( $_POST['fc_unit_show_shop'] ) ? '1' : '0',
            'cart'          => isset( $_POST['fc_unit_show_cart'] ) ? '1' : '0',
            'checkout'      => isset( $_POST['fc_unit_show_checkout'] ) ? '1' : '0',
            'product'       => isset( $_POST['fc_unit_show_product'] ) ? '1' : '0',
            'thank_you'     => isset( $_POST['fc_unit_show_thank_you'] ) ? '1' : '0',
            'account'       => isset( $_POST['fc_unit_show_account'] ) ? '1' : '0',
            'invoice'       => isset( $_POST['fc_unit_show_invoice'] ) ? '1' : '0',
            'orders_list'   => isset( $_POST['fc_unit_show_orders_list'] ) ? '1' : '0',
            'order_details' => isset( $_POST['fc_unit_show_order_details'] ) ? '1' : '0',
        );
        update_option( 'fc_unit_visibility', $visibility );

        wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&visibility_saved=1' ) );
        exit;
    }

    /**
     * Edycja pojedynczej jednostki
     */
    public static function handle_edit() {
        if ( ! isset( $_POST['fc_unit_nonce'] ) || ! wp_verify_nonce( $_POST['fc_unit_nonce'], 'fc_save_unit' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $old_name = isset( $_POST['old_unit_name'] ) ? sanitize_text_field( $_POST['old_unit_name'] ) : '';
        $new_name = isset( $_POST['new_unit_name'] ) ? sanitize_text_field( trim( $_POST['new_unit_name'] ) ) : '';

        if ( empty( $old_name ) || empty( $new_name ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&error=empty' ) );
            exit;
        }

        $units = self::get_all();

        // Sprawdź duplikaty (pomijając starą nazwę)
        if ( $old_name !== $new_name && in_array( $new_name, $units, true ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&error=exists' ) );
            exit;
        }

        // Zamień w liście
        $units = array_map( function( $u ) use ( $old_name, $new_name ) {
            return $u === $old_name ? $new_name : $u;
        }, $units );
        sort( $units );
        update_option( self::OPTION_KEY, $units );

        // Zaktualizuj domyślną jeśli była zmieniona
        if ( get_option( 'fc_default_unit' ) === $old_name ) {
            update_option( 'fc_default_unit', $new_name );
        }

        // Zaktualizuj meta produktów
        global $wpdb;
        $wpdb->update(
            $wpdb->postmeta,
            array( 'meta_value' => $new_name ),
            array( 'meta_key' => '_fc_unit', 'meta_value' => $old_name )
        );

        wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&edited=1' ) );
        exit;
    }

    /**
     * Akcje masowe
     */
    public static function handle_bulk() {
        if ( ! isset( $_POST['fc_unit_nonce'] ) || ! wp_verify_nonce( $_POST['fc_unit_nonce'], 'fc_save_unit' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
        $selected = isset( $_POST['bulk_units'] ) && is_array( $_POST['bulk_units'] ) ? array_map( 'sanitize_text_field', $_POST['bulk_units'] ) : array();

        if ( empty( $action ) || empty( $selected ) ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units' ) );
            exit;
        }

        $units = self::get_all();

        if ( $action === 'delete' ) {
            $units = array_values( array_filter( $units, function( $u ) use ( $selected ) {
                return ! in_array( $u, $selected, true );
            } ) );
            update_option( self::OPTION_KEY, $units );
            wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units&bulk_deleted=' . count( $selected ) ) );
            exit;
        }

        wp_safe_redirect( admin_url( 'edit.php?post_type=fc_product&page=fc-units' ) );
        exit;
    }

    /**
     * Strona zarządzania jednostkami
     */
    public static function render_page() {
        $units = self::get_all();
        $default_unit = self::get_default();
        $visibility = get_option( 'fc_unit_visibility', array( 'shop' => '1', 'cart' => '1', 'checkout' => '1', 'product' => '1', 'thank_you' => '1', 'account' => '1' ) );
        $saved   = isset( $_GET['saved'] ) ? true : false;
        $deleted = isset( $_GET['deleted'] ) ? true : false;
        $default_saved = isset( $_GET['default_saved'] ) ? true : false;
        $visibility_saved = isset( $_GET['visibility_saved'] ) ? true : false;
        $edited  = isset( $_GET['edited'] ) ? true : false;
        $bulk_deleted = isset( $_GET['bulk_deleted'] ) ? absint( $_GET['bulk_deleted'] ) : 0;
        $error   = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
        ?>
        <div class="wrap fc-units-page">
            <h1 class="wp-heading-inline"><?php fc_e( 'unit_units_of_measure' ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'unit_unit_has_been_added' ); ?></p></div>
            <?php endif; ?>
            <?php if ( $deleted ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'unit_unit_has_been_deleted' ); ?></p></div>
            <?php endif; ?>
            <?php if ( $default_saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'unit_default_unit_has_been_saved' ); ?></p></div>
            <?php endif; ?>
            <?php if ( $visibility_saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'unit_visibility_settings_have_been_saved' ); ?></p></div>
            <?php endif; ?>
            <?php if ( $edited ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'unit_unit_has_been_updated' ); ?></p></div>
            <?php endif; ?>
            <?php if ( $bulk_deleted > 0 ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php printf( fc__( 'unit_deleted_units' ), $bulk_deleted ); ?></p></div>
            <?php endif; ?>
            <?php if ( $error === 'empty' ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php fc_e( 'unit_unit_name_cannot_be_empty' ); ?></p></div>
            <?php elseif ( $error === 'exists' ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php fc_e( 'unit_this_unit_already_exists' ); ?></p></div>
            <?php endif; ?>

            <div class="fc-units-layout">
                <!-- Formularz dodawania -->
                <div class="fc-units-add">
                    <h2><?php fc_e( 'unit_add_new_unit' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="fc_save_unit">
                        <?php wp_nonce_field( 'fc_save_unit', 'fc_unit_nonce' ); ?>

                        <div class="form-field">
                            <label for="unit_name"><?php fc_e( 'attr_name' ); ?> <span class="required">*</span></label>
                            <input type="text" id="unit_name" name="unit_name" placeholder="<?php fc_e( 'prod_e_g_pcs_kg_pack' ); ?>" required>
                            <p class="description"><?php fc_e( 'unit_abbreviation_or_name_of_the_unit_of_measure_displa' ); ?></p>
                        </div>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php fc_e( 'prod_add_unit' ); ?></button>
                        </p>
                    </form>

                    <hr style="margin: 20px 0;">

                    <h2><?php fc_e( 'unit_default_unit' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="fc_save_default_unit">
                        <?php wp_nonce_field( 'fc_save_unit', 'fc_unit_nonce' ); ?>

                        <div class="form-field">
                            <label for="fc_default_unit"><?php fc_e( 'unit_default_unit_2' ); ?></label>
                            <select name="fc_default_unit" id="fc_default_unit" style="width:100%;">
                                <?php foreach ( $units as $unit ) : ?>
                                    <option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $default_unit, $unit ); ?>><?php echo esc_html( self::label( $unit ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php fc_e( 'unit_unit_used_when_a_product_does_not_have_its_own_ass' ); ?></p>
                        </div>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php fc_e( 'unit_save_default' ); ?></button>
                        </p>
                    </form>

                    <hr style="margin: 20px 0;">

                    <h2><?php fc_e( 'unit_visibility' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="fc_save_unit_visibility">
                        <?php wp_nonce_field( 'fc_save_unit', 'fc_unit_nonce' ); ?>

                        <p style="margin-bottom:8px;"><?php fc_e( 'unit_display_unit_on' ); ?></p>
                        <fieldset class="fc-unit-visibility-checks">
                            <label>
                                <input type="checkbox" name="fc_unit_show_shop" value="1" <?php checked( ! empty( $visibility['shop'] ) ); ?>>
                                <?php fc_e( 'unit_shop_page_product_tiles' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_product" value="1" <?php checked( ! empty( $visibility['product'] ) ); ?>>
                                <?php fc_e( 'unit_product_page' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_cart" value="1" <?php checked( ! empty( $visibility['cart'] ) ); ?>>
                                <?php fc_e( 'set_cart_page' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_checkout" value="1" <?php checked( ! empty( $visibility['checkout'] ) ); ?>>
                                <?php fc_e( 'unit_order_page_checkout' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_thank_you" value="1" <?php checked( ! empty( $visibility['thank_you'] ) ); ?>>
                                <?php fc_e( 'set_thank_you_page' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_account" value="1" <?php checked( ! empty( $visibility['account'] ) ); ?>>
                                <?php fc_e( 'unit_my_account_order_details' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_invoice" value="1" <?php checked( ! empty( $visibility['invoice'] ) ); ?>>
                                <?php fc_e( 'unit_invoice_pdf' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_orders_list" value="1" <?php checked( ! empty( $visibility['orders_list'] ) ); ?>>
                                <?php fc_e( 'unit_order_list_admin' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fc_unit_show_order_details" value="1" <?php checked( ! empty( $visibility['order_details'] ) ); ?>>
                                <?php fc_e( 'unit_order_details_admin' ); ?>
                            </label>
                        </fieldset>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php fc_e( 'unit_save_visibility' ); ?></button>
                        </p>
                    </form>
                </div>

                <!-- Lista jednostek -->
                <div class="fc-units-list">
                    <?php if ( empty( $units ) ) : ?>
                        <p><?php fc_e( 'unit_no_units_defined' ); ?></p>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fc_bulk_units_form">
                            <input type="hidden" name="action" value="fc_bulk_units">
                            <?php wp_nonce_field( 'fc_save_unit', 'fc_unit_nonce' ); ?>

                            <div class="fc-units-bulk-bar">
                                <select name="bulk_action" class="fc-bulk-select">
                                    <option value=""><?php fc_e( 'unit_bulk_actions' ); ?></option>
                                    <option value="delete"><?php fc_e( 'attr_delete' ); ?></option>
                                </select>
                                <button type="submit" class="button" onclick="return this.form.bulk_action.value === 'delete' ? confirm('<?php echo esc_attr( fc__( 'unit_delete_selected_units' ) ); ?>') : true;"><?php fc_e( 'prod_apply' ); ?></button>
                            </div>

                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th class="fc-unit-cb-col"><input type="checkbox" id="fc_unit_check_all"></th>
                                        <th><?php fc_e( 'attr_name' ); ?></th>
                                        <th><?php fc_e( 'unit_price_preview' ); ?></th>
                                        <th class="fc-unit-actions-col"><?php fc_e( 'attr_actions' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $units as $unit ) : ?>
                                        <tr class="fc-unit-row" data-unit="<?php echo esc_attr( $unit ); ?>">
                                            <td><input type="checkbox" name="bulk_units[]" value="<?php echo esc_attr( $unit ); ?>" class="fc-unit-cb"></td>
                                            <td>
                                                <strong class="fc-unit-name-display"><?php echo esc_html( self::label( $unit ) ); ?></strong>
                                                <?php if ( $unit === $default_unit ) : ?>
                                                    <span class="fc-unit-default-badge"><?php fc_e( 'unit_default' ); ?></span>
                                                <?php endif; ?>
                                                <div class="fc-unit-inline-edit" style="display:none;">
                                                    <input type="text" class="fc-unit-edit-input" value="<?php echo esc_attr( $unit ); ?>">
                                                    <button type="button" class="button button-primary button-small fc-unit-edit-save"><?php fc_e( 'unit_save' ); ?></button>
                                                    <button type="button" class="button button-small fc-unit-edit-cancel"><?php fc_e( 'coupon_cancel' ); ?></button>
                                                </div>
                                            </td>
                                            <td class="fc-unit-preview"><?php echo fc_format_price( 99.99 ); ?> / <?php echo esc_html( self::label( $unit ) ); ?></td>
                                            <td>
                                                <a href="#" class="fc-unit-edit-link"><?php fc_e( 'attr_edit' ); ?></a>
                                                <span class="fc-unit-sep">|</span>
                                                <a href="<?php echo esc_url( wp_nonce_url(
                                                    admin_url( 'admin-post.php?action=fc_delete_unit&unit=' . urlencode( $unit ) ),
                                                    'fc_delete_unit'
                                                ) ); ?>"
                                                   class="fc-unit-delete"
                                                   onclick="return confirm('<?php printf( esc_attr( fc__( 'unit_delete_unit' ) ), esc_attr( $unit ) ); ?>');">
                                                    <?php fc_e( 'attr_delete' ); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                    <?php endif; ?>

                    <!-- Ukryty formularz edycji -->
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fc_edit_unit_form" style="display:none;">
                        <input type="hidden" name="action" value="fc_edit_unit">
                        <?php wp_nonce_field( 'fc_save_unit', 'fc_unit_nonce' ); ?>
                        <input type="hidden" name="old_unit_name" id="fc_edit_old_name" value="">
                        <input type="hidden" name="new_unit_name" id="fc_edit_new_name" value="">
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
