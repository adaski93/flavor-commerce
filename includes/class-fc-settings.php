<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Strona ustawień wtyczki
 */
class FC_Settings {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_filter( 'submenu_file', array( __CLASS__, 'highlight_submenu' ) );
    }

    public static function add_menu() {
        add_menu_page(
            fc__( 'set_flavor_commerce' ),
            fc__( 'set_flavor_commerce' ),
            'manage_options',
            'flavor-commerce',
            array( __CLASS__, 'render_page' ),
            'dashicons-store',
            25
        );

        // Podmenu odpowiadające zakładkom
        add_submenu_page( 'flavor-commerce', fc__( 'set_dashboard' ), fc__( 'set_dashboard' ), 'manage_options', 'flavor-commerce', array( __CLASS__, 'render_page' ) );
        add_submenu_page( 'flavor-commerce', fc__( 'set_store_settings' ), fc__( 'set_store_settings' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=settings' );
        add_submenu_page( 'flavor-commerce', fc__( 'meta_shipping' ), fc__( 'meta_shipping' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=shipping' );
        add_submenu_page( 'flavor-commerce', fc__( 'set_taxes' ), fc__( 'set_taxes' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=taxes' );
        add_submenu_page( 'flavor-commerce', fc__( 'set_payments' ), fc__( 'set_payments' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=payments' );
        add_submenu_page( 'flavor-commerce', fc__( 'set_emails' ), fc__( 'set_emails' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=emails' );
        add_submenu_page( 'flavor-commerce', fc__( 'set_invoices' ), fc__( 'set_invoices' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=invoices' );
        add_submenu_page( 'flavor-commerce', fc__( 'coupon_coupons' ), fc__( 'coupon_coupons' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=coupons' );
        add_submenu_page( 'flavor-commerce', fc__( 'set_languages' ), fc__( 'set_languages' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=languages' );
        add_submenu_page( 'flavor-commerce', fc__( 'set_features' ), fc__( 'set_features' ), 'manage_options', 'admin.php?page=flavor-commerce&tab=features' );
    }

    /**
     * Podświetl aktywny element podmenu na podstawie parametru tab
     */
    public static function highlight_submenu( $submenu_file ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_flavor-commerce' ) return $submenu_file;

        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        $map = array(
            'dashboard' => 'flavor-commerce',
            'settings'  => 'admin.php?page=flavor-commerce&tab=settings',
            'shipping'  => 'admin.php?page=flavor-commerce&tab=shipping',
            'taxes'     => 'admin.php?page=flavor-commerce&tab=taxes',
            'payments'  => 'admin.php?page=flavor-commerce&tab=payments',
            'emails'    => 'admin.php?page=flavor-commerce&tab=emails',
            'invoices'  => 'admin.php?page=flavor-commerce&tab=invoices',
            'coupons'   => 'admin.php?page=flavor-commerce&tab=coupons',
            'features'  => 'admin.php?page=flavor-commerce&tab=features',
        );

        if ( isset( $map[ $tab ] ) ) {
            return $map[ $tab ];
        }

        return $submenu_file;
    }

    public static function register_settings() {
        register_setting( 'fc_settings', 'fc_currency' );
        register_setting( 'fc_settings', 'fc_currency_symbol' );
        register_setting( 'fc_settings', 'fc_currency_pos' );
        register_setting( 'fc_settings', 'fc_page_sklep' );
        register_setting( 'fc_settings', 'fc_page_koszyk' );
        register_setting( 'fc_settings', 'fc_page_zamowienie' );
        register_setting( 'fc_settings', 'fc_page_podziekowanie' );
        register_setting( 'fc_settings', 'fc_page_platnosc_nieudana' );
        register_setting( 'fc_settings', 'fc_page_moje-konto' );
        register_setting( 'fc_settings', 'fc_bank_account' );
        register_setting( 'fc_settings', 'fc_bank_swift' );
        register_setting( 'fc_settings', 'fc_tax_name' );
        register_setting( 'fc_settings', 'fc_tax_rate' );
        register_setting( 'fc_settings', 'fc_store_name' );
        register_setting( 'fc_settings', 'fc_store_street' );
        register_setting( 'fc_settings', 'fc_store_postcode' );
        register_setting( 'fc_settings', 'fc_store_city' );
        register_setting( 'fc_settings', 'fc_store_country' );
        register_setting( 'fc_settings', 'fc_store_tax_no' );
        register_setting( 'fc_settings', 'fc_store_crn' );
        register_setting( 'fc_settings', 'fc_store_email' );
        register_setting( 'fc_settings', 'fc_store_email_contact' );
        register_setting( 'fc_settings', 'fc_store_phone_prefix' );
        register_setting( 'fc_settings', 'fc_store_phone' );
        // fc_shipping_methods i fc_shipping_free_threshold są zapisywane
        // niezależnie w render_shipping_tab() — NIE rejestrujemy ich tutaj,
        // bo nadpisywałyby się przy zapisie zakładki ogólnej.
        register_setting( 'fc_settings', 'fc_sell_to_mode' );
        register_setting( 'fc_settings', 'fc_sell_to_excluded', array(
            'type'              => 'array',
            'sanitize_callback' => function( $val ) {
                return is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : array();
            },
        ) );
        // Strony dodatkowe
        register_setting( 'fc_settings', 'fc_page_wishlist' );
        register_setting( 'fc_settings', 'fc_page_porownanie' );

        // Toggles funkcji
        register_setting( 'fc_features', 'fc_enable_wishlist' );
        register_setting( 'fc_features', 'fc_enable_quick_view' );
        register_setting( 'fc_features', 'fc_enable_compare' );
        register_setting( 'fc_features', 'fc_enable_stock_notify' );
        register_setting( 'fc_features', 'fc_enable_view_toggle' );
        register_setting( 'fc_features', 'fc_enable_badges' );
        register_setting( 'fc_features', 'fc_enable_coupons' );
        register_setting( 'fc_features', 'fc_enable_purchase_note' );
        register_setting( 'fc_features', 'fc_enable_upsell' );
        register_setting( 'fc_features', 'fc_compare_max_items' );

        register_setting( 'fc_settings', 'fc_sell_to_included', array(
            'type'              => 'array',
            'sanitize_callback' => function( $val ) {
                return is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : array();
            },
        ) );

        register_setting( 'fc_languages', 'fc_frontend_lang', array(
            'type'              => 'string',
            'default'           => 'pl',
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        register_setting( 'fc_languages', 'fc_admin_lang', array(
            'type'              => 'string',
            'default'           => 'pl',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

        $pages = ( $active_tab === 'settings' || $active_tab === 'features' ) ? get_pages() : array();

        $tabs = array(
            'dashboard' => array( 'label' => fc__( 'set_dashboard' ), 'icon' => 'dashicons-dashboard' ),
            'settings'  => array( 'label' => fc__( 'set_store_settings' ), 'icon' => 'dashicons-admin-generic' ),
            'shipping'  => array( 'label' => fc__( 'meta_shipping' ), 'icon' => 'dashicons-migrate' ),
            'taxes'     => array( 'label' => fc__( 'set_taxes' ), 'icon' => 'dashicons-calculator' ),
            'payments'  => array( 'label' => fc__( 'set_payments' ), 'icon' => 'dashicons-money-alt' ),
            'emails'    => array( 'label' => fc__( 'set_emails' ), 'icon' => 'dashicons-email-alt' ),
            'invoices'  => array( 'label' => fc__( 'set_invoices' ), 'icon' => 'dashicons-media-text' ),
            'coupons'   => array( 'label' => fc__( 'coupon_coupons' ), 'icon' => 'dashicons-tickets-alt' ),
            'languages' => array( 'label' => fc__( 'set_languages' ), 'icon' => 'dashicons-translation' ),
            'features'  => array( 'label' => fc__( 'set_features' ), 'icon' => 'dashicons-admin-plugins' ),
        );
        ?>
        <div class="wrap fc-admin-wrap">
            <h1 class="fc-admin-title">
                <span class="dashicons dashicons-store"></span>
                <?php echo esc_html( $tabs[ $active_tab ]['label'] ?? fc__( 'set_flavor_commerce' ) ); ?>
            </h1>

            <div class="fc-admin-page-content">
                <?php
                switch ( $active_tab ) {
                    case 'shipping':
                        self::render_shipping_tab();
                        break;
                    case 'taxes':
                        self::render_tax_tab();
                        break;
                    case 'payments':
                        self::render_payments_tab();
                        break;
                    case 'emails':
                        self::render_emails_tab();
                        break;
                    case 'invoices':
                        FC_Invoices::render_tab();
                        break;
                    case 'coupons':
                        FC_Coupons::render_page();
                        break;

                    case 'languages':
                        self::render_languages_tab();
                        break;
                    case 'features':
                        self::render_features_tab();
                        break;
                    case 'settings':
                        self::render_settings_tab( $pages );
                        break;
                    case 'dashboard':
                    default:
                        self::render_dashboard_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Zakładka Pulpit — statystyki sklepu
     */
    private static function render_dashboard_tab() {
        global $wpdb;

        $total_orders   = wp_count_posts( 'fc_order' )->publish;
        $total_products = wp_count_posts( 'fc_product' )->fc_published;
        $total_users    = count_users();

        $today       = current_time( 'Y-m-d' );
        $month_start = current_time( 'Y-m-01' );

        // Revenue & counts via single SQL — no posts_per_page => -1
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN ms.meta_value NOT IN ('cancelled','refunded') THEN CAST(mt.meta_value AS DECIMAL(12,2)) ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN ms.meta_value NOT IN ('cancelled','refunded') THEN 1 ELSE 0 END), 0) AS order_count,
                COALESCE(SUM(CASE WHEN ms.meta_value NOT IN ('cancelled','refunded') AND md.meta_value LIKE %s THEN CAST(mt.meta_value AS DECIMAL(12,2)) ELSE 0 END), 0) AS revenue_today,
                COALESCE(SUM(CASE WHEN ms.meta_value NOT IN ('cancelled','refunded') AND md.meta_value LIKE %s THEN 1 ELSE 0 END), 0) AS orders_today,
                COALESCE(SUM(CASE WHEN ms.meta_value NOT IN ('cancelled','refunded') AND md.meta_value >= %s THEN CAST(mt.meta_value AS DECIMAL(12,2)) ELSE 0 END), 0) AS revenue_month
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} ms ON p.ID = ms.post_id AND ms.meta_key = '_fc_order_status'
            LEFT JOIN {$wpdb->postmeta} mt ON p.ID = mt.post_id AND mt.meta_key = '_fc_order_total'
            LEFT JOIN {$wpdb->postmeta} md ON p.ID = md.post_id AND md.meta_key = '_fc_order_date'
            WHERE p.post_type = 'fc_order' AND p.post_status = 'publish'",
            $today . '%',
            $today . '%',
            $month_start
        ) );

        $revenue       = floatval( $stats->revenue );
        $order_count_for_avg = intval( $stats->order_count );
        $revenue_today = floatval( $stats->revenue_today );
        $orders_today  = intval( $stats->orders_today );
        $revenue_month = floatval( $stats->revenue_month );
        $avg_order     = $order_count_for_avg > 0 ? $revenue / $order_count_for_avg : 0;

        // Status counts via SQL
        $status_rows = $wpdb->get_results(
            "SELECT pm.meta_value AS status, COUNT(*) AS cnt
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fc_order_status'
             WHERE p.post_type = 'fc_order' AND p.post_status = 'publish'
             GROUP BY pm.meta_value"
        );
        $status_counts = array();
        foreach ( $status_rows as $row ) {
            $status_counts[ $row->status ] = intval( $row->cnt );
        }

        // Bestsellers — scan only active orders (limited to last 500 for performance)
        $bestsellers = array();
        $active_orders = $wpdb->get_col(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fc_order_status'
             WHERE p.post_type = 'fc_order' AND p.post_status = 'publish'
               AND pm.meta_value NOT IN ('cancelled','refunded')
             ORDER BY p.ID DESC
             LIMIT 500"
        );
        if ( $active_orders ) {
            // Prime meta cache in one query
            update_meta_cache( 'post', $active_orders );
            foreach ( $active_orders as $oid ) {
                $items = get_post_meta( $oid, '_fc_order_items', true );
                if ( is_array( $items ) ) {
                    foreach ( $items as $item ) {
                        $pid = $item['product_id'] ?? 0;
                        $qty = intval( $item['quantity'] ?? 1 );
                        if ( $pid ) {
                            $bestsellers[ $pid ] = ( $bestsellers[ $pid ] ?? 0 ) + $qty;
                        }
                    }
                }
            }
        }

        // Nowi klienci w tym miesiącu
        $new_customers_month = count( get_users( array(
            'role'         => 'subscriber',
            'date_query'   => array( array( 'after' => $month_start ) ),
            'count_total'  => false,
            'fields'       => 'ID',
        ) ) );

        // Bestsellery — top 5
        arsort( $bestsellers );
        $top_bestsellers = array_slice( $bestsellers, 0, 5, true );

        // Recenzje
        $all_reviews = get_comments( array(
            'type'   => 'fc_review',
            'status' => 'approve',
        ) );
        $total_reviews = count( $all_reviews );
        $avg_rating = 0;
        if ( $total_reviews > 0 ) {
            $sum = 0;
            foreach ( $all_reviews as $rev ) {
                $sum += floatval( get_comment_meta( $rev->comment_ID, '_fc_rating', true ) );
            }
            $avg_rating = round( $sum / $total_reviews, 1 );
        }
        $pending_reviews = get_comments( array(
            'type'   => 'fc_review',
            'status' => 'hold',
            'count'  => true,
        ) );

        // Produkty z niskim stanem (<5)
        $low_stock_products = get_posts( array(
            'post_type'      => 'fc_product',
            'posts_per_page' => 10,
            'meta_query'     => array(
                array( 'key' => '_fc_stock', 'value' => '5', 'compare' => '<', 'type' => 'NUMERIC' ),
                array( 'key' => '_fc_stock', 'value' => '', 'compare' => '!=' ),
            ),
            'orderby'  => 'meta_value_num',
            'meta_key' => '_fc_stock',
            'order'    => 'ASC',
        ) );

        // Ostatnie zamówienia
        $recent_orders = get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $statuses = FC_Orders::get_statuses();
        ?>

        <!-- Kafelki statystyk -->
        <div class="fc-admin-stats">
            <div class="fc-admin-stat fc-admin-stat-revenue">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-chart-area"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo fc_format_price( $revenue ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_revenue' ); ?></div>
                </div>
            </div>
            <div class="fc-admin-stat fc-admin-stat-revenue">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-database"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo fc_format_price( $revenue_today ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_revenue_today' ); ?></div>
                </div>
            </div>
            <div class="fc-admin-stat fc-admin-stat-revenue">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo fc_format_price( $revenue_month ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_revenue_this_month' ); ?></div>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=fc_product' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-archive"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $total_products ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'order_products' ); ?></div>
                </div>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=fc_order' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-cart"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $total_orders ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'pt_orders' ); ?></div>
                </div>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=fc_order&fc_date=today' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $orders_today ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_orders_today' ); ?></div>
                </div>
            </a>
            <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-groups"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $total_users['total_users'] ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_customers' ); ?></div>
                </div>
            </a>
            <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-admin-users"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $new_customers_month ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_new_customers_month' ); ?></div>
                </div>
            </a>
            <div class="fc-admin-stat">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo fc_format_price( $avg_order ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_avg_order' ); ?></div>
                </div>
            </div>
            <a href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_type=fc_review' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-star-filled"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo $avg_rating > 0 ? esc_html( $avg_rating . ' / 5' ) : '—'; ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_average_rating' ); ?></div>
                </div>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_type=fc_review' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-testimonial"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $total_reviews ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'prod_reviews' ); ?></div>
                </div>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=moderated&comment_type=fc_review' ) ); ?>" class="fc-admin-stat fc-admin-stat-link">
                <div class="fc-admin-stat-icon"><span class="dashicons dashicons-clock"></span></div>
                <div class="fc-admin-stat-body">
                    <div class="fc-admin-stat-number"><?php echo intval( $pending_reviews ); ?></div>
                    <div class="fc-admin-stat-label"><?php fc_e( 'set_pending_reviews' ); ?></div>
                </div>
            </a>
        </div>

        <!-- Trzy kolumny: Zamówienia wg statusu + Bestsellery + Niski stan magazynowy -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;margin-top:24px;">

            <!-- Zamówienia wg statusu -->
            <div>
                <h2 style="margin:0 0 12px;"><?php fc_e( 'set_orders_by_status' ); ?></h2>
                <table class="widefat striped" style="margin:0;">
                    <tbody>
                        <?php foreach ( $statuses as $skey => $slabel ) :
                            $cnt = $status_counts[ $skey ] ?? 0;
                            $status_url = admin_url( 'edit.php?post_type=fc_order&fc_status=' . $skey );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $status_url ); ?>" style="text-decoration:none;"><span class="fc-order-badge fc-status-<?php echo esc_attr( $skey ); ?>"><?php echo esc_html( $slabel ); ?></span></a></td>
                            <td style="text-align:right;font-weight:600;width:60px;"><a href="<?php echo esc_url( $status_url ); ?>" style="text-decoration:none;color:inherit;"><?php echo intval( $cnt ); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bestsellery -->
            <div>
                <h2 style="margin:0 0 12px;"><?php fc_e( 'set_bestsellers' ); ?></h2>
                <?php if ( ! empty( $top_bestsellers ) ) : ?>
                <table class="widefat striped" style="margin:0;">
                    <thead>
                        <tr>
                            <th><?php fc_e( 'order_product' ); ?></th>
                            <th style="text-align:right;width:80px;"><?php fc_e( 'set_sold' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $top_bestsellers as $pid => $qty_sold ) :
                            $prod_title = get_the_title( $pid );
                            $edit_url   = admin_url( 'post.php?post=' . $pid . '&action=edit' );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $prod_title ); ?></a></td>
                            <td style="text-align:right;font-weight:600;"><?php echo intval( $qty_sold ); ?> <?php fc_e( 'set_pcs' ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="color:#666;"><?php fc_e( 'set_no_sales_data' ); ?></p>
                <?php endif; ?>
            </div>

            <!-- Niski stan magazynowy -->
            <div>
                <h2 style="margin:0 0 12px;"><?php fc_e( 'set_low_stock' ); ?></h2>
                <?php if ( ! empty( $low_stock_products ) ) : ?>
                <table class="widefat striped" style="margin:0;">
                    <thead>
                        <tr>
                            <th><?php fc_e( 'order_product' ); ?></th>
                            <th style="text-align:right;width:80px;"><?php fc_e( 'meta_status' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $low_stock_products as $prod ) :
                            $stock = get_post_meta( $prod->ID, '_fc_stock', true );
                            $edit_url = admin_url( 'post.php?post=' . $prod->ID . '&action=edit' );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $prod->post_title ); ?></a></td>
                            <td style="text-align:right;font-weight:600;color:<?php echo intval( $stock ) <= 0 ? '#d63638' : '#dba617'; ?>;"><?php echo intval( $stock ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="color:#666;"><?php fc_e( 'set_all_products_have_sufficient_stock' ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( ! empty( $recent_orders ) ) : ?>
        <h2 style="margin:24px 0 12px;"><?php fc_e( 'set_recent_orders' ); ?></h2>
        <table class="widefat striped" style="margin:0;">
            <thead>
                <tr>
                    <th><?php fc_e( 'set_number' ); ?></th>
                    <th><?php fc_e( 'order_customer' ); ?></th>
                    <th><?php fc_e( 'order_date' ); ?></th>
                    <th><?php fc_e( 'coupon_status' ); ?></th>
                    <th style="text-align:right;"><?php fc_e( 'set_amount' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent_orders as $order ) :
                    $number   = get_post_meta( $order->ID, '_fc_order_number', true ) ?: $order->post_title;
                    $customer = get_post_meta( $order->ID, '_fc_customer', true );
                    if ( is_array( $customer ) ) {
                        $c_type = $customer['account_type'] ?? 'private';
                        if ( $c_type === 'company' && ! empty( $customer['company'] ) ) {
                            $name = $customer['company'];
                        } else {
                            $name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
                        }
                        if ( $name === '' ) $name = '—';
                    } else {
                        $name = '—';
                    }
                    $date     = get_post_meta( $order->ID, '_fc_order_date', true );
                    $status   = get_post_meta( $order->ID, '_fc_order_status', true );
                    $total    = floatval( get_post_meta( $order->ID, '_fc_order_total', true ) );
                    $edit_url = admin_url( 'post.php?post=' . $order->ID . '&action=edit' );
                ?>
                <tr>
                    <td><a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight:600;"><?php echo esc_html( $number ); ?></a></td>
                    <td><?php echo esc_html( $name ); ?></td>
                    <td><?php echo $date ? esc_html( date_i18n( 'j M Y, H:i', strtotime( $date ) ) ) : '—'; ?></td>
                    <td><span class="fc-order-badge fc-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $statuses[ $status ] ?? $status ); ?></span></td>
                    <td style="text-align:right;"><?php echo fc_format_price( $total ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }

    /**
     * Zakładka ustawień sklepu
     */
    private static function render_settings_tab( $pages ) {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'fc_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'set_store_name' ); ?></th>
                    <td><input type="text" name="fc_store_name" value="<?php echo esc_attr( get_option( 'fc_store_name' ) ); ?>" class="regular-text"></td>
                </tr>

                <tr>
                    <th><?php fc_e( 'set_country' ); ?></th>
                    <td>
                        <?php
                        $current_country = get_option( 'fc_store_country', 'PL' );
                        FC_Shortcodes::render_admin_country_field( 'fc_store_country', $current_country, 'fc_store_country', FC_Shortcodes::get_all_countries( 'admin' ) );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_street_and_number' ); ?></th>
                    <td><input type="text" name="fc_store_street" value="<?php echo esc_attr( get_option( 'fc_store_street' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_postal_code_city' ); ?></th>
                    <td>
                        <input type="text" name="fc_store_postcode" value="<?php echo esc_attr( get_option( 'fc_store_postcode' ) ); ?>" style="width:80px;margin-right:8px;vertical-align:middle;">
                        <input type="text" name="fc_store_city" value="<?php echo esc_attr( get_option( 'fc_store_city' ) ); ?>" style="width:calc(25em - 92px);vertical-align:middle;">
                    </td>
                </tr>
                <tr>
                    <th id="fc_store_tax_no_label"><?php fc_e( 'set_tax_id' ); ?></th>
                    <td><input type="text" name="fc_store_tax_no" value="<?php echo esc_attr( get_option( 'fc_store_tax_no' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th id="fc_store_crn_label"><?php fc_e( 'set_company_registration_number' ); ?></th>
                    <td>
                        <input type="text" name="fc_store_crn" value="<?php echo esc_attr( get_option( 'fc_store_crn' ) ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_administrator_email' ); ?></th>
                    <td>
                        <input type="email" name="fc_store_email" value="<?php echo esc_attr( get_option( 'fc_store_email' ) ); ?>" class="regular-text">
                        <p class="description"><?php fc_e( 'set_main_email_address_of_the_store_owner' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_contact_email' ); ?></th>
                    <td>
                        <input type="email" name="fc_store_email_contact" value="<?php echo esc_attr( get_option( 'fc_store_email_contact' ) ); ?>" class="regular-text">
                        <p class="description"><?php fc_e( 'set_visible_to_customers_in_the_footer_and_on_the_cont' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_phone' ); ?></th>
                    <td>
                        <?php FC_Shortcodes::render_admin_phone_field( 'fc_store_phone', 'fc_store_phone_prefix', get_option( 'fc_store_phone', '' ), get_option( 'fc_store_phone_prefix', '' ) ); ?>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_currency' ); ?></th>
                    <td>
                        <?php $current_currency = get_option( 'fc_currency', 'PLN' ); ?>
                        <select name="fc_currency" id="fc_currency_select" class="regular-text">
                            <option value="PLN" data-symbol="zł" data-pos="after" <?php selected( $current_currency, 'PLN' ); ?>>PLN — Złoty polski (zł)</option>
                            <option value="EUR" data-symbol="€" data-pos="before" <?php selected( $current_currency, 'EUR' ); ?>>EUR — Euro (€)</option>
                            <option value="GBP" data-symbol="£" data-pos="before" <?php selected( $current_currency, 'GBP' ); ?>>GBP — Funt szterling (£)</option>
                            <option value="CHF" data-symbol="CHF" data-pos="after" <?php selected( $current_currency, 'CHF' ); ?>>CHF — Frank szwajcarski (CHF)</option>
                            <option value="CZK" data-symbol="Kč" data-pos="after" <?php selected( $current_currency, 'CZK' ); ?>>CZK — Korona czeska (Kč)</option>
                            <option value="DKK" data-symbol="kr" data-pos="after" <?php selected( $current_currency, 'DKK' ); ?>>DKK — Korona duńska (kr)</option>
                            <option value="SEK" data-symbol="kr" data-pos="after" <?php selected( $current_currency, 'SEK' ); ?>>SEK — Korona szwedzka (kr)</option>
                            <option value="NOK" data-symbol="kr" data-pos="after" <?php selected( $current_currency, 'NOK' ); ?>>NOK — Korona norweska (kr)</option>
                            <option value="ISK" data-symbol="kr" data-pos="after" <?php selected( $current_currency, 'ISK' ); ?>>ISK — Korona islandzka (kr)</option>
                            <option value="HUF" data-symbol="Ft" data-pos="after" <?php selected( $current_currency, 'HUF' ); ?>>HUF — Forint węgierski (Ft)</option>
                            <option value="RON" data-symbol="lei" data-pos="after" <?php selected( $current_currency, 'RON' ); ?>>RON — Lej rumuński (lei)</option>
                            <option value="BGN" data-symbol="лв" data-pos="after" <?php selected( $current_currency, 'BGN' ); ?>>BGN — Lew bułgarski (лв)</option>
                            <option value="HRK" data-symbol="kn" data-pos="after" <?php selected( $current_currency, 'HRK' ); ?>>HRK — Kuna chorwacka (kn)</option>
                            <option value="RSD" data-symbol="din." data-pos="after" <?php selected( $current_currency, 'RSD' ); ?>>RSD — Dinar serbski (din.)</option>
                            <option value="BAM" data-symbol="KM" data-pos="after" <?php selected( $current_currency, 'BAM' ); ?>>BAM — Marka konwertowalna (KM)</option>
                            <option value="MDL" data-symbol="L" data-pos="after" <?php selected( $current_currency, 'MDL' ); ?>>MDL — Lej mołdawski (L)</option>
                            <option value="UAH" data-symbol="₴" data-pos="after" <?php selected( $current_currency, 'UAH' ); ?>>UAH — Hrywna ukraińska (₴)</option>
                            <option value="BYN" data-symbol="Br" data-pos="after" <?php selected( $current_currency, 'BYN' ); ?>>BYN — Rubel białoruski (Br)</option>
                            <option value="ALL" data-symbol="L" data-pos="after" <?php selected( $current_currency, 'ALL' ); ?>>ALL — Lek albański (L)</option>
                            <option value="MKD" data-symbol="ден" data-pos="after" <?php selected( $current_currency, 'MKD' ); ?>>MKD — Denar macedoński (ден)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_symbol_position' ); ?></th>
                    <td>
                        <?php $sym = esc_html( get_option( 'fc_currency_symbol', 'zł' ) ); ?>
                        <input type="text" name="fc_currency_symbol" id="fc_currency_symbol" value="<?php echo esc_attr( $sym ); ?>" class="small-text" readonly style="width:50px;margin-right:8px;vertical-align:middle;">
                        <select name="fc_currency_pos" id="fc_currency_pos" style="vertical-align:middle;">
                            <option value="before" <?php selected( get_option( 'fc_currency_pos' ), 'before' ); ?>><?php printf( fc__( 'set_before_price_10_00' ), $sym ); ?></option>
                            <option value="after" <?php selected( get_option( 'fc_currency_pos', 'after' ), 'after' ); ?>><?php printf( fc__( 'set_after_price_10_00' ), $sym ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <script>
            jQuery(function($){
                var storeCountryLabels = {
                    AL:{tax_no:"NIPT",reg:"Numri i Regjistrimit (QKR)"},
                    AT:{tax_no:"UID (ATU)",reg:"Firmenbuchnummer (FN)"},
                    BY:{tax_no:"УНП",reg:"Рэгістрацыйны нумар"},
                    BE:{tax_no:"BTW / TVA",reg:"Ondernemingsnummer (KBO)"},
                    BA:{tax_no:"PDV broj",reg:"Registarski broj"},
                    BG:{tax_no:"ИН по ДДС",reg:"ЕИК (Булстат)"},
                    HR:{tax_no:"OIB",reg:"Matični broj subjekta (MBS)"},
                    CY:{tax_no:"Αριθμός ΦΠΑ",reg:"Αριθμός Εγγραφής (HE)"},
                    ME:{tax_no:"PIB",reg:"Registarski broj"},
                    CZ:{tax_no:"DIČ",reg:"Identifikační číslo (IČO)"},
                    DK:{tax_no:"SE-nummer",reg:"CVR-nummer"},
                    EE:{tax_no:"KMKR number",reg:"Registrikood"},
                    FI:{tax_no:"ALV-numero",reg:"Y-tunnus"},
                    FR:{tax_no:"Numéro de TVA",reg:"Numéro SIREN / SIRET"},
                    GR:{tax_no:"ΑΦΜ",reg:"Αριθμός ΓΕΜΗ"},
                    ES:{tax_no:"NIF / CIF",reg:"Registro Mercantil"},
                    NL:{tax_no:"BTW-nummer",reg:"KVK-nummer"},
                    IE:{tax_no:"VAT Number",reg:"Company Registration (CRO)"},
                    IS:{tax_no:"Virðisaukaskattnúmer (VSK)",reg:"Kennitala"},
                    LT:{tax_no:"PVM mokėtojo kodas",reg:"Įmonės kodas"},
                    LU:{tax_no:"Numéro TVA",reg:"Numéro RCS"},
                    LV:{tax_no:"PVN numurs",reg:"Reģistrācijas Nr."},
                    MK:{tax_no:"ДДВ број",reg:"ЕМБС"},
                    MT:{tax_no:"VAT Number",reg:"Company Number (C)"},
                    MD:{tax_no:"Codul TVA",reg:"IDNO (Cod fiscal)"},
                    DE:{tax_no:"Umsatzsteuer-IdNr.",reg:"Handelsregisternummer (HRB)"},
                    NO:{tax_no:"MVA-nummer",reg:"Organisasjonsnummer"},
                    PL:{tax_no:"NIP",reg:"KRS / REGON"},
                    PT:{tax_no:"Número de contribuinte (NIF)",reg:"NIPC"},
                    RO:{tax_no:"Cod de identificare fiscală (CIF)",reg:"Nr. Registrul Comerțului"},
                    RS:{tax_no:"ПИБ",reg:"Матични број"},
                    SK:{tax_no:"IČ DPH",reg:"Identifikačné číslo (IČO)"},
                    SI:{tax_no:"Identifikacijska št. za DDV",reg:"Matična številka"},
                    CH:{tax_no:"MWST-Nr. / Numéro TVA",reg:"Unternehmens-Id. (CHE/UID)"},
                    SE:{tax_no:"Momsregistreringsnummer",reg:"Organisationsnummer"},
                    UA:{tax_no:"ІПН",reg:"Код ЄДРПОУ"},
                    HU:{tax_no:"Adószám",reg:"Cégjegyzékszám"},
                    GB:{tax_no:"VAT Registration Number",reg:"Company Registration Number"},
                    IT:{tax_no:"Partita IVA",reg:"Numero REA"}
                };
                function fcUpdateStoreLabels(code){
                    var d = storeCountryLabels[code] || {tax_no:"NIP",reg:"Nr rejestrowy firmy"};
                    $('#fc_store_tax_no_label').text(d.tax_no);
                    $('#fc_store_crn_label').text(d.reg);
                }
                var storeCountryPrefixes = {
                    AL:'+355',AT:'+43',BY:'+375',BE:'+32',BA:'+387',BG:'+359',HR:'+385',CY:'+357',
                    ME:'+382',CZ:'+420',DK:'+45',EE:'+372',FI:'+358',FR:'+33',GR:'+30',ES:'+34',
                    NL:'+31',IE:'+353',IS:'+354',LT:'+370',LU:'+352',LV:'+371',MK:'+389',MT:'+356',
                    MD:'+373',DE:'+49',NO:'+47',PL:'+48',PT:'+351',RO:'+40',RS:'+381',SK:'+421',
                    SI:'+386',CH:'+41',SE:'+46',UA:'+380',HU:'+36',GB:'+44',IT:'+39'
                };
                $('#fc_store_country').on('change',function(){
                    var code = $(this).val();
                    fcUpdateStoreLabels(code);
                    var prefix = storeCountryPrefixes[code];
                    if(prefix && typeof fcAdminSetPhonePrefix==='function'){
                        fcAdminSetPhonePrefix('fc-admin-phone-fc_store_phone_prefix',code,prefix);
                    }
                });
                fcUpdateStoreLabels($('#fc_store_country').val());
            });
            </script>

            <h2><?php fc_e( 'set_sales' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'set_sell_to' ); ?></th>
                    <td>
                        <?php $sell_mode = get_option( 'fc_sell_to_mode', 'all' ); ?>
                        <label style="display:block;margin-bottom:6px;"><input type="radio" name="fc_sell_to_mode" value="all" <?php checked( $sell_mode, 'all' ); ?>> <?php fc_e( 'set_all_countries' ); ?></label>
                        <label style="display:block;margin-bottom:6px;"><input type="radio" name="fc_sell_to_mode" value="exclude" <?php checked( $sell_mode, 'exclude' ); ?>> <?php fc_e( 'set_all_countries_except_selected' ); ?></label>
                        <label style="display:block;"><input type="radio" name="fc_sell_to_mode" value="include" <?php checked( $sell_mode, 'include' ); ?>> <?php fc_e( 'set_ship_to_selected_countries' ); ?></label>
                    </td>
                </tr>
                <?php
                $excluded = get_option( 'fc_sell_to_excluded', array() );
                if ( ! is_array( $excluded ) ) $excluded = array();
                $included = get_option( 'fc_sell_to_included', array() );
                if ( ! is_array( $included ) ) $included = array();
                $all_countries = array(
                    'AL' => fc__('country_AL', 'admin'), 'AT' => fc__('country_AT', 'admin'), 'BY' => fc__('country_BY', 'admin'), 'BE' => fc__('country_BE', 'admin'),
                    'BA' => fc__('country_BA', 'admin'), 'BG' => fc__('country_BG', 'admin'), 'HR' => fc__('country_HR', 'admin'),
                    'CY' => fc__('country_CY', 'admin'), 'ME' => fc__('country_ME', 'admin'), 'CZ' => fc__('country_CZ', 'admin'), 'DK' => fc__('country_DK', 'admin'),
                    'EE' => fc__('country_EE', 'admin'), 'FI' => fc__('country_FI', 'admin'), 'FR' => fc__('country_FR', 'admin'), 'GR' => fc__('country_GR', 'admin'),
                    'ES' => fc__('country_ES', 'admin'), 'NL' => fc__('country_NL', 'admin'), 'IE' => fc__('country_IE', 'admin'), 'IS' => fc__('country_IS', 'admin'),
                    'LT' => fc__('country_LT', 'admin'), 'LU' => fc__('country_LU', 'admin'), 'LV' => fc__('country_LV', 'admin'), 'MK' => fc__('country_MK', 'admin'),
                    'MT' => fc__('country_MT', 'admin'), 'MD' => fc__('country_MD', 'admin'), 'DE' => fc__('country_DE', 'admin'), 'NO' => fc__('country_NO', 'admin'),
                    'PL' => fc__('country_PL', 'admin'), 'PT' => fc__('country_PT', 'admin'), 'RO' => fc__('country_RO', 'admin'), 'RS' => fc__('country_RS', 'admin'),
                    'SK' => fc__('country_SK', 'admin'), 'SI' => fc__('country_SI', 'admin'), 'CH' => fc__('country_CH', 'admin'), 'SE' => fc__('country_SE', 'admin'),
                    'UA' => fc__('country_UA', 'admin'), 'HU' => fc__('country_HU', 'admin'), 'GB' => fc__('country_GB', 'admin'), 'IT' => fc__('country_IT', 'admin'),
                );
                ?>
                <tr class="fc-sell-exclude-row" <?php if ( $sell_mode !== 'exclude' ) echo 'style="display:none;"'; ?>>
                    <th><?php fc_e( 'set_excluded_countries' ); ?></th>
                    <td>
                        <div class="fc-pill-picker" data-name="fc_sell_to_excluded[]">
                            <div class="fc-pill-selected"></div>
                            <div class="fc-pill-input-wrap">
                                <input type="text" class="fc-pill-search" placeholder="<?php echo esc_attr( fc__( 'set_search_country' ) ); ?>" autocomplete="off">
                                <div class="fc-pill-dropdown"></div>
                            </div>
                        </div>
                        <script type="application/json" class="fc-pill-data-excluded"><?php echo wp_json_encode( $all_countries ); ?></script>
                        <script type="application/json" class="fc-pill-selected-excluded"><?php echo wp_json_encode( $excluded ); ?></script>
                    </td>
                </tr>
                <tr class="fc-sell-include-row" <?php if ( $sell_mode !== 'include' ) echo 'style="display:none;"'; ?>>
                    <th><?php fc_e( 'set_ship_to_countries' ); ?></th>
                    <td>
                        <div class="fc-pill-picker" data-name="fc_sell_to_included[]">
                            <div class="fc-pill-selected"></div>
                            <div class="fc-pill-input-wrap">
                                <input type="text" class="fc-pill-search" placeholder="<?php echo esc_attr( fc__( 'set_search_country' ) ); ?>" autocomplete="off">
                                <div class="fc-pill-dropdown"></div>
                            </div>
                        </div>
                        <script type="application/json" class="fc-pill-data-included"><?php echo wp_json_encode( $all_countries ); ?></script>
                        <script type="application/json" class="fc-pill-selected-included"><?php echo wp_json_encode( $included ); ?></script>
                    </td>
                </tr>
            </table>

            <h2><?php fc_e( 'set_store_pages' ); ?></h2>
            <table class="form-table">
                <?php
                $page_options = array(
                    'fc_page_sklep'         => fc__( 'set_shop_page' ),
                    'fc_page_koszyk'        => fc__( 'set_cart_page' ),
                    'fc_page_zamowienie'    => fc__( 'set_order_page' ),
                    'fc_page_podziekowanie' => fc__( 'set_thank_you_page' ),
                    'fc_page_platnosc_nieudana' => fc__( 'set_failed_payment_page' ),
                    'fc_page_moje-konto'    => fc__( 'set_my_account_page' ),
                    'fc_page_wishlist'      => fc__( 'set_wishlist' ),
                    'fc_page_porownanie'    => fc__( 'set_product_comparison' ),
                );
                $page_shortcodes = array(
                    'fc_page_sklep'         => '[fc_shop]',
                    'fc_page_koszyk'        => '[fc_cart]',
                    'fc_page_zamowienie'    => '[fc_checkout]',
                    'fc_page_podziekowanie' => '[fc_thank_you]',
                    'fc_page_platnosc_nieudana' => '[fc_retry_payment]',
                    'fc_page_moje-konto'    => '[fc_account]',
                    'fc_page_wishlist'      => '[fc_wishlist]',
                    'fc_page_porownanie'    => '[fc_compare]',
                );
                foreach ( $page_options as $option_name => $label ) :
                    $current = get_option( $option_name );
                ?>
                    <tr>
                        <th><?php echo esc_html( $label ); ?></th>
                        <td>
                            <select name="<?php echo esc_attr( $option_name ); ?>">
                                <option value=""><?php fc_e( 'set_select_page' ); ?></option>
                                <?php foreach ( $pages as $page ) : ?>
                                    <option value="<?php echo $page->ID; ?>" <?php selected( $current, $page->ID ); ?>>
                                        <?php echo esc_html( $page->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <code style="margin-left:8px;"><?php echo esc_html( $page_shortcodes[ $option_name ] ); ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button(); ?>
        </form>
        <script>
        jQuery(function($){
            function fcUpdateCurrencyLabels(sym) {
                var $pos = $('#fc_currency_pos');
                $pos.find('option[value="before"]').text('<?php echo esc_js( fc__( 'set_before_price' ) ); ?> (' + sym + ' 10,00)');
                $pos.find('option[value="after"]').text('<?php echo esc_js( fc__( 'set_after_price' ) ); ?> (10,00 ' + sym + ')');
            }

            $('#fc_currency_select').on('change', function(){
                var $sel = $(this).find(':selected');
                var sym = $sel.data('symbol');
                $('#fc_currency_symbol').val(sym);
                $('#fc_currency_pos').val($sel.data('pos'));
                fcUpdateCurrencyLabels(sym);
            });

            var countryToCurrency = {
                'AL':'ALL','AT':'EUR','BY':'BYN','BE':'EUR','BA':'BAM','BG':'BGN',
                'HR':'EUR','CY':'EUR','CZ':'CZK','DK':'DKK','EE':'EUR','FI':'EUR',
                'FR':'EUR','DE':'EUR','GR':'EUR','HU':'HUF','IS':'ISK','IE':'EUR',
                'IT':'EUR','LV':'EUR','LT':'EUR','LU':'EUR','MK':'MKD','MT':'EUR',
                'MD':'MDL','ME':'EUR','NL':'EUR','NO':'NOK','PL':'PLN','PT':'EUR',
                'RO':'RON','RS':'RSD','SK':'EUR','SI':'EUR','ES':'EUR','SE':'SEK',
                'CH':'CHF','UA':'UAH','GB':'GBP'
            };

            $('#fc_store_country').on('change', function(){
                var code = $(this).val();
                if (countryToCurrency[code]) {
                    var $cur = $('#fc_currency_select');
                    $cur.val(countryToCurrency[code]).trigger('change');
                }
            });

            // Sprzedaż do - pokaż/ukryj listę krajów
            $('input[name="fc_sell_to_mode"]').on('change', function(){
                var val = $(this).val();
                $('.fc-sell-exclude-row').toggle(val === 'exclude');
                $('.fc-sell-include-row').toggle(val === 'include');
            });

            // Pill picker
            function initPillPicker($picker, countries, selected) {
                var name = $picker.data('name');
                var $sel = $picker.find('.fc-pill-selected');
                var $search = $picker.find('.fc-pill-search');
                var $dd = $picker.find('.fc-pill-dropdown');
                var chosen = {};

                function render() {
                    $sel.empty();
                    $.each(chosen, function(code, label) {
                        $sel.append(
                            '<span class="fc-pill">' +
                                '<input type="hidden" name="' + name + '" value="' + code + '">' +
                                label +
                                '<span class="fc-pill-remove" data-code="' + code + '">&times;</span>' +
                            '</span>'
                        );
                    });
                }

                function showDropdown(filter) {
                    filter = (filter || '').toLowerCase();
                    var html = '';
                    $.each(countries, function(code, label) {
                        if (chosen[code]) return;
                        if (filter && label.toLowerCase().indexOf(filter) === -1) return;
                        html += '<div class="fc-pill-option" data-code="' + code + '">' + label + '</div>';
                    });
                    $dd.html(html || '<div class=\"fc-pill-option fc-pill-empty\">' + <?php echo wp_json_encode( fc__( 'no_results' ) ); ?> + '</div>');
                    $dd.show();
                }

                // Init selected
                $.each(selected, function(i, code) {
                    if (countries[code]) chosen[code] = countries[code];
                });
                render();

                $search.on('focus', function() { showDropdown($search.val()); });
                $search.on('input', function() { showDropdown($search.val()); });

                $dd.on('click', '.fc-pill-option:not(.fc-pill-empty)', function() {
                    var code = $(this).data('code');
                    chosen[code] = countries[code];
                    render();
                    $search.val('').focus();
                    showDropdown('');
                });

                $sel.on('click', '.fc-pill-remove', function(e) {
                    e.stopPropagation();
                    delete chosen[$(this).data('code')];
                    render();
                    if ($search.is(':focus')) showDropdown($search.val());
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest($picker).length) $dd.hide();
                });
            }

            // Init excluded picker
            var exCountries = JSON.parse($('.fc-pill-data-excluded').text() || '{}');
            var exSel = JSON.parse($('.fc-pill-selected-excluded').text() || '[]');
            initPillPicker($('.fc-sell-exclude-row .fc-pill-picker'), exCountries, exSel);

            // Init included picker
            var inCountries = JSON.parse($('.fc-pill-data-included').text() || '{}');
            var inSel = JSON.parse($('.fc-pill-selected-included').text() || '[]');
            initPillPicker($('.fc-sell-include-row .fc-pill-picker'), inCountries, inSel);
        });
        </script>
        <?php
    }

    /**
     * Zakładka języków
     */
    private static function render_languages_tab() {
        $available      = FC_i18n::get_available_languages();
        $current_lang   = get_option( 'fc_frontend_lang', 'pl' );
        $current_admin  = get_option( 'fc_admin_lang', 'pl' );

        // Map language code → country code for flag (special cases)
        $flag_map = array( 'en' => 'gb', 'uk' => 'ua', 'cs' => 'cz', 'da' => 'dk', 'sv' => 'se', 'el' => 'gr', 'ja' => 'jp', 'ko' => 'kr', 'zh' => 'cn' );
        $flag_code = function( $lang ) use ( $flag_map ) {
            return strtolower( $flag_map[ $lang ] ?? $lang );
        };
        ?>
        <style>
        .fc-lang-wrap{display:inline-block;position:relative;width:22em;vertical-align:middle}
        .fc-lang-btn{display:flex;align-items:center;gap:8px;padding:0 10px;border:1px solid #8c8f94;background:#fff;cursor:pointer;font-size:14px;white-space:nowrap;box-sizing:border-box;border-radius:4px;line-height:2.15384615;width:100%;text-align:left}
        .fc-lang-btn:hover{border-color:#2271b1}
        .fc-lang-flag{width:22px;height:16px;object-fit:cover;border-radius:2px;box-shadow:0 0 0 1px rgba(0,0,0,.1)}
        .fc-lang-btn .fc-lang-name{flex:1;font-weight:400;color:#1d2327}
        .fc-lang-btn .fc-lang-arrow{font-size:.55rem;color:#50575e}
        .fc-lang-dd{display:none;position:absolute;top:100%;left:0;width:100%;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #8c8f94;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:100000;border-radius:0 0 4px 4px}
        .fc-lang-dd.open{display:block}
        .fc-lang-dd-list{padding:0;margin:0;list-style:none}
        .fc-lang-dd-list li{display:flex;align-items:center;gap:8px;padding:7px 10px;cursor:pointer;font-size:13px;transition:background .12s}
        .fc-lang-dd-list li:hover,.fc-lang-dd-list li.active{background:#f0f0f1}
        </style>
        <form method="post" action="options.php">
            <?php settings_fields( 'fc_languages' ); ?>

            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'set_store_language_frontend' ); ?></th>
                    <td>
                        <?php self::render_lang_picker( 'fc_frontend_lang', $current_lang, $available, $flag_code ); ?>
                        <p class="description"><?php fc_e( 'set_language_displayed_to_customers_on_the_store_page' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php fc_e( 'set_admin_panel_language' ); ?></th>
                    <td>
                        <?php self::render_lang_picker( 'fc_admin_lang', $current_admin, $available, $flag_code ); ?>
                        <p class="description"><?php fc_e( 'set_language_of_the_store_admin_panel' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( fc__( 'set_save_changes' ) ); ?>
        </form>
        <script>
        jQuery(function($){
            function fcLangFlag(c){
                var m={en:'gb',uk:'ua',cs:'cz',da:'dk',sv:'se',el:'gr',ja:'jp',ko:'kr',zh:'cn'};
                return 'https://flagcdn.com/w40/'+(m[c]||c).toLowerCase()+'.png';
            }
            $(document).on('click','.fc-lang-btn',function(e){
                e.preventDefault();e.stopPropagation();
                var $dd=$(this).closest('.fc-lang-wrap').find('.fc-lang-dd');
                $('.fc-lang-dd.open').not($dd).removeClass('open');
                $dd.toggleClass('open');
            });
            $(document).on('click','.fc-lang-dd-list li',function(){
                var $li=$(this),$w=$li.closest('.fc-lang-wrap'),
                    code=$li.data('code'),name=$li.data('name');
                $w.find('.fc-lang-btn .fc-lang-flag').attr('src',fcLangFlag(code));
                $w.find('.fc-lang-btn .fc-lang-name').text(name);
                $w.find('input[type=hidden]').val(code);
                $w.find('.fc-lang-dd-list li').removeClass('active');
                $li.addClass('active');
                $w.find('.fc-lang-dd').removeClass('open');
            });
            $(document).on('click',function(e){
                if(!$(e.target).closest('.fc-lang-wrap').length) $('.fc-lang-dd.open').removeClass('open');
            });
        });
        </script>
        <?php
    }

    /**
     * Renderuje custom dropdown z flagą do wyboru języka.
     */
    private static function render_lang_picker( $name, $selected, $languages, $flag_code ) {
        $sel_name = $languages[ $selected ] ?? strtoupper( $selected );
        $sel_flag = 'https://flagcdn.com/w40/' . $flag_code( $selected ) . '.png';
        ?>
        <div class="fc-lang-wrap">
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $selected ); ?>">
            <button type="button" class="fc-lang-btn">
                <img class="fc-lang-flag" src="<?php echo esc_url( $sel_flag ); ?>" alt="">
                <span class="fc-lang-name"><?php echo esc_html( $sel_name ); ?></span>
                <span class="fc-lang-arrow">&#9660;</span>
            </button>
            <div class="fc-lang-dd">
                <ul class="fc-lang-dd-list">
                    <?php foreach ( $languages as $code => $label ) :
                        $f = 'https://flagcdn.com/w40/' . $flag_code( $code ) . '.png';
                    ?>
                        <li data-code="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( $label ); ?>"<?php if ( $code === $selected ) echo ' class="active"'; ?>>
                            <img class="fc-lang-flag" src="<?php echo esc_url( $f ); ?>" alt="">
                            <span><?php echo esc_html( $label ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Zakładka podatków
     */
    private static function render_tax_tab() {
        // Obsługa zapisu
        if ( isset( $_POST['fc_save_taxes'] ) && check_admin_referer( 'fc_taxes_nonce' ) ) {
            update_option( 'fc_tax_name', sanitize_text_field( $_POST['fc_tax_name'] ?? 'VAT' ) );
            update_option( 'fc_tax_rate', floatval( $_POST['fc_tax_rate'] ?? 23 ) );
            update_option( 'fc_tax_included', sanitize_text_field( $_POST['fc_tax_included'] ?? 'yes' ) );
            update_option( 'fc_tax_display_cart', sanitize_text_field( $_POST['fc_tax_display_cart'] ?? 'incl' ) );
            update_option( 'fc_tax_display_shop', sanitize_text_field( $_POST['fc_tax_display_shop'] ?? 'incl' ) );
            update_option( 'fc_tax_shipping', ! empty( $_POST['fc_tax_shipping'] ) ? 1 : 0 );

            // Klasy podatkowe
            $classes = array();
            if ( ! empty( $_POST['fc_tc_key'] ) && is_array( $_POST['fc_tc_key'] ) ) {
                foreach ( $_POST['fc_tc_key'] as $i => $key ) {
                    $key = sanitize_key( $key );
                    if ( empty( $key ) ) continue;
                    $classes[ $key ] = array(
                        'label' => sanitize_text_field( $_POST['fc_tc_label'][ $i ] ?? $key ),
                        'rate'  => floatval( $_POST['fc_tc_rate'][ $i ] ?? 0 ),
                    );
                }
            }
            update_option( 'fc_tax_classes', $classes );

            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'set_tax_settings_have_been_saved' ) . '</p></div>';
        }

        $tax_name     = get_option( 'fc_tax_name', 'VAT' );
        $tax_rate     = get_option( 'fc_tax_rate', '23' );
        $tax_included = get_option( 'fc_tax_included', 'yes' );
        $tax_display_cart = get_option( 'fc_tax_display_cart', 'incl' );
        $tax_display_shop = get_option( 'fc_tax_display_shop', 'incl' );
        $tax_shipping = get_option( 'fc_tax_shipping', 0 );
        $tax_classes  = get_option( 'fc_tax_classes', array() );
        if ( ! is_array( $tax_classes ) ) $tax_classes = array();

        // Domyślne klasy jeśli brak konfiguracji
        if ( empty( $tax_classes ) ) {
            $tax_classes = array(
                'reduced'       => array( 'label' => fc__( 'set_reduced' ), 'rate' => 8 ),
                'super_reduced' => array( 'label' => fc__( 'set_super_reduced' ), 'rate' => 5 ),
                'zero'          => array( 'label' => fc__( 'set_zero' ), 'rate' => 0 ),
            );
        }
        ?>
        <form method="post">
            <?php wp_nonce_field( 'fc_taxes_nonce' ); ?>

            <h2><?php fc_e( 'set_general_settings' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'set_tax_name' ); ?></th>
                    <td>
                        <input type="text" name="fc_tax_name" value="<?php echo esc_attr( $tax_name ); ?>" class="small-text" style="width:120px;">
                        <p class="description"><?php fc_e( 'set_e_g_vat_gst_tax_displayed_on_invoices_and_in_the_s' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_default_rate' ); ?></th>
                    <td>
                        <input type="number" name="fc_tax_rate" value="<?php echo esc_attr( $tax_rate ); ?>" class="small-text" style="width:80px;" min="0" max="100" step="0.01"> %
                        <p class="description"><?php fc_e( 'set_standard_rate_applied_to_products_without_an_assig' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_prices_include_tax' ); ?></th>
                    <td>
                        <select name="fc_tax_included">
                            <option value="yes" <?php selected( $tax_included, 'yes' ); ?>><?php fc_e( 'set_yes_prices_in_the_store_are_gross_include_tax' ); ?></option>
                            <option value="no" <?php selected( $tax_included, 'no' ); ?>><?php fc_e( 'set_no_prices_in_the_store_are_net_tax_added' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_display_in_store' ); ?></th>
                    <td>
                        <select name="fc_tax_display_shop">
                            <option value="incl" <?php selected( $tax_display_shop, 'incl' ); ?>><?php fc_e( 'set_with_tax_gross' ); ?></option>
                            <option value="excl" <?php selected( $tax_display_shop, 'excl' ); ?>><?php fc_e( 'set_without_tax_net' ); ?></option>
                        </select>
                        <p class="description"><?php fc_e( 'set_how_to_display_prices_on_product_pages_and_in_the' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_display_in_cart' ); ?></th>
                    <td>
                        <select name="fc_tax_display_cart">
                            <option value="incl" <?php selected( $tax_display_cart, 'incl' ); ?>><?php fc_e( 'set_with_tax_gross' ); ?></option>
                            <option value="excl" <?php selected( $tax_display_cart, 'excl' ); ?>><?php fc_e( 'set_without_tax_net' ); ?></option>
                        </select>
                        <p class="description"><?php fc_e( 'set_how_to_display_prices_in_the_cart_and_on_the_order' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_shipping_tax' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="fc_tax_shipping" value="1" <?php checked( $tax_shipping, 1 ); ?>>
                            <?php fc_e( 'set_charge_tax_on_shipping_costs' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <hr style="margin: 2rem 0;">
            <h2><?php fc_e( 'set_tax_classes' ); ?></h2>
            <p class="description"><?php fc_e( 'set_tax_classes_allow_applying_different_tax_rates_to' ); ?></p>

            <table class="widefat striped" style="max-width:700px;margin-top:12px;">
                <thead>
                    <tr>
                        <th><?php fc_e( 'set_key' ); ?></th>
                        <th><?php fc_e( 'attr_name' ); ?></th>
                        <th><?php fc_e( 'set_rate' ); ?></th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background:#f0f6ff;">
                        <td><code>standard</code></td>
                        <td><?php fc_e( 'prod_standard' ); ?></td>
                        <td><strong><?php echo esc_html( $tax_rate ); ?>%</strong> <span style="color:#666;font-size:11px;">(<?php fc_e( 'set_default_rate_above' ); ?>)</span></td>
                        <td></td>
                    </tr>
                </tbody>
                <tbody id="fc-tax-classes">
                    <?php $tci = 0; foreach ( $tax_classes as $tc_key => $tc ) : ?>
                    <tr>
                        <td><input type="text" name="fc_tc_key[<?php echo $tci; ?>]" value="<?php echo esc_attr( $tc_key ); ?>" style="width:100%;" pattern="[a-z0-9_]+" title="<?php fc_e( 'set_only_lowercase_letters_numbers_and_underscore' ); ?>"></td>
                        <td><input type="text" name="fc_tc_label[<?php echo $tci; ?>]" value="<?php echo esc_attr( $tc['label'] ); ?>" style="width:100%;"></td>
                        <td><input type="number" name="fc_tc_rate[<?php echo $tci; ?>]" value="<?php echo esc_attr( $tc['rate'] ); ?>" min="0" max="100" step="0.01" style="width:100px;"> %</td>
                        <td><button type="button" class="button fc-remove-tc" title="<?php fc_e( 'attr_delete' ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>
                    </tr>
                    <?php $tci++; endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:8px;">
                <button type="button" id="fc-add-tc" class="button"><span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:28px;margin-right:4px;"></span> <?php fc_e( 'set_add_tax_class' ); ?></button>
            </p>

            <p style="margin-top:16px;">
                <button type="submit" name="fc_save_taxes" class="button button-primary"><?php fc_e( 'set_save_tax_settings' ); ?></button>
            </p>
        </form>

        <script>
        jQuery(function($) {
            var tcIdx = <?php echo $tci; ?>;

            $('#fc-add-tc').on('click', function() {
                var row = '<tr>' +
                    '<td><input type="text" name="fc_tc_key[' + tcIdx + ']" style="width:100%;" pattern="[a-z0-9_]+" placeholder="<?php echo esc_attr( fc__( 'set_eg_exempt' ) ); ?>"></td>' +
                    '<td><input type="text" name="fc_tc_label[' + tcIdx + ']" style="width:100%;" placeholder="<?php echo esc_attr( fc__( 'set_eg_exempt_label' ) ); ?>"></td>' +
                    '<td><input type="number" name="fc_tc_rate[' + tcIdx + ']" value="0" min="0" max="100" step="0.01" style="width:100px;"> %</td>' +
                    '<td><button type="button" class="button fc-remove-tc" title="<?php echo esc_attr( fc__( 'set_delete' ) ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>' +
                    '</tr>';
                $('#fc-tax-classes').append(row);
                tcIdx++;
            });
            $(document).on('click', '.fc-remove-tc', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Zakładka wysyłki
     */
    private static function render_shipping_tab() {
        $methods = get_option( 'fc_shipping_methods', array() );
        if ( ! is_array( $methods ) ) $methods = array();

        // Pobierz klasy wysyłkowe
        $all_sc = get_terms( array( 'taxonomy' => 'fc_shipping_class', 'hide_empty' => false ) );
        if ( is_wp_error( $all_sc ) ) $all_sc = array();

        // Obsługa zapisu (niezależny formularz)
        if ( isset( $_POST['fc_save_shipping'] ) && check_admin_referer( 'fc_shipping_nonce' ) ) {
            $new_methods = array();
            if ( ! empty( $_POST['fc_sm_name'] ) && is_array( $_POST['fc_sm_name'] ) ) {
                foreach ( $_POST['fc_sm_name'] as $i => $name ) {
                    $name = sanitize_text_field( $name );
                    if ( empty( $name ) ) continue;
                    $countries = array();
                    if ( ! empty( $_POST['fc_sm_countries'][ $i ] ) && is_array( $_POST['fc_sm_countries'][ $i ] ) ) {
                        $countries = array_map( 'sanitize_text_field', $_POST['fc_sm_countries'][ $i ] );
                    }
                    $ft = isset( $_POST['fc_sm_free_threshold'][ $i ] ) && $_POST['fc_sm_free_threshold'][ $i ] !== '' ? floatval( $_POST['fc_sm_free_threshold'][ $i ] ) : '';

                    // Koszty klas wysyłkowych
                    $class_costs = array();
                    if ( ! empty( $_POST['fc_sm_class_cost'][ $i ] ) && is_array( $_POST['fc_sm_class_cost'][ $i ] ) ) {
                        foreach ( $_POST['fc_sm_class_cost'][ $i ] as $class_id => $cval ) {
                            $cval = trim( $cval );
                            if ( $cval === '' ) continue; // puste = bazowy koszt
                            if ( $cval === 'none' ) {
                                $class_costs[ $class_id ] = 'none';
                            } else {
                                $class_costs[ $class_id ] = floatval( $cval );
                            }
                        }
                    }
                    // Zablokowane klasy
                    if ( ! empty( $_POST['fc_sm_class_blocked'][ $i ] ) && is_array( $_POST['fc_sm_class_blocked'][ $i ] ) ) {
                        foreach ( $_POST['fc_sm_class_blocked'][ $i ] as $class_id => $val ) {
                            $class_costs[ $class_id ] = 'none';
                        }
                    }

                    $new_methods[] = array(
                        'name'           => $name,
                        'cost'           => floatval( $_POST['fc_sm_cost'][ $i ] ?? 0 ),
                        'free_threshold' => $ft,
                        'enabled'        => isset( $_POST['fc_sm_enabled'][ $i ] ) ? 1 : 0,
                        'countries'      => $countries,
                        'class_costs'    => $class_costs,
                    );
                }
            }
            update_option( 'fc_shipping_methods', $new_methods );
            $methods = $new_methods;
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'set_shipping_settings_have_been_saved' ) . '</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field( 'fc_shipping_nonce' ); ?>

            <h2 style="margin-top:0;"><?php fc_e( 'set_shipping_methods' ); ?></h2>
            <p class="description"><?php fc_e( 'set_add_shipping_methods_available_in_the_store_disabl' ); ?></p>

            <?php
            $shipping_countries = FC_Shortcodes::get_allowed_countries( 'admin' );
            ?>
            <script type="application/json" id="fc-shipping-countries-data"><?php echo wp_json_encode( $shipping_countries ); ?></script>
            <script type="application/json" id="fc-shipping-classes-data"><?php
                $sc_map = array();
                foreach ( $all_sc as $sc_term ) {
                    $sc_map[ $sc_term->term_id ] = $sc_term->name;
                }
                echo wp_json_encode( $sc_map );
            ?></script>

            <table class="widefat fc-shipping-table" style="margin:16px 0;">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th><?php fc_e( 'set_method_name' ); ?></th>
                        <th style="width:120px;"><?php fc_e( 'set_cost' ); ?></th>
                        <th style="width:140px;"><?php fc_e( 'set_free_from' ); ?></th>
                        <th style="width:80px;"><?php fc_e( 'set_active' ); ?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="fc-shipping-methods">
                    <?php if ( ! empty( $methods ) ) : foreach ( $methods as $i => $m ) :
                        $m_countries = isset( $m['countries'] ) && is_array( $m['countries'] ) ? $m['countries'] : array();
                        $countries_label = empty( $m_countries ) ? fc__( 'set_all_countries' ) : implode( ', ', array_map( function( $c ) use ( $shipping_countries ) { return $shipping_countries[ $c ] ?? $c; }, $m_countries ) );
                    ?>
                        <tr>
                            <td><span class="dashicons dashicons-menu" style="color:#999;cursor:grab;"></span></td>
                            <td>
                                <input type="text" name="fc_sm_name[<?php echo $i; ?>]" value="<?php echo esc_attr( $m['name'] ); ?>" class="regular-text" style="width:100%;">
                                <a href="#" class="fc-toggle-countries" role="button" aria-expanded="false" style="display:block;font-size:12px;text-decoration:none;margin-top:4px;"><?php echo esc_html( $countries_label ); ?> ▼</a>
                                <div class="fc-countries-panel" style="display:none;margin-top:8px;padding:10px;background:#f9f9f9;border:1px solid #ddd;max-height:180px;overflow-y:auto;">
                                    <label style="display:block;margin-bottom:6px;font-weight:600;"><input type="checkbox" class="fc-countries-all" <?php checked( empty( $m_countries ) ); ?>> <?php fc_e( 'set_all_countries' ); ?></label>
                                    <div class="fc-countries-list" style="column-count:3;column-gap:12px;<?php if ( empty( $m_countries ) ) echo 'opacity:0.4;pointer-events:none;'; ?>">
                                        <?php foreach ( $shipping_countries as $code => $cname ) : ?>
                                            <label style="display:block;font-size:12px;line-height:1.8;white-space:nowrap;"><input type="checkbox" name="fc_sm_countries[<?php echo $i; ?>][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $m_countries ) ); ?>> <?php echo esc_html( $cname ); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php if ( ! empty( $all_sc ) ) :
                                    $m_class_costs = isset( $m['class_costs'] ) && is_array( $m['class_costs'] ) ? $m['class_costs'] : array();
                                    $has_class_config = ! empty( $m_class_costs );
                                    $class_label = $has_class_config
                                        ? implode( ', ', array_map( function( $cid ) use ( $all_sc, $m_class_costs ) {
                                            $name = $cid;
                                            foreach ( $all_sc as $t ) { if ( $t->term_id == $cid ) { $name = $t->name; break; } }
                                            return $m_class_costs[ $cid ] === 'none' ? $name . ' ✕' : $name . ' (' . $m_class_costs[ $cid ] . ')';
                                        }, array_keys( $m_class_costs ) ) )
                                        : fc__( 'set_no_configuration' );
                                ?>
                                <a href="#" class="fc-toggle-classes" style="display:block;font-size:12px;text-decoration:none;margin-top:4px;"><?php echo esc_html( $class_label ); ?> ▼</a>
                                <div class="fc-classes-panel" style="display:none;margin-top:8px;padding:10px;background:#f0f6ff;border:1px solid #c3d9f1;">
                                    <p style="margin:0 0 8px;font-size:11px;color:#666;"><?php fc_e( 'set_empty_base_cost_check_lock_to_hide_the_method_when' ); ?></p>
                                    <?php foreach ( $all_sc as $sc_term ) :
                                        $sc_id  = $sc_term->term_id;
                                        $sc_val = isset( $m_class_costs[ $sc_id ] ) ? $m_class_costs[ $sc_id ] : '';
                                        $is_blocked = $sc_val === 'none';
                                    ?>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <span style="min-width:120px;font-size:12px;"><?php echo esc_html( $sc_term->name ); ?></span>
                                        <input type="number" name="fc_sm_class_cost[<?php echo $i; ?>][<?php echo $sc_id; ?>]" value="<?php echo $is_blocked ? '' : esc_attr( $sc_val ); ?>" min="0" step="0.01" style="width:90px;<?php if ( $is_blocked ) echo 'opacity:0.3;pointer-events:none;'; ?>" placeholder="—" class="fc-class-cost-input">
                                        <label style="font-size:11px;white-space:nowrap;"><input type="checkbox" class="fc-class-blocked-cb" name="fc_sm_class_blocked[<?php echo $i; ?>][<?php echo $sc_id; ?>]" value="1" <?php checked( $is_blocked ); ?>> <?php fc_e( 'set_lock' ); ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><input type="number" name="fc_sm_cost[<?php echo $i; ?>]" value="<?php echo esc_attr( $m['cost'] ); ?>" min="0" step="0.01" style="width:100%;"> </td>
                            <td><input type="number" name="fc_sm_free_threshold[<?php echo $i; ?>]" value="<?php echo esc_attr( $m['free_threshold'] ?? '' ); ?>" min="0" step="0.01" style="width:100%;" placeholder="—"></td>
                            <td style="text-align:center;"><input type="checkbox" name="fc_sm_enabled[<?php echo $i; ?>]" value="1" <?php checked( $m['enabled'] ?? 1, 1 ); ?>></td>
                            <td><button type="button" class="button fc-remove-shipping" title="<?php echo esc_attr( fc__( 'attr_delete' ) ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <button type="button" class="button" id="fc-add-shipping">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:28px;margin-right:4px;"></span>
                <?php fc_e( 'set_add_shipping_method' ); ?>
            </button>

            <p class="description" style="margin-top:12px;"><?php fc_e( 'set_free_from_column_cart_amount_from_which_shipping_f' ); ?></p>

            <?php submit_button( fc__( 'set_save_shipping' ), 'primary', 'fc_save_shipping' ); ?>
        </form>

        <script>
        jQuery(function($){
            // Sortowanie metod wysyłki
            $('#fc-shipping-methods').sortable({
                handle: '.dashicons-menu',
                axis: 'y',
                cursor: 'grabbing',
                placeholder: 'fc-sortable-placeholder',
                helper: function(e, tr) {
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function(index) {
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                stop: function() {
                    var newIdx = 0;
                    $('#fc-shipping-methods tr').each(function() {
                        $(this).find('input, select').each(function() {
                            var name = $(this).attr('name');
                            if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + newIdx + ']'));
                        });
                        newIdx++;
                    });
                }
            });

            var idx = <?php echo count( $methods ); ?>;
            var shippingCountries = JSON.parse($('#fc-shipping-countries-data').text() || '{}');
            var shippingClasses = JSON.parse($('#fc-shipping-classes-data').text() || '{}');

            function buildCountriesHtml(i) {
                var html = '<a href="#" class="fc-toggle-countries" style="display:block;font-size:12px;text-decoration:none;margin-top:4px;outline:none;box-shadow:none;"><?php echo esc_js( fc__( 'set_all_countries' ) ); ?> ▼</a>' +
                    '<div class="fc-countries-panel" style="display:none;margin-top:8px;padding:10px;background:#f9f9f9;border:1px solid #ddd;max-height:180px;overflow-y:auto;">' +
                    '<label style="display:block;margin-bottom:6px;font-weight:600;"><input type="checkbox" class="fc-countries-all" checked> <?php echo esc_js( fc__( 'set_all_countries' ) ); ?></label>' +
                    '<div class="fc-countries-list" style="column-count:3;column-gap:12px;opacity:0.4;pointer-events:none;">';
                $.each(shippingCountries, function(code, name) {
                    html += '<label style="display:block;font-size:12px;line-height:1.8;white-space:nowrap;"><input type="checkbox" name="fc_sm_countries[' + i + '][]" value="' + code + '"> ' + name + '</label>';
                });
                html += '</div></div>';
                return html;
            }

            function buildClassCostsHtml(i) {
                if ($.isEmptyObject(shippingClasses)) return '';
                var html = '<a href="#" class="fc-toggle-classes" style="display:block;font-size:12px;text-decoration:none;margin-top:4px;"><?php echo esc_js( fc__( 'set_no_configuration' ) ); ?> ▼</a>' +
                    '<div class="fc-classes-panel" style="display:none;margin-top:8px;padding:10px;background:#f0f6ff;border:1px solid #c3d9f1;">' +
                    '<p style="margin:0 0 8px;font-size:11px;color:#666;"><?php echo esc_js( fc__( 'set_empty_base_cost_check_lock_to_hide_the_method_when' ) ); ?></p>';
                $.each(shippingClasses, function(cid, cname) {
                    html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">' +
                        '<span style="min-width:120px;font-size:12px;">' + $('<span>').text(cname).html() + '</span>' +
                        '<input type="number" name="fc_sm_class_cost[' + i + '][' + cid + ']" min="0" step="0.01" style="width:90px;" placeholder="—" class="fc-class-cost-input">' +
                        '<label style="font-size:11px;white-space:nowrap;"><input type="checkbox" class="fc-class-blocked-cb" name="fc_sm_class_blocked[' + i + '][' + cid + ']" value="1"> <?php echo esc_js( fc__( 'set_lock' ) ); ?></label>' +
                        '</div>';
                });
                html += '</div>';
                return html;
            }

            $('#fc-add-shipping').on('click', function(){
                var row = '<tr>' +
                    '<td><span class="dashicons dashicons-menu" style="color:#999;cursor:grab;"></span></td>' +
                    '<td><input type="text" name="fc_sm_name[' + idx + ']" class="regular-text" style="width:100%;">' + buildCountriesHtml(idx) + buildClassCostsHtml(idx) + '</td>' +
                    '<td><input type="number" name="fc_sm_cost[' + idx + ']" value="0" min="0" step="0.01" style="width:100%;"></td>' +
                    '<td><input type="number" name="fc_sm_free_threshold[' + idx + ']" min="0" step="0.01" style="width:100%;" placeholder="—"></td>' +
                    '<td style="text-align:center;"><input type="checkbox" name="fc_sm_enabled[' + idx + ']" value="1" checked></td>' +
                    '<td><button type="button" class="button fc-remove-shipping" title="<?php echo esc_attr( fc__( 'set_delete' ) ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>' +
                    '</tr>';
                $('#fc-shipping-methods').append(row);
                $('#fc-shipping-methods').sortable('refresh');
                idx++;
            });
            $(document).on('click', '.fc-remove-shipping', function(){
                $(this).closest('tr').remove();
            });

            // Toggle countries panel
            $(document).on('click', '.fc-toggle-countries', function(e) {
                e.preventDefault();
                $(this).next('.fc-countries-panel').slideToggle(200);
            });

            // "Wszystkie kraje" checkbox
            $(document).on('change', '.fc-countries-all', function() {
                var $panel = $(this).closest('.fc-countries-panel');
                var $list = $panel.find('.fc-countries-list');
                if ($(this).is(':checked')) {
                    $list.css({opacity: 0.4, 'pointer-events': 'none'});
                    $list.find('input[type="checkbox"]').prop('checked', false);
                } else {
                    $list.css({opacity: 1, 'pointer-events': 'auto'});
                }
                updateCountriesLabel($(this));
            });

            // Individual country checkbox
            $(document).on('change', '.fc-countries-list input[type="checkbox"]', function() {
                var $td = $(this).closest('td');
                var $allCheckbox = $td.find('.fc-countries-all');
                var checked = $td.find('.fc-countries-list input:checked').length;
                if (checked === 0) {
                    $allCheckbox.prop('checked', true).trigger('change');
                }
                updateCountriesLabel($(this));
            });

            function updateCountriesLabel($el) {
                var $td = $el.closest('td');
                var $link = $td.find('.fc-toggle-countries');
                var isAll = $td.find('.fc-countries-all').is(':checked');
                if (isAll) {
                    $link.text('<?php echo esc_js( fc__( 'set_all_countries' ) ); ?> ▼');
                } else {
                    var names = [];
                    $td.find('.fc-countries-list input:checked').each(function() {
                        var code = $(this).val();
                        if (shippingCountries[code]) names.push(shippingCountries[code]);
                    });
                    $link.text(names.length ? names.join(', ') + ' ▼' : '<?php echo esc_js( fc__( 'set_all_countries' ) ); ?> ▼');
                }
            }

            // Toggle classes panel
            $(document).on('click', '.fc-toggle-classes', function(e) {
                e.preventDefault();
                $(this).next('.fc-classes-panel').slideToggle(200);
            });

            // Block checkbox toggles cost input
            $(document).on('change', '.fc-class-blocked-cb', function() {
                var $input = $(this).closest('div').find('.fc-class-cost-input');
                if ($(this).is(':checked')) {
                    $input.css({opacity: 0.3, 'pointer-events': 'none'}).val('');
                } else {
                    $input.css({opacity: 1, 'pointer-events': 'auto'});
                }
            });
        });
        </script>

        <hr style="margin: 2rem 0;">
        <h2 style="display:flex;align-items:center;gap:8px;">
            <span class="dashicons dashicons-car"></span>
            <?php fc_e( 'pt_shipping_classes' ); ?>
        </h2>
        <p class="description"><?php fc_e( 'set_shipping_classes_allow_grouping_products_by_shippi' ); ?></p>

        <?php
        // Obsługa zapisu klas wysyłkowych
        if ( isset( $_POST['fc_save_sc'] ) && check_admin_referer( 'fc_sc_manage_nonce' ) ) {
            // Usunięcie zaznaczonych
            if ( ! empty( $_POST['fc_sc_delete'] ) && is_array( $_POST['fc_sc_delete'] ) ) {
                foreach ( $_POST['fc_sc_delete'] as $del_id ) {
                    wp_delete_term( absint( $del_id ), 'fc_shipping_class' );
                }
            }
            // Aktualizacja istniejących nazw
            if ( ! empty( $_POST['fc_sc_name'] ) && is_array( $_POST['fc_sc_name'] ) ) {
                foreach ( $_POST['fc_sc_name'] as $term_id => $name ) {
                    $name = sanitize_text_field( $name );
                    if ( ! empty( $name ) ) {
                        wp_update_term( absint( $term_id ), 'fc_shipping_class', array( 'name' => $name ) );
                    }
                }
            }
            // Dodanie nowych
            if ( ! empty( $_POST['fc_sc_new'] ) && is_array( $_POST['fc_sc_new'] ) ) {
                foreach ( $_POST['fc_sc_new'] as $new_name ) {
                    $new_name = sanitize_text_field( $new_name );
                    if ( ! empty( $new_name ) ) {
                        wp_insert_term( $new_name, 'fc_shipping_class' );
                    }
                }
            }
            // Odśwież listę klas
            $all_sc = get_terms( array( 'taxonomy' => 'fc_shipping_class', 'hide_empty' => false ) );
            if ( is_wp_error( $all_sc ) ) $all_sc = array();
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'set_shipping_classes_have_been_saved' ) . '</p></div>';
        }
        ?>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field( 'fc_sc_manage_nonce' ); ?>
            <table class="widefat striped" style="max-width:500px;">
                <thead>
                    <tr>
                        <th><?php fc_e( 'set_class_name' ); ?></th>
                        <th style="width:80px;text-align:center;"><?php fc_e( 'order_products' ); ?></th>
                        <th style="width:50px;text-align:center;"><?php fc_e( 'attr_delete' ); ?></th>
                    </tr>
                </thead>
                <tbody id="fc-sc-list">
                    <?php if ( ! empty( $all_sc ) ) : foreach ( $all_sc as $sc_term ) :
                        $product_count = $sc_term->count;
                    ?>
                    <tr>
                        <td><input type="text" name="fc_sc_name[<?php echo $sc_term->term_id; ?>]" value="<?php echo esc_attr( $sc_term->name ); ?>" style="width:100%;"></td>
                        <td style="text-align:center;color:#666;"><?php echo intval( $product_count ); ?></td>
                        <td style="text-align:center;"><label><input type="checkbox" name="fc_sc_delete[]" value="<?php echo $sc_term->term_id; ?>"></label></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tbody id="fc-sc-new"></tbody>
            </table>

            <p style="margin-top:8px;display:flex;gap:8px;">
                <button type="button" id="fc-add-sc" class="button"><span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:28px;margin-right:4px;"></span> <?php fc_e( 'set_add_class' ); ?></button>
                <button type="submit" name="fc_save_sc" class="button button-primary"><?php fc_e( 'set_save_classes' ); ?></button>
            </p>
        </form>

        <script>
        jQuery(function($) {
            var scNewIdx = 0;
            $('#fc-add-sc').on('click', function() {
                var row = '<tr>' +
                    '<td><input type="text" name="fc_sc_new[' + scNewIdx + ']" style="width:100%;" placeholder="<?php echo esc_js( fc__( 'set_new_class_name' ) ); ?>"></td>' +
                    '<td style="text-align:center;color:#999;">—</td>' +
                    '<td style="text-align:center;"><button type="button" class="button fc-sc-remove-new" title="<?php echo esc_js( fc__( 'attr_delete' ) ); ?>"><span class="dashicons dashicons-no" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>' +
                    '</tr>';
                $('#fc-sc-new').append(row);
                scNewIdx++;
            });
            $(document).on('click', '.fc-sc-remove-new', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>

        <?php
        // --- Reguły automatycznego przypisywania klas wysyłkowych ---
        $sc_rules = get_option( 'fc_shipping_class_rules', array() );
        if ( ! is_array( $sc_rules ) ) $sc_rules = array();

        // Obsługa zapisu reguł
        if ( isset( $_POST['fc_save_sc_rules'] ) && check_admin_referer( 'fc_sc_rules_nonce' ) ) {
            $new_rules = array();
            if ( ! empty( $_POST['fc_scr_class'] ) && is_array( $_POST['fc_scr_class'] ) ) {
                foreach ( $_POST['fc_scr_class'] as $ri => $class_id ) {
                    $class_id = absint( $class_id );
                    if ( $class_id < 1 ) continue;
                    $rule = array(
                        'class_id'   => $class_id,
                        'min_weight' => $_POST['fc_scr_min_weight'][ $ri ] !== '' ? floatval( $_POST['fc_scr_min_weight'][ $ri ] ) : '',
                        'max_weight' => $_POST['fc_scr_max_weight'][ $ri ] !== '' ? floatval( $_POST['fc_scr_max_weight'][ $ri ] ) : '',
                        'max_length' => $_POST['fc_scr_max_length'][ $ri ] !== '' ? floatval( $_POST['fc_scr_max_length'][ $ri ] ) : '',
                        'max_width'  => $_POST['fc_scr_max_width'][ $ri ] !== '' ? floatval( $_POST['fc_scr_max_width'][ $ri ] ) : '',
                        'max_height' => $_POST['fc_scr_max_height'][ $ri ] !== '' ? floatval( $_POST['fc_scr_max_height'][ $ri ] ) : '',
                        'priority'   => intval( $_POST['fc_scr_priority'][ $ri ] ?? 10 ),
                    );
                    $new_rules[] = $rule;
                }
            }
            // Sortuj po priorytecie (niższy = ważniejszy)
            usort( $new_rules, function( $a, $b ) { return $a['priority'] - $b['priority']; } );
            update_option( 'fc_shipping_class_rules', $new_rules );
            $sc_rules = $new_rules;
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'set_automatic_assignment_rules_have_been_saved' ) . '</p></div>';
        }
        ?>

        <h3 style="margin-top:1.5rem;"><?php fc_e( 'set_automatic_assignment_rules' ); ?></h3>
        <p class="description"><?php fc_e( 'set_if_a_product_does_not_have_a_manually_assigned_shi' ); ?></p>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field( 'fc_sc_rules_nonce' ); ?>
            <table class="widefat striped" style="max-width:1000px;">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th><?php fc_e( 'pt_shipping_class' ); ?></th>
                        <th><?php fc_e( 'set_min_weight_kg' ); ?></th>
                        <th><?php fc_e( 'set_max_weight_kg' ); ?></th>
                        <th><?php fc_e( 'set_max_length_cm' ); ?></th>
                        <th><?php fc_e( 'set_max_width_cm' ); ?></th>
                        <th><?php fc_e( 'set_max_height_cm' ); ?></th>
                        <th style="width:70px;"><?php fc_e( 'set_priority' ); ?></th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody id="fc-sc-rules">
                    <?php foreach ( $sc_rules as $ri => $rule ) : ?>
                    <tr>
                        <td><?php echo $ri + 1; ?></td>
                        <td>
                            <select name="fc_scr_class[<?php echo $ri; ?>]" style="width:100%;">
                                <option value="0">—</option>
                                <?php foreach ( $all_sc as $sc_term ) : ?>
                                    <option value="<?php echo $sc_term->term_id; ?>" <?php selected( $rule['class_id'], $sc_term->term_id ); ?>><?php echo esc_html( $sc_term->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="fc_scr_min_weight[<?php echo $ri; ?>]" value="<?php echo $rule['min_weight'] !== '' ? esc_attr( $rule['min_weight'] ) : ''; ?>" min="0" step="0.01" style="width:100%;" placeholder="—"></td>
                        <td><input type="number" name="fc_scr_max_weight[<?php echo $ri; ?>]" value="<?php echo $rule['max_weight'] !== '' ? esc_attr( $rule['max_weight'] ) : ''; ?>" min="0" step="0.01" style="width:100%;" placeholder="—"></td>
                        <td><input type="number" name="fc_scr_max_length[<?php echo $ri; ?>]" value="<?php echo $rule['max_length'] !== '' ? esc_attr( $rule['max_length'] ) : ''; ?>" min="0" step="0.01" style="width:100%;" placeholder="—"></td>
                        <td><input type="number" name="fc_scr_max_width[<?php echo $ri; ?>]" value="<?php echo $rule['max_width'] !== '' ? esc_attr( $rule['max_width'] ) : ''; ?>" min="0" step="0.01" style="width:100%;" placeholder="—"></td>
                        <td><input type="number" name="fc_scr_max_height[<?php echo $ri; ?>]" value="<?php echo $rule['max_height'] !== '' ? esc_attr( $rule['max_height'] ) : ''; ?>" min="0" step="0.01" style="width:100%;" placeholder="—"></td>
                        <td><input type="number" name="fc_scr_priority[<?php echo $ri; ?>]" value="<?php echo esc_attr( $rule['priority'] ); ?>" min="0" style="width:100%;"></td>
                        <td><button type="button" class="button fc-remove-sc-rule" title="<?php fc_e( 'attr_delete' ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:8px;">
                <button type="button" id="fc-add-sc-rule" class="button"><span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:28px;margin-right:4px;"></span> <?php fc_e( 'set_add_rule' ); ?></button>
            </p>
            <p style="margin-top:12px;">
                <button type="submit" name="fc_save_sc_rules" class="button button-primary"><?php fc_e( 'set_save_rules' ); ?></button>
            </p>
        </form>

        <script>
        jQuery(function($) {
            var rIdx = <?php echo count( $sc_rules ); ?>;
            var scOptions = <?php
                $opts = '<option value="0">—</option>';
                foreach ( $all_sc as $sc_term ) {
                    $opts .= '<option value="' . $sc_term->term_id . '">' . esc_html( $sc_term->name ) . '</option>';
                }
                echo wp_json_encode( $opts );
            ?>;

            $('#fc-add-sc-rule').on('click', function() {
                var row = '<tr>' +
                    '<td>' + (rIdx + 1) + '</td>' +
                    '<td><select name="fc_scr_class[' + rIdx + ']" style="width:100%;">' + scOptions + '</select></td>' +
                    '<td><input type="number" name="fc_scr_min_weight[' + rIdx + ']" min="0" step="0.01" style="width:100%;" placeholder="—"></td>' +
                    '<td><input type="number" name="fc_scr_max_weight[' + rIdx + ']" min="0" step="0.01" style="width:100%;" placeholder="—"></td>' +
                    '<td><input type="number" name="fc_scr_max_length[' + rIdx + ']" min="0" step="0.01" style="width:100%;" placeholder="—"></td>' +
                    '<td><input type="number" name="fc_scr_max_width[' + rIdx + ']" min="0" step="0.01" style="width:100%;" placeholder="—"></td>' +
                    '<td><input type="number" name="fc_scr_max_height[' + rIdx + ']" min="0" step="0.01" style="width:100%;" placeholder="—"></td>' +
                    '<td><input type="number" name="fc_scr_priority[' + rIdx + ']" value="10" min="0" style="width:100%;"></td>' +
                    '<td><button type="button" class="button fc-remove-sc-rule" title="<?php echo esc_attr( fc__( 'set_delete' ) ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>' +
                    '</tr>';
                $('#fc-sc-rules').append(row);
                rIdx++;
            });
            $(document).on('click', '.fc-remove-sc-rule', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Zakładka płatności
     */
    private static function render_payments_tab() {
        $methods = get_option( 'fc_payment_methods', array(
            array( 'id' => 'transfer', 'name' => fc__( 'set_bank_transfer' ), 'description' => '', 'enabled' => 1 ),
            array( 'id' => 'cod',      'name' => fc__( 'set_cash_on_delivery' ), 'description' => '', 'enabled' => 1 ),
        ) );
        if ( ! is_array( $methods ) ) $methods = array();

        // Obsługa zapisu
        if ( isset( $_POST['fc_save_payments'] ) && check_admin_referer( 'fc_payments_nonce' ) ) {
            // Dane bankowe
            update_option( 'fc_bank_account', sanitize_text_field( $_POST['fc_bank_account'] ?? '' ) );
            update_option( 'fc_bank_swift', sanitize_text_field( $_POST['fc_bank_swift'] ?? '' ) );

            $new_methods = array();
            if ( ! empty( $_POST['fc_pm_id'] ) && is_array( $_POST['fc_pm_id'] ) ) {
                foreach ( $_POST['fc_pm_id'] as $i => $id ) {
                    $id = sanitize_key( $id );
                    if ( empty( $id ) ) continue;
                    $new_methods[] = array(
                        'id'          => $id,
                        'name'        => sanitize_text_field( $_POST['fc_pm_name'][ $i ] ?? '' ),
                        'description' => sanitize_textarea_field( $_POST['fc_pm_desc'][ $i ] ?? '' ),
                        'enabled'     => isset( $_POST['fc_pm_enabled'][ $i ] ) ? 1 : 0,
                    );
                }
            }
            update_option( 'fc_payment_methods', $new_methods );
            $methods = $new_methods;
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'set_payment_settings_have_been_saved' ) . '</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field( 'fc_payments_nonce' ); ?>

            <h2 style="margin-top:0;"><?php fc_e( 'set_bank_transfer_details' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'set_bank_account_number' ); ?></th>
                    <td><input type="text" name="fc_bank_account" value="<?php echo esc_attr( get_option( 'fc_bank_account' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php fc_e( 'set_swift_bic' ); ?></th>
                    <td><input type="text" name="fc_bank_swift" value="<?php echo esc_attr( get_option( 'fc_bank_swift' ) ); ?>" class="small-text" style="width:140px;"></td>
                </tr>
            </table>

            <hr style="margin: 1.5rem 0;">

            <h2><?php fc_e( 'set_payment_methods' ); ?></h2>
            <p class="description"><?php fc_e( 'set_manage_payment_methods_available_in_the_store_disa' ); ?></p>

            <table class="widefat" style="margin:16px 0;">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:140px;"><?php fc_e( 'set_identifier' ); ?></th>
                        <th><?php fc_e( 'set_method_name' ); ?></th>
                        <th><?php fc_e( 'set_description_optional' ); ?></th>
                        <th style="width:80px;"><?php fc_e( 'set_active' ); ?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="fc-payment-methods">
                    <?php if ( ! empty( $methods ) ) : foreach ( $methods as $i => $m ) : ?>
                        <tr>
                            <td><span class="dashicons dashicons-menu" style="color:#999;cursor:grab;"></span></td>
                            <td><input type="text" name="fc_pm_id[<?php echo $i; ?>]" value="<?php echo esc_attr( $m['id'] ); ?>" style="width:100%;" placeholder="<?php echo esc_attr( fc__( 'set_eg_paypal' ) ); ?>"></td>
                            <td><input type="text" name="fc_pm_name[<?php echo $i; ?>]" value="<?php echo esc_attr( $m['name'] ); ?>" style="width:100%;"></td>
                            <td><input type="text" name="fc_pm_desc[<?php echo $i; ?>]" value="<?php echo esc_attr( $m['description'] ?? '' ); ?>" style="width:100%;" placeholder="<?php echo esc_attr( fc__( 'set_description_visible_to_the_customer' ) ); ?>"></td>
                            <td style="text-align:center;"><input type="checkbox" name="fc_pm_enabled[<?php echo $i; ?>]" value="1" <?php checked( $m['enabled'] ?? 1, 1 ); ?>></td>
                            <td><button type="button" class="button fc-remove-payment" title="<?php echo esc_attr( fc__( 'attr_delete' ) ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <button type="button" class="button" id="fc-add-payment">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:28px;margin-right:4px;"></span>
                <?php fc_e( 'set_add_payment_method' ); ?>
            </button>

            <p class="description" style="margin-top:12px;"><?php fc_e( 'set_identifier_is_used_for_internal_method_identificat' ); ?></p>

            <?php submit_button( fc__( 'set_save_payments' ), 'primary', 'fc_save_payments' ); ?>
        </form>

        <script>
        jQuery(function($){
            // Sortowanie metod płatności
            $('#fc-payment-methods').sortable({
                handle: '.dashicons-menu',
                axis: 'y',
                cursor: 'grabbing',
                placeholder: 'fc-sortable-placeholder',
                helper: function(e, tr) {
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function(index) {
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                stop: function() {
                    // Przenumeruj indeksy
                    $('#fc-payment-methods tr').each(function(idx) {
                        $(this).find('input, select').each(function() {
                            var name = $(this).attr('name');
                            if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
                        });
                    });
                }
            });

            var idx = <?php echo count( $methods ); ?>;

            $('#fc-add-payment').on('click', function(){
                var row = '<tr>' +
                    '<td><span class="dashicons dashicons-menu" style="color:#999;cursor:grab;"></span></td>' +
                    '<td><input type="text" name="fc_pm_id[' + idx + ']" style="width:100%;" placeholder="<?php echo esc_attr( fc__( 'set_eg_paypal' ) ); ?>"></td>' +
                    '<td><input type="text" name="fc_pm_name[' + idx + ']" style="width:100%;"></td>' +
                    '<td><input type="text" name="fc_pm_desc[' + idx + ']" style="width:100%;" placeholder="<?php echo esc_attr( fc__( 'set_desc_visible_to_customer' ) ); ?>"></td>' +
                    '<td style="text-align:center;"><input type="checkbox" name="fc_pm_enabled[' + idx + ']" value="1" checked></td>' +
                    '<td><button type="button" class="button fc-remove-payment" title="<?php echo esc_attr( fc__( 'set_delete' ) ); ?>"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button></td>' +
                    '</tr>';
                $('#fc-payment-methods').append(row);
                idx++;
            });

            $(document).on('click', '.fc-remove-payment', function(){
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php

        // Hook for Stripe settings section
        do_action( 'fc_payments_tab_after' );
    }

    /**
     * Zakładka e-maili
     */
    private static function render_emails_tab() {
        $statuses = FC_Orders::get_statuses();
        $store_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );

        // Dostępne języki i aktualnie edytowany
        $available_langs = FC_i18n::get_available_languages();
        $edit_lang = isset( $_GET['email_lang'] ) ? sanitize_key( $_GET['email_lang'] ) : get_option( 'fc_frontend_lang', 'pl' );
        if ( ! isset( $available_langs[ $edit_lang ] ) ) $edit_lang = 'pl';
        $option_key = self::get_email_option_key( $edit_lang );

        // Obsługa zapisu
        if ( isset( $_POST['fc_save_emails'] ) && check_admin_referer( 'fc_emails_nonce', 'fc_emails_nonce' ) ) {
            $save_lang = sanitize_key( $_POST['fc_email_lang'] ?? $edit_lang );
            $save_option_key = self::get_email_option_key( $save_lang );

            // Sanityzacja: admin może używać pełnego HTML w szablonach emaili
            $sanitize_email_html = function( $raw ) {
                return current_user_can( 'manage_options' ) ? wp_unslash( $raw ) : wp_kses_post( $raw );
            };

            $templates = get_option( $save_option_key, array() );
            // Zapisz header i footer
            $templates['_header'] = $sanitize_email_html( $_POST['fc_email_header'] ?? '' );
            $templates['_footer'] = $sanitize_email_html( $_POST['fc_email_footer'] ?? '' );
            foreach ( $statuses as $key => $label ) {
                $templates[ $key ] = array(
                    'enabled' => isset( $_POST['fc_email_enabled'][ $key ] ) ? 1 : 0,
                    'subject' => sanitize_text_field( $_POST['fc_email_subject'][ $key ] ?? '' ),
                    'body'    => $sanitize_email_html( $_POST['fc_email_body'][ $key ] ?? '' ),
                );
            }
            // Szablon resetowania hasła
            $templates['password_reset'] = array(
                'enabled' => isset( $_POST['fc_email_enabled']['password_reset'] ) ? 1 : 0,
                'subject' => sanitize_text_field( $_POST['fc_email_subject']['password_reset'] ?? '' ),
                'body'    => $sanitize_email_html( $_POST['fc_email_body']['password_reset'] ?? '' ),
            );
            // Szablon aktywacji konta
            $templates['account_activation'] = array(
                'enabled' => isset( $_POST['fc_email_enabled']['account_activation'] ) ? 1 : 0,
                'subject' => sanitize_text_field( $_POST['fc_email_subject']['account_activation'] ?? '' ),
                'body'    => $sanitize_email_html( $_POST['fc_email_body']['account_activation'] ?? '' ),
            );
            // Szablon nowego zamówienia do admina
            $templates['new_order_admin'] = array(
                'enabled' => isset( $_POST['fc_email_enabled']['new_order_admin'] ) ? 1 : 0,
                'subject' => sanitize_text_field( $_POST['fc_email_subject']['new_order_admin'] ?? '' ),
                'body'    => $sanitize_email_html( $_POST['fc_email_body']['new_order_admin'] ?? '' ),
            );
            // Szablon powiadomienia o dostępności
            $templates['stock_notify'] = array(
                'enabled' => isset( $_POST['fc_email_enabled']['stock_notify'] ) ? 1 : 0,
                'subject' => sanitize_text_field( $_POST['fc_email_subject']['stock_notify'] ?? '' ),
                'body'    => $sanitize_email_html( $_POST['fc_email_body']['stock_notify'] ?? '' ),
            );
            update_option( $save_option_key, $templates );
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'set_email_templates_saved' ) . '</p></div>';

            // Reload po zapisie z tego samego języka
            $edit_lang = $save_lang;
            $option_key = $save_option_key;
        }

        $templates = get_option( $option_key, array() );
        $templates = self::maybe_migrate_email_templates( $templates, $edit_lang );
        $defaults = self::get_default_email_templates( $edit_lang );

        // Pobierz ostatnie zamówienia do selecta
        $recent_orders = get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        // Dane z prawdziwego zamówienia (ostatniego lub wybranego)
        $selected_order_id = 0;
        if ( ! empty( $recent_orders ) ) {
            $selected_order_id = $recent_orders[0];
        }
        $preview_data = self::get_order_preview_data( $selected_order_id, $edit_lang );
        ?>
        <form method="post">
            <?php wp_nonce_field( 'fc_emails_nonce', 'fc_emails_nonce' ); ?>
            <input type="hidden" name="fc_email_lang" value="<?php echo esc_attr( $edit_lang ); ?>">

            <?php if ( count( $available_langs ) > 1 ) : ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:12px 16px;background:#f0f6fc;border:1px solid #a8c7fa;border-radius:6px;">
                <span class="dashicons dashicons-translation" style="color:#2271b1;font-size:20px;"></span>
                <strong style="white-space:nowrap;"><?php fc_e( 'set_templates_for_language' ); ?></strong>
                <?php foreach ( $available_langs as $lang_code => $lang_name ) :
                    $lang_url = add_query_arg( array( 'page' => 'flavor-commerce', 'tab' => 'emails', 'email_lang' => $lang_code ), admin_url( 'admin.php' ) );
                    $is_active = ( $lang_code === $edit_lang );
                    $is_frontend = ( $lang_code === get_option( 'fc_frontend_lang', 'pl' ) );
                ?>
                    <a href="<?php echo esc_url( $lang_url ); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:6px 16px;border-radius:4px;text-decoration:none;font-weight:<?php echo $is_active ? '700' : '400'; ?>;background:<?php echo $is_active ? '#2271b1' : '#fff'; ?>;color:<?php echo $is_active ? '#fff' : '#333'; ?>;border:1px solid <?php echo $is_active ? '#2271b1' : '#c3c4c7'; ?>;font-size:13px;">
                        <?php echo esc_html( strtoupper( $lang_code ) ); ?>
                        <?php if ( $is_frontend ) : ?><span style="font-size:11px;opacity:0.7;">(<?php fc_e( 'set_active_2' ); ?>)</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:16px;flex-wrap:wrap;">
                <div class="description" style="margin:0;flex:1;min-width:300px;">
                    <strong><?php fc_e( 'set_available_variables_in_templates' ); ?></strong>
                    <style>.fc-email-vars{display:grid;grid-template-columns:1fr 1fr;gap:0 32px;margin-top:8px;font-size:13px;}@media(max-width:960px){.fc-email-vars{grid-template-columns:1fr;}}.fc-email-vars .fc-ev{display:flex;padding:3px 0;}.fc-email-vars .fc-ev code{margin-right:8px;white-space:nowrap;}.fc-email-vars .fc-ev span{color:#666;}.fc-email-vars .fc-ev-heading{grid-column:1/-1;padding:8px 0 3px;font-weight:600;color:#333;}</style>
                    <div class="fc-email-vars">
                        <div class="fc-ev"><code>{header}</code><span><?php fc_e( 'set_shared_header_html_editable_above' ); ?></span></div>
                        <div class="fc-ev"><code>{footer}</code><span><?php fc_e( 'set_shared_footer_html_editable_above' ); ?></span></div>
                        <div class="fc-ev"><code>{logo}</code><span><?php fc_e( 'set_store_logo_set_in_the_store_tab' ); ?></span></div>
                        <div class="fc-ev"><code>{store_name}</code><span><?php fc_e( 'set_store_name' ); ?></span></div>
                        <div class="fc-ev"><code>{order_number}</code><span><?php fc_e( 'set_order_number' ); ?></span></div>
                        <div class="fc-ev"><code>{order_date}</code><span><?php fc_e( 'set_order_date' ); ?></span></div>
                        <div class="fc-ev"><code>{status}</code><span><?php fc_e( 'set_current_order_status' ); ?></span></div>
                        <div class="fc-ev"><code>{first_name}</code><span><?php fc_e( 'set_customer_first_name' ); ?></span></div>
                        <div class="fc-ev"><code>{last_name}</code><span><?php fc_e( 'set_customer_last_name' ); ?></span></div>
                        <div class="fc-ev"><code>{email}</code><span><?php fc_e( 'set_customer_email_address' ); ?></span></div>
                        <div class="fc-ev"><code>{phone}</code><span><?php fc_e( 'set_customer_phone_number' ); ?></span></div>
                        <div class="fc-ev"><code>{billing_address}</code><span><?php fc_e( 'set_billing_address' ); ?></span></div>
                        <div class="fc-ev"><code>{shipping_address}</code><span><?php fc_e( 'set_delivery_address' ); ?></span></div>
                        <div class="fc-ev"><code>{payment_method}</code><span><?php fc_e( 'set_selected_payment_method' ); ?></span></div>
                        <div class="fc-ev"><code>{shipping_method}</code><span><?php fc_e( 'set_selected_shipping_method' ); ?></span></div>
                        <div class="fc-ev"><code>{products}</code><span><?php fc_e( 'set_ordered_products_table' ); ?></span></div>
                        <div class="fc-ev"><code>{subtotal}</code><span><?php fc_e( 'set_subtotal_without_shipping' ); ?></span></div>
                        <div class="fc-ev"><code>{coupon_rows}</code><span><?php fc_e( 'set_coupon_discount_rows' ); ?></span></div>
                        <div class="fc-ev"><code>{shipping_cost}</code><span><?php fc_e( 'set_shipping_cost' ); ?></span></div>
                        <div class="fc-ev"><code>{total}</code><span><?php fc_e( 'set_order_total' ); ?></span></div>
                        <div class="fc-ev"><code>{customer_name}</code><span><?php fc_e( 'set_company_name_or_customer_full_name' ); ?></span></div>
                        <div class="fc-ev"><code>{tax_no}</code><span><?php fc_e( 'set_customer_tax_number_tax_id' ); ?></span></div>
                        <div class="fc-ev"><code>{crn}</code><span><?php fc_e( 'set_customer_company_registration_number' ); ?></span></div>
                        <div class="fc-ev"><code>{tax_no_row}</code><span><?php fc_e( 'set_table_row_with_tax_id_empty_when_none' ); ?></span></div>
                        <div class="fc-ev"><code>{crn_row}</code><span><?php fc_e( 'set_table_row_with_registration_number_empty_when_none' ); ?></span></div>
                        <div class="fc-ev-heading"><?php fc_e( 'set_special_only_in_specific_templates' ); ?></div>
                        <div class="fc-ev"><code>{activation_code}</code><span><?php fc_e( 'set_activation_code_account_activation_template' ); ?></span></div>
                        <div class="fc-ev"><code>{reset_link}</code><span><?php fc_e( 'set_password_reset_link_password_reset_template' ); ?></span></div>
                        <div class="fc-ev"><code>{user_login}</code><span><?php fc_e( 'set_user_login_account_template' ); ?></span></div>
                        <div class="fc-ev"><code>{product_name}</code><span><?php fc_e( 'set_product_name_availability_notification_template' ); ?></span></div>
                        <div class="fc-ev"><code>{product_url}</code><span><?php fc_e( 'set_product_url_availability_notification_template' ); ?></span></div>
                        <div class="fc-ev"><code>{payment_url}</code><span><?php fc_e( 'set_link_to_order_payment_page_awaiting_payment_templa' ); ?></span></div>
                        <div class="fc-ev-heading"><?php fc_e( 'set_theme_colors_and_style_from_customizer' ); ?></div>
                        <div class="fc-ev"><code>{theme_accent}</code><span><?php fc_e( 'set_accent_color' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_accent_hover}</code><span><?php fc_e( 'set_accent_color_hover' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_accent_light}</code><span><?php fc_e( 'set_accent_color_light' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_text}</code><span><?php fc_e( 'set_text_color' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_text_light}</code><span><?php fc_e( 'set_text_color_light' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_bg}</code><span><?php fc_e( 'set_background_color' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_surface}</code><span><?php fc_e( 'set_surface_color_cards' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_border}</code><span><?php fc_e( 'set_border_color' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_footer_bg}</code><span><?php fc_e( 'set_footer_background_color' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_font}</code><span><?php fc_e( 'set_theme_font' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_btn_radius}</code><span><?php fc_e( 'set_button_border_radius_e_g_8px' ); ?></span></div>
                        <div class="fc-ev"><code>{theme_card_radius}</code><span><?php fc_e( 'set_card_border_radius_e_g_12px' ); ?></span></div>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-bottom:16px;width:100%;box-sizing:border-box;">
                <label for="fc-email-preview-order" style="font-weight:600;white-space:nowrap;">
                    <span class="dashicons dashicons-visibility" style="vertical-align:middle;margin-right:2px;"></span>
                    <?php fc_e( 'set_preview_based_on' ); ?>
                </label>
                <select id="fc-email-preview-order" style="min-width:220px;">
                    <?php if ( empty( $recent_orders ) ) : ?>
                        <option value="0"><?php fc_e( 'set_no_orders_sample_data' ); ?></option>
                    <?php else : foreach ( $recent_orders as $oid ) :
                        $o_number = get_post_meta( $oid, '_fc_order_number', true );
                        $o_customer = get_post_meta( $oid, '_fc_customer', true );
                        $o_name = '';
                        if ( is_array( $o_customer ) ) {
                            $o_type = $o_customer['account_type'] ?? 'private';
                            if ( $o_type === 'company' && ! empty( $o_customer['company'] ) ) {
                                $o_name = $o_customer['company'];
                            } else {
                                $o_name = trim( ( $o_customer['first_name'] ?? '' ) . ' ' . ( $o_customer['last_name'] ?? '' ) );
                            }
                        }
                        $o_total = fc_format_price( get_post_meta( $oid, '_fc_order_total', true ) );
                    ?>
                        <option value="<?php echo $oid; ?>" <?php selected( $oid, $selected_order_id ); ?>>
                            <?php echo esc_html( $o_number . ' — ' . $o_name . ' — ' . $o_total ); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
                <span id="fc-email-preview-loading" class="spinner" style="float:none;margin:0;"></span>
            </div>

            <?php
            $saved_header = isset( $templates['_header'] ) && $templates['_header'] !== '' ? $templates['_header'] : ( $defaults['_header'] ?? '' );
            $saved_footer = isset( $templates['_footer'] ) && $templates['_footer'] !== '' ? $templates['_footer'] : ( $defaults['_footer'] ?? '' );
            ?>

            <!-- Header e-mail -->
            <div class="fc-email-block" data-status="_header">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#e8f0fe;border:1px solid #a8c7fa;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-arrow-up-alt" style="color:#2271b1;" aria-hidden="true"></span>
                        <strong><?php fc_e( 'set_header_shared' ); ?></strong>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon" aria-hidden="true"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #a8c7fa;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <p class="description" style="margin:0;"><?php fc_e( 'set_header_html_inserted_by_header_you_can_use_variabl' ); ?></p>
                        <button type="button" class="button fc-reset-template-btn" data-template="_header" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>
                    <div style="display:flex;gap:16px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <textarea name="fc_email_header" class="fc-email-editor fc-email-hf-editor" id="fc_email_header" rows="12" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $saved_header ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <iframe class="fc-email-hf-preview" data-hf="header" style="width:100%;height:300px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer e-mail -->
            <div class="fc-email-block" data-status="_footer">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#e8f0fe;border:1px solid #a8c7fa;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-arrow-down-alt" style="color:#2271b1;" aria-hidden="true"></span>
                        <strong><?php fc_e( 'set_footer_shared' ); ?></strong>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon" aria-hidden="true"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #a8c7fa;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <p class="description" style="margin:0;"><?php fc_e( 'set_footer_html_inserted_by_footer_you_can_use_variabl' ); ?></p>
                        <button type="button" class="button fc-reset-template-btn" data-template="_footer" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>
                    <div style="display:flex;gap:16px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <textarea name="fc_email_footer" class="fc-email-editor fc-email-hf-editor" id="fc_email_footer" rows="8" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $saved_footer ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <iframe class="fc-email-hf-preview" data-hf="footer" style="width:100%;height:200px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ( $statuses as $status_key => $status_label ) :
                $tpl = isset( $templates[ $status_key ] ) ? $templates[ $status_key ] : array();
                $enabled = isset( $tpl['enabled'] ) ? $tpl['enabled'] : ( isset( $defaults[ $status_key ] ) ? 1 : 0 );
                $subject = ! empty( $tpl['subject'] ) ? $tpl['subject'] : ( $defaults[ $status_key ]['subject'] ?? '' );
                $body    = isset( $tpl['body'] ) && $tpl['body'] !== '' ? $tpl['body'] : ( $defaults[ $status_key ]['body'] ?? '' );

                $preview_data['{status}'] = $status_label;
            ?>
            <div class="fc-email-block" data-status="<?php echo esc_attr( $status_key ); ?>">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-email" style="color:#2271b1;" aria-hidden="true"></span>
                        <strong><?php echo esc_html( $status_label ); ?></strong>
                        <label style="margin-left:12px;font-weight:normal;cursor:pointer;" onclick="event.stopPropagation();">
                            <input type="checkbox" name="fc_email_enabled[<?php echo esc_attr( $status_key ); ?>]" value="1" <?php checked( $enabled, 1 ); ?>>
                            <?php fc_e( 'coupon_active' ); ?>
                        </label>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #c3c4c7;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <table class="form-table" style="margin:0;flex:1;">
                            <tr>
                                <th style="width:120px;padding:8px 10px 8px 0;"><?php fc_e( 'set_subject' ); ?></th>
                                <td><input type="text" name="fc_email_subject[<?php echo esc_attr( $status_key ); ?>]" value="<?php echo esc_attr( $subject ); ?>" class="large-text fc-email-subject" style="width:100%;"></td>
                            </tr>
                        </table>
                        <button type="button" class="button fc-reset-template-btn" data-template="<?php echo esc_attr( $status_key ); ?>" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>" style="margin-left:12px;white-space:nowrap;align-self:center;"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>

                    <div style="display:flex;gap:16px;margin-top:12px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_html_template' ); ?></label>
                            <textarea name="fc_email_body[<?php echo esc_attr( $status_key ); ?>]" class="fc-email-editor" rows="20" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $body ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_preview' ); ?></label>
                            <iframe class="fc-email-preview" style="width:100%;height:480px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Szablon resetowania hasła -->
            <?php
                $pw_tpl = isset( $templates['password_reset'] ) ? $templates['password_reset'] : array();
                $pw_enabled = isset( $pw_tpl['enabled'] ) ? $pw_tpl['enabled'] : 1;
                $pw_subject = ! empty( $pw_tpl['subject'] ) ? $pw_tpl['subject'] : ( $defaults['password_reset']['subject'] ?? '' );
                $pw_body    = isset( $pw_tpl['body'] ) && $pw_tpl['body'] !== '' ? $pw_tpl['body'] : ( $defaults['password_reset']['body'] ?? '' );
            ?>
            <div class="fc-email-block" data-status="password_reset">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#fef3e8;border:1px solid #f0c8a0;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-lock" style="color:#d63638;" aria-hidden="true"></span>
                        <strong><?php fc_e( 'set_password_reset' ); ?></strong>
                        <label style="margin-left:12px;font-weight:normal;cursor:pointer;" onclick="event.stopPropagation();">
                            <input type="checkbox" name="fc_email_enabled[password_reset]" value="1" <?php checked( $pw_enabled, 1 ); ?>>
                            <?php fc_e( 'coupon_active' ); ?>
                        </label>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #f0c8a0;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                        <p class="description" style="margin:0;">
                            <?php fc_e( 'set_available_variables_first_name_user_login_email_re' ); ?>
                        </p>
                        <button type="button" class="button fc-reset-template-btn" data-template="password_reset" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>" style="white-space:nowrap;margin-left:12px;"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:120px;padding:8px 10px 8px 0;"><?php fc_e( 'set_subject' ); ?></th>
                            <td><input type="text" name="fc_email_subject[password_reset]" value="<?php echo esc_attr( $pw_subject ); ?>" class="large-text" style="width:100%;"></td>
                        </tr>
                    </table>
                    <div style="display:flex;gap:16px;margin-top:12px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_html_template' ); ?></label>
                            <textarea name="fc_email_body[password_reset]" class="fc-email-editor" rows="16" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $pw_body ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_preview' ); ?></label>
                            <iframe class="fc-email-preview" style="width:100%;height:380px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Szablon aktywacji konta -->
            <?php
                $ac_tpl = isset( $templates['account_activation'] ) ? $templates['account_activation'] : array();
                $ac_enabled = isset( $ac_tpl['enabled'] ) ? $ac_tpl['enabled'] : 1;
                $ac_subject = ! empty( $ac_tpl['subject'] ) ? $ac_tpl['subject'] : ( $defaults['account_activation']['subject'] ?? '' );
                $ac_body    = isset( $ac_tpl['body'] ) && $ac_tpl['body'] !== '' ? $ac_tpl['body'] : ( $defaults['account_activation']['body'] ?? '' );
            ?>
            <div class="fc-email-block" data-status="account_activation">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#e8f8e8;border:1px solid #a0d4a0;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-shield" style="color:#00a32a;" aria-hidden="true"></span>
                        <strong><?php fc_e( 'set_account_activation' ); ?></strong>
                        <label style="margin-left:12px;font-weight:normal;cursor:pointer;" onclick="event.stopPropagation();">
                            <input type="checkbox" name="fc_email_enabled[account_activation]" value="1" <?php checked( $ac_enabled, 1 ); ?>>
                            <?php fc_e( 'coupon_active' ); ?>
                        </label>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #a0d4a0;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                        <p class="description" style="margin:0;">
                            <?php fc_e( 'set_available_variables_first_name_user_login_email_ac' ); ?>
                        </p>
                        <button type="button" class="button fc-reset-template-btn" data-template="account_activation" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>" style="white-space:nowrap;margin-left:12px;"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:120px;padding:8px 10px 8px 0;"><?php fc_e( 'set_subject' ); ?></th>
                            <td><input type="text" name="fc_email_subject[account_activation]" value="<?php echo esc_attr( $ac_subject ); ?>" class="large-text" style="width:100%;"></td>
                        </tr>
                    </table>
                    <div style="display:flex;gap:16px;margin-top:12px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_html_template' ); ?></label>
                            <textarea name="fc_email_body[account_activation]" class="fc-email-editor" rows="16" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $ac_body ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_preview' ); ?></label>
                            <iframe class="fc-email-preview" style="width:100%;height:380px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Szablon nowego zamówienia do admina -->
            <?php
                $na_tpl = isset( $templates['new_order_admin'] ) ? $templates['new_order_admin'] : array();
                $na_enabled = isset( $na_tpl['enabled'] ) ? $na_tpl['enabled'] : 1;
                $na_subject = ! empty( $na_tpl['subject'] ) ? $na_tpl['subject'] : ( $defaults['new_order_admin']['subject'] ?? '' );
                $na_body    = isset( $na_tpl['body'] ) && $na_tpl['body'] !== '' ? $na_tpl['body'] : ( $defaults['new_order_admin']['body'] ?? '' );
            ?>
            <div class="fc-email-block" data-status="new_order_admin">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#fff8e1;border:1px solid #f0d060;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-store" style="color:#d4a017;" aria-hidden="true"></span>
                        <strong><?php fc_e( 'set_new_order_admin' ); ?></strong>
                        <label style="margin-left:12px;font-weight:normal;cursor:pointer;" onclick="event.stopPropagation();">
                            <input type="checkbox" name="fc_email_enabled[new_order_admin]" value="1" <?php checked( $na_enabled, 1 ); ?>>
                            <?php fc_e( 'coupon_active' ); ?>
                        </label>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #f0d060;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                        <p class="description" style="margin:0;">
                            <?php fc_e( 'set_notification_sent_to_the_store_admin_for_a_new_ord' ); ?>
                        </p>
                        <button type="button" class="button fc-reset-template-btn" data-template="new_order_admin" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>" style="white-space:nowrap;margin-left:12px;"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:120px;padding:8px 10px 8px 0;"><?php fc_e( 'set_subject' ); ?></th>
                            <td><input type="text" name="fc_email_subject[new_order_admin]" value="<?php echo esc_attr( $na_subject ); ?>" class="large-text fc-email-subject" style="width:100%;"></td>
                        </tr>
                    </table>
                    <div style="display:flex;gap:16px;margin-top:12px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_html_template' ); ?></label>
                            <textarea name="fc_email_body[new_order_admin]" class="fc-email-editor" rows="20" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $na_body ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_preview' ); ?></label>
                            <iframe class="fc-email-preview" style="width:100%;height:480px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Szablon powiadomienia o dostępności -->
            <?php
                $sn_tpl = isset( $templates['stock_notify'] ) ? $templates['stock_notify'] : array();
                $sn_enabled = isset( $sn_tpl['enabled'] ) ? $sn_tpl['enabled'] : 1;
                $sn_subject = ! empty( $sn_tpl['subject'] ) ? $sn_tpl['subject'] : ( $defaults['stock_notify']['subject'] ?? '' );
                $sn_body    = isset( $sn_tpl['body'] ) && $sn_tpl['body'] !== '' ? $sn_tpl['body'] : ( $defaults['stock_notify']['body'] ?? '' );
            ?>
            <div class="fc-email-block" data-status="stock_notify">
                <div class="fc-email-block-header" role="button" tabindex="0" aria-expanded="false" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:4px;cursor:pointer;margin-bottom:0;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="dashicons dashicons-bell" style="color:#2e7d32;" aria-hidden="true"></span>
                        <strong><?php fc_e( 'set_availability_notification' ); ?></strong>
                        <label style="margin-left:12px;font-weight:normal;cursor:pointer;" onclick="event.stopPropagation();">
                            <input type="checkbox" name="fc_email_enabled[stock_notify]" value="1" <?php checked( $sn_enabled, 1 ); ?>>
                            <?php fc_e( 'coupon_active' ); ?>
                        </label>
                    </div>
                    <span class="dashicons dashicons-arrow-down-alt2 fc-email-toggle-icon"></span>
                </div>
                <div class="fc-email-block-body" style="display:none;border:1px solid #a5d6a7;border-top:none;border-radius:0 0 4px 4px;padding:16px;background:#fff;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                        <p class="description" style="margin:0;">
                            <?php fc_e( 'set_sent_to_customers_who_signed_up_for_notification_w' ); ?>
                        </p>
                        <button type="button" class="button fc-reset-template-btn" data-template="stock_notify" title="<?php echo esc_attr( fc__( 'set_restore_default_template' ) ); ?>" style="white-space:nowrap;margin-left:12px;"><span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset' ); ?></button>
                    </div>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:120px;padding:8px 10px 8px 0;"><?php fc_e( 'set_subject' ); ?></th>
                            <td><input type="text" name="fc_email_subject[stock_notify]" value="<?php echo esc_attr( $sn_subject ); ?>" class="large-text" style="width:100%;"></td>
                        </tr>
                    </table>
                    <div style="display:flex;gap:16px;margin-top:12px;">
                        <div style="flex:0 0 40%;min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_html_template' ); ?></label>
                            <textarea name="fc_email_body[stock_notify]" class="fc-email-editor" rows="16" style="width:100%;font-family:monospace;font-size:13px;tab-size:2;resize:vertical;"><?php echo esc_textarea( $sn_body ); ?></textarea>
                        </div>
                        <div style="flex:0 0 calc(60% - 16px);min-width:0;">
                            <label style="display:block;margin-bottom:6px;font-weight:600;"><?php fc_e( 'set_preview' ); ?></label>
                            <iframe class="fc-email-preview" style="width:100%;height:380px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;margin-top:20px;">
                <?php submit_button( fc__( 'set_save_templates' ), 'primary', 'fc_save_emails', false ); ?>
                <button type="button" class="button" id="fc-reset-all-templates" style="color:#d63638;border-color:#d63638;">
                    <span class="dashicons dashicons-image-rotate" style="vertical-align:middle;margin-right:4px;"></span><?php fc_e( 'set_reset_all_templates' ); ?>
                </button>
            </div>
        </form>

        <script>
        jQuery(function($){
            // Preview data (z prawdziwego zamówienia)
            var previewData = <?php echo json_encode( array_merge( $preview_data, array(
                '{user_login}'      => 'jan.kowalski',
                '{reset_link}'      => '#',
                '{activation_code}' => '482917',
                '{product_name}'    => fc__( 'set_sample_product' ),
                '{product_url}'     => home_url( '/produkt/przykladowy-produkt/' ),
                '{payment_url}'     => get_permalink( get_option( 'fc_page_platnosc_nieudana' ) ) ?: get_permalink( get_option( 'fc_page_podziekowanie' ) ),
            ) ), JSON_UNESCAPED_UNICODE ); ?>;
            var statuses = <?php echo json_encode( $statuses, JSON_UNESCAPED_UNICODE ); ?>;
            var emailDefaults = <?php echo json_encode( $defaults, JSON_UNESCAPED_UNICODE ); ?>;

            // Zmiana zamówienia do podglądu
            $('#fc-email-preview-order').on('change', function() {
                var orderId = $(this).val();
                var $spinner = $('#fc-email-preview-loading');
                $spinner.addClass('is-active');
                $.post(ajaxurl, {
                    action: 'fc_get_order_preview_data',
                    order_id: orderId,
                    email_lang: '<?php echo esc_js( $edit_lang ); ?>',
                    _wpnonce: '<?php echo wp_create_nonce( 'fc_email_preview' ); ?>'
                }, function(response) {
                    $spinner.removeClass('is-active');
                    if (response.success) {
                        previewData = response.data;
                        // Odśwież wszystkie otwarte podglądy
                        $('.fc-email-editor').each(function() {
                            var $block = $(this).closest('.fc-email-block');
                            if ($block.find('.fc-email-block-body').is(':visible')) {
                                updatePreview($(this));
                            }
                        });
                    }
                });
            });

            // Toggle sections (click + keyboard Enter/Space)
            function fcToggleEmailBlock(el) {
                var $header = $(el);
                var $body = $header.next('.fc-email-block-body');
                var $icon = $header.find('.fc-email-toggle-icon');
                $body.slideToggle(200);
                $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                var isExpanding = !$body.is(':visible');
                $header.attr('aria-expanded', isExpanding ? 'true' : 'false');
                if (isExpanding) {
                    setTimeout(function(){ $body.find('.fc-email-editor').trigger('input'); }, 250);
                }
            }
            $(document).on('click', '.fc-email-block-header', function() {
                fcToggleEmailBlock(this);
            });
            $(document).on('keydown', '.fc-email-block-header', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fcToggleEmailBlock(this);
                }
            });

            // Resolve header/footer helper
            function getHeaderFooterHtml(which) {
                var raw = which === 'header' ? $('#fc_email_header').val() : $('#fc_email_footer').val();
                var localData = $.extend({}, previewData);
                $.each(localData, function(key, val) {
                    raw = raw.split(key).join(val);
                });
                return raw;
            }

            // Header/footer preview
            function updateHfPreview($editor) {
                var html = $editor.val();
                var localData = $.extend({}, previewData);
                $.each(localData, function(key, val) {
                    html = html.split(key).join(val);
                });
                var $iframe = $editor.closest('.fc-email-block-body').find('.fc-email-hf-preview');
                if ($iframe.length) {
                    var doc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
                    doc.open(); doc.write(html); doc.close();
                }
            }

            // Live preview
            function updatePreview($editor) {
                var html = $editor.val();
                var statusKey = $editor.closest('.fc-email-block').data('status');

                // Header/footer editors — update their own previews
                if (statusKey === '_header' || statusKey === '_footer') {
                    updateHfPreview($editor);
                    // Also refresh all open status previews
                    $('.fc-email-block').each(function(){
                        var sk = $(this).data('status');
                        if (sk !== '_header' && sk !== '_footer' && $(this).find('.fc-email-block-body').is(':visible')) {
                            updatePreview($(this).find('.fc-email-editor'));
                        }
                    });
                    return;
                }

                // Resolve {header} and {footer} first
                html = html.split('{header}').join(getHeaderFooterHtml('header'));
                html = html.split('{footer}').join(getHeaderFooterHtml('footer'));

                var localData = $.extend({}, previewData);
                localData['{status}'] = statuses[statusKey] || statusKey;
                $.each(localData, function(key, val) {
                    html = html.split(key).join(val);
                });
                var $iframe = $editor.closest('.fc-email-block-body').find('.fc-email-preview');
                var doc = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
                doc.open();
                doc.write(html);
                doc.close();
            }

            // Bind editor input
            $(document).on('input', '.fc-email-editor', function() {
                updatePreview($(this));
            });

            // Tab key in textarea
            $(document).on('keydown', '.fc-email-editor', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    var start = this.selectionStart;
                    var end = this.selectionEnd;
                    var value = $(this).val();
                    $(this).val(value.substring(0, start) + '  ' + value.substring(end));
                    this.selectionStart = this.selectionEnd = start + 2;
                    $(this).trigger('input');
                }
            });

            // Init previews for first opened
            $('.fc-email-editor').each(function() {
                var $editor = $(this);
                // Delay to allow iframe to be ready
                setTimeout(function(){ updatePreview($editor); }, 100);
            });

            // Init header/footer previews
            $('.fc-email-hf-editor').each(function() {
                var $editor = $(this);
                setTimeout(function(){ updateHfPreview($editor); }, 100);
            });

            // ──── Reset pojedynczego szablonu ────
            $(document).on('click', '.fc-reset-template-btn', function(e) {
                e.preventDefault();
                var key = $(this).data('template');
                var label = key;
                if (!confirm('<?php echo esc_js( fc__( 'set_confirm_reset_template' ) ); ?>')) return;

                var def = emailDefaults[key];
                var $block = $(this).closest('.fc-email-block');

                if (key === '_header' || key === '_footer') {
                    // Header/footer: just a textarea
                    $block.find('.fc-email-editor').val(def).trigger('input');
                } else {
                    // Status/special templates: subject + body
                    if (def && def.subject !== undefined) {
                        $block.find('input[name*="fc_email_subject"]').val(def.subject);
                    }
                    if (def && def.body !== undefined) {
                        $block.find('.fc-email-editor').val(def.body).trigger('input');
                    }
                }
            });

            // ──── Reset wszystkich szablonów ────
            $('#fc-reset-all-templates').on('click', function(e) {
                e.preventDefault();
                if (!confirm('<?php echo esc_js( fc__( 'set_confirm_reset_all_templates' ) ); ?>')) return;

                $.each(emailDefaults, function(key, def) {
                    var $block = $('.fc-email-block[data-status="' + key + '"]');
                    if (!$block.length) return;

                    if (key === '_header' || key === '_footer') {
                        $block.find('.fc-email-editor').val(def);
                    } else {
                        if (def.subject !== undefined) {
                            $block.find('input[name*="fc_email_subject"]').val(def.subject);
                        }
                        if (def.body !== undefined) {
                            $block.find('.fc-email-editor').val(def.body);
                        }
                    }
                });

                // Odśwież podglądy
                $('.fc-email-editor').trigger('input');
                $('.fc-email-hf-editor').each(function() { updateHfPreview($(this)); });
            });
        });
        </script>
        <?php
    }

    /**
     * Migracja starych szablonów e-mail: zamień hardcodowane kolory na {theme_*} placeholdery.
     * Uruchamiana jednorazowo — po migracji zapisuje wynik w bazie.
     */
    public static function maybe_migrate_email_templates( $templates, $lang = 'pl' ) {
        if ( empty( $templates ) || ! is_array( $templates ) ) return $templates;

        $changed = false;

        // Migracja: dodaj {coupon_rows} do szablonów bez nich
        $needs_coupon = false;
        foreach ( $templates as $key => $tpl ) {
            if ( $key === '_header' || $key === '_footer' ) continue;
            if ( is_array( $tpl ) && ! empty( $tpl['body'] ) && strpos( $tpl['body'], '{shipping_cost}' ) !== false && strpos( $tpl['body'], '{coupon_rows}' ) === false ) {
                $needs_coupon = true;
                break;
            }
        }
        if ( $needs_coupon ) {
            foreach ( $templates as $key => &$tpl ) {
                if ( $key === '_header' || $key === '_footer' ) continue;
                if ( is_array( $tpl ) && ! empty( $tpl['body'] ) && strpos( $tpl['body'], '{shipping_cost}' ) !== false && strpos( $tpl['body'], '{coupon_rows}' ) === false ) {
                    // Obsłuż zarówno stary placeholder jak i zwykły tekst
                    $tpl['body'] = str_replace(
                        array(
                            '<tr><td>Wysyłka</td><td>{shipping_cost}</td></tr>',
                            '<tr><td>{shipping_label}</td><td>{shipping_cost}</td></tr>',
                        ),
                        array(
                            '{coupon_rows}<tr><td>Wysyłka</td><td>{shipping_cost}</td></tr>',
                            '{coupon_rows}<tr><td>{shipping_label}</td><td>{shipping_cost}</td></tr>',
                        ),
                        $tpl['body']
                    );
                }
            }
            unset( $tpl );
            $changed = true;
        }

        // Sprawdź czy nagłówek wymaga migracji (test na hardcodowane #2271b1 bez {theme_accent})
        $header = $templates['_header'] ?? '';
        $needs = ( ! empty( $header ) && strpos( $header, '#2271b1' ) !== false && strpos( $header, '{theme_accent}' ) === false );

        if ( $needs ) {
        $color_map = array(
            'background: #2271b1'    => 'background: {theme_accent}',
            'background:#2271b1'     => 'background:{theme_accent}',
            'color: #2271b1'         => 'color: {theme_accent}',
            'color:#2271b1'          => 'color:{theme_accent}',
            'border-bottom: 1px solid #eee' => 'border-bottom: 1px solid {theme_border}',
            'border-bottom:1px solid #eee'  => 'border-bottom:1px solid {theme_border}',
            'border: 1px solid #ddd'        => 'border: 1px solid {theme_border}',
            'border:1px solid #ddd'         => 'border:1px solid {theme_border}',
            'background: #f0f0f1'    => 'background: {theme_footer_bg}',
            'background:#f0f0f1'     => 'background:{theme_footer_bg}',
            'background: #f5f5f5'    => 'background: {theme_bg}',
            'background:#f5f5f5'     => 'background:{theme_bg}',
            'background: #ffffff'    => 'background: {theme_surface}',
            'background:#ffffff'     => 'background:{theme_surface}',
            'background: #f8f8f8'    => 'background: {theme_accent_light}',
            'background:#f8f8f8'     => 'background:{theme_accent_light}',
            'color: #333'            => 'color: {theme_text}',
            'color:#333'             => 'color:{theme_text}',
            'color: #555'            => 'color: {theme_text_light}',
            'color:#555'             => 'color:{theme_text_light}',
            'color: #888'            => 'color: {theme_text_light}',
            'color:#888'             => 'color:{theme_text_light}',
            'color: #999'            => 'color: {theme_text_light}',
            'color:#999'             => 'color:{theme_text_light}',
            'color: #666'            => 'color: {theme_text_light}',
            'color:#666'             => 'color:{theme_text_light}',
            'background:#f0f6fc'     => 'background:{theme_accent_light}',
            'background: #f0f6fc'    => 'background: {theme_accent_light}',
            'border:2px dashed #2271b1' => 'border:2px dashed {theme_accent}',
            'border: 2px dashed #2271b1' => 'border: 2px dashed {theme_accent}',
            'color:#1d2327'          => 'color:{theme_text}',
            'color: #1d2327'         => 'color: {theme_text}',
        );
        $font_old = 'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        $font_new = 'font-family: {theme_font}';

        // Migruj _header i _footer
        foreach ( array( '_header', '_footer' ) as $key ) {
            if ( ! empty( $templates[ $key ] ) ) {
                $templates[ $key ] = str_replace( $font_old, $font_new, $templates[ $key ] );
                $templates[ $key ] = str_replace( array_keys( $color_map ), array_values( $color_map ), $templates[ $key ] );
            }
        }

        // Migruj body szablonów ze specjalnymi stylami inline
        $status_keys = array_keys( $templates );
        foreach ( $status_keys as $key ) {
            if ( $key === '_header' || $key === '_footer' ) continue;
            if ( ! is_array( $templates[ $key ] ) || empty( $templates[ $key ]['body'] ) ) continue;
            $templates[ $key ]['body'] = str_replace( array_keys( $color_map ), array_values( $color_map ), $templates[ $key ]['body'] );
        }
        } // end if $needs

        // Migracja: stare placeholdery i18n (_label, _heading) → zwykły tekst
        $old_placeholders_found = false;
        foreach ( $templates as $key => $tpl ) {
            if ( $key === '_header' || $key === '_footer' ) continue;
            if ( is_array( $tpl ) && ! empty( $tpl['body'] ) ) {
                if ( strpos( $tpl['body'], '{order_number_label}' ) !== false || strpos( $tpl['body'], '{products_heading}' ) !== false || strpos( $tpl['body'], '{date_label}' ) !== false || strpos( $tpl['body'], '{customer_data_heading}' ) !== false ) {
                    $old_placeholders_found = true;
                    break;
                }
            }
        }
        if ( $old_placeholders_found ) {
            // Mapa starych placeholderów i18n → zwykły tekst
            $i18n_map = array(
                '{order_number_label}'     => 'Numer zamówienia',
                '{date_label}'             => 'Data',
                '{payment_method_label}'   => 'Metoda płatności',
                '{shipping_method_label}'  => 'Metoda wysyłki',
                '{products_heading}'       => 'Produkty',
                '{order_details_heading}'  => 'Szczegóły zamówienia',
                '{subtotal_label}'         => 'Suma częściowa',
                '{shipping_label}'         => 'Wysyłka',
                '{total_label}'            => 'Razem',
                '{customer_heading}'       => 'Dane klienta',
                '{customer_data_heading}'  => 'Dane klienta',
                '{customer_label}'         => 'Klient',
                '{email_label}'            => 'E-mail',
                '{phone_label}'            => 'Telefon',
                '{billing_address_label}'  => 'Adres do faktury',
                '{shipping_address_label}' => 'Adres dostawy',
                '{product_column}'         => 'Produkt',
                '{quantity_column}'        => 'Ilość',
                '{sum_column}'             => 'Suma',
                '{tax_no_label}'           => 'NIP',
                '{crn_label}'              => 'Nr rejestrowy',
            );

            foreach ( $templates as $key => &$tpl ) {
                if ( $key === '_header' || $key === '_footer' ) continue;
                if ( is_array( $tpl ) && ! empty( $tpl['body'] ) ) {
                    $tpl['body'] = str_replace( array_keys( $i18n_map ), array_values( $i18n_map ), $tpl['body'] );
                }
                if ( is_array( $tpl ) && ! empty( $tpl['subject'] ) ) {
                    $tpl['subject'] = str_replace( array_keys( $i18n_map ), array_values( $i18n_map ), $tpl['subject'] );
                }
            }
            unset( $tpl );
            $changed = true;
        }

        if ( $changed ) {
            update_option( self::get_email_option_key( $lang ), $templates );
        }
        return $templates;
    }

    /**
     * Pobierz kolory, czcionki i zaokrąglenia motywu do e-maili i faktur.
     * Odczytuje Customizer motywu Flavor (jeśli aktywny), inaczej zwraca domyślne wartości.
     */
    public static function get_email_theme_colors() {
        $t = array(
            'accent'       => '#2271b1',
            'accent_hover' => '#135e96',
            'accent_light' => '#f0f6fc',
            'text'         => '#333333',
            'text_light'   => '#888888',
            'bg'           => '#f5f5f5',
            'surface'      => '#ffffff',
            'border'       => '#dddddd',
            'footer_bg'    => '#f0f0f1',
            'font'         => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'btn_radius'   => 6,
            'card_radius'  => 12,
        );

        if ( ! function_exists( 'get_theme_mod' ) ) return $t;

        $accent = get_theme_mod( 'flavor_color_accent' );
        if ( ! $accent ) return $t;

        $mode = get_theme_mod( 'flavor_color_mode', 'light' );

        $auto = function_exists( 'flavor_generate_palette' )
            ? flavor_generate_palette( $accent, $mode )
            : array();

        $map = array(
            'accent'       => 'flavor_color_accent',
            'accent_hover' => 'flavor_color_accent_hover',
            'accent_light' => 'flavor_color_accent_light',
            'text'         => 'flavor_color_text',
            'text_light'   => 'flavor_color_text_light',
            'bg'           => 'flavor_color_bg',
            'surface'      => 'flavor_color_surface',
            'border'       => 'flavor_color_border',
            'footer_bg'    => 'flavor_color_footer_bg',
        );

        foreach ( $map as $key => $setting_id ) {
            $v = get_theme_mod( $setting_id, $auto[ $key ] ?? null );
            if ( $v ) $t[ $key ] = $v;
        }

        $font_key = get_theme_mod( 'flavor_font_family', 'system' );
        if ( function_exists( 'flavor_get_font_stack' ) ) {
            $t['font'] = flavor_get_font_stack( $font_key );
        }

        $t['btn_radius']  = intval( get_theme_mod( 'flavor_shop_btn_radius', 8 ) );
        $t['card_radius'] = intval( get_theme_mod( 'flavor_shop_card_radius', 12 ) );

        return $t;
    }

    /**
     * Tablica placeholderów {theme_*} do użycia w str_replace na szablonach e-mail / faktur.
     */
    public static function get_theme_replacements() {
        $t = self::get_email_theme_colors();
        return array(
            '{theme_accent}'       => $t['accent'],
            '{theme_accent_hover}' => $t['accent_hover'],
            '{theme_accent_light}' => $t['accent_light'],
            '{theme_text}'         => $t['text'],
            '{theme_text_light}'   => $t['text_light'],
            '{theme_bg}'           => $t['bg'],
            '{theme_surface}'      => $t['surface'],
            '{theme_border}'       => $t['border'],
            '{theme_footer_bg}'    => $t['footer_bg'],
            '{theme_font}'         => $t['font'],
            '{theme_btn_radius}'   => $t['btn_radius'] . 'px',
            '{theme_card_radius}'  => $t['card_radius'] . 'px',
        );
    }

    /**
     * HTML loga sklepu do e-maili i faktur — pobiera logo z ustawień motywu (Wygląd → Dostosuj → Logo)
     * SVG jest osadzane inline (Dompdf dobrze wspiera inline SVG)
     */
    public static function get_logo_html() {
        $logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
        if ( ! $logo_id ) return '';

        $mime = get_post_mime_type( $logo_id );
        $is_svg = ( $mime === 'image/svg+xml' );

        if ( $is_svg ) {
            $svg_path = get_attached_file( $logo_id );
            if ( ! $svg_path || ! file_exists( $svg_path ) ) return '';

            $svg_content = file_get_contents( $svg_path );
            if ( ! $svg_content ) return '';

            // Usuń deklarację XML i komentarze — zostawiamy czysty <svg>
            $svg_content = preg_replace( '/<\?xml[^?]*\?>/', '', $svg_content );
            $svg_content = preg_replace( '/<!--.*?-->/s', '', $svg_content );
            $svg_content = trim( $svg_content );

            // Oblicz proporcjonalną wysokość na podstawie viewBox
            $width = 200;
            $height = 60;
            if ( preg_match( '/viewBox=["\'][\s]*([\d.]+)[\s]+([\d.]+)[\s]+([\d.]+)[\s]+([\d.]+)/', $svg_content, $vb ) ) {
                $vb_w = floatval( $vb[3] );
                $vb_h = floatval( $vb[4] );
                if ( $vb_w > 0 && $vb_h > 0 ) {
                    $height = intval( $width * $vb_h / $vb_w );
                    if ( $height > 80 ) {
                        $height = 80;
                        $width = intval( 80 * $vb_w / $vb_h );
                    }
                }
            }

            // Usuń istniejące width/height z tagu <svg> i ustaw nowe
            $svg_content = preg_replace( '/(<svg\b[^>]*?)\s+width\s*=\s*"[^"]*"/', '$1', $svg_content );
            $svg_content = preg_replace( '/(<svg\b[^>]*?)\s+height\s*=\s*"[^"]*"/', '$1', $svg_content );
            $svg_content = preg_replace( '/<svg\b/', '<svg width="' . $width . '" height="' . $height . '"', $svg_content, 1 );

            return '<div style="max-width:200px;max-height:80px;display:block;margin:0 auto 12px;">' . $svg_content . '</div>';
        }

        $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
        if ( ! $logo_url ) return '';
        return '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_option( 'fc_store_name', get_bloginfo( 'name' ) ) ) . '" style="max-width:200px;max-height:80px;display:block;margin:0 auto 12px;">';
    }

    /**
     * Pobierz dane zamówienia do podglądu e-maila
     */
    public static function get_order_preview_data( $order_id, $lang = null ) {
        if ( $lang === null ) $lang = get_option( 'fc_frontend_lang', 'pl' );
        $et = self::get_email_texts( $lang );
        $store_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );

        if ( ! $order_id || get_post_type( $order_id ) !== 'fc_order' ) {
            // Dane przykładowe
            $tc = self::get_email_theme_colors();
            $logo_html = self::get_logo_html();
            $sample_product = fc__( 'set_sample_product' );
            $sample_company = $lang === 'en' ? 'Example Company Ltd. (John Smith)' : 'Firma Przykładowa Sp. z o.o. (Jan Kowalski)';
            $sample_first = $lang === 'en' ? 'John' : 'Jan';
            $sample_last = $lang === 'en' ? 'Smith' : 'Kowalski';
            $sample_billing = $lang === 'en' ? '1 Example Street<br>00-001 Warsaw, PL' : 'ul. Przykładowa 1<br>00-001 Warszawa, PL';
            $sample_shipping = $lang === 'en' ? '5 Delivery Road<br>00-002 Warsaw, PL' : 'ul. Dostawcza 5<br>00-002 Warszawa, PL';
            return array_merge( array(
                '{order_number}'     => 'FC-ABC123',
                '{order_date}'       => date_i18n( 'j F Y, H:i' ),
                '{status}'           => $et['awaiting'],
                '{first_name}'       => $sample_first,
                '{last_name}'        => $sample_last,
                '{email}'            => 'jan@example.com',
                '{phone}'            => '+48 123 456 789',
                '{billing_address}'  => $sample_billing,
                '{shipping_address}' => $sample_shipping,
                '{payment_method}'   => $et['bank_transfer'],
                '{shipping_method}'  => fc__( 'set_sample_shipping_method' ),
                '{products}'         => '<table style="width:100%;border-collapse:collapse;font-size:14px;"><tr style="background:' . esc_attr( $tc['footer_bg'] ) . ';"><th style="padding:10px 12px;text-align:left;border:1px solid ' . esc_attr( $tc['border'] ) . ';width:60px;"></th><th style="padding:10px 12px;text-align:left;border:1px solid ' . esc_attr( $tc['border'] ) . ';">' . esc_html( $et['product_col'] ) . '</th><th style="padding:10px 12px;text-align:center;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">' . esc_html( $et['qty_col'] ) . '</th><th style="padding:10px 12px;text-align:right;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">' . esc_html( $et['sum_col'] ) . '</th></tr><tr><td style="padding:8px 12px;border:1px solid ' . esc_attr( $tc['border'] ) . ';"><img src="https://placehold.co/50x50/f0f0f1/999?text=IMG" style="width:50px;height:50px;object-fit:cover;border-radius:4px;display:block;"></td><td style="padding:10px 12px;border:1px solid ' . esc_attr( $tc['border'] ) . ';">' . esc_html( $sample_product ) . '</td><td style="padding:10px 12px;text-align:center;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">2</td><td style="padding:10px 12px;text-align:right;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">99,00 zł</td></tr></table>',
                '{subtotal}'         => '198,00 zł',
                '{coupon_rows}'      => '<tr style="color:#27ae60;"><td>' . esc_html( $et['coupon'] ) . ': <code>START10</code></td><td>−19,80 zł</td></tr>',
                '{shipping_cost}'    => '15,00 zł',
                '{total}'            => '193,20 zł',
                '{store_name}'       => $store_name,
                '{logo}'             => $logo_html,
                '{customer_name}'    => $sample_company,
                '{tax_no}'           => '1234567890',
                '{crn}'              => '0000012345',
                '{tax_no_row}'       => '<tr><td>' . esc_html( $et['nip'] ) . '</td><td>1234567890</td></tr>',
                '{crn_row}'          => '<tr><td>' . esc_html( $et['crn'] ) . '</td><td>0000012345</td></tr>',
            ), self::get_theme_replacements() );
        }

        return self::build_email_replacements( $order_id, null, $lang );
    }

    /**
     * Klucz opcji bazy danych dla szablonów e-mail danego języka.
     * PL używa oryginalnego klucza (kompatybilność wsteczna), inne języki mają sufiks.
     */
    public static function get_email_option_key( $lang = 'pl' ) {
        return $lang === 'pl' ? 'fc_email_templates' : 'fc_email_templates_' . $lang;
    }

    /**
     * Zwróć wszystkie teksty szablonów e-mail dla danego języka.
     */
    public static function get_email_texts( $lang = 'pl' ) {
        $texts = array(
            'pl' => array(
                'all_rights'              => 'Wszystkie prawa zastrzeżone.',
                'order_details'           => 'Szczegóły zamówienia',
                'order_number'            => 'Numer zamówienia',
                'date'                    => 'Data',
                'payment_method'          => 'Metoda płatności',
                'shipping_method'         => 'Metoda wysyłki',
                'products'                => 'Produkty',
                'shipping'                => 'Wysyłka',
                'total'                   => 'Razem',
                'customer_details'        => 'Dane klienta',
                'customer'                => 'Klient',
                'email'                   => 'E-mail',
                'phone'                   => 'Telefon',
                'billing_address'         => 'Adres do faktury',
                'shipping_address'        => 'Adres dostawy',
                'product_col'             => 'Produkt',
                'qty_col'                 => 'Ilość',
                'sum_col'                 => 'Suma',
                'coupon'                  => 'Kupon',
                'thank_you'               => 'Dziękujemy za zamówienie!',
                'hello'                   => 'Cześć',
                'order_accepted'          => 'Twoje zamówienie <strong>{order_number}</strong> zostało przyjęte i oczekuje na realizację.',
                'pending_subject'         => 'Zamówienie {order_number} — potwierdzenie złożenia',
                'pending_payment_subject' => 'Zamówienie {order_number} — oczekuje na płatność',
                'finish_payment'          => 'Dokończ płatność',
                'pending_payment_text'    => 'Twoje zamówienie <strong>{order_number}</strong> zostało złożone, ale płatność nie została jeszcze zrealizowana.',
                'pending_payment_cta'     => 'Aby dokończyć zakup, kliknij poniższy przycisk:',
                'pending_payment_note'    => 'Masz ograniczony czas na dokończenie płatności. Jeśli płatność nie zostanie zrealizowana, zamówienie zostanie automatycznie anulowane.',
                'processing_subject'      => 'Zamówienie {order_number} — w realizacji',
                'processing_heading'      => 'Zamówienie w realizacji',
                'processing_text'         => 'Twoje zamówienie <strong>{order_number}</strong> jest teraz w trakcie realizacji. Powiadomimy Cię, gdy zostanie wysłane.',
                'shipped_subject'         => 'Zamówienie {order_number} — wysłane!',
                'shipped_heading'         => 'Twoje zamówienie zostało wysłane!',
                'shipped_text'            => 'Zamówienie <strong>{order_number}</strong> zostało wysłane. Wkrótce powinno do Ciebie dotrzeć.',
                'completed_subject'       => 'Zamówienie {order_number} — zrealizowane',
                'completed_heading'       => 'Zamówienie zrealizowane',
                'completed_text'          => 'Twoje zamówienie <strong>{order_number}</strong> zostało zrealizowane. Dziękujemy za zakupy!',
                'cancelled_subject'       => 'Zamówienie {order_number} — anulowane',
                'cancelled_heading'       => 'Zamówienie anulowane',
                'cancelled_text'          => 'Informujemy, że zamówienie <strong>{order_number}</strong> zostało anulowane.',
                'refunded_subject'        => 'Zamówienie {order_number} — zwrot',
                'refunded_heading'        => 'Zwrot zamówienia',
                'refunded_text'           => 'Zamówienie <strong>{order_number}</strong> zostało oznaczone jako zwrócone. Zwrot środków zostanie przetworzony w ciągu kilku dni roboczych.',
                'password_reset_subject'  => 'Resetowanie hasła — {store_name}',
                'password_reset_heading'  => 'Resetowanie hasła',
                'password_reset_text'     => 'Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta w sklepie <strong>{store_name}</strong>.',
                'password_reset_cta'      => 'Kliknij poniższy przycisk, aby ustawić nowe hasło:',
                'password_reset_btn'      => 'Ustaw nowe hasło',
                'password_reset_note'     => 'Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość. Twoje obecne hasło pozostanie bez zmian.',
                'password_reset_expiry'   => 'Link jest ważny przez 24 godziny.',
                'activation_subject'      => 'Aktywacja konta — {store_name}',
                'activation_heading'      => 'Aktywacja konta',
                'activation_text'         => 'Dziękujemy za rejestrację w sklepie <strong>{store_name}</strong>!',
                'activation_cta'          => 'Aby aktywować swoje konto, wpisz poniższy kod na stronie aktywacji:',
                'activation_note'         => 'Jeśli nie rejestrowałeś konta, zignoruj tę wiadomość.',
                'activation_expiry'       => 'Kod jest ważny przez 24 godziny.',
                'new_order_admin_subject' => '[Nowe zamówienie] {order_number} — {store_name}',
                'new_order_admin_heading' => 'Nowe zamówienie!',
                'new_order_admin_text'    => 'Otrzymano nowe zamówienie <strong>{order_number}</strong> od klienta <strong>{customer_name}</strong> ({email}).',
                'stock_notify_subject'    => '{product_name} jest znów dostępny! — {store_name}',
                'stock_notify_heading'    => 'Produkt ponownie dostępny!',
                'stock_notify_hello'      => 'Cześć,',
                'stock_notify_text'       => 'Produkt, na który czekasz, jest ponownie dostępny w naszym sklepie:',
                'stock_notify_btn'        => 'Zobacz produkt',
                'stock_notify_note'       => 'Spiesz się — ilość może być ograniczona!',
                'nip'                     => 'NIP',
                'crn'                     => 'Nr rejestrowy',
                'bank_transfer'           => 'Przelew bankowy',
                'awaiting'                => 'Oczekuje',
            ),
            'en' => array(
                'all_rights'              => 'All rights reserved.',
                'order_details'           => 'Order details',
                'order_number'            => 'Order number',
                'date'                    => 'Date',
                'payment_method'          => 'Payment method',
                'shipping_method'         => 'Shipping method',
                'products'                => 'Products',
                'shipping'                => 'Shipping',
                'total'                   => 'Total',
                'customer_details'        => 'Customer details',
                'customer'                => 'Customer',
                'email'                   => 'Email',
                'phone'                   => 'Phone',
                'billing_address'         => 'Billing address',
                'shipping_address'        => 'Shipping address',
                'product_col'             => 'Product',
                'qty_col'                 => 'Qty',
                'sum_col'                 => 'Total',
                'coupon'                  => 'Coupon',
                'thank_you'               => 'Thank you for your order!',
                'hello'                   => 'Hi',
                'order_accepted'          => 'Your order <strong>{order_number}</strong> has been received and is awaiting processing.',
                'pending_subject'         => 'Order {order_number} — confirmation',
                'pending_payment_subject' => 'Order {order_number} — awaiting payment',
                'finish_payment'          => 'Complete payment',
                'pending_payment_text'    => 'Your order <strong>{order_number}</strong> has been placed, but payment has not yet been completed.',
                'pending_payment_cta'     => 'To complete your purchase, click the button below:',
                'pending_payment_note'    => 'You have a limited time to complete payment. If payment is not received, the order will be automatically cancelled.',
                'processing_subject'      => 'Order {order_number} — processing',
                'processing_heading'      => 'Order in progress',
                'processing_text'         => 'Your order <strong>{order_number}</strong> is now being processed. We will notify you when it has been shipped.',
                'shipped_subject'         => 'Order {order_number} — shipped!',
                'shipped_heading'         => 'Your order has been shipped!',
                'shipped_text'            => 'Order <strong>{order_number}</strong> has been shipped. It should arrive soon.',
                'completed_subject'       => 'Order {order_number} — completed',
                'completed_heading'       => 'Order completed',
                'completed_text'          => 'Your order <strong>{order_number}</strong> has been completed. Thank you for shopping with us!',
                'cancelled_subject'       => 'Order {order_number} — cancelled',
                'cancelled_heading'       => 'Order cancelled',
                'cancelled_text'          => 'We would like to inform you that order <strong>{order_number}</strong> has been cancelled.',
                'refunded_subject'        => 'Order {order_number} — refund',
                'refunded_heading'        => 'Order refunded',
                'refunded_text'           => 'Order <strong>{order_number}</strong> has been marked as refunded. The refund will be processed within a few business days.',
                'password_reset_subject'  => 'Password reset — {store_name}',
                'password_reset_heading'  => 'Password reset',
                'password_reset_text'     => 'We received a request to reset the password for your account at <strong>{store_name}</strong>.',
                'password_reset_cta'      => 'Click the button below to set a new password:',
                'password_reset_btn'      => 'Set new password',
                'password_reset_note'     => 'If you did not request a password reset, please ignore this message. Your current password will remain unchanged.',
                'password_reset_expiry'   => 'This link is valid for 24 hours.',
                'activation_subject'      => 'Account activation — {store_name}',
                'activation_heading'      => 'Account activation',
                'activation_text'         => 'Thank you for registering at <strong>{store_name}</strong>!',
                'activation_cta'          => 'To activate your account, enter the code below on the activation page:',
                'activation_note'         => 'If you did not create an account, please ignore this message.',
                'activation_expiry'       => 'This code is valid for 24 hours.',
                'new_order_admin_subject' => '[New order] {order_number} — {store_name}',
                'new_order_admin_heading' => 'New order!',
                'new_order_admin_text'    => 'A new order <strong>{order_number}</strong> has been received from <strong>{customer_name}</strong> ({email}).',
                'stock_notify_subject'    => '{product_name} is back in stock! — {store_name}',
                'stock_notify_heading'    => 'Product back in stock!',
                'stock_notify_hello'      => 'Hi,',
                'stock_notify_text'       => 'The product you have been waiting for is back in stock:',
                'stock_notify_btn'        => 'View product',
                'stock_notify_note'       => 'Hurry — quantities may be limited!',
                'nip'                     => 'Tax ID',
                'crn'                     => 'Registration No.',
                'bank_transfer'           => 'Bank transfer',
                'awaiting'                => 'Pending',
            ),
        );

        return $texts[ $lang ] ?? $texts['pl'];
    }

    /**
     * Domyślne szablony e-mail dla danego języka
     */
    public static function get_default_email_templates( $lang = 'pl' ) {
        $t = self::get_email_texts( $lang );
        $store_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        $base_style = '
            body { font-family: {theme_font}; margin: 0; padding: 0; background: {theme_bg}; }
            .wrapper { max-width: 600px; margin: 0 auto; background: {theme_surface}; }
            .header { background: {theme_accent}; color: #ffffff; padding: 24px 32px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
            .content { padding: 32px; color: {theme_text}; line-height: 1.6; font-size: 15px; }
            .content h2 { color: {theme_accent}; font-size: 18px; margin-top: 0; }
            .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
            .info-table td { padding: 8px 12px; border-bottom: 1px solid {theme_border}; font-size: 14px; }
            .info-table td:first-child { font-weight: 600; color: {theme_text_light}; width: 160px; }
            .products-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
            .products-table th { background: {theme_footer_bg}; padding: 10px 12px; text-align: left; font-size: 13px; border: 1px solid {theme_border}; }
            .products-table td { padding: 10px 12px; border: 1px solid {theme_border}; font-size: 14px; }
            .total-row { background: {theme_accent_light}; font-weight: 600; }
            .footer { background: {theme_footer_bg}; padding: 20px 32px; text-align: center; font-size: 12px; color: {theme_text_light}; }
        ';

        $default_header = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $base_style . '</style></head><body><div class="wrapper"><div class="header">{logo}</div><div class="content">';
        $default_footer = '</div><div class="footer">&copy; ' . wp_date('Y') . ' {store_name}. ' . $t['all_rights'] . '</div></div></body></html>';

        $order_details = '
<h2>' . $t['order_details'] . '</h2>
<table class="info-table">
    <tr><td>' . $t['order_number'] . '</td><td>{order_number}</td></tr>
    <tr><td>' . $t['date'] . '</td><td>{order_date}</td></tr>
    <tr><td>' . $t['payment_method'] . '</td><td>{payment_method}</td></tr>
    <tr><td>' . $t['shipping_method'] . '</td><td>{shipping_method}</td></tr>
</table>

<h2>' . $t['products'] . '</h2>
{products}

<table class="info-table">
    {coupon_rows}
    <tr><td>' . $t['shipping'] . '</td><td>{shipping_cost}</td></tr>
    <tr class="total-row"><td>' . $t['total'] . '</td><td>{total}</td></tr>
</table>

<h2>' . $t['customer_details'] . '</h2>
<table class="info-table">
    <tr><td>' . $t['customer'] . '</td><td>{customer_name}</td></tr>
    <tr><td>' . $t['email'] . '</td><td>{email}</td></tr>
    <tr><td>' . $t['phone'] . '</td><td>{phone}</td></tr>
    <tr><td>' . $t['billing_address'] . '</td><td>{billing_address}</td></tr>
    <tr><td>' . $t['shipping_address'] . '</td><td>{shipping_address}</td></tr>
    {tax_no_row}
    {crn_row}
</table>';

        return array(
            '_header' => $default_header,
            '_footer' => $default_footer,
            'pending' => array(
                'subject' => $t['pending_subject'],
                'body'    => '{header}<h2>' . $t['thank_you'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['order_accepted'] . '</p>' . $order_details . '{footer}',
            ),
            'pending_payment' => array(
                'subject' => $t['pending_payment_subject'],
                'body'    => '{header}<h2>' . $t['finish_payment'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['pending_payment_text'] . '</p><p>' . $t['pending_payment_cta'] . '</p><p style="text-align:center;margin:24px 0;"><a href="{payment_url}" style="display:inline-block;background:{theme_accent};color:#ffffff;padding:12px 32px;border-radius:{theme_btn_radius};text-decoration:none;font-weight:600;font-size:16px;">' . $t['finish_payment'] . '</a></p><p style="color:{theme_text_light};font-size:13px;">' . $t['pending_payment_note'] . '</p>' . $order_details . '{footer}',
            ),
            'processing' => array(
                'subject' => $t['processing_subject'],
                'body'    => '{header}<h2>' . $t['processing_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['processing_text'] . '</p>' . $order_details . '{footer}',
            ),
            'shipped' => array(
                'subject' => $t['shipped_subject'],
                'body'    => '{header}<h2>' . $t['shipped_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['shipped_text'] . '</p>' . $order_details . '{footer}',
            ),
            'completed' => array(
                'subject' => $t['completed_subject'],
                'body'    => '{header}<h2>' . $t['completed_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['completed_text'] . '</p>' . $order_details . '{footer}',
            ),
            'cancelled' => array(
                'subject' => $t['cancelled_subject'],
                'body'    => '{header}<h2>' . $t['cancelled_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['cancelled_text'] . '</p>' . $order_details . '{footer}',
            ),
            'refunded' => array(
                'subject' => $t['refunded_subject'],
                'body'    => '{header}<h2>' . $t['refunded_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['refunded_text'] . '</p>' . $order_details . '{footer}',
            ),
            'password_reset' => array(
                'subject' => $t['password_reset_subject'],
                'body'    => '{header}<h2>' . $t['password_reset_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['password_reset_text'] . '</p><p>' . $t['password_reset_cta'] . '</p><p style="text-align:center;margin:24px 0;"><a href="{reset_link}" style="display:inline-block;background:{theme_accent};color:#ffffff;padding:12px 32px;border-radius:{theme_btn_radius};text-decoration:none;font-weight:600;font-size:16px;">' . $t['password_reset_btn'] . '</a></p><p style="color:{theme_text_light};font-size:13px;">' . $t['password_reset_note'] . '</p><p style="color:{theme_text_light};font-size:12px;margin-top:20px;">' . $t['password_reset_expiry'] . '</p>{footer}',
            ),
            'account_activation' => array(
                'subject' => $t['activation_subject'],
                'body'    => '{header}<h2>' . $t['activation_heading'] . '</h2><p>' . $t['hello'] . ' {first_name},</p><p>' . $t['activation_text'] . '</p><p>' . $t['activation_cta'] . '</p><div style="text-align:center;margin:28px 0;"><div style="display:inline-block;background:{theme_accent_light};border:2px dashed {theme_accent};border-radius:{theme_card_radius};padding:20px 40px;"><span style="font-size:36px;font-weight:700;letter-spacing:10px;color:{theme_text};font-family:monospace;">{activation_code}</span></div></div><p style="color:{theme_text_light};font-size:13px;">' . $t['activation_note'] . '</p><p style="color:{theme_text_light};font-size:12px;margin-top:20px;">' . $t['activation_expiry'] . '</p>{footer}',
            ),
            'new_order_admin' => array(
                'subject' => $t['new_order_admin_subject'],
                'body'    => '{header}<h2>' . $t['new_order_admin_heading'] . '</h2><p>' . $t['new_order_admin_text'] . '</p>' . $order_details . '{footer}',
            ),
            'stock_notify' => array(
                'subject' => $t['stock_notify_subject'],
                'body'    => '{header}<h2>' . $t['stock_notify_heading'] . '</h2><p>' . $t['stock_notify_hello'] . '</p><p>' . $t['stock_notify_text'] . '</p><div style="text-align:center;margin:28px 0;"><p style="font-size:18px;font-weight:700;color:{theme_text};">{product_name}</p><p style="margin-top:16px;"><a href="{product_url}" style="display:inline-block;background:{theme_accent};color:#ffffff;padding:12px 32px;border-radius:{theme_btn_radius};text-decoration:none;font-weight:600;font-size:16px;">' . $t['stock_notify_btn'] . '</a></p></div><p style="color:{theme_text_light};font-size:13px;">' . $t['stock_notify_note'] . '</p>{footer}',
            ),
        );
    }

    /**
     * Wyślij e-mail powiadomienia o zmianie statusu
     */
    public static function send_status_email( $order_id, $new_status ) {
        $lang       = get_option( 'fc_frontend_lang', 'pl' );
        $option_key = self::get_email_option_key( $lang );
        $templates  = self::maybe_migrate_email_templates( get_option( $option_key, array() ), $lang );
        $defaults   = self::get_default_email_templates( $lang );

        $tpl = isset( $templates[ $new_status ] ) ? $templates[ $new_status ] : array();

        // Sprawdź czy włączony
        $enabled = isset( $tpl['enabled'] ) ? $tpl['enabled'] : ( isset( $defaults[ $new_status ] ) ? 1 : 0 );
        if ( ! $enabled ) return;

        $subject = ! empty( $tpl['subject'] ) ? $tpl['subject'] : ( $defaults[ $new_status ]['subject'] ?? '' );
        $body    = isset( $tpl['body'] ) && $tpl['body'] !== '' ? $tpl['body'] : ( $defaults[ $new_status ]['body'] ?? '' );

        if ( empty( $subject ) || empty( $body ) ) return;

        // Dane zamówienia
        $customer = get_post_meta( $order_id, '_fc_customer', true );
        if ( ! is_array( $customer ) || empty( $customer['email'] ) ) return;

        $replacements = self::build_email_replacements( $order_id, $new_status, $lang );
        if ( empty( $replacements ) ) return;
        $store_name = $replacements['{store_name}'];

        // Resolve {header} and {footer} first
        $saved_templates = get_option( $option_key, array() );
        $header_html = isset( $saved_templates['_header'] ) && $saved_templates['_header'] !== '' ? $saved_templates['_header'] : ( $defaults['_header'] ?? '' );
        $footer_html = isset( $saved_templates['_footer'] ) && $saved_templates['_footer'] !== '' ? $saved_templates['_footer'] : ( $defaults['_footer'] ?? '' );

        $body = str_replace( '{header}', $header_html, $body );
        $body = str_replace( '{footer}', $footer_html, $body );

        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject );
        $body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <' . get_option( 'fc_store_email', get_option( 'admin_email' ) ) . '>',
        );

        // Dołącz fakturę PDF jako załącznik jeśli skonfigurowano
        $attachments = array();
        $tmp_pdf_path = null;
        if ( class_exists( 'FC_Invoices' ) && FC_Invoices::should_attach_to_email( $new_status ) ) {
            $tmp_pdf_path = FC_Invoices::get_temp_pdf_path( $order_id );
            if ( $tmp_pdf_path ) {
                $attachments[] = $tmp_pdf_path;
            }
        }

        wp_mail( $customer['email'], $subject, $body, $headers, $attachments );

        // Wyczyść tymczasowy plik PDF
        if ( $tmp_pdf_path ) {
            FC_Invoices::cleanup_temp_pdf( $tmp_pdf_path );
        }

        // Powiadomienie do admina przy nowym zamówieniu
        if ( $new_status === 'pending' ) {
            self::send_admin_new_order_email( $order_id, $replacements, $defaults, $lang );
        }
    }

    /**
     * Wyślij e-mail do admina o nowym zamówieniu (z szablonu)
     */
    public static function send_admin_new_order_email( $order_id, $replacements = array(), $defaults = array(), $lang = null ) {
        if ( $lang === null ) $lang = get_option( 'fc_frontend_lang', 'pl' );
        $option_key = self::get_email_option_key( $lang );
        $templates  = self::maybe_migrate_email_templates( get_option( $option_key, array() ), $lang );
        if ( empty( $defaults ) ) {
            $defaults = self::get_default_email_templates( $lang );
        }

        $tpl = isset( $templates['new_order_admin'] ) ? $templates['new_order_admin'] : array();
        $enabled = isset( $tpl['enabled'] ) ? $tpl['enabled'] : 1;
        if ( ! $enabled ) return;

        $subject = ! empty( $tpl['subject'] ) ? $tpl['subject'] : ( $defaults['new_order_admin']['subject'] ?? '' );
        $body    = isset( $tpl['body'] ) && $tpl['body'] !== '' ? $tpl['body'] : ( $defaults['new_order_admin']['body'] ?? '' );

        if ( empty( $subject ) || empty( $body ) ) return;

        // Jeśli replacements nie zostały przekazane, zbuduj pełny zestaw
        if ( empty( $replacements ) ) {
            $replacements = self::build_email_replacements( $order_id, null, $lang );
            if ( empty( $replacements ) ) return;
        }

        $replacements = array_merge( $replacements, self::get_theme_replacements() );

        // Resolve {header} and {footer}
        $saved_templates = get_option( $option_key, array() );
        $header_html = isset( $saved_templates['_header'] ) && $saved_templates['_header'] !== '' ? $saved_templates['_header'] : ( $defaults['_header'] ?? '' );
        $footer_html = isset( $saved_templates['_footer'] ) && $saved_templates['_footer'] !== '' ? $saved_templates['_footer'] : ( $defaults['_footer'] ?? '' );

        $body = str_replace( '{header}', $header_html, $body );
        $body = str_replace( '{footer}', $footer_html, $body );
        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject );
        $body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

        $store_name  = $replacements['{store_name}'] ?? get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        $from_email  = get_option( 'fc_store_email', get_option( 'admin_email' ) );
        $admin_email = get_option( 'fc_store_email', get_option( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <' . $from_email . '>',
        );

        wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Wyślij e-mail resetowania hasła
     */
    public static function send_password_reset_email( $user, $reset_url ) {
        $lang       = get_option( 'fc_frontend_lang', 'pl' );
        $option_key = self::get_email_option_key( $lang );
        $templates  = self::maybe_migrate_email_templates( get_option( $option_key, array() ), $lang );
        $defaults   = self::get_default_email_templates( $lang );

        $tpl = isset( $templates['password_reset'] ) ? $templates['password_reset'] : array();

        $enabled = isset( $tpl['enabled'] ) ? $tpl['enabled'] : 1;
        if ( ! $enabled ) return;

        $subject = ! empty( $tpl['subject'] ) ? $tpl['subject'] : ( $defaults['password_reset']['subject'] ?? '' );
        $body    = isset( $tpl['body'] ) && $tpl['body'] !== '' ? $tpl['body'] : ( $defaults['password_reset']['body'] ?? '' );

        if ( empty( $subject ) || empty( $body ) ) return;

        $store_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );

        $replacements = array(
            '{first_name}'  => $user->first_name ?: $user->display_name,
            '{user_login}'  => $user->user_login,
            '{email}'       => $user->user_email,
            '{reset_link}'  => esc_url( $reset_url ),
            '{store_name}'  => $store_name,
            '{logo}'        => self::get_logo_html(),
        );

        $replacements = array_merge( $replacements, self::get_theme_replacements() );

        // Resolve {header} and {footer}
        $saved_templates = get_option( $option_key, array() );
        $header_html = isset( $saved_templates['_header'] ) && $saved_templates['_header'] !== '' ? $saved_templates['_header'] : ( $defaults['_header'] ?? '' );
        $footer_html = isset( $saved_templates['_footer'] ) && $saved_templates['_footer'] !== '' ? $saved_templates['_footer'] : ( $defaults['_footer'] ?? '' );

        $body = str_replace( '{header}', $header_html, $body );
        $body = str_replace( '{footer}', $footer_html, $body );

        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject );
        $body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <' . get_option( 'fc_store_email', get_option( 'admin_email' ) ) . '>',
        );

        wp_mail( $user->user_email, $subject, $body, $headers );
    }

    /**
     * Wyślij e-mail z kodem aktywacyjnym
     */
    public static function send_activation_email( $user, $activation_code ) {
        $lang       = get_option( 'fc_frontend_lang', 'pl' );
        $option_key = self::get_email_option_key( $lang );
        $templates  = self::maybe_migrate_email_templates( get_option( $option_key, array() ), $lang );
        $defaults   = self::get_default_email_templates( $lang );

        $tpl = isset( $templates['account_activation'] ) ? $templates['account_activation'] : array();

        $enabled = isset( $tpl['enabled'] ) ? $tpl['enabled'] : 1;
        if ( ! $enabled ) return;

        $subject = ! empty( $tpl['subject'] ) ? $tpl['subject'] : ( $defaults['account_activation']['subject'] ?? '' );
        $body    = isset( $tpl['body'] ) && $tpl['body'] !== '' ? $tpl['body'] : ( $defaults['account_activation']['body'] ?? '' );

        if ( empty( $subject ) || empty( $body ) ) return;

        $store_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );

        $replacements = array(
            '{first_name}'      => $user->first_name ?: $user->display_name,
            '{user_login}'      => $user->user_login,
            '{email}'           => $user->user_email,
            '{activation_code}' => $activation_code,
            '{store_name}'      => $store_name,
            '{logo}'            => self::get_logo_html(),
        );

        $replacements = array_merge( $replacements, self::get_theme_replacements() );

        // Resolve {header} and {footer}
        $saved_templates = get_option( $option_key, array() );
        $header_html = isset( $saved_templates['_header'] ) && $saved_templates['_header'] !== '' ? $saved_templates['_header'] : ( $defaults['_header'] ?? '' );
        $footer_html = isset( $saved_templates['_footer'] ) && $saved_templates['_footer'] !== '' ? $saved_templates['_footer'] : ( $defaults['_footer'] ?? '' );

        $body = str_replace( '{header}', $header_html, $body );
        $body = str_replace( '{footer}', $footer_html, $body );

        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject );
        $body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <' . get_option( 'fc_store_email', get_option( 'admin_email' ) ) . '>',
        );

        wp_mail( $user->user_email, $subject, $body, $headers );
    }

    /**
     * Wyślij e-mail powiadomienia o dostępności produktu (stock notify)
     */
    public static function send_stock_notify_email( $email, $product_id ) {
        $lang       = get_option( 'fc_frontend_lang', 'pl' );
        $option_key = self::get_email_option_key( $lang );
        $templates  = self::maybe_migrate_email_templates( get_option( $option_key, array() ), $lang );
        $defaults   = self::get_default_email_templates( $lang );

        $tpl = isset( $templates['stock_notify'] ) ? $templates['stock_notify'] : array();

        $enabled = isset( $tpl['enabled'] ) ? $tpl['enabled'] : 1;
        if ( ! $enabled ) return false;

        $subject = ! empty( $tpl['subject'] ) ? $tpl['subject'] : ( $defaults['stock_notify']['subject'] ?? '' );
        $body    = isset( $tpl['body'] ) && $tpl['body'] !== '' ? $tpl['body'] : ( $defaults['stock_notify']['body'] ?? '' );

        if ( empty( $subject ) || empty( $body ) ) return false;

        $store_name    = get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        $product_title = get_the_title( $product_id );
        $product_url   = get_permalink( $product_id );

        $replacements = array(
            '{product_name}' => $product_title,
            '{product_url}'  => esc_url( $product_url ),
            '{store_name}'   => $store_name,
            '{logo}'         => self::get_logo_html(),
        );

        $replacements = array_merge( $replacements, self::get_theme_replacements() );

        // Resolve {header} and {footer}
        $saved_templates = get_option( $option_key, array() );
        $header_html = isset( $saved_templates['_header'] ) && $saved_templates['_header'] !== '' ? $saved_templates['_header'] : ( $defaults['_header'] ?? '' );
        $footer_html = isset( $saved_templates['_footer'] ) && $saved_templates['_footer'] !== '' ? $saved_templates['_footer'] : ( $defaults['_footer'] ?? '' );

        $body = str_replace( '{header}', $header_html, $body );
        $body = str_replace( '{footer}', $footer_html, $body );

        $subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject );
        $body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <' . get_option( 'fc_store_email', get_option( 'admin_email' ) ) . '>',
        );

        return wp_mail( $email, $subject, $body, $headers );
    }

    /* ===================================================================
     * Zakładka Funkcje — zarządzanie funkcjonalnościami sklepu
     * =================================================================== */
    private static function render_features_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'fc_features' ); ?>

            <h2 style="margin-top:0;"><?php fc_e( 'set_store_features' ); ?></h2>
            <p class="description" style="margin-bottom:20px;"><?php fc_e( 'set_enable_or_disable_individual_store_features_disabl' ); ?></p>

            <table class="form-table fc-features-table">
                <tbody>
                    <?php
                    $features = array(
                        array(
                            'key'   => 'fc_enable_wishlist',
                            'label' => fc__( 'set_wishlist' ),
                            'desc'  => fc__( 'set_customers_can_add_products_to_a_wishlist_heart_ico' ),
                            'icon'  => 'dashicons-heart',
                        ),
                        array(
                            'key'   => 'fc_enable_quick_view',
                            'label' => fc__( 'set_quick_view' ),
                            'desc'  => fc__( 'set_quick_view_button_on_the_product_card_preview_in_a' ),
                            'icon'  => 'dashicons-visibility',
                        ),
                        array(
                            'key'   => 'fc_enable_compare',
                            'label' => fc__( 'set_product_comparison' ),
                            'desc'  => fc__( 'set_customers_can_compare_parameters_of_selected_produ' ),
                            'icon'  => 'dashicons-columns',
                        ),
                        array(
                            'key'   => 'fc_enable_stock_notify',
                            'label' => fc__( 'set_availability_notification' ),
                            'desc'  => fc__( 'set_email_notification_signup_form_when_a_product_is_b' ),
                            'icon'  => 'dashicons-bell',
                        ),
                        array(
                            'key'   => 'fc_enable_view_toggle',
                            'label' => fc__( 'set_grid_list_view_toggle' ),
                            'desc'  => fc__( 'set_buttons_to_switch_product_view_on_the_store_page_g' ),
                            'icon'  => 'dashicons-grid-view',
                        ),
                        array(
                            'key'   => 'fc_enable_badges',
                            'label' => fc__( 'set_product_badges' ),
                            'desc'  => fc__( 'set_colorful_labels_on_product_cards_bestseller_new_ec' ),
                            'icon'  => 'dashicons-awards',
                        ),
                        array(
                            'key'   => 'fc_enable_coupons',
                            'label' => fc__( 'set_coupon_system' ),
                            'desc'  => fc__( 'set_discount_codes_with_usage_limits_expiration_date_a' ),
                            'icon'  => 'dashicons-tickets-alt',
                        ),
                        array(
                            'key'   => 'fc_enable_purchase_note',
                            'label' => fc__( 'set_purchase_note' ),
                            'desc'  => fc__( 'set_display_a_note_for_the_customer_on_the_product_pag' ),
                            'icon'  => 'dashicons-edit',
                        ),
                        array(
                            'key'   => 'fc_enable_upsell',
                            'label' => fc__( 'set_related_products_up_sell' ),
                            'desc'  => fc__( 'set_you_may_also_like_section_on_the_product_page' ),
                            'icon'  => 'dashicons-products',
                        ),
                    );

                    foreach ( $features as $f ) :
                        $val = get_option( $f['key'], '1' );
                    ?>
                    <tr>
                        <th style="width:220px;">
                            <label for="<?php echo esc_attr( $f['key'] ); ?>" style="display:flex;align-items:center;gap:8px;">
                                <span class="dashicons <?php echo esc_attr( $f['icon'] ); ?>" style="color:#2271b1;font-size:18px;"></span>
                                <?php echo esc_html( $f['label'] ); ?>
                            </label>
                        </th>
                        <td>
                            <label class="fc-toggle-switch">
                                <input type="hidden" name="<?php echo esc_attr( $f['key'] ); ?>" value="0">
                                <input type="checkbox" name="<?php echo esc_attr( $f['key'] ); ?>" id="<?php echo esc_attr( $f['key'] ); ?>" value="1" <?php checked( $val, '1' ); ?> role="switch" aria-checked="<?php echo $val === '1' ? 'true' : 'false'; ?>">
                                <span class="fc-toggle-slider" aria-hidden="true"></span>
                            </label>
                            <p class="description" style="margin-top:4px;"><?php echo esc_html( $f['desc'] ); ?></p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php fc_e( 'set_comparison_settings' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'set_max_products_for_comparison' ); ?></th>
                    <td>
                        <input type="number" name="fc_compare_max_items" value="<?php echo esc_attr( get_option( 'fc_compare_max_items', '4' ) ); ?>" min="2" max="8" class="small-text" style="width:70px;">
                        <p class="description"><?php fc_e( 'set_how_many_products_a_customer_can_compare_at_the_sa' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <style>
        .fc-features-table th { padding-top: 15px; padding-bottom: 15px; }
        .fc-features-table td { padding-top: 15px; padding-bottom: 15px; }
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
        .fc-toggle-switch input:focus + .fc-toggle-slider {
            box-shadow: 0 0 0 2px rgba(34, 113, 177, .3);
        }
        </style>
        <?php
    }

    /**
     * Zbuduj tabelę produktów HTML do szablonów e-mail
     *
     * @param int $order_id
     * @return array{ html: string, subtotal: float }
     */
    public static function build_email_product_table( $order_id, $lang = null ) {
        if ( $lang === null ) $lang = get_option( 'fc_frontend_lang', 'pl' );
        $et = self::get_email_texts( $lang );
        $items      = get_post_meta( $order_id, '_fc_order_items', true );
        $show_units = FC_Units_Admin::is_visible( 'checkout' );
        $tc         = self::get_email_theme_colors();

        $products_html = '<table style="width:100%;border-collapse:collapse;font-size:14px;">';
        $products_html .= '<tr style="background:' . esc_attr( $tc['footer_bg'] ) . ';"><th style="padding:10px 12px;text-align:left;border:1px solid ' . esc_attr( $tc['border'] ) . ';width:60px;"></th><th style="padding:10px 12px;text-align:left;border:1px solid ' . esc_attr( $tc['border'] ) . ';">' . esc_html( $et['product_col'] ) . '</th><th style="padding:10px 12px;text-align:center;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">' . esc_html( $et['qty_col'] ) . '</th><th style="padding:10px 12px;text-align:right;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">' . esc_html( $et['sum_col'] ) . '</th></tr>';
        $items_subtotal = 0;
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $thumb_url = '';
                $product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
                if ( $product_id && has_post_thumbnail( $product_id ) ) {
                    $thumb_url = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
                }
                $thumb_html = $thumb_url
                    ? '<img src="' . esc_url( $thumb_url ) . '" style="width:50px;height:50px;object-fit:cover;border-radius:4px;display:block;" alt="">'
                    : '<div style="width:50px;height:50px;background:' . esc_attr( $tc['footer_bg'] ) . ';border-radius:4px;"></div>';
                $unit_label = '';
                if ( $show_units && $product_id ) {
                    $unit_label = get_post_meta( $product_id, '_fc_unit', true ) ?: FC_Units_Admin::get_default();
                }
                $qty_text = intval( $item['quantity'] ) . ( $unit_label ? ' ' . esc_html( $unit_label ) : '' );
                $variant_label = '';
                if ( ! empty( $item['attribute_values'] ) && is_array( $item['attribute_values'] ) ) {
                    $vp = array();
                    foreach ( $item['attribute_values'] as $an => $av ) {
                        $vp[] = esc_html( $an ) . ': ' . esc_html( $av );
                    }
                    $variant_label = '<br><span style="font-size:12px;color:#888;">' . implode( ', ', $vp ) . '</span>';
                } elseif ( ! empty( $item['variant_name'] ) ) {
                    $variant_label = '<br><span style="font-size:12px;color:#888;">' . esc_html( $item['variant_name'] ) . '</span>';
                }
                $products_html .= '<tr>';
                $products_html .= '<td style="padding:8px 12px;border:1px solid ' . esc_attr( $tc['border'] ) . ';">' . $thumb_html . '</td>';
                $products_html .= '<td style="padding:10px 12px;border:1px solid ' . esc_attr( $tc['border'] ) . ';">' . esc_html( $item['product_name'] ) . $variant_label . '</td>';
                $products_html .= '<td style="padding:10px 12px;text-align:center;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">' . $qty_text . '</td>';
                $products_html .= '<td style="padding:10px 12px;text-align:right;border:1px solid ' . esc_attr( $tc['border'] ) . ';white-space:nowrap;">' . fc_format_price( $item['line_total'] ) . '</td>';
                $products_html .= '</tr>';
                $items_subtotal += floatval( $item['line_total'] );
            }
        }
        $products_html .= '</table>';

        return array( 'html' => $products_html, 'subtotal' => $items_subtotal );
    }

    /**
     * Zbuduj tablicę zamienników (replacements) do szablonów e-mail
     *
     * @param int         $order_id
     * @param string|null $status_override  Opcjonalnie nadpisz status (np. przy zmianie statusu)
     * @return array
     */
    public static function build_email_replacements( $order_id, $status_override = null, $lang = null ) {
        if ( $lang === null ) $lang = get_option( 'fc_frontend_lang', 'pl' );
        $et = self::get_email_texts( $lang );
        $customer = get_post_meta( $order_id, '_fc_customer', true );
        if ( ! is_array( $customer ) ) return array();

        $order_number  = get_post_meta( $order_id, '_fc_order_number', true );
        $order_date    = get_post_meta( $order_id, '_fc_order_date', true );
        $order_total   = floatval( get_post_meta( $order_id, '_fc_order_total', true ) );
        $shipping_name = get_post_meta( $order_id, '_fc_shipping_method', true );
        $shipping_cost = floatval( get_post_meta( $order_id, '_fc_shipping_cost', true ) );
        $statuses      = FC_Orders::get_statuses();
        $store_name    = get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        $order_status  = $status_override ?: get_post_meta( $order_id, '_fc_order_status', true );

        $payment_label = FC_Orders::get_order_payment_label( $order_id );

        // Tabela produktów
        $table_data = self::build_email_product_table( $order_id, $lang );

        // Adres do faktury
        $billing_parts = array_filter( array(
            ( $customer['street'] ?? $customer['address'] ?? '' ),
            trim( ( $customer['postcode'] ?? '' ) . ' ' . ( $customer['city'] ?? '' ) . ( ! empty( $customer['country'] ) ? ', ' . $customer['country'] : '' ) ),
        ) );
        $billing_address = implode( '<br>', $billing_parts );

        // Adres dostawy
        $shipping_address = $billing_address;
        if ( ! empty( $customer['shipping'] ) && is_array( $customer['shipping'] ) ) {
            $sh = $customer['shipping'];
            $shipping_parts = array_filter( array(
                ( $sh['street'] ?? $sh['address'] ?? '' ),
                trim( ( $sh['postcode'] ?? '' ) . ' ' . ( $sh['city'] ?? '' ) . ( ! empty( $sh['country'] ) ? ', ' . $sh['country'] : '' ) ),
            ) );
            if ( ! empty( $shipping_parts ) ) {
                $shipping_address = implode( '<br>', $shipping_parts );
            }
        }

        // Dane firmowe klienta
        $account_type  = $customer['account_type'] ?? 'private';
        $company_name  = $customer['company'] ?? '';
        $full_name     = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
        $customer_name = ( $account_type === 'company' && ! empty( $company_name ) )
            ? esc_html( $company_name ) . ( ! empty( $full_name ) ? ' (' . esc_html( $full_name ) . ')' : '' )
            : esc_html( $full_name );
        $tax_no  = $customer['tax_no'] ?? '';
        $crn_val = $customer['crn'] ?? '';
        $country = $customer['country'] ?? '';
        $tax_labels = class_exists( 'FC_Shortcodes' ) ? FC_Shortcodes::get_country_tax_labels( $country ) : array( 'tax_no' => 'NIP', 'crn' => 'Nr rejestrowy' );

        $replacements = array(
            '{order_number}'     => $order_number ?: '',
            '{order_date}'       => $order_date ? date_i18n( 'j F Y, H:i', strtotime( $order_date ) ) : '',
            '{status}'           => $statuses[ $order_status ] ?? $order_status,
            '{first_name}'       => ! empty( $customer['first_name'] ) ? $customer['first_name'] : ( $customer['company'] ?? '' ),
            '{last_name}'        => $customer['last_name'] ?? '',
            '{email}'            => $customer['email'] ?? '',
            '{phone}'            => trim( ( $customer['phone_prefix'] ?? '' ) . ' ' . ( $customer['phone'] ?? '' ) ),
            '{billing_address}'  => $billing_address,
            '{shipping_address}' => $shipping_address,
            '{payment_method}'   => $payment_label,
            '{shipping_method}'  => $shipping_name ?: '',
            '{products}'         => $table_data['html'],
            '{subtotal}'         => fc_format_price( $table_data['subtotal'] ),
            '{coupon_rows}'      => self::build_coupon_rows_html( $order_id, $lang ),
            '{shipping_cost}'    => fc_format_price( $shipping_cost ),
            '{total}'            => fc_format_price( $order_total ),
            '{store_name}'       => $store_name,
            '{logo}'             => self::get_logo_html(),
            '{customer_name}'    => $customer_name,
            '{tax_no}'           => esc_html( $tax_no ),
            '{crn}'              => esc_html( $crn_val ),
            '{tax_no_row}'       => ! empty( $tax_no ) ? '<tr><td>' . esc_html( $tax_labels['tax_no'] ) . '</td><td>' . esc_html( $tax_no ) . '</td></tr>' : '',
            '{crn_row}'          => ! empty( $crn_val ) ? '<tr><td>' . esc_html( $tax_labels['crn'] ) . '</td><td>' . esc_html( $crn_val ) . '</td></tr>' : '',
            '{payment_url}'      => self::build_payment_url( $order_id ),
        );

        return array_merge( $replacements, self::get_theme_replacements() );
    }

    /**
     * Generuj wiersze HTML kuponów rabatowych do szablonów e-mail
     */
    public static function build_coupon_rows_html( $order_id, $lang = null ) {
        if ( $lang === null ) $lang = get_option( 'fc_frontend_lang', 'pl' );
        $et = self::get_email_texts( $lang );
        $coupon_details  = get_post_meta( $order_id, '_fc_coupon_details', true );
        $coupon_discount = floatval( get_post_meta( $order_id, '_fc_coupon_discount', true ) );

        $html = '';

        if ( ! empty( $coupon_details ) && is_array( $coupon_details ) ) {
            foreach ( $coupon_details as $cd ) {
                $html .= '<tr style="color:#27ae60;"><td>' . esc_html( $et['coupon'] ) . ': <code>' . esc_html( $cd['code'] ) . '</code></td><td>−' . fc_format_price( $cd['discount'] ) . '</td></tr>';
            }
        } elseif ( $coupon_discount > 0 ) {
            $coupon_code = get_post_meta( $order_id, '_fc_coupon_code', true );
            $html .= '<tr style="color:#27ae60;"><td>' . esc_html( $et['coupon'] ) . ': <code>' . esc_html( $coupon_code ) . '</code></td><td>−' . fc_format_price( $coupon_discount ) . '</td></tr>';
        }

        return $html;
    }

    /**
     * Buduje URL płatności / zamówienia — dla gości prowadzi do retry page z tokenem,
     * dla zalogowanych do strony „Moje Konto".
     */
    private static function build_payment_url( $order_id ) {
        $customer_id = get_post_meta( $order_id, '_fc_customer_id', true );

        // Zalogowany użytkownik — link do konta
        if ( ! empty( $customer_id ) ) {
            return add_query_arg( array( 'tab' => 'orders', 'order_id' => $order_id ), get_permalink( get_option( 'fc_page_moje-konto' ) ) );
        }

        // Gość — link do strony retry payment z tokenem
        $retry_page_id = get_option( 'fc_page_platnosc_nieudana' );
        $base_url      = $retry_page_id ? get_permalink( $retry_page_id ) : get_permalink( get_option( 'fc_page_podziekowanie' ) );
        $args          = array( 'order_id' => $order_id );
        $order_token   = get_post_meta( $order_id, '_fc_order_token', true );
        if ( $order_token ) {
            $args['token'] = $order_token;
        }
        return add_query_arg( $args, $base_url );
    }
}
