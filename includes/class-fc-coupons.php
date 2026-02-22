<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * System kuponów rabatowych (K4)
 */
class FC_Coupons {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_post_fc_save_coupon', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_fc_delete_coupon', array( __CLASS__, 'handle_delete' ) );
        add_action( 'wp_ajax_fc_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_nopriv_fc_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_fc_remove_coupon', array( __CLASS__, 'ajax_remove_coupon' ) );
        add_action( 'wp_ajax_nopriv_fc_remove_coupon', array( __CLASS__, 'ajax_remove_coupon' ) );
    }

    /**
     * Rejestracja CPT kuponu
     */
    public static function register_post_type() {
        register_post_type( 'fc_coupon', array(
            'labels' => array(
                'name'               => fc__( 'coupon_coupons' ),
                'singular_name'      => fc__( 'coupon_coupon' ),
                'add_new'            => fc__( 'coupon_add_coupon' ),
                'add_new_item'       => fc__( 'coupon_add_new_coupon' ),
                'edit_item'          => fc__( 'coupon_edit_coupon' ),
                'all_items'          => fc__( 'coupon_coupons' ),
                'menu_name'          => fc__( 'coupon_coupons' ),
            ),
            'public'             => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'supports'           => array( 'title' ),
            'capability_type'    => 'post',
        ) );
    }

    /**
     * Menu — zintegrowane z zakładkami Flavor Commerce
     */
    public static function add_menu() {
        // Menu jest teraz zakładką w panelu FC (class-fc-settings.php)
    }

    /**
     * Strona listy i formularza kuponów
     */
    public static function render_page() {
        $edit_id  = isset( $_GET['coupon_id'] ) ? absint( $_GET['coupon_id'] ) : 0;
        $caction  = isset( $_GET['coupon_action'] ) ? sanitize_text_field( $_GET['coupon_action'] ) : '';
        $saved    = isset( $_GET['saved'] ) ? sanitize_text_field( $_GET['saved'] ) : '';

        if ( $caction === 'edit' || $caction === 'add' ) {
            self::render_form( $edit_id );
            return;
        }

        // Lista kuponów
        $coupons = get_posts( array(
            'post_type'      => 'fc_coupon',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        ?>
            <h1 class="wp-heading-inline"><?php fc_e( 'coupon_discount_coupons' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=flavor-commerce&tab=coupons&coupon_action=add' ) ); ?>" class="page-title-action"><?php fc_e( 'coupon_add_coupon' ); ?></a>

            <?php if ( $saved === '1' ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php fc_e( 'coupon_coupon_saved' ); ?></p></div>
            <?php elseif ( $saved === 'deleted' ) : ?>
                <div class="notice notice-warning is-dismissible"><p><?php fc_e( 'coupon_coupon_deleted' ); ?></p></div>
            <?php endif; ?>

            <table class="wp-list-table widefat striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th><?php fc_e( 'coupon_code' ); ?></th>
                        <th><?php fc_e( 'attr_type' ); ?></th>
                        <th><?php fc_e( 'coupon_value' ); ?></th>
                        <th><?php fc_e( 'coupon_uses' ); ?></th>
                        <th><?php fc_e( 'coupon_limit' ); ?></th>
                        <th><?php fc_e( 'coupon_validity' ); ?></th>
                        <th><?php fc_e( 'coupon_combinability' ); ?></th>
                        <th><?php fc_e( 'coupon_status' ); ?></th>
                        <th><?php fc_e( 'attr_actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $coupons ) ) : ?>
                        <tr><td colspan="9" style="text-align:center;padding:20px;"><?php fc_e( 'coupon_no_coupons' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $coupons as $coupon ) :
                            $meta = self::get_coupon_data( $coupon->ID );
                            $usage = intval( get_post_meta( $coupon->ID, '_fc_coupon_usage', true ) );
                            $limit = $meta['usage_limit'] ? intval( $meta['usage_limit'] ) : '∞';
                            $now = time();
                            $expired = $meta['expiry_date'] && strtotime( $meta['expiry_date'] ) < $now;
                            $exhausted = $meta['usage_limit'] && $usage >= intval( $meta['usage_limit'] );
                            $is_active = ! $expired && ! $exhausted && $meta['enabled'];
                        ?>
                            <tr>
                                <td><strong><code style="font-size:13px;"><?php echo esc_html( strtoupper( $coupon->post_title ) ); ?></code></strong></td>
                                <td><?php echo $meta['discount_type'] === 'percent' ? fc__( 'coupon_percentage' ) : fc__( 'coupon_fixed_amount' ); ?></td>
                                <td>
                                    <?php if ( $meta['discount_type'] === 'percent' ) : ?>
                                        <?php echo esc_html( $meta['amount'] ); ?>%
                                    <?php else : ?>
                                        <?php echo fc_format_price( $meta['amount'] ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $usage; ?></td>
                                <td><?php echo $limit; ?></td>
                                <td>
                                    <?php if ( $meta['expiry_date'] ) : ?>
                                        <?php echo date_i18n( 'j M Y', strtotime( $meta['expiry_date'] ) ); ?>
                                    <?php else : ?>
                                        <?php fc_e( 'coupon_no_expiration' ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $meta['stackable'] === '1' ) : ?>
                                        <span style="color:#27ae60;">✓ <?php fc_e( 'coupon_yes' ); ?></span>
                                    <?php else : ?>
                                        <span style="color:#95a5a6;">✗ <?php fc_e( 'coupon_no' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $is_active ) : ?>
                                        <span style="color:#27ae60;font-weight:600;"><?php fc_e( 'coupon_active' ); ?></span>
                                    <?php elseif ( $expired ) : ?>
                                        <span style="color:#e74c3c;"><?php fc_e( 'coupon_expired' ); ?></span>
                                    <?php elseif ( $exhausted ) : ?>
                                        <span style="color:#e67e22;"><?php fc_e( 'coupon_exhausted' ); ?></span>
                                    <?php else : ?>
                                        <span style="color:#95a5a6;"><?php fc_e( 'coupon_disabled' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=flavor-commerce&tab=coupons&coupon_action=edit&coupon_id=' . $coupon->ID ) ); ?>"><?php fc_e( 'attr_edit' ); ?></a>
                                    |
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fc_delete_coupon&coupon_id=' . $coupon->ID ), 'fc_delete_coupon_' . $coupon->ID ) ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( fc__( 'coupon_delete_coupon' ) ); ?>');"><?php fc_e( 'attr_delete' ); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php
    }

    /**
     * Formularz dodawania/edycji kuponu
     */
    public static function render_form( $coupon_id = 0 ) {
        $is_edit = $coupon_id > 0;
        $data = array(
            'code'            => '',
            'discount_type'   => 'percent',
            'amount'          => '',
            'min_order'       => '',
            'max_discount'    => '',
            'usage_limit'     => '',
            'usage_per_user'  => '',
            'expiry_date'     => '',
            'free_shipping'   => '0',
            'enabled'         => '1',
            'stackable'       => '1',
            'product_ids'     => array(),
            'category_ids'    => array(),
            'exclude_sale'    => '0',
            'description'     => '',
        );

        if ( $is_edit ) {
            $post = get_post( $coupon_id );
            if ( ! $post || $post->post_type !== 'fc_coupon' ) {
                echo '<p>' . fc__( 'coupon_coupon_does_not_exist' ) . '</p>';
                return;
            }
            $data = self::get_coupon_data( $coupon_id );
            $data['code'] = $post->post_title;
        }

        $symbol = get_option( 'fc_currency_symbol', 'zł' );
        ?>
            <h1><?php echo $is_edit ? fc__( 'coupon_edit_coupon' ) : fc__( 'coupon_add_new_coupon' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fc-admin-form" style="max-width:800px;">
                <?php wp_nonce_field( 'fc_save_coupon', 'fc_coupon_nonce' ); ?>
                <input type="hidden" name="action" value="fc_save_coupon">
                <input type="hidden" name="coupon_id" value="<?php echo esc_attr( $coupon_id ); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="fc_coupon_code"><?php fc_e( 'coupon_coupon_code' ); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="fc_coupon_code" name="fc_coupon_code" value="<?php echo esc_attr( $data['code'] ); ?>" class="regular-text" required style="text-transform:uppercase;" placeholder="<?php echo esc_attr( fc__( 'coupon_eg_discount20' ) ); ?>">
                            <p class="description"><?php fc_e( 'coupon_unique_code_that_the_customer_enters_at_checkout' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fc_coupon_desc"><?php fc_e( 'coupon_description' ); ?></label></th>
                        <td>
                            <textarea id="fc_coupon_desc" name="fc_coupon_desc" rows="2" class="large-text" placeholder="<?php fc_e( 'coupon_internal_description_optional' ); ?>"><?php echo esc_textarea( $data['description'] ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fc_discount_type"><?php fc_e( 'coupon_discount_type' ); ?></label></th>
                        <td>
                            <select id="fc_discount_type" name="fc_discount_type">
                                <option value="percent" <?php selected( $data['discount_type'], 'percent' ); ?>><?php fc_e( 'coupon_percentage_2' ); ?></option>
                                <option value="fixed" <?php selected( $data['discount_type'], 'fixed' ); ?>><?php printf( fc__( 'coupon_fixed_amount_2' ), $symbol ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fc_coupon_amount"><?php fc_e( 'coupon_discount_value' ); ?> <span class="required">*</span></label></th>
                        <td><input type="number" id="fc_coupon_amount" name="fc_coupon_amount" value="<?php echo esc_attr( $data['amount'] ); ?>" step="0.01" min="0" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="fc_min_order"><?php printf( fc__( 'coupon_min_order_value' ), $symbol ); ?></label></th>
                        <td><input type="number" id="fc_min_order" name="fc_min_order" value="<?php echo esc_attr( $data['min_order'] ); ?>" step="0.01" min="0" class="regular-text" placeholder="<?php fc_e( 'coupon_no_minimum' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="fc_max_discount"><?php printf( fc__( 'coupon_max_discount_amount' ), $symbol ); ?></label></th>
                        <td>
                            <input type="number" id="fc_max_discount" name="fc_max_discount" value="<?php echo esc_attr( $data['max_discount'] ); ?>" step="0.01" min="0" class="regular-text" placeholder="<?php fc_e( 'coupon_no_limit' ); ?>">
                            <p class="description"><?php fc_e( 'coupon_for_percentage_coupons_maximum_discount_amount' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fc_usage_limit"><?php fc_e( 'coupon_usage_limit_total' ); ?></label></th>
                        <td><input type="number" id="fc_usage_limit" name="fc_usage_limit" value="<?php echo esc_attr( $data['usage_limit'] ); ?>" min="0" step="1" class="regular-text" placeholder="<?php fc_e( 'coupon_no_limit' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="fc_usage_per_user"><?php fc_e( 'coupon_limit_per_user' ); ?></label></th>
                        <td><input type="number" id="fc_usage_per_user" name="fc_usage_per_user" value="<?php echo esc_attr( $data['usage_per_user'] ); ?>" min="0" step="1" class="regular-text" placeholder="<?php fc_e( 'coupon_no_limit' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="fc_expiry_date"><?php fc_e( 'coupon_expiration_date' ); ?></label></th>
                        <td><input type="date" id="fc_expiry_date" name="fc_expiry_date" value="<?php echo esc_attr( $data['expiry_date'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php fc_e( 'coupon_options' ); ?></th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="fc_free_shipping" value="1" <?php checked( $data['free_shipping'], '1' ); ?>>
                                <?php fc_e( 'coupon_free_shipping' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="fc_exclude_sale" value="1" <?php checked( $data['exclude_sale'], '1' ); ?>>
                                <?php fc_e( 'coupon_exclude_products_on_sale' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="fc_stackable" value="1" <?php checked( $data['stackable'], '1' ); ?>>
                                <?php fc_e( 'coupon_combines_with_other_coupons' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="checkbox" name="fc_coupon_enabled" value="1" <?php checked( $data['enabled'], '1' ); ?>>
                                <?php fc_e( 'coupon_coupon_active' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php fc_e( 'coupon_allowed_products' ); ?></label></th>
                        <td>
                            <?php
                            $all_products = get_posts( array( 'post_type' => 'fc_product', 'post_status' => array( 'fc_published', 'fc_preorder' ), 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
                            $selected_products = is_array( $data['product_ids'] ) ? array_map( 'intval', $data['product_ids'] ) : array();
                            ?>
                            <input type="text" class="fc-select-filter" placeholder="<?php fc_e( 'search_products' ); ?>…" style="width:100%;margin-bottom:4px;">
                            <select name="fc_coupon_product_ids[]" multiple style="width:100%;min-height:120px;">
                                <?php foreach ( $all_products as $prod ) : ?>
                                    <option value="<?php echo esc_attr( $prod->ID ); ?>" <?php echo in_array( $prod->ID, $selected_products, true ) ? 'selected' : ''; ?>><?php echo esc_html( $prod->post_title ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php fc_e( 'coupon_leave_empty_for_the_coupon_to_apply_to_all_product' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php fc_e( 'coupon_allowed_categories' ); ?></label></th>
                        <td>
                            <?php
                            $all_cats = get_terms( array( 'taxonomy' => 'fc_product_cat', 'hide_empty' => false ) );
                            $selected_cats = is_array( $data['category_ids'] ) ? array_map( 'intval', $data['category_ids'] ) : array();
                            ?>
                            <input type="text" class="fc-select-filter" placeholder="<?php fc_e( 'search_categories' ); ?>…" style="width:100%;margin-bottom:4px;">
                            <select name="fc_coupon_category_ids[]" multiple style="width:100%;min-height:100px;">
                                <?php if ( ! is_wp_error( $all_cats ) && ! empty( $all_cats ) ) : ?>
                                    <?php foreach ( $all_cats as $cat ) : ?>
                                        <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $selected_cats, true ) ? 'selected' : ''; ?>><?php echo esc_html( $cat->name ); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description"><?php fc_e( 'coupon_leave_empty_for_the_coupon_to_apply_to_all_categor' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? fc__( 'attr_save_changes' ) : fc__( 'coupon_add_coupon' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=flavor-commerce&tab=coupons' ) ); ?>" class="button"><?php fc_e( 'coupon_cancel' ); ?></a>
                </p>
            </form>
        <?php
    }

    /**
     * Pobierz dane kuponu
     */
    public static function get_coupon_data( $coupon_id ) {
        return array(
            'code'           => get_the_title( $coupon_id ),
            'discount_type'  => get_post_meta( $coupon_id, '_fc_discount_type', true ) ?: 'percent',
            'amount'         => get_post_meta( $coupon_id, '_fc_coupon_amount', true ),
            'min_order'      => get_post_meta( $coupon_id, '_fc_min_order', true ),
            'max_discount'   => get_post_meta( $coupon_id, '_fc_max_discount', true ),
            'usage_limit'    => get_post_meta( $coupon_id, '_fc_usage_limit', true ),
            'usage_per_user' => get_post_meta( $coupon_id, '_fc_usage_per_user', true ),
            'expiry_date'    => get_post_meta( $coupon_id, '_fc_expiry_date', true ),
            'free_shipping'  => get_post_meta( $coupon_id, '_fc_free_shipping', true ) ?: '0',
            'enabled'        => get_post_meta( $coupon_id, '_fc_coupon_enabled', true ) !== '0' ? '1' : '0',
            'stackable'      => get_post_meta( $coupon_id, '_fc_stackable', true ) !== '0' ? '1' : '0',
            'product_ids'    => get_post_meta( $coupon_id, '_fc_coupon_product_ids', true ) ?: array(),
            'category_ids'   => get_post_meta( $coupon_id, '_fc_coupon_category_ids', true ) ?: array(),
            'exclude_sale'   => get_post_meta( $coupon_id, '_fc_exclude_sale', true ) ?: '0',
            'description'    => get_post_meta( $coupon_id, '_fc_coupon_description', true ),
        );
    }

    /**
     * Zapisanie kuponu
     */
    public static function handle_save() {
        if ( ! isset( $_POST['fc_coupon_nonce'] ) || ! wp_verify_nonce( $_POST['fc_coupon_nonce'], 'fc_save_coupon' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $coupon_id = absint( $_POST['coupon_id'] ?? 0 );
        $code = strtoupper( sanitize_text_field( $_POST['fc_coupon_code'] ?? '' ) );

        if ( empty( $code ) ) {
            wp_die( fc__( 'coupon_coupon_code_is_required' ) );
        }

        // Sprawdź unikalność kodu
        $existing = get_posts( array(
            'post_type'      => 'fc_coupon',
            'title'          => $code,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'exclude'        => $coupon_id ? array( $coupon_id ) : array(),
        ) );
        if ( ! empty( $existing ) ) {
            wp_die( fc__( 'coupon_a_coupon_with_this_code_already_exists' ) );
        }

        $post_data = array(
            'post_type'   => 'fc_coupon',
            'post_title'  => $code,
            'post_status' => 'publish',
        );

        if ( $coupon_id ) {
            $post_data['ID'] = $coupon_id;
            wp_update_post( $post_data );
        } else {
            $coupon_id = wp_insert_post( $post_data );
        }

        // Meta
        update_post_meta( $coupon_id, '_fc_discount_type', sanitize_text_field( $_POST['fc_discount_type'] ?? 'percent' ) );
        update_post_meta( $coupon_id, '_fc_coupon_amount', sanitize_text_field( $_POST['fc_coupon_amount'] ?? '' ) );
        update_post_meta( $coupon_id, '_fc_min_order', sanitize_text_field( $_POST['fc_min_order'] ?? '' ) );
        update_post_meta( $coupon_id, '_fc_max_discount', sanitize_text_field( $_POST['fc_max_discount'] ?? '' ) );
        update_post_meta( $coupon_id, '_fc_usage_limit', sanitize_text_field( $_POST['fc_usage_limit'] ?? '' ) );
        update_post_meta( $coupon_id, '_fc_usage_per_user', sanitize_text_field( $_POST['fc_usage_per_user'] ?? '' ) );
        update_post_meta( $coupon_id, '_fc_expiry_date', sanitize_text_field( $_POST['fc_expiry_date'] ?? '' ) );
        update_post_meta( $coupon_id, '_fc_free_shipping', isset( $_POST['fc_free_shipping'] ) ? '1' : '0' );
        update_post_meta( $coupon_id, '_fc_exclude_sale', isset( $_POST['fc_exclude_sale'] ) ? '1' : '0' );
        update_post_meta( $coupon_id, '_fc_stackable', isset( $_POST['fc_stackable'] ) ? '1' : '0' );
        update_post_meta( $coupon_id, '_fc_coupon_enabled', isset( $_POST['fc_coupon_enabled'] ) ? '1' : '0' );
        update_post_meta( $coupon_id, '_fc_coupon_description', sanitize_textarea_field( $_POST['fc_coupon_desc'] ?? '' ) );

        // Produkty i kategorie
        $product_ids = isset( $_POST['fc_coupon_product_ids'] ) && is_array( $_POST['fc_coupon_product_ids'] )
            ? array_map( 'absint', $_POST['fc_coupon_product_ids'] )
            : array();
        $category_ids = isset( $_POST['fc_coupon_category_ids'] ) && is_array( $_POST['fc_coupon_category_ids'] )
            ? array_map( 'absint', $_POST['fc_coupon_category_ids'] )
            : array();
        update_post_meta( $coupon_id, '_fc_coupon_product_ids', $product_ids );
        update_post_meta( $coupon_id, '_fc_coupon_category_ids', $category_ids );

        wp_safe_redirect( admin_url( 'admin.php?page=flavor-commerce&tab=coupons&saved=1' ) );
        exit;
    }

    /**
     * Usunięcie kuponu
     */
    public static function handle_delete() {
        $coupon_id = absint( $_GET['coupon_id'] ?? 0 );
        if ( ! $coupon_id || ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_delete_coupon_' . $coupon_id ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }
        wp_delete_post( $coupon_id, true );
        wp_safe_redirect( admin_url( 'admin.php?page=flavor-commerce&tab=coupons&saved=deleted' ) );
        exit;
    }

    /**
     * Znajdź kupon po kodzie
     */
    public static function find_by_code( $code ) {
        $code = strtoupper( trim( $code ) );
        $posts = get_posts( array(
            'post_type'      => 'fc_coupon',
            'title'          => $code,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ) );
        return ! empty( $posts ) ? $posts[0] : null;
    }

    /**
     * Walidacja kuponu
     */
    public static function validate( $code, $cart_total = 0, $user_id = 0 ) {
        $coupon = self::find_by_code( $code );
        if ( ! $coupon ) {
            return array( 'valid' => false, 'error' => fc__( 'coupon_invalid_coupon_code' ) );
        }

        $data = self::get_coupon_data( $coupon->ID );

        // Czy aktywny?
        if ( ! $data['enabled'] || $data['enabled'] === '0' ) {
            return array( 'valid' => false, 'error' => fc__( 'coupon_coupon_is_inactive' ) );
        }

        // Czy wygasł?
        if ( $data['expiry_date'] && strtotime( $data['expiry_date'] ) < time() ) {
            return array( 'valid' => false, 'error' => fc__( 'coupon_coupon_has_expired' ) );
        }

        // Limit użyć
        $usage = intval( get_post_meta( $coupon->ID, '_fc_coupon_usage', true ) );
        if ( $data['usage_limit'] && $usage >= intval( $data['usage_limit'] ) ) {
            return array( 'valid' => false, 'error' => fc__( 'coupon_coupon_has_already_been_used' ) );
        }

        // Limit na użytkownika
        if ( $data['usage_per_user'] && $user_id ) {
            $user_usage = intval( get_user_meta( $user_id, '_fc_coupon_used_' . $coupon->ID, true ) );
            if ( $user_usage >= intval( $data['usage_per_user'] ) ) {
                return array( 'valid' => false, 'error' => fc__( 'coupon_coupon_usage_limit_for_your_account_has_been_reach' ) );
            }
        }

        // Minimalna wartość zamówienia
        if ( $data['min_order'] && $cart_total < floatval( $data['min_order'] ) ) {
            return array( 'valid' => false, 'error' => sprintf(
                fc__( 'coupon_minimum_order_value_is' ),
                fc_format_price( $data['min_order'] )
            ) );
        }

        // Sprawdź, czy w koszyku są kwalifikujące się produkty
        $eligible_total = self::get_eligible_total( $data );
        if ( $eligible_total <= 0 ) {
            if ( $data['exclude_sale'] === '1' ) {
                return array( 'valid' => false, 'error' => fc__( 'coupon_coupon_does_not_apply_to_products_on_sale' ) );
            }
            if ( ! empty( $data['product_ids'] ) || ! empty( $data['category_ids'] ) ) {
                return array( 'valid' => false, 'error' => fc__( 'coupon_coupon_does_not_apply_to_products_in_your_cart' ) );
            }
        }

        return array( 'valid' => true, 'coupon_id' => $coupon->ID, 'data' => $data );
    }

    /**
     * Oblicz rabat
     */
    public static function calculate_discount( $coupon_id, $cart_total ) {
        $data = self::get_coupon_data( $coupon_id );
        $amount = floatval( $data['amount'] );

        // Oblicz kwalifikowaną kwotę koszyka (uwzględnij exclude_sale, product_ids, category_ids)
        $eligible_total = self::get_eligible_total( $data );

        if ( $data['discount_type'] === 'percent' ) {
            $discount = $eligible_total * ( $amount / 100 );
            // Max kwota rabatu
            if ( $data['max_discount'] && $discount > floatval( $data['max_discount'] ) ) {
                $discount = floatval( $data['max_discount'] );
            }
        } else {
            $discount = $amount;
        }

        // Nie może być ujemna wartość zamówienia
        if ( $discount > $cart_total ) {
            $discount = $cart_total;
        }

        return round( $discount, 2 );
    }

    /**
     * Oblicz kwalifikowaną sumę koszyka (po odfiltrowaniu wyklucz. produktów)
     */
    public static function get_eligible_total( $data ) {
        if ( ! class_exists( 'FC_Cart' ) ) return 0;

        $cart = FC_Cart::get_cart();
        $eligible = 0;
        $exclude_sale = $data['exclude_sale'] === '1';
        $product_ids  = ! empty( $data['product_ids'] ) && is_array( $data['product_ids'] ) ? array_map( 'intval', $data['product_ids'] ) : array();
        $category_ids = ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ? array_map( 'intval', $data['category_ids'] ) : array();

        foreach ( $cart as $item ) {
            $pid = intval( $item['product_id'] );
            $variant_id = isset( $item['variant_id'] ) ? $item['variant_id'] : '';
            $price = FC_Cart::get_product_price( $pid, $variant_id );
            $qty = intval( $item['quantity'] );

            // Wyklucz produkty w promocji
            if ( $exclude_sale ) {
                $is_on_sale = false;
                if ( $variant_id !== '' ) {
                    $variants = get_post_meta( $pid, '_fc_variants', true );
                    $v = FC_Cart::find_variant( $variants, $variant_id );
                    if ( $v && ! empty( $v['sale_price'] ) && floatval( $v['sale_price'] ) > 0 ) {
                        $is_on_sale = true;
                    }
                } else {
                    $sale_price = get_post_meta( $pid, '_fc_sale_price', true );
                    if ( $sale_price && floatval( $sale_price ) > 0 ) {
                        $is_on_sale = true;
                    }
                }
                if ( $is_on_sale ) continue;
            }

            // Ogranicz do konkretnych produktów
            if ( ! empty( $product_ids ) && ! in_array( $pid, $product_ids, true ) ) {
                continue;
            }

            // Ogranicz do konkretnych kategorii
            if ( ! empty( $category_ids ) ) {
                $product_cats = wp_get_post_terms( $pid, 'fc_product_cat', array( 'fields' => 'ids' ) );
                if ( ! is_array( $product_cats ) || empty( array_intersect( $product_cats, $category_ids ) ) ) {
                    continue;
                }
            }

            $eligible += $price * $qty;
        }

        return $eligible;
    }

    /**
     * Zapisz użycie kuponu (po złożeniu zamówienia)
     */
    public static function record_usage( $coupon_id, $user_id = 0 ) {
        $usage = intval( get_post_meta( $coupon_id, '_fc_coupon_usage', true ) );
        update_post_meta( $coupon_id, '_fc_coupon_usage', $usage + 1 );

        if ( $user_id ) {
            $user_usage = intval( get_user_meta( $user_id, '_fc_coupon_used_' . $coupon_id, true ) );
            update_user_meta( $user_id, '_fc_coupon_used_' . $coupon_id, $user_usage + 1 );
        }
    }

    /**
     * AJAX: Zastosuj kupon w sesji koszyka (obsługa wielu kuponów)
     */
    public static function ajax_apply_coupon() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $code = strtoupper( sanitize_text_field( $_POST['coupon_code'] ?? '' ) );
        if ( empty( $code ) ) {
            wp_send_json_error( fc__( 'enter_coupon_code' ) );
        }

        if ( ! class_exists( 'FC_Cart' ) ) {
            wp_send_json_error( fc__( 'cart_unavailable' ) );
        }

        $cart = FC_Cart::get_cart();
        $cart_total = 0;
        foreach ( $cart as $item ) {
            $price = FC_Cart::get_product_price( $item['product_id'], isset( $item['variant_id'] ) ? $item['variant_id'] : '' );
            $cart_total += $price * intval( $item['quantity'] );
        }

        $user_id = get_current_user_id();
        $result = self::validate( $code, $cart_total, $user_id );

        if ( ! $result['valid'] ) {
            wp_send_json_error( $result['error'] );
        }

        // Pobierz aktualne kupony z sesji
        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) session_start();
        $current_coupons = isset( $_SESSION['fc_coupons'] ) && is_array( $_SESSION['fc_coupons'] ) ? $_SESSION['fc_coupons'] : array();

        // Migracja ze starego formatu (fc_coupon → fc_coupons)
        if ( empty( $current_coupons ) && isset( $_SESSION['fc_coupon'] ) && is_array( $_SESSION['fc_coupon'] ) ) {
            $current_coupons = array( $_SESSION['fc_coupon'] );
            unset( $_SESSION['fc_coupon'] );
        }

        // Sprawdź, czy ten kupon nie jest już zastosowany
        foreach ( $current_coupons as $cc ) {
            if ( strtoupper( $cc['code'] ) === $code ) {
                wp_send_json_error( fc__( 'coupon_already_applied' ) );
            }
        }

        // Sprawdź łączność — nowy kupon musi być stackable jeśli są inne
        $new_data = $result['data'];
        if ( ! empty( $current_coupons ) ) {
            if ( $new_data['stackable'] !== '1' ) {
                wp_send_json_error( fc__( 'coupon_not_stackable' ) );
            }
            foreach ( $current_coupons as $cc ) {
                if ( empty( $cc['data']['stackable'] ) || $cc['data']['stackable'] !== '1' ) {
                    wp_send_json_error( fc__( 'existing_coupon_not_stackable' ) );
                }
            }
        }

        // Oblicz rabat na pozostałą kwotę (po odjęciu rabatów z istniejących kuponów)
        $existing_discount = 0;
        foreach ( $current_coupons as $cc ) {
            $existing_discount += floatval( $cc['discount'] );
        }
        $remaining_total = max( 0, $cart_total - $existing_discount );
        $discount = self::calculate_discount( $result['coupon_id'], $remaining_total );

        // Dodaj kupon do tablicy
        $current_coupons[] = array(
            'coupon_id' => $result['coupon_id'],
            'code'      => $code,
            'discount'  => $discount,
            'data'      => $result['data'],
        );

        $_SESSION['fc_coupons'] = $current_coupons;

        // Oblicz łączny rabat
        $total_discount = $existing_discount + $discount;

        wp_send_json_success( array(
            'code'     => $code,
            'discount' => $discount,
            'total_discount' => $total_discount,
            'discount_formatted' => fc_format_price( $discount ),
            'message'  => sprintf( fc__( 'coupon_coupon_applied_discount' ), $code, fc_format_price( $discount ) ),
        ) );
    }

    /**
     * AJAX: Usuń kupon z sesji (po coupon_id)
     */
    public static function ajax_remove_coupon() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) session_start();

        $remove_id = absint( $_POST['coupon_id'] ?? 0 );

        if ( $remove_id && isset( $_SESSION['fc_coupons'] ) && is_array( $_SESSION['fc_coupons'] ) ) {
            $_SESSION['fc_coupons'] = array_values( array_filter( $_SESSION['fc_coupons'], function( $c ) use ( $remove_id ) {
                return intval( $c['coupon_id'] ) !== $remove_id;
            } ) );
            // Przelicz rabaty po usunięciu
            if ( class_exists( 'FC_Cart' ) ) {
                $cart_total = FC_Cart::get_total();
                $running_total = $cart_total;
                foreach ( $_SESSION['fc_coupons'] as &$cc ) {
                    $cc['discount'] = self::calculate_discount( $cc['coupon_id'], $running_total );
                    $running_total = max( 0, $running_total - $cc['discount'] );
                }
                unset( $cc );
            }
        } else {
            // Fallback — usuń wszystko
            unset( $_SESSION['fc_coupons'] );
        }

        // Wyczyść stary format
        unset( $_SESSION['fc_coupon'] );

        wp_send_json_success( array( 'message' => fc__( 'coupon_removed' ) ) );
    }

    /**
     * Pobierz aktywne kupony z sesji (tablica)
     */
    public static function get_session_coupons() {

        // Obsługa nowego formatu tablicowego
        if ( isset( $_SESSION['fc_coupons'] ) && is_array( $_SESSION['fc_coupons'] ) && ! empty( $_SESSION['fc_coupons'] ) ) {
            return $_SESSION['fc_coupons'];
        }

        // Kompatybilność wsteczna — stary format pojedynczego kuponu
        if ( isset( $_SESSION['fc_coupon'] ) && is_array( $_SESSION['fc_coupon'] ) ) {
            return array( $_SESSION['fc_coupon'] );
        }

        return array();
    }

    /**
     * Sprawdź, czy jakikolwiek kupon sesyjny daje darmową dostawę
     */
    public static function has_free_shipping() {
        $coupons = self::get_session_coupons();
        foreach ( $coupons as $c ) {
            if ( ! empty( $c['data']['free_shipping'] ) && $c['data']['free_shipping'] === '1' ) {
                return true;
            }
        }
        return false;
    }
}
