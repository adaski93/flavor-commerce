<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Panel zamówień w adminie: kolumny, meta box szczegółów, zarządzanie statusem
 */
class FC_Orders {

    private static $status_keys = array(
        'pending',
        'pending_payment',
        'processing',
        'shipped',
        'completed',
        'cancelled',
        'refunded',
    );

    /**
     * Get translated status labels (using fc__() i18n system)
     */
    public static function get_statuses() {
        return array(
            'pending'         => fc__( 'status_pending' ),
            'pending_payment' => fc__( 'status_pending_payment' ),
            'processing'      => fc__( 'status_processing' ),
            'shipped'         => fc__( 'status_shipped' ),
            'completed'       => fc__( 'status_completed' ),
            'cancelled'       => fc__( 'status_cancelled' ),
            'refunded'        => fc__( 'status_refunded' ),
        );
    }

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_fc_order', array( __CLASS__, 'save_order' ) );
        add_filter( 'manage_fc_order_posts_columns', array( __CLASS__, 'columns' ) );
        add_action( 'manage_fc_order_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
        add_filter( 'manage_edit-fc_order_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        add_filter( 'bulk_actions-edit-fc_order', array( __CLASS__, 'bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-fc_order', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
        add_filter( 'views_edit-fc_order', array( __CLASS__, 'status_views' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'filter_by_status' ) );
        add_action( 'restrict_manage_posts', array( __CLASS__, 'render_order_filters' ) );
        add_filter( 'posts_clauses', array( __CLASS__, 'search_orders_clauses' ), 10, 2 );
        add_action( 'admin_menu', array( __CLASS__, 'menu_order_count' ) );
    }

    /**
     * Dodaj badge z liczbą niezrealizowanych zamówień do menu
     */
    public static function menu_order_count() {
        global $menu, $wpdb;

        // Single SQL count instead of loading all order IDs
        $pending = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'fc_order'
               AND p.post_status = 'publish'
               AND pm.meta_key = '_fc_order_status'
               AND pm.meta_value IN ('pending','pending_payment','processing')"
        );

        if ( $pending > 0 ) {
            foreach ( $menu as $key => $item ) {
                if ( isset( $item[2] ) && $item[2] === 'edit.php?post_type=fc_order' ) {
                    $menu[ $key ][0] .= ' <span class="awaiting-mod update-plugins count-' . $pending . '"><span class="pending-count">' . $pending . '</span></span>';
                    break;
                }
            }
        }
    }

    public static function get_status_label( $status ) {
        $statuses = self::get_statuses();
        return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
    }

    public static function get_status_color( $status ) {
        $colors = array(
            'pending'          => '#f39c12',
            'pending_payment'  => '#e67e22',
            'processing'       => '#3498db',
            'shipped'          => '#9b59b6',
            'completed'        => '#27ae60',
            'cancelled'        => '#e74c3c',
            'refunded'         => '#95a5a6',
        );
        return isset( $colors[ $status ] ) ? $colors[ $status ] : '#999';
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'fc_order_details',
            fc__( 'order_order_details' ),
            array( __CLASS__, 'render_details' ),
            'fc_order',
            'normal',
            'high'
        );

        add_meta_box(
            'fc_order_items',
            fc__( 'order_order_items' ),
            array( __CLASS__, 'render_items' ),
            'fc_order',
            'normal',
            'default'
        );

        add_meta_box(
            'fc_order_status_box',
            fc__( 'order_order_status' ),
            array( __CLASS__, 'render_status' ),
            'fc_order',
            'side',
            'high'
        );

        if ( class_exists( 'FC_Invoices' ) ) {
            add_meta_box(
                'fc_order_invoice_box',
                fc__( 'inv_invoice' ),
                array( __CLASS__, 'render_invoice_box' ),
                'fc_order',
                'side',
                'default'
            );
        }
    }

    public static function render_details( $post ) {
        $customer        = get_post_meta( $post->ID, '_fc_customer', true );
        $payment         = get_post_meta( $post->ID, '_fc_payment_method', true );
        $notes           = get_post_meta( $post->ID, '_fc_order_notes', true );
        $date            = get_post_meta( $post->ID, '_fc_order_date', true );
        $shipping_method = get_post_meta( $post->ID, '_fc_shipping_method', true );
        $shipping_cost   = floatval( get_post_meta( $post->ID, '_fc_shipping_cost', true ) );

        if ( ! is_array( $customer ) ) return;

        $payment_labels = self::get_payment_labels();
        $country_code = strtoupper( $customer['country'] ?? 'PL' );
        $tax_labels   = FC_Shortcodes::get_country_tax_labels( $country_code );
        ?>
        <div class="fc-order-details">
            <div class="fc-order-col">
                <h4><?php fc_e( 'order_customer_details' ); ?></h4>
                <p><?php
                    if ( ( $customer['account_type'] ?? '' ) === 'company' && ! empty( $customer['company'] ) ) {
                        echo '<strong>' . esc_html( $customer['company'] ) . '</strong><br>';
                        $full_name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
                        if ( $full_name !== '' ) {
                            echo esc_html( $full_name ) . '<br>';
                        }
                    } else {
                        echo '<strong>' . esc_html( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ) . '</strong><br>';
                    }
                    echo esc_html( $customer['address'] ?? '' ) . '<br>';
                    echo esc_html( ( $customer['postcode'] ?? '' ) . ' ' . ( $customer['city'] ?? '' ) . ', ' . $country_code ) . '<br>';
                    echo fc__( 'order_phone' ) . ' ' . esc_html( ( $customer['phone_prefix'] ?? '' ) . ' ' . ( $customer['phone'] ?? '' ) ) . '<br>';
                    echo fc__( 'order_email' ) . ' <a href="mailto:' . esc_attr( $customer['email'] ?? '' ) . '">' . esc_html( $customer['email'] ?? '' ) . '</a>';
                    if ( ! empty( $customer['tax_no'] ) ) {
                        echo '<br>' . esc_html( $tax_labels['tax_no'] ) . ': ' . esc_html( $customer['tax_no'] );
                    }
                    if ( ! empty( $customer['crn'] ) ) {
                        echo '<br>' . esc_html( $tax_labels['crn'] ) . ': ' . esc_html( $customer['crn'] );
                    }
                ?></p>
            </div>
            <div class="fc-order-col">
                <h4><?php fc_e( 'order_order_info' ); ?></h4>
                <p>
                    <?php fc_e( 'order_date_2' ); ?> <?php echo esc_html( $date ); ?><br>
                    <?php fc_e( 'order_payment_2' ); ?> <?php echo esc_html( self::get_order_payment_label( $post->ID ) ); ?>
                    <?php
                    // Stripe payment details
                    if ( $payment === 'stripe' ) :
                        $stripe_intent = get_post_meta( $post->ID, '_fc_stripe_intent_id', true );
                        $stripe_paid   = get_post_meta( $post->ID, '_fc_stripe_paid_at', true );
                        $stripe_error  = get_post_meta( $post->ID, '_fc_stripe_error', true );
                        if ( $stripe_intent ) :
                            $dashboard_url = ( FC_Stripe::is_test_mode() ? 'https://dashboard.stripe.com/test' : 'https://dashboard.stripe.com' ) . '/payments/' . $stripe_intent;
                        ?>
                        <br><small><?php fc_e( 'order_stripe_id' ); ?> <a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank"><?php echo esc_html( $stripe_intent ); ?></a></small>
                        <?php endif; ?>
                        <?php if ( $stripe_paid ) : ?>
                        <br><small style="color:#27ae60;"><?php fc_e( 'order_paid' ); ?> <?php echo esc_html( $stripe_paid ); ?></small>
                        <?php endif; ?>
                        <?php if ( $stripe_error ) : ?>
                        <br><small style="color:#e74c3c;"><?php fc_e( 'order_error' ); ?> <?php echo esc_html( $stripe_error ); ?></small>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( ! empty( $shipping_method ) ) : ?>
                        <br><?php fc_e( 'order_shipping' ); ?> <?php echo esc_html( $shipping_method ); ?> — <?php echo $shipping_cost > 0 ? fc_format_price( $shipping_cost ) : fc__( 'order_free' ); ?>
                    <?php endif; ?>
                </p>
                <?php if ( $notes ) : ?>
                    <h4><?php fc_e( 'order_customer_notes' ); ?></h4>
                    <p><?php echo esc_html( $notes ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function render_items( $post ) {
        $items = get_post_meta( $post->ID, '_fc_order_items', true );
        $total = get_post_meta( $post->ID, '_fc_order_total', true );
        $shipping_method = get_post_meta( $post->ID, '_fc_shipping_method', true );
        $shipping_cost   = floatval( get_post_meta( $post->ID, '_fc_shipping_cost', true ) );
        $show_units = class_exists( 'FC_Units_Admin' ) && FC_Units_Admin::is_visible( 'order_details' );

        if ( ! is_array( $items ) || empty( $items ) ) {
            echo '<p>' . fc__( 'order_no_items' ) . '</p>';
            return;
        }
        ?>
        <table class="fc-order-items-table widefat">
            <thead>
                <tr>
                    <th><?php fc_e( 'order_product' ); ?></th>
                    <th><?php fc_e( 'order_unit_price' ); ?></th>
                    <th><?php fc_e( 'order_quantity' ); ?></th>
                    <th><?php fc_e( 'order_total_2' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) :
                    $unit_label = '';
                    if ( $show_units && ! empty( $item['product_id'] ) ) {
                        $unit_label = FC_Units_Admin::label( get_post_meta( $item['product_id'], '_fc_unit', true ) ?: FC_Units_Admin::get_default() );
                    }
                ?>
                    <tr>
                        <td style="display:flex;align-items:center;gap:8px;">
                            <?php
                            $thumb = '';
                            if ( ! empty( $item['product_id'] ) ) {
                                $thumb_id = get_post_thumbnail_id( $item['product_id'] );
                                if ( $thumb_id ) {
                                    $thumb = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
                                }
                            }
                            if ( $thumb ) :
                            ?>
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0;">
                            <?php endif; ?>
                            <div>
                                <a href="<?php echo get_edit_post_link( $item['product_id'] ); ?>">
                                    <?php echo esc_html( $item['product_name'] ); ?>
                                </a>
                                <?php
                                if ( ! empty( $item['attribute_values'] ) && is_array( $item['attribute_values'] ) ) {
                                    $parts = array();
                                    foreach ( $item['attribute_values'] as $an => $av ) {
                                        $parts[] = esc_html( $an ) . ': ' . esc_html( $av );
                                    }
                                    echo '<br><small style="color:#888;">' . implode( ', ', $parts ) . '</small>';
                                } elseif ( ! empty( $item['variant_name'] ) ) {
                                    echo '<br><small style="color:#888;">' . esc_html( $item['variant_name'] ) . '</small>';
                                }
                                ?>
                            </div>
                        </td>
                        <td><?php echo fc_format_price( $item['price'] ); ?></td>
                        <td><?php echo intval( $item['quantity'] ); ?><?php if ( $unit_label ) echo ' ' . esc_html( $unit_label ); ?></td>
                        <td><?php echo fc_format_price( $item['line_total'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php
                $coupon_details  = get_post_meta( $post->ID, '_fc_coupon_details', true );
                $coupon_discount = floatval( get_post_meta( $post->ID, '_fc_coupon_discount', true ) );
                if ( ! empty( $coupon_details ) && is_array( $coupon_details ) ) :
                    foreach ( $coupon_details as $cd ) : ?>
                <tr style="color:#27ae60;">
                    <td colspan="3" style="text-align:right;"><?php printf( fc__( 'order_coupon' ), '<code>' . esc_html( $cd['code'] ) . '</code>' ); ?></td>
                    <td>−<?php echo fc_format_price( $cd['discount'] ); ?></td>
                </tr>
                    <?php endforeach; ?>
                <?php elseif ( $coupon_discount > 0 ) :
                    $coupon_code = get_post_meta( $post->ID, '_fc_coupon_code', true );
                ?>
                <tr style="color:#27ae60;">
                    <td colspan="3" style="text-align:right;"><?php printf( fc__( 'order_coupon' ), '<code>' . esc_html( $coupon_code ) . '</code>' ); ?></td>
                    <td>−<?php echo fc_format_price( $coupon_discount ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( ! empty( $shipping_method ) ) : ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><?php fc_e( 'order_shipping' ); ?> <?php echo esc_html( $shipping_method ); ?></td>
                    <td><?php echo $shipping_cost > 0 ? fc_format_price( $shipping_cost ) : fc__( 'order_free' ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><strong><?php fc_e( 'order_total' ); ?></strong></td>
                    <td><strong><?php echo fc_format_price( $total ); ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <?php
    }

    public static function render_status( $post ) {
        wp_nonce_field( 'fc_order_status', 'fc_order_nonce' );
        $status = get_post_meta( $post->ID, '_fc_order_status', true );
        if ( ! $status ) $status = 'pending';
        ?>
        <div class="fc-order-status-wrap">
            <select name="fc_order_status" id="fc_order_status" style="width:100%;">
                <?php foreach ( self::get_statuses() as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description" style="margin-top:8px;">
                <?php fc_e( 'order_current' ); ?>
                <span style="color:<?php echo esc_attr( self::get_status_color( $status ) ); ?>;font-weight:bold;">
                    <?php echo esc_html( self::get_status_label( $status ) ); ?>
                </span>
            </p>
        </div>
        <?php
    }

    public static function render_invoice_box( $post ) {
        $s = FC_Invoices::get_settings();
        if ( ! $s['enabled'] ) {
            echo '<p style="color:#999;">' . fc__( 'order_invoice_system_is_disabled' ) . '</p>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=flavor-commerce&tab=invoices' ) ) . '">' . fc__( 'order_go_to_settings' ) . '</a>';
            return;
        }

        if ( FC_Invoices::has_invoice( $post->ID ) ) {
            $inv_number = get_post_meta( $post->ID, '_fc_invoice_number', true );
            $inv_date   = get_post_meta( $post->ID, '_fc_invoice_date', true );
            ?>
            <div style="margin-bottom:12px;">
                <strong><?php echo esc_html( $inv_number ); ?></strong><br>
                <span style="color:#666;font-size:12px;"><?php echo esc_html( date_i18n( 'j F Y, H:i', strtotime( $inv_date ) ) ); ?></span>
            </div>
            <a href="<?php echo esc_url( FC_Invoices::get_admin_download_url( $post->ID ) ); ?>" class="button button-primary" target="_blank" style="width:100%;text-align:center;margin-bottom:6px;">
                <span class="dashicons dashicons-pdf" style="vertical-align:middle;margin-right:4px;font-size:16px;"></span>
                <?php fc_e( 'inv_download_pdf' ); ?>
            </a>
            <?php
        } else {
            ?>
            <p style="color:#666;margin-bottom:12px;"><?php fc_e( 'inv_invoice_has_not_been_generated_yet' ); ?></p>
            <a href="<?php echo esc_url( FC_Invoices::get_generate_url( $post->ID ) ); ?>" class="button" style="width:100%;text-align:center;">
                <span class="dashicons dashicons-media-text" style="vertical-align:middle;margin-right:4px;font-size:16px;"></span>
                <?php fc_e( 'order_generate_invoice' ); ?>
            </a>
            <?php
        }
    }

    public static function save_order( $post_id ) {
        if ( ! isset( $_POST['fc_order_nonce'] ) || ! wp_verify_nonce( $_POST['fc_order_nonce'], 'fc_order_status' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['fc_order_status'] ) ) {
            $old_status = get_post_meta( $post_id, '_fc_order_status', true );
            $new_status = sanitize_text_field( $_POST['fc_order_status'] );
            if ( ! in_array( $new_status, self::$status_keys, true ) ) return;
            update_post_meta( $post_id, '_fc_order_status', $new_status );

            // Zmiana statusu — najpierw generuj fakturę, potem wyślij e-mail
            if ( $old_status !== $new_status ) {
                do_action( 'fc_order_status_changed', $post_id, $old_status, $new_status );
                FC_Settings::send_status_email( $post_id, $new_status );
                // Invalidate bestsellers cache
                global $wpdb;
                $wpdb->query( $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    '_transient_fc_bestsellers_%',
                    '_transient_timeout_fc_bestsellers_%'
                ) );
            }

            // Przywróć stan magazynowy przy anulowaniu lub zwrocie
            $restore_statuses = array( 'cancelled', 'refunded' );
            if ( in_array( $new_status, $restore_statuses ) && ! in_array( $old_status, $restore_statuses ) ) {
                self::restore_stock( $post_id );
            }
        }
    }

    public static function restore_stock( $order_id ) {
        // Idempotency guard — prevent double stock restoration
        if ( get_post_meta( $order_id, '_fc_stock_restored', true ) ) return;

        $items = get_post_meta( $order_id, '_fc_order_items', true );
        if ( ! is_array( $items ) ) return;

        foreach ( $items as $item ) {
            $variant_id = ! empty( $item['variant_id'] ) ? $item['variant_id'] : '';
            $product_id = $item['product_id'];
            $qty        = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

            // Zmniejsz licznik sprzedaży
            $current_sales = absint( get_post_meta( $product_id, '_fc_total_sales', true ) );
            update_post_meta( $product_id, '_fc_total_sales', max( 0, $current_sales - $qty ) );

            if ( $variant_id ) {
                // Przywróć stan wariantu (niezależnie od _fc_manage_stock)
                $variants = get_post_meta( $item['product_id'], '_fc_variants', true );
                if ( is_array( $variants ) ) {
                    foreach ( $variants as &$v ) {
                        if ( isset( $v['id'] ) && $v['id'] === $variant_id && $v['stock'] !== '' ) {
                            $v['stock'] = intval( $v['stock'] ) + $item['quantity'];
                        }
                    }
                    unset( $v );
                    update_post_meta( $item['product_id'], '_fc_variants', $variants );

                    // Jeśli jakikolwiek wariant ma stock > 0, przywróć parent instock
                    foreach ( $variants as $v ) {
                        if ( $v['stock'] !== '' && intval( $v['stock'] ) > 0 ) {
                            update_post_meta( $item['product_id'], '_fc_stock_status', 'instock' );
                            break;
                        }
                    }
                }
            } else {
                $manage = get_post_meta( $item['product_id'], '_fc_manage_stock', true );
                if ( $manage !== '1' ) continue;

                $stock = intval( get_post_meta( $item['product_id'], '_fc_stock', true ) );
                $new_stock = $stock + $item['quantity'];
                update_post_meta( $item['product_id'], '_fc_stock', $new_stock );

                if ( $new_stock > 0 ) {
                    update_post_meta( $item['product_id'], '_fc_stock_status', 'instock' );
                }
            }
        }

        update_post_meta( $order_id, '_fc_stock_restored', '1' );
        delete_transient( 'fc_auto_bestseller_ids' );
    }

    /**
     * Kolumny listy zamówień
     */
    public static function columns( $columns ) {
        return array(
            'cb'              => $columns['cb'],
            'title'           => fc__( 'order_order_number' ),
            'fc_customer'     => fc__( 'order_customer' ),
            'fc_items_count'  => fc__( 'order_products' ),
            'fc_total'        => fc__( 'order_total_2' ),
            'fc_status'       => fc__( 'coupon_status' ),
            'fc_payment'      => fc__( 'order_payment' ),
            'date'            => fc__( 'order_date' ),
        );
    }

    public static function column_content( $column, $post_id ) {
        // Preload meta for all orders on screen (runs once)
        static $meta_cached = false;
        if ( ! $meta_cached ) {
            $meta_cached = true;
            global $wp_query;
            if ( ! empty( $wp_query->posts ) ) {
                update_postmeta_cache( wp_list_pluck( $wp_query->posts, 'ID' ) );
            }
        }

        switch ( $column ) {
            case 'fc_customer':
                $customer = get_post_meta( $post_id, '_fc_customer', true );
                if ( is_array( $customer ) ) {
                    if ( ( $customer['account_type'] ?? '' ) === 'company' && ! empty( $customer['company'] ) ) {
                        $name = $customer['company'];
                    } else {
                        $name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
                    }
                    echo esc_html( $name );
                    echo '<br><small>' . esc_html( $customer['email'] ?? '' ) . '</small>';
                }
                break;
            case 'fc_items_count':
                $items = get_post_meta( $post_id, '_fc_order_items', true );
                if ( is_array( $items ) && ! empty( $items ) ) {
                    $total_qty = 0;
                    foreach ( $items as $item ) {
                        $total_qty += intval( $item['quantity'] );
                    }
                    echo $total_qty;
                } else {
                    echo '0';
                }
                break;
            case 'fc_total':
                $total = get_post_meta( $post_id, '_fc_order_total', true );
                echo '<strong>' . fc_format_price( $total ) . '</strong>';
                break;
            case 'fc_status':
                $status = get_post_meta( $post_id, '_fc_order_status', true );
                $color = self::get_status_color( $status );
                echo '<span class="fc-status-badge" style="background:' . esc_attr( $color ) . ';">' . esc_html( self::get_status_label( $status ) ) . '</span>';
                break;
            case 'fc_payment':
                echo esc_html( self::get_order_payment_label( $post_id ) );
                break;
        }
    }

    public static function sortable_columns( $columns ) {
        $columns['date'] = 'date';
        return $columns;
    }

    /**
     * Widoki filtrujące po statusie zamówienia (zamiast domyślnych Wszystkie/Moje/Opublikowane)
     */
    public static function status_views( $views ) {
        $views = array();
        $current = isset( $_GET['fc_status'] ) ? sanitize_text_field( $_GET['fc_status'] ) : '';
        $current_post_status = isset( $_GET['post_status'] ) ? sanitize_text_field( $_GET['post_status'] ) : '';
        $base_url = admin_url( 'edit.php?post_type=fc_order' );

        $post_counts = wp_count_posts( 'fc_order' );

        // Wszystkie (publish + draft)
        $total = intval( $post_counts->publish ) + intval( $post_counts->draft );
        $is_all = empty( $current ) && empty( $current_post_status );
        $class = $is_all ? ' class="current"' : '';
        $views['all'] = '<a href="' . esc_url( $base_url ) . '"' . $class . '>'
            . fc__( 'order_all' )
            . ' <span class="count">(' . intval( $total ) . ')</span></a>';

        // Opublikowane
        $publish_count = intval( $post_counts->publish );
        if ( $publish_count > 0 ) {
            $class = ( $current_post_status === 'publish' && empty( $current ) ) ? ' class="current"' : '';
            $url   = add_query_arg( 'post_status', 'publish', $base_url );
            $views['publish'] = '<a href="' . esc_url( $url ) . '"' . $class . '>'
                . fc__( 'order_published' )
                . ' <span class="count">(' . $publish_count . ')</span></a>';
        }

        // Szkice
        $draft_count = intval( $post_counts->draft );
        if ( $draft_count > 0 ) {
            $class = ( $current_post_status === 'draft' && empty( $current ) ) ? ' class="current"' : '';
            $url   = add_query_arg( 'post_status', 'draft', $base_url );
            $views['draft'] = '<a href="' . esc_url( $url ) . '"' . $class . '>'
                . fc__( 'order_drafts' )
                . ' <span class="count">(' . $draft_count . ')</span></a>';
        }

        // Policz zamówienia dla każdego statusu — single SQL zamiast N+1
        global $wpdb;
        $status_rows = $wpdb->get_results(
            "SELECT pm.meta_value AS status, COUNT(*) AS cnt
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'fc_order'
               AND p.post_status IN ('publish','draft')
               AND pm.meta_key = '_fc_order_status'
             GROUP BY pm.meta_value"
        );
        $counts = array();
        foreach ( $status_rows as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
        }

        foreach ( self::get_statuses() as $key => $label ) {
            $count = $counts[ $key ] ?? 0;
            $class = ( $current === $key ) ? ' class="current"' : '';
            $url   = add_query_arg( 'fc_status', $key, $base_url );
            $views[ $key ] = '<a href="' . esc_url( $url ) . '"' . $class . '>'
                . esc_html( $label )
                . ' <span class="count">(' . intval( $count ) . ')</span></a>';
        }

        // Kosz
        $trash_count = wp_count_posts( 'fc_order' )->trash;
        if ( $trash_count > 0 ) {
            $is_trash = isset( $_GET['post_status'] ) && $_GET['post_status'] === 'trash';
            $class = $is_trash ? ' class="current"' : '';
            $url   = add_query_arg( 'post_status', 'trash', $base_url );
            $views['trash'] = '<a href="' . esc_url( $url ) . '"' . $class . '>'
                . fc__( 'order_trash' )
                . ' <span class="count">(' . intval( $trash_count ) . ')</span></a>';
        }

        return $views;
    }

    /**
     * Filtruj listę zamówień po meta statusie
     */
    public static function filter_by_status( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'fc_order' ) return;

        // Filtruj po dacie (dziś)
        if ( ! empty( $_GET['fc_date'] ) && sanitize_text_field( $_GET['fc_date'] ) === 'today' ) {
            $today = wp_date( 'Y-m-d' );
            $meta_query = $query->get( 'meta_query' ) ?: array();
            $meta_query[] = array(
                'key'     => '_fc_order_date',
                'value'   => $today,
                'compare' => 'LIKE',
            );
            $query->set( 'meta_query', $meta_query );
        }

        if ( empty( $_GET['fc_status'] ) ) return;

        $status = sanitize_text_field( $_GET['fc_status'] );
        if ( ! in_array( $status, self::$status_keys, true ) ) return;

        $meta_query = $query->get( 'meta_query' ) ?: array();
        $meta_query[] = array(
            'key'   => '_fc_order_status',
            'value' => $status,
        );
        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Render order filters: search, date range, CSV export
     */
    public static function render_order_filters( $post_type ) {
        if ( $post_type !== 'fc_order' ) return;

        $search    = isset( $_GET['fc_search'] ) ? sanitize_text_field( $_GET['fc_search'] ) : '';
        $date_from = isset( $_GET['fc_date_from'] ) ? sanitize_text_field( $_GET['fc_date_from'] ) : '';
        $date_to   = isset( $_GET['fc_date_to'] ) ? sanitize_text_field( $_GET['fc_date_to'] ) : '';
        ?>
        <input type="text" name="fc_search" value="<?php echo esc_attr( $search ); ?>"
               placeholder="<?php echo esc_attr( fc__( 'admin_order_search_placeholder' ) ); ?>"
               style="width:180px;" />
        <input type="date" name="fc_date_from" value="<?php echo esc_attr( $date_from ); ?>"
               placeholder="<?php echo esc_attr( fc__( 'admin_date_from' ) ); ?>"
               style="width:140px;" />
        <input type="date" name="fc_date_to" value="<?php echo esc_attr( $date_to ); ?>"
               placeholder="<?php echo esc_attr( fc__( 'admin_date_to' ) ); ?>"
               style="width:140px;" />
        <?php
    }

    /**
     * Modify query clauses to search orders by customer name, email, or order number
     */
    public static function search_orders_clauses( $clauses, $query ) {
        global $wpdb;
        if ( ! is_admin() || ! $query->is_main_query() ) return $clauses;
        if ( $query->get( 'post_type' ) !== 'fc_order' ) return $clauses;

        // Search by customer name / email / order title
        $search = isset( $_GET['fc_search'] ) ? sanitize_text_field( $_GET['fc_search'] ) : '';
        if ( $search !== '' ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $clauses['join']  .= " LEFT JOIN {$wpdb->postmeta} fc_sm ON ({$wpdb->posts}.ID = fc_sm.post_id AND fc_sm.meta_key = '_fc_customer')";
            $clauses['where'] .= $wpdb->prepare(
                " AND ({$wpdb->posts}.post_title LIKE %s OR fc_sm.meta_value LIKE %s)",
                $like, $like
            );
        }

        // Date range filter
        $date_from = isset( $_GET['fc_date_from'] ) ? sanitize_text_field( $_GET['fc_date_from'] ) : '';
        $date_to   = isset( $_GET['fc_date_to'] ) ? sanitize_text_field( $_GET['fc_date_to'] ) : '';
        if ( $date_from !== '' || $date_to !== '' ) {
            $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} fc_dm ON ({$wpdb->posts}.ID = fc_dm.post_id AND fc_dm.meta_key = '_fc_order_date')";
            if ( $date_from !== '' && $date_to !== '' ) {
                $clauses['where'] .= $wpdb->prepare(
                    " AND fc_dm.meta_value >= %s AND fc_dm.meta_value <= %s",
                    $date_from . ' 00:00:00', $date_to . ' 23:59:59'
                );
            } elseif ( $date_from !== '' ) {
                $clauses['where'] .= $wpdb->prepare(
                    " AND fc_dm.meta_value >= %s",
                    $date_from . ' 00:00:00'
                );
            } else {
                $clauses['where'] .= $wpdb->prepare(
                    " AND fc_dm.meta_value <= %s",
                    $date_to . ' 23:59:59'
                );
            }
        }

        return $clauses;
    }

    public static function bulk_actions( $actions ) {
        foreach ( self::get_statuses() as $key => $label ) {
            $actions[ 'fc_status_' . $key ] = sprintf( fc__( 'order_change_to' ), $label );
        }
        return $actions;
    }

    public static function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( strpos( $action, 'fc_status_' ) !== 0 ) return $redirect_to;

        $status = str_replace( 'fc_status_', '', $action );
        if ( ! in_array( $status, self::$status_keys, true ) ) return $redirect_to;

        foreach ( $post_ids as $post_id ) {
            $old_status = get_post_meta( $post_id, '_fc_order_status', true );
            update_post_meta( $post_id, '_fc_order_status', $status );

            if ( $old_status !== $status ) {
                do_action( 'fc_order_status_changed', $post_id, $old_status, $status );
                FC_Settings::send_status_email( $post_id, $status );
            }

            // Przywróć stan magazynowy przy anulowaniu/zwrocie
            $restore_statuses = array( 'cancelled', 'refunded' );
            if ( in_array( $status, $restore_statuses ) && ! in_array( $old_status, $restore_statuses ) ) {
                self::restore_stock( $post_id );
            }
        }

        return add_query_arg( 'fc_updated', count( $post_ids ), $redirect_to );
    }

    /**
     * Pobierz etykiety metod płatności z ustawień
     */
    public static function get_payment_labels() {
        $methods = get_option( 'fc_payment_methods', array(
            array( 'id' => 'transfer', 'name' => fc__( 'set_bank_transfer' ) ),
            array( 'id' => 'cod',      'name' => fc__( 'set_cash_on_delivery' ) ),
        ) );
        $labels = array();
        if ( is_array( $methods ) ) {
            foreach ( $methods as $m ) {
                if ( ! empty( $m['id'] ) && ! empty( $m['name'] ) ) {
                    $labels[ $m['id'] ] = $m['name'];
                }
            }
        }
        // Always include Stripe label if it's enabled
        if ( class_exists( 'FC_Stripe' ) && FC_Stripe::is_enabled() && ! isset( $labels['stripe'] ) ) {
            $labels['stripe'] = 'Stripe';
        }
        return $labels;
    }

    /**
     * Get the resolved payment label for a specific order.
     * For Stripe orders, returns the specific method (iDEAL, Przelewy24, Card, etc.)
     */
    public static function get_order_payment_label( $order_id ) {
        $payment = get_post_meta( $order_id, '_fc_payment_method', true );
        if ( $payment === 'stripe' && class_exists( 'FC_Stripe' ) ) {
            return FC_Stripe::get_order_payment_label( $order_id );
        }
        $labels = self::get_payment_labels();
        return isset( $labels[ $payment ] ) ? $labels[ $payment ] : $payment;
    }
}
