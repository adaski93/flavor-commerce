<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Strona "Moje konto" — zamówienia, pliki do pobrania, edycja danych
 */
class FC_Account {

    public static function init() {
        add_shortcode( 'fc_account', array( __CLASS__, 'render' ) );
        add_action( 'wp_ajax_fc_save_account', array( __CLASS__, 'save_account' ) );
        add_action( 'init', array( __CLASS__, 'handle_download' ) );
        add_action( 'init', array( __CLASS__, 'handle_password_reset' ) );
        add_filter( 'the_title', array( __CLASS__, 'filter_page_title' ), 10, 2 );
        add_filter( 'authenticate', array( __CLASS__, 'block_pending_user' ), 99, 3 );
    }

    /**
     * Zmienia tytuł strony "Moje konto" na "Witaj, {nazwa}"
     */
    public static function filter_page_title( $title, $post_id = 0 ) {
        static $page_id = null;
        if ( $page_id === null ) $page_id = (int) get_option( 'fc_page_moje-konto' );
        if ( $page_id && (int) $post_id === $page_id && is_user_logged_in() && in_the_loop() && is_main_query() ) {
            $user  = wp_get_current_user();
            $title = sprintf( fc__( 'welcome_user' ), $user->display_name );
            if ( $user->user_login !== $user->display_name ) {
                $title .= ' <span class="fc-user-role">(' . esc_html( $user->user_login ) . ')</span>';
            }
            if ( current_user_can( 'manage_options' ) ) {
                $title .= '<a href="' . esc_url( admin_url() ) . '" class="fc-btn fc-btn-admin" target="_blank" rel="noopener">' . fc__( 'admin_panel' ) . '</a>';
            }
        }
        return $title;
    }

    /**
     * Główny shortcode [fc_account]
     */
    public static function render( $atts ) {
        ob_start();

        if ( ! is_user_logged_in() ) {
            $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
            if ( $action === 'forgot_password' ) {
                self::render_forgot_password();
            } elseif ( $action === 'reset_password' ) {
                self::render_reset_password();
            } elseif ( $action === 'activate_account' ) {
                self::render_activate_account();
            } else {
                self::render_login_register();
            }
            return ob_get_clean();
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
        $valid_tabs = array( 'dashboard', 'orders', 'downloads', 'reviews', 'edit' );
        $valid_tabs = apply_filters( 'fc_account_valid_tabs', $valid_tabs );
        if ( ! in_array( $tab, $valid_tabs ) ) $tab = 'dashboard';

        ?>
        <div class="fc-account">
            <div class="fc-account-nav">
                <?php self::render_nav( $tab ); ?>
            </div>
            <div class="fc-account-content">
                <?php
                switch ( $tab ) {
                    case 'orders':
                        self::render_orders();
                        break;
                    case 'downloads':
                        self::render_downloads();
                        break;
                    case 'reviews':
                        self::render_reviews();
                        break;
                    case 'edit':
                        self::render_edit();
                        break;
                    default:
                        if ( has_action( 'fc_account_tab_' . $tab ) ) {
                            do_action( 'fc_account_tab_' . $tab );
                        } else {
                            self::render_dashboard();
                        }
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        // Inline styles & countdown JS for pending_payment orders (fc-stripe.js is not loaded on account page)
        ?>
        <style>
        .fc-retry-banner{background:#fff8ee;border:1px solid #f0dbb8;border-radius:var(--fc-card-radius,8px);padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem}
        .fc-retry-banner p{margin:0 0 .5rem}
        .fc-retry-countdown-inline{display:flex;align-items:center;gap:.4rem;font-weight:600}
        .fc-countdown-table{color:#b45309;font-size:.75rem;margin-top:.25rem}
        .fc-countdown-timer{font-variant-numeric:tabular-nums;display:inline-block;min-width:3.2em;text-align:left}
        .fc-order-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
        .fc-order-actions .fc-btn-sm{min-width:0;white-space:nowrap;box-sizing:border-box}
        .fc-btn-pay{background:var(--fc-accent,#d4a843);color:#fff!important;font-weight:600;text-decoration:none;transition:opacity .2s}
        .fc-btn-pay:hover{opacity:.85;color:#fff!important}
        .fc-retry-banner .fc-btn-pay{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem 1rem;border-radius:var(--fc-btn-radius,6px);font-size:.8125rem}
        </style>
        <script>
        (function(){
            var els = document.querySelectorAll('.fc-retry-countdown-inline, .fc-retry-countdown');
            if (!els.length) return;
            els.forEach(function(el) {
                var remaining = parseInt(el.getAttribute('data-deadline'), 10) || 0;
                var timer = el.querySelector('.fc-countdown-timer');
                if (!timer) return;
                function tick() {
                    if (remaining <= 0) { timer.textContent = '0:00'; return; }
                    var m = Math.floor(remaining / 60);
                    var s = remaining % 60;
                    timer.textContent = m + ':' + (s < 10 ? '0' : '') + s;
                    remaining--;
                    setTimeout(tick, 1000);
                }
                tick();
            });
        })();
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Nawigacja zakładek
     */
    private static function render_nav( $current ) {
        $page_url = get_permalink();
        $tabs = array(
            'dashboard' => array( 'label' => fc__( 'dashboard' ), 'icon' => 'dashicons-dashboard' ),
            'orders'    => array( 'label' => fc__( 'orders' ), 'icon' => 'dashicons-list-view' ),
            'downloads' => array( 'label' => fc__( 'downloads' ), 'icon' => 'dashicons-download' ),
            'reviews'   => array( 'label' => fc__( 'my_reviews' ), 'icon' => 'dashicons-star-filled' ),
            'edit'      => array( 'label' => fc__( 'edit_details' ), 'icon' => 'dashicons-admin-users' ),
        );
        $tabs = apply_filters( 'fc_account_tabs', $tabs );

        echo '<ul>';
        foreach ( $tabs as $key => $tab ) {
            $url   = $key === 'dashboard' ? $page_url : add_query_arg( 'tab', $key, $page_url );
            $class = $current === $key ? ' class="active"' : '';
            echo '<li' . $class . '>';
            echo '<a href="' . esc_url( $url ) . '">';
            echo '<span class="dashicons ' . esc_attr( $tab['icon'] ) . '"></span> ';
            echo esc_html( $tab['label'] );
            if ( ! empty( $tab['count'] ) ) {
                echo ' <span class="fc-account-tab-count">' . intval( $tab['count'] ) . '</span>';
            }
            echo '</a></li>';
        }
        echo '<li><a href="' . esc_url( wp_logout_url( $page_url ) ) . '">';
        echo '<span class="dashicons dashicons-exit"></span> ';
        echo esc_html( fc__( 'logout' ) );
        echo '</a></li>';
        echo '</ul>';
    }

    /**
     * Pulpit — podsumowanie
     */
    private static function render_dashboard() {
        $user = wp_get_current_user();
        $orders = self::get_user_orders();
        $recent = array_slice( $orders, 0, 3 );

        // Preload all order meta in one query
        if ( ! empty( $orders ) ) {
            update_postmeta_cache( wp_list_pluck( $orders, 'ID' ) );
        }
        ?>

        <div class="fc-account-stats">
            <div class="fc-stat-box">
                <span class="fc-stat-number"><?php echo count( $orders ); ?></span>
                <span class="fc-stat-label"><?php fc_e( 'orders' ); ?></span>
            </div>
            <div class="fc-stat-box">
                <?php
                $total_spent = 0;
                foreach ( $orders as $o ) {
                    $total_spent += floatval( get_post_meta( $o->ID, '_fc_order_total', true ) );
                }
                ?>
                <span class="fc-stat-number"><?php echo fc_format_price( $total_spent ); ?></span>
                <span class="fc-stat-label"><?php fc_e( 'total_spent' ); ?></span>
            </div>
            <div class="fc-stat-box">
                <?php
                $downloads = self::get_user_downloads( $orders );
                ?>
                <span class="fc-stat-number"><?php echo count( $downloads ); ?></span>
                <span class="fc-stat-label"><?php fc_e( 'downloads' ); ?></span>
            </div>
            <div class="fc-stat-box">
                <?php
                $reviews_count = get_comments( array(
                    'user_id' => $user->ID,
                    'type'    => 'fc_review',
                    'count'   => true,
                ) );
                ?>
                <span class="fc-stat-number"><?php echo intval( $reviews_count ); ?></span>
                <span class="fc-stat-label"><?php fc_e( 'reviews_stat' ); ?></span>
            </div>
        </div>

        <p><?php fc_e( 'dashboard_description' ); ?></p>

        <?php if ( ! empty( $recent ) ) : ?>
            <h3><?php fc_e( 'recent_orders' ); ?></h3>
            <?php self::render_orders_table( $recent ); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Zakładka zamówień
     */
    private static function render_orders() {
        $orders = self::get_user_orders();

        // Widok szczegółów pojedynczego zamówienia
        if ( isset( $_GET['order_id'] ) ) {
            self::render_order_detail( absint( $_GET['order_id'] ) );
            return;
        }

        ?>
        <h2><?php fc_e( 'my_orders' ); ?></h2>
        <?php

        if ( empty( $orders ) ) {
            echo '<p class="fc-account-empty">' . fc__( 'no_orders' ) . '</p>';
            echo '<a href="' . esc_url( fc_get_shop_url() ) . '" class="fc-btn">' . fc__( 'go_to_shop' ) . '</a>';
            return;
        }

        self::render_orders_table( $orders );
    }

    /**
     * Tabela zamówień
     */
    private static function render_orders_table( $orders ) {
        // Preload all order meta in one query
        if ( ! empty( $orders ) ) {
            update_postmeta_cache( wp_list_pluck( $orders, 'ID' ) );
        }
        ?>
        <table class="fc-account-table">
            <thead>
                <tr>
                    <th><?php fc_e( 'order_number_column' ); ?></th>
                    <th><?php fc_e( 'date' ); ?></th>
                    <th><?php fc_e( 'status' ); ?></th>
                    <th><?php fc_e( 'total' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $orders as $order ) :
                    $status = get_post_meta( $order->ID, '_fc_order_status', true ) ?: 'pending';
                    $total  = get_post_meta( $order->ID, '_fc_order_total', true );
                    $date   = get_post_meta( $order->ID, '_fc_order_date', true );
                    $number = get_post_meta( $order->ID, '_fc_order_number', true ) ?: $order->post_title;
                    $color  = FC_Orders::get_status_color( $status );
                    $detail_url = add_query_arg( array( 'tab' => 'orders', 'order_id' => $order->ID ), get_permalink() );
                ?>
                    <tr>
                        <td data-label="<?php echo esc_attr( fc__( 'order_number_column' ) ); ?>"><strong><?php echo esc_html( $number ); ?></strong></td>
                        <td data-label="<?php echo esc_attr( fc__( 'date' ) ); ?>"><?php echo $date ? esc_html( date_i18n( 'j M Y, H:i', strtotime( $date ) ) ) : '—'; ?></td>
                        <td data-label="<?php echo esc_attr( fc__( 'status' ) ); ?>">
                            <span class="fc-status-dot" style="background:<?php echo esc_attr( $color ); ?>;"></span> <?php echo esc_html( FC_Orders::get_status_label( $status ) ); ?>
                            <?php if ( class_exists( 'FC_Stripe' ) && FC_Stripe::is_order_payable( $order->ID ) ) :
                                $dl = FC_Stripe::get_deadline_remaining( $order->ID );
                            ?>
                                <br><small class="fc-retry-countdown-inline fc-countdown-table" data-deadline="<?php echo esc_attr( $dl ); ?>">
                                    <span class="fc-countdown-icon">⏱</span>
                                    <?php printf( fc__( 'retry_time_remaining' ), '<strong class="fc-countdown-timer"></strong>' ); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo esc_attr( fc__( 'total' ) ); ?>"><?php echo fc_format_price( $total ); ?></td>
                        <td data-label="">
                            <div class="fc-order-actions">
                                <a href="<?php echo esc_url( $detail_url ); ?>" class="fc-btn fc-btn-sm"><?php fc_e( 'details' ); ?></a>
                                <?php if ( class_exists( 'FC_Stripe' ) && FC_Stripe::is_order_payable( $order->ID ) ) :
                                    $retry_page_id = get_option( 'fc_page_platnosc_nieudana' );
                                    $retry_url = add_query_arg( 'order_id', $order->ID,
                                        $retry_page_id ? get_permalink( $retry_page_id ) : get_permalink( get_option( 'fc_page_podziekowanie' ) )
                                    );
                                ?>
                                    <a href="<?php echo esc_url( $retry_url ); ?>" class="fc-btn fc-btn-sm fc-btn-pay"><?php fc_e( 'retry_pay_now' ); ?></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Szczegóły zamówienia
     */
    private static function render_order_detail( $order_id ) {
        $user_id = get_current_user_id();
        $order_user = get_post_meta( $order_id, '_fc_customer_id', true );

        if ( intval( $order_user ) !== $user_id ) {
            echo '<p class="fc-account-error">' . fc__( 'no_access_to_order' ) . '</p>';
            return;
        }

        $number   = get_post_meta( $order_id, '_fc_order_number', true );
        $status   = get_post_meta( $order_id, '_fc_order_status', true ) ?: 'pending';
        $total    = get_post_meta( $order_id, '_fc_order_total', true );
        $date     = get_post_meta( $order_id, '_fc_order_date', true );
        $items    = get_post_meta( $order_id, '_fc_order_items', true );
        $customer = get_post_meta( $order_id, '_fc_customer', true );
        $payment  = get_post_meta( $order_id, '_fc_payment_method', true );
        $color    = FC_Orders::get_status_color( $status );

        $payment_labels = FC_Orders::get_payment_labels();

        $back_url = add_query_arg( 'tab', 'orders', get_permalink() );
        ?>
        <p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php fc_e( 'back_to_orders' ); ?></a></p>

        <h2><?php printf( fc__( 'order_title' ), esc_html( $number ) ); ?></h2>

        <div class="fc-order-detail-meta">
            <div>
                <strong><?php fc_e( 'date_label' ); ?></strong>
                <?php echo $date ? esc_html( date_i18n( 'j M Y, H:i', strtotime( $date ) ) ) : '—'; ?>
            </div>
            <div>
                <strong><?php fc_e( 'status_label' ); ?></strong>
                <span class="fc-status-dot" style="background:<?php echo esc_attr( $color ); ?>;"></span>
                <?php echo esc_html( FC_Orders::get_status_label( $status ) ); ?>
            </div>
            <div>
                <strong><?php fc_e( 'payment_label' ); ?></strong>
                <?php echo esc_html( FC_Orders::get_order_payment_label( $order_id ) ); ?>
            </div>
        </div>

        <?php if ( class_exists( 'FC_Stripe' ) && FC_Stripe::is_order_payable( $order_id ) ) :
            $retry_page_id = get_option( 'fc_page_platnosc_nieudana' );
            $retry_url = add_query_arg( 'order_id', $order_id,
                $retry_page_id ? get_permalink( $retry_page_id ) : get_permalink( get_option( 'fc_page_podziekowanie' ) )
            );
            $deadline_remaining = FC_Stripe::get_deadline_remaining( $order_id );
        ?>
            <div class="fc-retry-banner">
                <p><?php fc_e( 'retry_payment_message_account' ); ?></p>
                <p class="fc-retry-countdown-inline" data-deadline="<?php echo esc_attr( $deadline_remaining ); ?>">
                    <span class="fc-countdown-icon">⏱</span>
                    <?php printf( fc__( 'retry_time_remaining' ), '<strong class="fc-countdown-timer"></strong>' ); ?>
                </p>
                <a href="<?php echo esc_url( $retry_url ); ?>" class="fc-btn fc-btn-pay"><?php fc_e( 'retry_pay_now' ); ?></a>
            </div>
        <?php endif; ?>

        <?php if ( is_array( $customer ) ) :
            $billing_cc  = strtoupper( $customer['country'] ?? 'PL' );
            $tax_labels  = FC_Shortcodes::get_country_tax_labels( $billing_cc );
        ?>
            <div class="fc-summary-details">
                <div class="fc-summary-section">
                    <h4><?php fc_e( 'billing_info' ); ?></h4>
                    <?php if ( ( $customer['account_type'] ?? '' ) === 'company' ) : ?>
                        <p><strong><?php echo esc_html( $customer['company'] ?? '' ); ?></strong></p>
                        <?php
                            $contact_name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
                            if ( $contact_name !== '' ) : ?>
                            <p><?php echo esc_html( $contact_name ); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p><strong><?php echo esc_html( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) ); ?></strong></p>
                    <?php endif; ?>
                    <p><?php echo esc_html( $customer['address'] ); ?></p>
                    <p><?php echo esc_html( $customer['postcode'] . ' ' . $customer['city'] . ', ' . $billing_cc ); ?></p>
                    <p><?php echo esc_html( $customer['email'] ); ?></p>
                    <p><?php echo esc_html( ( $customer['phone_prefix'] ?? '' ) . ' ' . $customer['phone'] ); ?></p>
                    <?php if ( ( $customer['account_type'] ?? '' ) === 'company' ) : ?>
                        <p><?php echo esc_html( $tax_labels['tax_no'] ); ?>: <?php echo esc_html( $customer['tax_no'] ?? '' ); ?></p>
                        <?php if ( ! empty( $customer['crn'] ) ) : ?>
                            <p><?php echo esc_html( $tax_labels['crn'] ); ?>: <?php echo esc_html( $customer['crn'] ); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="fc-summary-section">
                    <h4><?php fc_e( 'shipping_address' ); ?></h4>
                    <?php if ( ! empty( $customer['shipping'] ) ) :
                        $ship = $customer['shipping'];
                        $ship_cc = strtoupper( $ship['country'] ?? $billing_cc ); ?>
                        <?php if ( ! empty( $ship['company'] ) ) : ?>
                            <p><strong><?php echo esc_html( $ship['company'] ); ?></strong></p>
                        <?php elseif ( ! empty( $ship['first_name'] ) ) : ?>
                            <p><strong><?php echo esc_html( $ship['first_name'] . ' ' . $ship['last_name'] ); ?></strong></p>
                        <?php endif; ?>
                        <p><?php echo esc_html( $ship['address'] ); ?></p>
                        <p><?php echo esc_html( $ship['postcode'] . ' ' . $ship['city'] . ', ' . $ship_cc ); ?></p>
                    <?php else : ?>
                        <p><?php fc_e( 'same_as_billing' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( FC_Invoices::has_invoice( $order_id ) ) : ?>
            <div style="margin:16px 0;">
                <a href="<?php echo esc_url( FC_Invoices::get_frontend_download_url( $order_id ) ); ?>" class="fc-btn" target="_blank" style="display:inline-flex;align-items:center;gap:6px;">
                    <span class="dashicons dashicons-pdf" style="font-size:18px;"></span>
                    <?php printf( fc__( 'download_invoice' ), esc_html( get_post_meta( $order_id, '_fc_invoice_number', true ) ) ); ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if ( is_array( $items ) && ! empty( $items ) ) : ?>
            <h3><?php fc_e( 'order_items' ); ?></h3>
            <table class="fc-account-table">
                <thead>
                    <tr>
                        <th><?php fc_e( 'product' ); ?></th>
                        <th><?php fc_e( 'price' ); ?></th>
                        <th><?php fc_e( 'quantity' ); ?></th>
                        <th><?php fc_e( 'total' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $item ) :
                        $item_unit = '';
                        if ( FC_Units_Admin::is_visible( 'account' ) && ! empty( $item['product_id'] ) ) {
                            $item_unit = get_post_meta( $item['product_id'], '_fc_unit', true ) ?: FC_Units_Admin::get_default();
                        }
                    ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_permalink( $item['product_id'] ) ); ?>">
                                    <?php echo esc_html( $item['product_name'] ); ?>
                                </a>
                                <?php
                                if ( ! empty( $item['attribute_values'] ) && is_array( $item['attribute_values'] ) ) {
                                    $parts = array();
                                    foreach ( $item['attribute_values'] as $an => $av ) {
                                        $parts[] = esc_html( $an ) . ': ' . esc_html( $av );
                                    }
                                    echo '<br><small style="color:var(--fc-text-light);">' . implode( ', ', $parts ) . '</small>';
                                } elseif ( ! empty( $item['variant_name'] ) ) {
                                    echo '<br><small style="color:var(--fc-text-light);">' . esc_html( $item['variant_name'] ) . '</small>';
                                }
                                ?>
                            </td>
                            <td><?php echo fc_format_price( $item['price'] ); ?><?php if ( $item_unit ) echo ' <span class="fc-price-unit">/ ' . esc_html( $item_unit ) . '</span>'; ?></td>
                            <td><?php echo intval( $item['quantity'] ); ?><?php if ( $item_unit ) echo ' ' . esc_html( $item_unit ); ?></td>
                            <td><?php echo fc_format_price( $item['line_total'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php
                    $coupon_details  = get_post_meta( $order_id, '_fc_coupon_details', true );
                    $coupon_discount = floatval( get_post_meta( $order_id, '_fc_coupon_discount', true ) );
                    if ( ! empty( $coupon_details ) && is_array( $coupon_details ) ) :
                        foreach ( $coupon_details as $cd ) : ?>
                    <tr style="color:#27ae60;">
                        <td colspan="3" style="text-align:right;"><?php printf( fc__( 'coupon_label' ), '<code>' . esc_html( $cd['code'] ) . '</code>' ); ?></td>
                        <td>−<?php echo fc_format_price( $cd['discount'] ); ?></td>
                    </tr>
                        <?php endforeach; ?>
                    <?php elseif ( $coupon_discount > 0 ) :
                        $coupon_code = get_post_meta( $order_id, '_fc_coupon_code', true );
                    ?>
                    <tr style="color:#27ae60;">
                        <td colspan="3" style="text-align:right;"><?php printf( fc__( 'coupon_label' ), '<code>' . esc_html( $coupon_code ) . '</code>' ); ?></td>
                        <td>−<?php echo fc_format_price( $coupon_discount ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php
                    $acct_shipping_method = get_post_meta( $order_id, '_fc_shipping_method', true );
                    $acct_shipping_cost   = floatval( get_post_meta( $order_id, '_fc_shipping_cost', true ) );
                    if ( ! empty( $acct_shipping_method ) ) : ?>
                    <tr>
                        <td colspan="3" style="text-align:right;"><?php fc_e( 'shipping_label' ); ?> <?php echo esc_html( $acct_shipping_method ); ?></td>
                        <td><?php echo $acct_shipping_cost > 0 ? fc_format_price( $acct_shipping_cost ) : fc__( 'free' ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong><?php fc_e( 'total_label' ); ?></strong></td>
                        <td><strong><?php echo fc_format_price( $total ); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
        <?php
    }

    /**
     * Zakładka pliki do pobrania
     */
    private static function render_downloads() {
        $downloads = self::get_user_downloads();
        ?>
        <h2><?php fc_e( 'downloads' ); ?></h2>
        <?php

        if ( empty( $downloads ) ) {
            echo '<p class="fc-account-empty">' . fc__( 'no_downloads' ) . '</p>';
            return;
        }
        ?>
        <table class="fc-account-table">
            <thead>
                <tr>
                    <th><?php fc_e( 'product' ); ?></th>
                    <th><?php fc_e( 'order' ); ?></th>
                    <th><?php fc_e( 'date' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $downloads as $dl ) : ?>
                    <tr>
                        <td data-label="<?php echo esc_attr( fc__( 'product' ) ); ?>">
                            <a href="<?php echo esc_url( get_permalink( $dl['product_id'] ) ); ?>">
                                <?php echo esc_html( $dl['product_name'] ); ?>
                            </a>
                        </td>
                        <td data-label="<?php echo esc_attr( fc__( 'order' ) ); ?>"><?php echo esc_html( $dl['order_number'] ); ?></td>
                        <td data-label="<?php echo esc_attr( fc__( 'date' ) ); ?>"><?php echo esc_html( $dl['date'] ); ?></td>
                        <td data-label="">
                            <a href="<?php echo esc_url( $dl['download_url'] ); ?>" class="fc-btn fc-btn-sm">
                                <span class="dashicons dashicons-download" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span>
                                <?php fc_e( 'download' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Zakładka opinii użytkownika
     */
    private static function render_reviews() {
        $user = wp_get_current_user();

        $reviews = get_comments( array(
            'user_id' => $user->ID,
            'type'    => 'fc_review',
            'status'  => 'all',
            'orderby' => 'comment_date',
            'order'   => 'DESC',
        ) );
        ?>
        <h2><?php fc_e( 'my_reviews' ); ?></h2>
        <?php

        if ( empty( $reviews ) ) {
            echo '<p class="fc-account-empty">' . fc__( 'no_reviews' ) . '</p>';
            return;
        }

        // Preload comment meta and product data
        update_meta_cache( 'comment', wp_list_pluck( $reviews, 'comment_ID' ) );
        $review_product_ids = array_unique( wp_list_pluck( $reviews, 'comment_post_ID' ) );
        _prime_post_caches( $review_product_ids, true, true );
        ?>
        <div class="fc-my-reviews">
            <?php foreach ( $reviews as $review ) :
                $product_id = $review->comment_post_ID;
                $product    = get_post( $product_id );
                $rating     = floatval( get_comment_meta( $review->comment_ID, '_fc_rating', true ) );
                $thumb      = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
                $status     = $review->comment_approved;

                if ( $status === '1' ) {
                    $status_label = fc__( 'approved' );
                    $status_class = 'fc-review-approved';
                } elseif ( $status === '0' ) {
                    $status_label = fc__( 'pending_moderation' );
                    $status_class = 'fc-review-pending';
                } else {
                    $status_label = fc__( 'rejected' );
                    $status_class = 'fc-review-rejected';
                }
            ?>
                <div class="fc-my-review <?php echo esc_attr( $status_class ); ?>">
                    <div class="fc-my-review-product">
                        <?php if ( $thumb ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-my-review-thumb">
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $product ? $product->post_title : '' ); ?>">
                            </a>
                        <?php endif; ?>
                        <div class="fc-my-review-info">
                            <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-my-review-title">
                                <?php echo esc_html( $product ? $product->post_title : fc__( 'deleted_product' ) ); ?>
                            </a>
                            <div class="fc-my-review-meta">
                                <?php if ( $rating ) FC_Reviews::render_stars( $rating ); ?>
                                <span class="fc-my-review-date"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $review->comment_date ) ); ?></span>
                                <span class="fc-my-review-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="fc-my-review-content">
                        <?php echo wpautop( esc_html( $review->comment_content ) ); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Zakładka edycji danych
     */
    private static function render_edit() {
        $user = wp_get_current_user();
        $uid  = $user->ID;
        $saved = isset( $_GET['saved'] ) && sanitize_text_field( $_GET['saved'] ) === '1';
        $error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';

        $account_type = get_user_meta( $uid, 'fc_account_type', true ) ?: 'private';
        ?>
        <h2><?php fc_e( 'edit_data' ); ?></h2>

        <?php if ( $saved ) : ?>
            <div class="fc-account-notice fc-notice-success"><?php fc_e( 'data_saved' ); ?></div>
        <?php endif; ?>
        <?php if ( $error ) : ?>
            <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" class="fc-account-form fc-checkout-form" id="fc-account-edit-form">
            <input type="hidden" name="action" value="fc_save_account">
            <?php wp_nonce_field( 'fc_save_account', 'fc_account_nonce' ); ?>

            <div class="fc-field-group fc-two-cols">
                <div class="fc-field">
                    <label for="fc_display_name"><?php fc_e( 'display_name' ); ?></label>
                    <input type="text" name="display_name" id="fc_display_name" value="<?php echo esc_attr( $user->display_name ); ?>">
                </div>
                <div class="fc-field">
                    <label for="fc_email"><?php fc_e( 'email_address' ); ?> <span class="fc-required">*</span></label>
                    <input type="email" name="email" id="fc_email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
                </div>
            </div>

            <div class="fc-field">
                <label for="fc_user_login"><?php fc_e( 'login' ); ?></label>
                <input type="text" id="fc_user_login" value="<?php echo esc_attr( $user->user_login ); ?>" disabled>
                <p class="fc-field-desc"><?php fc_e( 'login_auto_generated_info' ); ?></p>
            </div>

            <hr>
            <h3><?php fc_e( 'billing_info' ); ?></h3>

            <!-- Typ konta -->
            <div class="fc-account-type">
                <label class="fc-account-type-option">
                    <input type="radio" name="account_type" value="private" <?php checked( $account_type, 'private' ); ?>>
                    <span><?php fc_e( 'private_account' ); ?></span>
                </label>
                <label class="fc-account-type-option">
                    <input type="radio" name="account_type" value="company" <?php checked( $account_type, 'company' ); ?>>
                    <span><?php fc_e( 'company_account' ); ?></span>
                </label>
            </div>

            <div class="fc-private-fields">
                <div class="fc-field-group fc-two-cols">
                    <div class="fc-field">
                        <label for="billing_first_name"><?php fc_e( 'first_name' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="billing_first_name" id="billing_first_name" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_first_name', true ) ?: $user->first_name ); ?>">
                    </div>
                    <div class="fc-field">
                        <label for="billing_last_name"><?php fc_e( 'last_name' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="billing_last_name" id="billing_last_name" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_last_name', true ) ?: $user->last_name ); ?>">
                    </div>
                </div>
            </div>

            <div class="fc-field">
                <label for="billing_country"><?php fc_e( 'country' ); ?> <span class="fc-required">*</span></label>
                <?php FC_Shortcodes::render_country_select_public( 'billing_country', get_user_meta( $uid, 'fc_billing_country', true ) ?: get_option( 'fc_store_country', 'PL' ) ); ?>
            </div>

            <div class="fc-company-fields" style="display:none;">
                <div class="fc-field">
                    <label for="billing_company"><?php fc_e( 'company_name' ); ?> <span class="fc-required">*</span></label>
                    <input type="text" name="billing_company" id="billing_company" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_company', true ) ); ?>">
                </div>
                <div class="fc-field-group fc-two-cols">
                    <div class="fc-field">
                        <label for="billing_tax_no" id="billing_tax_no_label"><?php fc_e( 'tax_id' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="billing_tax_no" id="billing_tax_no" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_tax_no', true ) ); ?>">
                    </div>
                    <div class="fc-field">
                        <label for="billing_crn" id="billing_crn_label"><?php fc_e( 'registration_number' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="billing_crn" id="billing_crn" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_crn', true ) ); ?>">
                    </div>
                </div>
            </div>

            <div class="fc-field">
                <label for="billing_address"><?php fc_e( 'address' ); ?> <span class="fc-required">*</span></label>
                <input type="text" name="billing_address" id="billing_address" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_address', true ) ); ?>" placeholder="<?php fc_e( 'street_and_number' ); ?>">
            </div>

            <div class="fc-field-group fc-two-cols">
                <div class="fc-field">
                    <label for="billing_postcode"><?php fc_e( 'postcode' ); ?> <span class="fc-required">*</span></label>
                    <input type="text" name="billing_postcode" id="billing_postcode" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_postcode', true ) ); ?>" placeholder="00-000">
                </div>
                <div class="fc-field">
                    <label for="billing_city"><?php fc_e( 'city' ); ?> <span class="fc-required">*</span></label>
                    <input type="text" name="billing_city" id="billing_city" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_billing_city', true ) ); ?>">
                </div>
            </div>

            <div class="fc-field">
                <label for="billing_phone"><?php fc_e( 'phone' ); ?> <span class="fc-required">*</span></label>
                <?php FC_Shortcodes::render_phone_field( 'billing_phone', 'billing_phone_prefix', get_user_meta( $uid, 'fc_billing_phone', true ), get_user_meta( $uid, 'fc_billing_phone_prefix', true ) ); ?>
            </div>

            <hr>
            <h3><?php fc_e( 'shipping_address' ); ?></h3>

            <div class="fc-ship-different">
                <label class="fc-ship-different-toggle">
                    <input type="checkbox" name="ship_to_different" value="1" <?php checked( get_user_meta( $uid, 'fc_ship_to_different', true ), '1' ); ?>>
                    <span><?php fc_e( 'different_shipping_address' ); ?></span>
                </label>
            </div>

            <div class="fc-shipping-fields" style="display:none;">
                <div class="fc-shipping-private-fields">
                    <div class="fc-field-group fc-two-cols">
                        <div class="fc-field">
                            <label for="shipping_first_name"><?php fc_e( 'first_name' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="shipping_first_name" id="shipping_first_name" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_shipping_first_name', true ) ); ?>">
                        </div>
                        <div class="fc-field">
                            <label for="shipping_last_name"><?php fc_e( 'last_name' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="shipping_last_name" id="shipping_last_name" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_shipping_last_name', true ) ); ?>">
                        </div>
                    </div>
                </div>

                <div class="fc-shipping-company-field" style="display:none;">
                    <div class="fc-field">
                        <label for="shipping_company"><?php fc_e( 'company_name' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="shipping_company" id="shipping_company" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_shipping_company', true ) ); ?>">
                    </div>
                </div>

                <div class="fc-field">
                    <label for="shipping_country"><?php fc_e( 'country' ); ?> <span class="fc-required">*</span></label>
                    <?php FC_Shortcodes::render_country_select_public( 'shipping_country', get_user_meta( $uid, 'fc_shipping_country', true ) ?: get_option( 'fc_store_country', 'PL' ) ); ?>
                </div>

                <div class="fc-field">
                    <label for="shipping_address"><?php fc_e( 'address' ); ?> <span class="fc-required">*</span></label>
                    <input type="text" name="shipping_address" id="shipping_address" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_shipping_address', true ) ); ?>" placeholder="<?php fc_e( 'street_and_number' ); ?>">
                </div>

                <div class="fc-field-group fc-two-cols">
                    <div class="fc-field">
                        <label for="shipping_postcode"><?php fc_e( 'postcode' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="shipping_postcode" id="shipping_postcode" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_shipping_postcode', true ) ); ?>" placeholder="00-000">
                    </div>
                    <div class="fc-field">
                        <label for="shipping_city"><?php fc_e( 'city' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="shipping_city" id="shipping_city" value="<?php echo esc_attr( get_user_meta( $uid, 'fc_shipping_city', true ) ); ?>">
                    </div>
                </div>
            </div>

            <hr>
            <h3><?php fc_e( 'change_password' ); ?></h3>
            <p class="fc-field-hint"><?php fc_e( 'leave_empty_if_no_change' ); ?></p>

            <div class="fc-field">
                <label for="fc_current_password"><?php fc_e( 'current_password' ); ?></label>
                <input type="password" name="current_password" id="fc_current_password" autocomplete="current-password">
            </div>

            <div class="fc-field-group fc-two-cols">
                <div class="fc-field">
                    <label for="fc_new_password"><?php fc_e( 'new_password' ); ?></label>
                    <input type="password" name="new_password" id="fc_new_password" autocomplete="new-password">
                </div>
                <div class="fc-field">
                    <label for="fc_confirm_password"><?php fc_e( 'confirm_new_password' ); ?></label>
                    <input type="password" name="confirm_password" id="fc_confirm_password" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="fc-btn"><?php fc_e( 'save_changes' ); ?></button>
        </form>
        <?php
    }

    /**
     * Formularz logowania / rejestracji
     */
    private static function render_login_register() {
        $login_error = isset( $_GET['login_error'] ) ? sanitize_text_field( $_GET['login_error'] ) : '';
        $reg_error   = isset( $_GET['reg_error'] ) ? sanitize_text_field( $_GET['reg_error'] ) : '';
        $registered  = isset( $_GET['registered'] ) && $_GET['registered'] === '1';
        ?>
        <div class="fc-account-auth">
            <div class="fc-auth-box">
                <h2><?php fc_e( 'login_heading' ); ?></h2>

                <?php if ( $login_error ) : ?>
                    <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $login_error ); ?></div>
                <?php endif; ?>
                <?php if ( $registered ) : ?>
                    <div class="fc-account-notice fc-notice-success"><?php fc_e( 'account_created_login' ); ?></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
                    <div class="fc-field">
                        <label for="fc_login_user"><?php fc_e( 'login_or_email' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="log" id="fc_login_user" required>
                    </div>
                    <div class="fc-field">
                        <label for="fc_login_pass"><?php fc_e( 'password' ); ?> <span class="fc-required">*</span></label>
                        <input type="password" name="pwd" id="fc_login_pass" required>
                    </div>
                    <div class="fc-field">
                        <label><input type="checkbox" name="rememberme" value="forever"> <?php fc_e( 'remember_me' ); ?></label>
                    </div>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>">
                    <button type="submit" class="fc-btn"><?php fc_e( 'log_in' ); ?></button>
                    <p style="margin:12px 0 0;font-size:13px;"><a href="<?php echo esc_url( add_query_arg( 'action', 'forgot_password', get_permalink() ) ); ?>"><?php fc_e( 'forgot_password' ); ?></a></p>
                </form>
            </div>

            <?php if ( get_option( 'users_can_register' ) ) : ?>
            <div class="fc-auth-box">
                <h2><?php fc_e( 'registration' ); ?></h2>

                <?php if ( $reg_error ) : ?>
                    <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $reg_error ); ?></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field( 'fc_register', 'fc_register_nonce' ); ?>
                    <input type="hidden" name="fc_action" value="register">

                    <div class="fc-field">
                        <label for="fc_reg_username"><?php fc_e( 'username' ); ?> <span class="fc-required">*</span></label>
                        <input type="text" name="reg_username" id="fc_reg_username" required>
                    </div>
                    <div class="fc-field">
                        <label for="fc_reg_email"><?php fc_e( 'email_address' ); ?> <span class="fc-required">*</span></label>
                        <input type="email" name="reg_email" id="fc_reg_email" required>
                    </div>
                    <div class="fc-field">
                        <label for="fc_reg_password"><?php fc_e( 'password' ); ?> <span class="fc-required">*</span></label>
                        <input type="password" name="reg_password" id="fc_reg_password" required minlength="6">
                    </div>
                    <div class="fc-field">
                        <label for="fc_reg_password2"><?php fc_e( 'confirm_password' ); ?> <span class="fc-required">*</span></label>
                        <input type="password" name="reg_password2" id="fc_reg_password2" required minlength="6">
                    </div>
                    <button type="submit" class="fc-btn"><?php fc_e( 'register' ); ?></button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php

        // Obsługa rejestracji
        if ( isset( $_POST['fc_action'] ) && $_POST['fc_action'] === 'register' ) {
            if ( ! wp_verify_nonce( $_POST['fc_register_nonce'] ?? '', 'fc_register' ) ) return;

            $username = sanitize_user( $_POST['reg_username'] ?? '' );
            $email    = sanitize_email( $_POST['reg_email'] ?? '' );
            $password  = $_POST['reg_password'] ?? '';
            $password2 = $_POST['reg_password2'] ?? '';

            if ( $password !== $password2 ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( fc__( 'passwords_not_matching' ) ), get_permalink() ) );
                exit;
            }

            if ( strlen( $password ) < 6 ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( fc__( 'password_min_length' ) ), get_permalink() ) );
                exit;
            }

            if ( empty( $username ) || strlen( $username ) < 3 ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( fc__( 'username_min_length' ) ), get_permalink() ) );
                exit;
            }

            if ( username_exists( $username ) ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( fc__( 'username_taken' ) ), get_permalink() ) );
                exit;
            }

            if ( ! is_email( $email ) ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( fc__( 'invalid_email' ) ), get_permalink() ) );
                exit;
            }

            if ( email_exists( $email ) ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( fc__( 'email_already_registered' ) ), get_permalink() ) );
                exit;
            }

            $user_id = wp_create_user( $username, $password, $email );
            if ( is_wp_error( $user_id ) ) {
                wp_safe_redirect( add_query_arg( 'reg_error', urlencode( $user_id->get_error_message() ), get_permalink() ) );
                exit;
            }

            // Oznacz konto jako oczekujące na aktywację
            $activation_code = str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
            update_user_meta( $user_id, 'fc_account_pending', '1' );
            update_user_meta( $user_id, 'fc_activation_code', $activation_code );
            update_user_meta( $user_id, 'fc_activation_code_time', time() );

            // Wyślij e-mail z kodem aktywacyjnym
            $user = get_user_by( 'id', $user_id );
            FC_Settings::send_activation_email( $user, $activation_code );

            wp_safe_redirect( add_query_arg( array(
                'action'    => 'activate_account',
                'email'     => rawurlencode( $email ),
                'code_sent' => '1',
            ), get_permalink() ) );
            exit;
        }
    }

    /**
     * Formularz aktywacji konta (wpisanie 6-cyfrowego kodu)
     */
    private static function render_activate_account() {
        $page_url  = get_permalink();
        $email     = isset( $_GET['email'] ) ? sanitize_email( rawurldecode( $_GET['email'] ) ) : '';
        $code_sent = isset( $_GET['code_sent'] ) && $_GET['code_sent'] === '1';
        $resent    = isset( $_GET['resent'] ) && $_GET['resent'] === '1';
        $error     = isset( $_GET['activation_error'] ) ? sanitize_text_field( $_GET['activation_error'] ) : '';
        $success   = isset( $_GET['activated'] ) && $_GET['activated'] === '1';
        ?>
        <div class="fc-account-auth">
            <div class="fc-auth-box" style="max-width:600px;margin:0 auto;">
                <h2><?php fc_e( 'account_activation' ); ?></h2>

                <?php if ( $success ) : ?>
                    <div class="fc-account-notice fc-notice-success">
                        <?php fc_e( 'account_activated' ); ?>
                    </div>
                    <p><a href="<?php echo esc_url( $page_url ); ?>">&larr; <?php fc_e( 'go_to_login' ); ?></a></p>
                <?php else : ?>

                    <?php if ( $code_sent ) : ?>
                        <div class="fc-account-notice fc-notice-success">
                            <?php fc_e( 'activation_code_sent' ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $resent ) : ?>
                        <div class="fc-account-notice fc-notice-success">
                            <?php fc_e( 'new_activation_code_sent' ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $error ) : ?>
                        <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $error ); ?></div>
                    <?php endif; ?>

                    <p class="description" style="margin-bottom:16px;">
                        <?php printf( fc__( 'activation_code_info' ), esc_html( $email ) ); ?>
                    </p>

                    <form method="post" action="">
                        <?php wp_nonce_field( 'fc_activate_account', 'fc_activate_nonce' ); ?>
                        <input type="hidden" name="fc_action" value="activate_account">
                        <input type="hidden" name="fc_activate_email" value="<?php echo esc_attr( $email ); ?>">
                        <div class="fc-field">
                            <label for="fc_activation_code"><?php fc_e( 'activation_code' ); ?> <span class="fc-required">*</span></label>
                            <input type="text" name="fc_activation_code" id="fc_activation_code" required maxlength="6" minlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" style="letter-spacing:8px;font-size:24px;text-align:center;font-weight:700;">
                        </div>
                        <button type="submit" class="fc-btn"><?php fc_e( 'activate_account' ); ?></button>
                    </form>

                    <hr style="margin:20px 0;border:0;border-top:1px solid #ddd;">
                    <p style="font-size:13px;color:#666;margin:0 0 8px;">
                        <?php fc_e( 'didnt_receive_code' ); ?>
                    </p>
                    <form method="post" action="" style="display:inline;">
                        <?php wp_nonce_field( 'fc_resend_activation', 'fc_resend_nonce' ); ?>
                        <input type="hidden" name="fc_action" value="resend_activation">
                        <input type="hidden" name="fc_activate_email" value="<?php echo esc_attr( $email ); ?>">
                        <button type="submit" class="fc-btn fc-btn-secondary" style="font-size:13px;padding:6px 16px;"><?php fc_e( 'resend' ); ?></button>
                    </form>
                    <p style="margin-top:12px;font-size:13px;"><a href="<?php echo esc_url( $page_url ); ?>">&larr; <?php fc_e( 'back_to_login' ); ?></a></p>

                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Formularz "Zapomniałem hasła"
     */
    private static function render_forgot_password() {
        $page_url = get_permalink();
        $sent     = isset( $_GET['reset_sent'] ) && $_GET['reset_sent'] === '1';
        $error    = isset( $_GET['reset_error'] ) ? sanitize_text_field( $_GET['reset_error'] ) : '';
        ?>
        <div class="fc-account-auth">
            <div class="fc-auth-box" style="max-width:600px;margin:0 auto;">
                <h2><?php fc_e( 'password_reset' ); ?></h2>

                <?php if ( $sent ) : ?>
                    <div class="fc-account-notice fc-notice-success">
                        <?php fc_e( 'reset_link_sent' ); ?>
                    </div>
                    <p><a href="<?php echo esc_url( $page_url ); ?>">&larr; <?php fc_e( 'back_to_login' ); ?></a></p>
                <?php else : ?>
                    <?php if ( $error ) : ?>
                        <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $error ); ?></div>
                    <?php endif; ?>

                    <p class="description" style="margin-bottom:16px;"><?php fc_e( 'reset_instruction' ); ?></p>

                    <form method="post" action="">
                        <?php wp_nonce_field( 'fc_forgot_password', 'fc_forgot_nonce' ); ?>
                        <input type="hidden" name="fc_action" value="forgot_password">
                        <div class="fc-field">
                            <label for="fc_forgot_email"><?php fc_e( 'email_address' ); ?> <span class="fc-required">*</span></label>
                            <input type="email" name="fc_forgot_email" id="fc_forgot_email" required>
                        </div>
                        <button type="submit" class="fc-btn"><?php fc_e( 'send_reset_link' ); ?></button>
                    </form>
                    <p style="margin-top:12px;font-size:13px;"><a href="<?php echo esc_url( $page_url ); ?>">&larr; <?php fc_e( 'back_to_login' ); ?></a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Formularz ustawiania nowego hasła (po kliknięciu linku z maila)
     */
    private static function render_reset_password() {
        $page_url = get_permalink();
        $login    = sanitize_text_field( $_GET['login'] ?? '' );
        $key      = sanitize_text_field( $_GET['key'] ?? '' );
        $error    = isset( $_GET['reset_error'] ) ? sanitize_text_field( $_GET['reset_error'] ) : '';
        $success  = isset( $_GET['password_reset'] ) && $_GET['password_reset'] === '1';

        // Weryfikacja tokena
        $user = false;
        if ( $login && $key ) {
            $user = check_password_reset_key( $key, $login );
        }
        ?>
        <div class="fc-account-auth">
            <div class="fc-auth-box" style="max-width:600px;margin:0 auto;">
                <h2><?php fc_e( 'set_new_password' ); ?></h2>

                <?php if ( $success ) : ?>
                    <div class="fc-account-notice fc-notice-success">
                        <?php fc_e( 'password_changed' ); ?>
                    </div>
                    <p><a href="<?php echo esc_url( $page_url ); ?>">&larr; <?php fc_e( 'go_to_login' ); ?></a></p>
                <?php elseif ( is_wp_error( $user ) || ! $user ) : ?>
                    <div class="fc-account-notice fc-notice-error">
                        <?php fc_e( 'reset_link_invalid' ); ?>
                    </div>
                    <p><a href="<?php echo esc_url( add_query_arg( 'action', 'forgot_password', $page_url ) ); ?>"><?php fc_e( 'send_new_link' ); ?></a></p>
                <?php else : ?>
                    <?php if ( $error ) : ?>
                        <div class="fc-account-notice fc-notice-error"><?php echo esc_html( $error ); ?></div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?php wp_nonce_field( 'fc_reset_password', 'fc_reset_nonce' ); ?>
                        <input type="hidden" name="fc_action" value="reset_password">
                        <input type="hidden" name="fc_reset_login" value="<?php echo esc_attr( $login ); ?>">
                        <input type="hidden" name="fc_reset_key" value="<?php echo esc_attr( $key ); ?>">
                        <div class="fc-field">
                            <label for="fc_new_password"><?php fc_e( 'new_password' ); ?> <span class="fc-required">*</span></label>
                            <input type="password" name="fc_new_password" id="fc_new_password" required minlength="6">
                        </div>
                        <div class="fc-field">
                            <label for="fc_new_password2"><?php fc_e( 'confirm_new_password' ); ?> <span class="fc-required">*</span></label>
                            <input type="password" name="fc_new_password2" id="fc_new_password2" required minlength="6">
                        </div>
                        <button type="submit" class="fc-btn"><?php fc_e( 'change_password_button' ); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Obsługa formularzy resetowania hasła (init hook)
     */
    public static function handle_password_reset() {
        if ( ! isset( $_POST['fc_action'] ) ) return;

        $page_url = get_permalink( get_option( 'fc_page_moje-konto' ) );
        if ( ! $page_url ) return;

        // 1. Wysłanie linku resetującego
        if ( $_POST['fc_action'] === 'forgot_password' ) {
            if ( ! wp_verify_nonce( $_POST['fc_forgot_nonce'] ?? '', 'fc_forgot_password' ) ) return;

            $email = sanitize_email( $_POST['fc_forgot_email'] ?? '' );
            $redirect = add_query_arg( array( 'action' => 'forgot_password', 'reset_sent' => '1' ), $page_url );

            if ( $email && is_email( $email ) ) {
                $user = get_user_by( 'email', $email );
                if ( $user ) {
                    $key = get_password_reset_key( $user );
                    if ( ! is_wp_error( $key ) ) {
                        $reset_url = add_query_arg( array(
                            'action' => 'reset_password',
                            'key'    => $key,
                            'login'  => rawurlencode( $user->user_login ),
                        ), $page_url );

                        FC_Settings::send_password_reset_email( $user, $reset_url );
                    }
                }
            }

            // Zawsze przekieruj z sukcesem (bezpieczeństwo — nie ujawniaj czy email istnieje)
            wp_safe_redirect( $redirect );
            exit;
        }

        // 2. Zapis nowego hasła
        if ( $_POST['fc_action'] === 'reset_password' ) {
            if ( ! wp_verify_nonce( $_POST['fc_reset_nonce'] ?? '', 'fc_reset_password' ) ) return;

            $login    = sanitize_text_field( $_POST['fc_reset_login'] ?? '' );
            $key      = sanitize_text_field( $_POST['fc_reset_key'] ?? '' );
            $pass     = $_POST['fc_new_password'] ?? '';
            $pass2    = $_POST['fc_new_password2'] ?? '';

            $base_url = add_query_arg( array(
                'action' => 'reset_password',
                'key'    => $key,
                'login'  => rawurlencode( $login ),
            ), $page_url );

            if ( strlen( $pass ) < 6 ) {
                wp_safe_redirect( add_query_arg( 'reset_error', urlencode( fc__( 'password_min_length' ) ), $base_url ) );
                exit;
            }

            if ( $pass !== $pass2 ) {
                wp_safe_redirect( add_query_arg( 'reset_error', urlencode( fc__( 'passwords_not_matching' ) ), $base_url ) );
                exit;
            }

            $user = check_password_reset_key( $key, $login );
            if ( is_wp_error( $user ) ) {
                wp_safe_redirect( add_query_arg( array(
                    'action' => 'reset_password',
                    'reset_error' => urlencode( fc__( 'reset_link_expired' ) ),
                ), $page_url ) );
                exit;
            }

            reset_password( $user, $pass );

            wp_safe_redirect( add_query_arg( array(
                'action'         => 'reset_password',
                'password_reset' => '1',
            ), $page_url ) );
            exit;
        }

        // 3. Aktywacja konta kodem
        if ( $_POST['fc_action'] === 'activate_account' ) {
            if ( ! wp_verify_nonce( $_POST['fc_activate_nonce'] ?? '', 'fc_activate_account' ) ) return;

            $email = sanitize_email( $_POST['fc_activate_email'] ?? '' );
            $code  = sanitize_text_field( $_POST['fc_activation_code'] ?? '' );

            $base_url = add_query_arg( array(
                'action' => 'activate_account',
                'email'  => rawurlencode( $email ),
            ), $page_url );

            if ( ! $email || ! $code ) {
                wp_safe_redirect( add_query_arg( 'activation_error', urlencode( fc__( 'fill_all_fields' ) ), $base_url ) );
                exit;
            }

            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                wp_safe_redirect( add_query_arg( 'activation_error', urlencode( fc__( 'account_not_found' ) ), $base_url ) );
                exit;
            }

            $pending = get_user_meta( $user->ID, 'fc_account_pending', true );
            if ( $pending !== '1' ) {
                // Konto już aktywne
                wp_safe_redirect( add_query_arg( array(
                    'action'    => 'activate_account',
                    'activated' => '1',
                ), $page_url ) );
                exit;
            }

            $saved_code = get_user_meta( $user->ID, 'fc_activation_code', true );
            $code_time  = (int) get_user_meta( $user->ID, 'fc_activation_code_time', true );

            // Kod ważny 24h
            if ( ( time() - $code_time ) > 86400 ) {
                wp_safe_redirect( add_query_arg( 'activation_error', urlencode( fc__( 'activation_code_expired' ) ), $base_url ) );
                exit;
            }

            if ( $code !== $saved_code ) {
                wp_safe_redirect( add_query_arg( 'activation_error', urlencode( fc__( 'invalid_activation_code' ) ), $base_url ) );
                exit;
            }

            // Aktywuj konto
            delete_user_meta( $user->ID, 'fc_account_pending' );
            delete_user_meta( $user->ID, 'fc_activation_code' );
            delete_user_meta( $user->ID, 'fc_activation_code_time' );

            wp_safe_redirect( add_query_arg( array(
                'action'    => 'activate_account',
                'activated' => '1',
            ), $page_url ) );
            exit;
        }

        // 4. Ponowne wysłanie kodu aktywacyjnego
        if ( $_POST['fc_action'] === 'resend_activation' ) {
            if ( ! wp_verify_nonce( $_POST['fc_resend_nonce'] ?? '', 'fc_resend_activation' ) ) return;

            $email = sanitize_email( $_POST['fc_activate_email'] ?? '' );

            $base_url = add_query_arg( array(
                'action' => 'activate_account',
                'email'  => rawurlencode( $email ),
            ), $page_url );

            if ( $email ) {
                $user = get_user_by( 'email', $email );
                if ( $user && get_user_meta( $user->ID, 'fc_account_pending', true ) === '1' ) {
                    $new_code = str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
                    update_user_meta( $user->ID, 'fc_activation_code', $new_code );
                    update_user_meta( $user->ID, 'fc_activation_code_time', time() );
                    FC_Settings::send_activation_email( $user, $new_code );
                }
            }

            // Zawsze przekieruj z sukcesem (bezpieczeństwo)
            wp_safe_redirect( add_query_arg( 'resent', '1', $base_url ) );
            exit;
        }
    }

    /**
     * Blokuje logowanie dla kont oczekujących na aktywację
     */
    public static function block_pending_user( $user, $username, $password ) {
        if ( is_wp_error( $user ) ) return $user;
        if ( ! ( $user instanceof WP_User ) ) return $user;

        $pending = get_user_meta( $user->ID, 'fc_account_pending', true );
        if ( $pending === '1' ) {
            $page_url = get_permalink( get_option( 'fc_page_moje-konto' ) );
            $activate_url = $page_url ? add_query_arg( array(
                'action' => 'activate_account',
                'email'  => rawurlencode( $user->user_email ),
            ), $page_url ) : '';

            $message = fc__( 'account_not_activated' );
            if ( $activate_url ) {
                $message .= ' <a href="' . esc_url( $activate_url ) . '">' . fc__( 'activate_account' ) . '</a>';
            }
            return new WP_Error( 'fc_account_pending', $message );
        }

        return $user;
    }

    /**
     * Zapis danych konta (AJAX)
     */
    public static function save_account() {
        if ( ! is_user_logged_in() ) wp_die();
        check_ajax_referer( 'fc_save_account', 'fc_account_nonce' );

        $user_id = get_current_user_id();
        $redirect = wp_get_referer() ?: get_permalink( get_option( 'fc_page_moje-konto' ) );
        $redirect = add_query_arg( 'tab', 'edit', remove_query_arg( array( 'saved', 'error' ), $redirect ) );

        // Dane podstawowe
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => sanitize_text_field( $_POST['display_name'] ?? '' ),
        ) );

        // Email
        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( $email && is_email( $email ) ) {
            $existing = email_exists( $email );
            if ( ! $existing || $existing === $user_id ) {
                wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
            }
        }

        // Hasło
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if ( $new_pass ) {
            if ( ! $current_pass ) {
                wp_safe_redirect( add_query_arg( 'error', urlencode( fc__( 'enter_current_password' ) ), $redirect ) );
                exit;
            }

            $user = wp_get_current_user();
            if ( ! wp_check_password( $current_pass, $user->user_pass, $user_id ) ) {
                wp_safe_redirect( add_query_arg( 'error', urlencode( fc__( 'current_password_incorrect' ) ), $redirect ) );
                exit;
            }

            if ( $new_pass !== $confirm_pass ) {
                wp_safe_redirect( add_query_arg( 'error', urlencode( fc__( 'new_passwords_not_matching' ) ), $redirect ) );
                exit;
            }

            wp_set_password( $new_pass, $user_id );
            wp_set_auth_cookie( $user_id );
        }

        // Typ konta
        update_user_meta( $user_id, 'fc_account_type', sanitize_text_field( $_POST['account_type'] ?? 'private' ) );

        // Dane rozliczeniowe
        $billing_fields = array( 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_tax_no', 'billing_crn', 'billing_address', 'billing_postcode', 'billing_city', 'billing_country', 'billing_phone', 'billing_phone_prefix' );
        foreach ( $billing_fields as $field ) {
            update_user_meta( $user_id, 'fc_' . $field, sanitize_text_field( $_POST[ $field ] ?? '' ) );
        }

        // Sync first_name / last_name do profilu WP
        wp_update_user( array(
            'ID'         => $user_id,
            'first_name' => sanitize_text_field( $_POST['billing_first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $_POST['billing_last_name'] ?? '' ),
        ) );

        // Adres wysyłki
        $ship_different = ! empty( $_POST['ship_to_different'] );
        update_user_meta( $user_id, 'fc_ship_to_different', $ship_different ? '1' : '' );

        $shipping_fields = array( 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address', 'shipping_postcode', 'shipping_city', 'shipping_country' );
        foreach ( $shipping_fields as $field ) {
            update_user_meta( $user_id, 'fc_' . $field, sanitize_text_field( $_POST[ $field ] ?? '' ) );
        }

        wp_safe_redirect( add_query_arg( 'saved', '1', $redirect ) );
        exit;
    }

    /**
     * Obsługa pobierania pliku cyfrowego
     */
    public static function handle_download() {
        if ( ! isset( $_GET['fc_download'] ) || ! is_user_logged_in() ) return;

        $product_id = absint( $_GET['fc_download'] );
        $order_id   = absint( $_GET['order_id'] ?? 0 );
        $user_id    = get_current_user_id();

        if ( ! $order_id || ! $product_id ) return;

        // Sprawdź czy zamówienie należy do użytkownika
        $order_user = get_post_meta( $order_id, '_fc_customer_id', true );
        if ( intval( $order_user ) !== $user_id ) return;

        // Sprawdź status zamówienia
        $status = get_post_meta( $order_id, '_fc_order_status', true );
        if ( ! in_array( $status, array( 'processing', 'completed' ) ) ) return;

        // Pobierz URL pliku
        $file_url = get_post_meta( $product_id, '_fc_digital_file', true );
        if ( ! $file_url ) return;

        // Redirect do pliku
        $safe_url = wp_validate_redirect( $file_url, home_url() );
        wp_redirect( $safe_url );
        exit;
    }

    /**
     * Helper: zamówienia użytkownika
     */
    private static function get_user_orders() {
        return get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 100,
            'meta_key'       => '_fc_customer_id',
            'meta_value'     => get_current_user_id(),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
    }

    /**
     * Helper: pliki do pobrania użytkownika
     */
    private static function get_user_downloads( $orders = null ) {
        if ( $orders === null ) {
            $orders = self::get_user_orders();
        }
        $downloads = array();

        if ( empty( $orders ) ) return $downloads;

        // Preload order meta
        $order_ids = wp_list_pluck( $orders, 'ID' );
        update_postmeta_cache( $order_ids );

        // Collect product IDs to preload their meta too
        $product_ids = array();
        foreach ( $orders as $order ) {
            $items = get_post_meta( $order->ID, '_fc_order_items', true );
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    if ( ! empty( $item['product_id'] ) ) {
                        $product_ids[] = (int) $item['product_id'];
                    }
                }
            }
        }
        if ( ! empty( $product_ids ) ) {
            update_postmeta_cache( array_unique( $product_ids ) );
        }

        foreach ( $orders as $order ) {
            $status = get_post_meta( $order->ID, '_fc_order_status', true );
            if ( ! in_array( $status, array( 'processing', 'completed' ) ) ) continue;

            $items  = get_post_meta( $order->ID, '_fc_order_items', true );
            $number = get_post_meta( $order->ID, '_fc_order_number', true ) ?: $order->post_title;
            $date   = get_post_meta( $order->ID, '_fc_order_date', true );

            if ( ! is_array( $items ) ) continue;

            foreach ( $items as $item ) {
                $type = get_post_meta( $item['product_id'], '_fc_product_type', true );
                if ( $type !== 'digital' ) continue;

                $file = get_post_meta( $item['product_id'], '_fc_digital_file', true );
                if ( ! $file ) continue;

                $download_url = add_query_arg( array(
                    'fc_download' => $item['product_id'],
                    'order_id'    => $order->ID,
                ), home_url( '/' ) );

                $downloads[] = array(
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'order_number' => $number,
                    'date'         => $date ? date_i18n( 'j M Y', strtotime( $date ) ) : '—',
                    'download_url' => $download_url,
                );
            }
        }

        return $downloads;
    }
}
