<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Dodatkowe funkcjonalności frontendowe:
 * - N3: Widok listy
 * - N4: Quick View (podgląd produktu)
 * - N6: Porównanie produktów
 * - N7: Powiadomienie o dostępności
 * - N8: Najczęściej kupowane razem
 * - N10: Ceny wg roli
 */
class FC_Frontend_Features {

    public static function init() {
        // Sesja PHP: start na init:1, zamknięcie na init:99 (zwalnia lock pliku sesji)
        add_action( 'init', array( __CLASS__, 'ensure_frontend_session' ), 1 );
        add_action( 'init', array( __CLASS__, 'close_frontend_session' ), 99 );

        // Quick View AJAX
        add_action( 'wp_ajax_fc_quick_view', array( __CLASS__, 'ajax_quick_view' ) );
        add_action( 'wp_ajax_nopriv_fc_quick_view', array( __CLASS__, 'ajax_quick_view' ) );

        // Powiadomienie o dostępności
        add_action( 'wp_ajax_fc_stock_notify', array( __CLASS__, 'ajax_stock_notify' ) );
        add_action( 'wp_ajax_nopriv_fc_stock_notify', array( __CLASS__, 'ajax_stock_notify' ) );

        // Wysyłanie e-maili po zmianie stanu magazynowego
        add_action( 'updated_post_meta', array( __CLASS__, 'maybe_send_stock_notifications' ), 10, 4 );

        // Porównanie
        add_shortcode( 'fc_compare', array( __CLASS__, 'compare_shortcode' ) );
        add_action( 'wp_ajax_fc_compare_toggle', array( __CLASS__, 'ajax_compare_toggle' ) );
        add_action( 'wp_ajax_nopriv_fc_compare_toggle', array( __CLASS__, 'ajax_compare_toggle' ) );
        add_action( 'wp_ajax_fc_compare_clear', array( __CLASS__, 'ajax_compare_clear' ) );
        add_action( 'wp_ajax_nopriv_fc_compare_clear', array( __CLASS__, 'ajax_compare_clear' ) );
        add_action( 'wp_ajax_fc_get_compare_panel', array( __CLASS__, 'ajax_get_compare_panel' ) );
        add_action( 'wp_ajax_nopriv_fc_get_compare_panel', array( __CLASS__, 'ajax_get_compare_panel' ) );
        add_action( 'wp_ajax_fc_get_wishlist_panel', array( __CLASS__, 'ajax_get_wishlist_panel' ) );
        add_action( 'wp_ajax_nopriv_fc_get_wishlist_panel', array( __CLASS__, 'ajax_get_wishlist_panel' ) );

        // Footer: modale + JS
        add_action( 'wp_footer', array( __CLASS__, 'render_modals' ) );
        add_action( 'wp_footer', array( __CLASS__, 'render_scripts' ), 99 );
    }

    /**
     * Uruchom sesję PHP na frontendzie (potrzebna na porównywarkę, kupony, checkout).
     * Ustawia cookie PHPSESSID, ładuje dane sesji do $_SESSION.
     */
    public static function ensure_frontend_session() {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
        if ( defined( 'WP_CLI' ) && WP_CLI ) return;
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) {
            session_start();
        }
    }

    /**
     * Zamknij sesję po init — zwalnia blokadę pliku sesji,
     * ale dane $_SESSION pozostają dostępne do odczytu.
     */
    public static function close_frontend_session() {
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }
    }

    /**
     * AJAX: Quick View — zwraca HTML podglądu produktu
     */
    public static function ajax_quick_view() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) wp_send_json_error( fc__( 'missing_id' ) );

        $post = get_post( $product_id );
        if ( ! $post || $post->post_type !== 'fc_product' ) wp_send_json_error( fc__( 'not_found' ) );

        $price       = get_post_meta( $product_id, '_fc_price', true );
        $sale_price  = get_post_meta( $product_id, '_fc_sale_price', true );
        $stock_status = get_post_meta( $product_id, '_fc_stock_status', true );
        $product_type = get_post_meta( $product_id, '_fc_product_type', true ) ?: 'simple';
        $unit        = get_post_meta( $product_id, '_fc_unit', true ) ?: FC_Units_Admin::get_default();
        $sku         = get_post_meta( $product_id, '_fc_sku', true );

        ob_start();
        ?>
        <div class="fc-qv-content">
            <div class="fc-qv-image">
                <?php if ( has_post_thumbnail( $product_id ) ) : ?>
                    <?php echo get_the_post_thumbnail( $product_id, 'medium_large' ); ?>
                <?php else : ?>
                    <div class="fc-no-image fc-no-image-large"><?php fc_e( 'no_image' ); ?></div>
                <?php endif; ?>
            </div>
            <div class="fc-qv-details">
                <h2 class="fc-qv-title"><?php echo esc_html( $post->post_title ); ?></h2>
                <div class="fc-product-price fc-price-large">
                    <?php if ( $sale_price && floatval( $sale_price ) > 0 ) : ?>
                        <del><?php echo fc_format_price( $price ); ?></del>
                        <ins><?php echo fc_format_price( $sale_price ); ?></ins>
                    <?php elseif ( $price ) : ?>
                        <span><?php echo fc_format_price( $price ); ?></span>
                    <?php endif; ?>
                    <?php if ( FC_Units_Admin::is_visible( 'product' ) ) : ?>
                        <span class="fc-price-unit">/ <?php echo esc_html( FC_Units_Admin::label( $unit ) ); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ( $post->post_excerpt ) : ?>
                    <div class="fc-qv-excerpt"><?php echo wpautop( $post->post_excerpt ); ?></div>
                <?php endif; ?>

                <div class="fc-stock-info">
                    <?php if ( $stock_status === 'outofstock' ) : ?>
                        <span class="fc-stock-badge out"><?php fc_e( 'out_of_stock_badge' ); ?></span>
                    <?php else : ?>
                        <span class="fc-stock-badge in"><?php fc_e( 'in_stock_badge' ); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ( $sku ) : ?>
                    <p style="color:var(--fc-text-light,#888);font-size:13px;margin:8px 0;">SKU: <?php echo esc_html( $sku ); ?></p>
                <?php endif; ?>

                <div class="fc-qv-actions" style="margin-top:15px;display:flex;gap:10px;align-items:center;">
                    <?php if ( $product_type === 'variable' ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-btn"><?php fc_e( 'choose_variants' ); ?></a>
                    <?php elseif ( $stock_status !== 'outofstock' ) : ?>
                        <button class="fc-btn fc-add-to-cart" data-product-id="<?php echo esc_attr( $product_id ); ?>"><?php fc_e( 'add_to_cart' ); ?></button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="fc-btn fc-btn-outline"><?php fc_e( 'details' ); ?></a>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX: Powiadomienie o dostępności (N7)
     */
    public static function ajax_stock_notify() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        // Rate limiting — max 5 requests per minute per IP
        $ip_key = 'fc_notify_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
        $attempts = intval( get_transient( $ip_key ) );
        if ( $attempts >= 5 ) {
            wp_send_json_error( fc__( 'too_many_requests' ) );
        }
        set_transient( $ip_key, $attempts + 1, 60 );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        $email = sanitize_email( $_POST['email'] ?? '' );

        if ( ! $product_id || ! is_email( $email ) ) {
            wp_send_json_error( fc__( 'invalid_email' ) );
        }

        // Zapisz w liście subskrybentów produktu
        $subscribers = get_post_meta( $product_id, '_fc_stock_subscribers', true );
        if ( ! is_array( $subscribers ) ) $subscribers = array();

        if ( in_array( $email, $subscribers ) ) {
            wp_send_json_error( fc__( 'already_subscribed' ) );
        }

        $subscribers[] = $email;
        update_post_meta( $product_id, '_fc_stock_subscribers', $subscribers );

        wp_send_json_success( array(
            'message' => fc__( 'notify_when_available' ),
        ) );
    }

    /**
     * Hook: Gdy zmieni się _fc_stock_status z 'outofstock' na 'instock', wyślij e-maile subskrybentom
     */
    public static function maybe_send_stock_notifications( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( $meta_key !== '_fc_stock_status' ) return;
        if ( $meta_value !== 'instock' ) return;
        if ( get_post_type( $post_id ) !== 'fc_product' ) return;
        if ( get_option( 'fc_enable_stock_notify', '1' ) !== '1' ) return;

        $subscribers = get_post_meta( $post_id, '_fc_stock_subscribers', true );
        if ( ! is_array( $subscribers ) || empty( $subscribers ) ) return;

        foreach ( $subscribers as $email ) {
            if ( ! is_email( $email ) ) continue;
            FC_Settings::send_stock_notify_email( $email, $post_id );
        }

        // Wyczyść listę subskrybentów po wysłaniu
        delete_post_meta( $post_id, '_fc_stock_subscribers' );
    }

    /**
     * AJAX: Porównaj — dodaj/usuń (N6)
     */
    public static function ajax_compare_toggle() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) wp_send_json_error( fc__( 'missing_id' ) );

        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) session_start();
        if ( ! isset( $_SESSION['fc_compare'] ) ) $_SESSION['fc_compare'] = array();

        if ( in_array( $product_id, $_SESSION['fc_compare'] ) ) {
            $_SESSION['fc_compare'] = array_values( array_diff( $_SESSION['fc_compare'], array( $product_id ) ) );
            $added = false;
        } else {
            $max_compare = intval( get_option( 'fc_compare_max_items', '4' ) ) ?: 4;
            if ( count( $_SESSION['fc_compare'] ) >= $max_compare ) {
                wp_send_json_error( sprintf( fc__( 'compare_max_items' ), $max_compare ) );
            }
            $_SESSION['fc_compare'][] = $product_id;
            $added = true;
        }

        $response = array(
            'added'   => $added,
            'count'   => count( $_SESSION['fc_compare'] ),
            'items'   => array_values( $_SESSION['fc_compare'] ),
            'message' => $added
                ? fc__( 'added_to_compare' )
                : fc__( 'removed_from_compare' ),
        );
        session_write_close();
        wp_send_json_success( $response );
    }

    /**
     * AJAX: Wyczyść porównanie
     */
    public static function ajax_compare_clear() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) session_start();
        $_SESSION['fc_compare'] = array();
        session_write_close();
        wp_send_json_success( array(
            'count'   => 0,
            'message' => fc__( 'compare_cleared' ),
        ) );
    }

    /**
     * AJAX: Pobierz zawartość panelu porównania
     */
    public static function ajax_get_compare_panel() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $count = isset( $_SESSION['fc_compare'] ) ? count( $_SESSION['fc_compare'] ) : 0;
        $html  = Flavor_Commerce::render_compare_panel_items();
        wp_send_json_success( array(
            'count'        => $count,
            'compare_html' => $html,
        ) );
    }

    /**
     * AJAX: Pobierz zawartość panelu listy życzeń
     */
    public static function ajax_get_wishlist_panel() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $ids   = class_exists( 'FC_Wishlist' ) ? FC_Wishlist::get_wishlist( get_current_user_id() ) : array();
        $count = count( $ids );
        $html  = Flavor_Commerce::render_wishlist_panel_items();
        wp_send_json_success( array(
            'count'         => $count,
            'wishlist_html' => $html,
        ) );
    }

    /**
     * Shortcode [fc_compare] — tabela porównawcza
     */
    public static function compare_shortcode( $atts ) {
        $ids = isset( $_SESSION['fc_compare'] ) ? $_SESSION['fc_compare'] : array();

        ob_start();
        echo '<!--nocache-->';
        ?>
        <div class="fc-compare-page">
            <?php if ( empty( $ids ) || count( $ids ) < 2 ) : ?>
                <div class="fc-compare-empty">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--fc-text-light,#999)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
                    <h3><?php fc_e( 'compare_minimum' ); ?></h3>
                    <p><?php printf( fc__( 'compare_current_count' ), count( $ids ) ); ?></p>
                    <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'go_to_shop' ); ?></a>
                </div>
            <?php else : ?>
                <div class="fc-compare-header">
                    <h2><?php fc_e( 'product_comparison' ); ?> <span class="fc-compare-header-count">(<?php echo count( $ids ); ?>)</span></h2>
                    <button type="button" class="fc-btn fc-btn-outline fc-btn-sm fc-compare-page-clear"><?php fc_e( 'clear_all' ); ?></button>
                </div>

                <!-- Comparison table with archive-style product cards as header -->
                <div class="fc-compare-table-wrap">
                <table class="fc-compare-table">
                    <thead>
                        <tr class="fc-compare-products-row">
                            <th class="fc-compare-label-cell"></th>
                            <?php foreach ( $ids as $pid ) : ?>
                                <th class="fc-compare-product-cell">
                                    <?php echo FC_Shortcodes::render_product_card( $pid ); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Cena -->
                        <tr>
                            <th><?php fc_e( 'price' ); ?></th>
                            <?php foreach ( $ids as $pid ) :
                                $p = get_post_meta( $pid, '_fc_effective_price', true ) ?: get_post_meta( $pid, '_fc_price', true );
                            ?>
                                <td><?php echo $p ? fc_format_price( $p, $pid ) : '—'; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <!-- Dostępność -->
                        <tr>
                            <th><?php fc_e( 'availability' ); ?></th>
                            <?php foreach ( $ids as $pid ) :
                                $ss = get_post_meta( $pid, '_fc_stock_status', true );
                            ?>
                                <td>
                                    <span class="fc-compare-stock fc-compare-stock--<?php echo $ss === 'outofstock' ? 'out' : 'in'; ?>">
                                        <?php echo $ss === 'outofstock' ? fc__( 'unavailable' ) : fc__( 'available' ); ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <!-- SKU -->
                        <tr>
                            <th>SKU</th>
                            <?php foreach ( $ids as $pid ) : ?>
                                <td><?php echo esc_html( get_post_meta( $pid, '_fc_sku', true ) ?: '—' ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <!-- Waga -->
                        <tr>
                            <th><?php fc_e( 'weight' ); ?></th>
                            <?php foreach ( $ids as $pid ) :
                                $w = get_post_meta( $pid, '_fc_weight', true );
                            ?>
                                <td><?php echo $w ? esc_html( $w . ' kg' ) : '—'; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <!-- Wymiary -->
                        <tr>
                            <th><?php fc_e( 'dimensions' ); ?></th>
                            <?php foreach ( $ids as $pid ) :
                                $l = get_post_meta( $pid, '_fc_length', true );
                                $wi = get_post_meta( $pid, '_fc_width', true );
                                $h = get_post_meta( $pid, '_fc_height', true );
                                $dim = ( $l || $wi || $h ) ? implode( ' × ', array_filter( array( $l, $wi, $h ) ) ) . ' cm' : '—';
                            ?>
                                <td><?php echo esc_html( $dim ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <!-- Specyfikacja -->
                        <?php
                        $all_spec_keys = array();
                        $all_specs = array();
                        foreach ( $ids as $pid ) {
                            $specs = get_post_meta( $pid, '_fc_specifications', true );
                            if ( ! is_array( $specs ) ) $specs = array();
                            $all_specs[ $pid ] = $specs;
                            foreach ( $specs as $sp ) {
                                if ( ! in_array( $sp['key'], $all_spec_keys ) ) {
                                    $all_spec_keys[] = $sp['key'];
                                }
                            }
                        }
                        foreach ( $all_spec_keys as $skey ) : ?>
                            <tr>
                                <th><?php echo esc_html( $skey ); ?></th>
                                <?php foreach ( $ids as $pid ) :
                                    $val = '—';
                                    foreach ( $all_specs[ $pid ] as $sp ) {
                                        if ( $sp['key'] === $skey ) { $val = $sp['value']; break; }
                                    }
                                ?>
                                    <td><?php echo esc_html( $val ); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fc-compare-actions-row">
                            <th></th>
                            <?php foreach ( $ids as $pid ) :
                                $stock = get_post_meta( $pid, '_fc_stock_status', true );
                                $ptype = get_post_meta( $pid, '_fc_product_type', true ) ?: 'simple';
                            ?>
                                <td>
                                    <?php if ( $stock !== 'outofstock' ) : ?>
                                        <?php if ( $ptype === 'variable' ) : ?>
                                            <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" class="fc-btn fc-choose-options">
                                                <?php fc_e( 'choose_variants' ); ?>
                                            </a>
                                        <?php else : ?>
                                            <button class="fc-btn fc-add-to-cart" data-product-id="<?php echo esc_attr( $pid ); ?>">
                                                <?php fc_e( 'add_to_cart' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="fc-out-of-stock"><?php fc_e( 'unavailable' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuj modalne okna w stopce
     */
    public static function render_modals() {
        if ( is_admin() ) return;
        ?>
        <?php if ( get_option( 'fc_enable_quick_view', '1' ) ) : ?>
        <!-- Quick View Modal -->
        <div class="fc-modal fc-qv-modal" id="fc-quick-view-modal" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;">
            <div class="fc-modal-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
            <div class="fc-modal-content" style="position:relative;background:var(--fc-bg,#fff);border-radius:var(--radius,0);max-width:800px;width:95%;max-height:90vh;overflow-y:auto;padding:30px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <button type="button" class="fc-modal-close" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:24px;cursor:pointer;color:var(--fc-text,#333);z-index:2;">&times;</button>
                <div class="fc-qv-loading" style="text-align:center;padding:40px;">
                    <span class="spinner is-active" style="float:none;"></span>
                </div>
                <div class="fc-qv-body" style="display:none;"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( get_option( 'fc_enable_stock_notify', '1' ) ) : ?>
        <!-- Stock Notify Modal -->
        <div class="fc-modal fc-notify-modal" id="fc-stock-notify-modal" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;">
            <div class="fc-modal-backdrop" style="position:absolute;inset:0;background:rgba(0,0,0,0.5);"></div>
            <div class="fc-modal-content" style="position:relative;background:var(--fc-bg,#fff);border-radius:var(--radius,0);max-width:400px;width:95%;padding:30px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <button type="button" class="fc-modal-close" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>
                <h3 style="margin:0 0 15px;"><?php fc_e( 'notify_availability' ); ?></h3>
                <p style="color:var(--fc-text-light,#666);font-size:14px;"><?php fc_e( 'notify_modal_description' ); ?></p>
                <input type="hidden" id="fc-notify-product-id" value="">
                <input type="email" id="fc-notify-email" placeholder="<?php fc_e( 'your_email_placeholder' ); ?>" style="width:100%;padding:10px;border:1px solid var(--fc-border,#ddd);border-radius:var(--radius,0);margin-bottom:10px;">
                <button type="button" id="fc-notify-submit" class="fc-btn" style="width:100%;"><?php fc_e( 'notify_me' ); ?></button>
                <div id="fc-notify-message" style="display:none;margin-top:10px;padding:8px;border-radius:var(--radius,0);font-size:14px;"></div>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * JavaScript dla funkcji frontendowych
     */
    public static function render_scripts() {
        if ( is_admin() ) return;
        ?>
        <script>
        (function(){
            /* Quick View */
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.fc-quick-view-btn');
                if (!btn) return;
                e.preventDefault();
                var pid = btn.getAttribute('data-product-id');
                var modal = document.getElementById('fc-quick-view-modal');
                if (!modal || !pid) return;
                modal.style.display = 'flex';
                modal.querySelector('.fc-qv-loading').style.display = 'block';
                modal.querySelector('.fc-qv-body').style.display = 'none';
                var fd = new FormData();
                fd.append('action', 'fc_quick_view');
                fd.append('product_id', pid);
                fd.append('nonce', fc_ajax.nonce);
                fetch(fc_ajax.url, { method:'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        modal.querySelector('.fc-qv-loading').style.display = 'none';
                        var body = modal.querySelector('.fc-qv-body');
                        body.innerHTML = res.success ? res.data.html : '<p>Error</p>';
                        body.style.display = 'block';
                    });
            });

            /* Stock Notify */
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.fc-stock-notify-btn');
                if (!btn) return;
                e.preventDefault();
                var modal = document.getElementById('fc-stock-notify-modal');
                if (!modal) return;
                document.getElementById('fc-notify-product-id').value = btn.getAttribute('data-product-id');
                document.getElementById('fc-notify-email').value = '';
                document.getElementById('fc-notify-message').style.display = 'none';
                modal.style.display = 'flex';
            });
            var notifySubmit = document.getElementById('fc-notify-submit');
            if (notifySubmit) {
                notifySubmit.addEventListener('click', function(){
                    var email = document.getElementById('fc-notify-email').value;
                    var pid = document.getElementById('fc-notify-product-id').value;
                    var msg = document.getElementById('fc-notify-message');
                    if (!email) { document.getElementById('fc-notify-email').focus(); return; }
                    var fd = new FormData();
                    fd.append('action', 'fc_stock_notify');
                    fd.append('product_id', pid);
                    fd.append('email', email);
                    fd.append('nonce', fc_ajax.nonce);
                    fetch(fc_ajax.url, { method:'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            msg.style.display = 'block';
                            if (res.success) {
                                msg.style.background = '#d4edda'; msg.style.color = '#155724';
                                msg.textContent = res.data.message;
                            } else {
                                msg.style.background = '#f8d7da'; msg.style.color = '#721c24';
                                msg.textContent = res.data;
                            }
                        });
                });
            }

            /* List/Grid view toggle */
            (function(){
                var savedView = localStorage.getItem('fc_shop_view');
                if (savedView) {
                    var grid = document.querySelector('.fc-products-grid');
                    if (grid) {
                        if (savedView === 'list') {
                            grid.classList.add('fc-view-list');
                            grid.classList.remove('fc-view-grid');
                        } else {
                            grid.classList.remove('fc-view-list');
                            grid.classList.add('fc-view-grid');
                        }
                        document.querySelectorAll('.fc-view-toggle').forEach(function(b){
                            b.classList.toggle('active', b.getAttribute('data-view') === savedView);
                        });
                    }
                }
            })();
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.fc-view-toggle');
                if (!btn) return;
                var view = btn.getAttribute('data-view');
                var grid = document.querySelector('.fc-products-grid');
                if (!grid) return;
                if (view === 'list') {
                    grid.classList.add('fc-view-list');
                    grid.classList.remove('fc-view-grid');
                } else {
                    grid.classList.remove('fc-view-list');
                    grid.classList.add('fc-view-grid');
                }
                document.querySelectorAll('.fc-view-toggle').forEach(function(b){ b.classList.remove('active'); });
                btn.classList.add('active');
                try { localStorage.setItem('fc_shop_view', view); } catch(e){}
            });

            /* Modal close handlers */
            document.addEventListener('click', function(e){
                if (e.target.closest('.fc-modal-close') || e.target.classList.contains('fc-modal-backdrop')) {
                    var modal = e.target.closest('.fc-modal');
                    if (modal) modal.style.display = 'none';
                }
            });
            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape') {
                    document.querySelectorAll('.fc-modal').forEach(function(m){ m.style.display = 'none'; });
                }
            });
        })();
        </script>

        <style>
        /* Quick View Content */
        .fc-qv-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .fc-qv-image img {
            width: 100%;
            height: auto;
            border-radius: var(--radius, 0);
        }
        @media (max-width: 640px) {
            .fc-qv-content { grid-template-columns: 1fr; }
        }

        </style>
        <?php
    }

    /**
     * Pobierz klasy CSS przycisków akcji z ustawień customizera
     */
    public static function get_action_btn_classes() {
        $shape = get_theme_mod( 'fc_action_btn_shape', 'circle' );
        $style = get_theme_mod( 'fc_action_btn_style', 'glass' );
        return 'fc-action-btn fc-action-btn--' . esc_attr( $shape ) . ' fc-action-btn--' . esc_attr( $style );
    }

    /**
     * Renderuj przycisk Quick View (do wstawienia w karcie produktu)
     */
    public static function render_quick_view_button( $product_id ) {
        $cls = self::get_action_btn_classes();
        ?>
        <button type="button" class="<?php echo esc_attr( $cls ); ?> fc-quick-view-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>"
                title="<?php fc_e( 'quick_view' ); ?>">
            👁
        </button>
        <?php
    }

    /**
     * Renderuj przycisk porównania (do wstawienia w karcie produktu)
     */
    public static function render_compare_button( $product_id ) {
        $is_compared = isset( $_SESSION['fc_compare'] ) && in_array( $product_id, $_SESSION['fc_compare'] );
        $cls = self::get_action_btn_classes();
        ?>
        <button type="button" class="<?php echo esc_attr( $cls ); ?> fc-compare-btn<?php echo $is_compared ? ' active' : ''; ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"
                title="<?php fc_e( 'compare' ); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
        </button>
        <?php
    }

    /**
     * Renderuj przycisk powiadomienia o dostępności
     */
    public static function render_stock_notify_button( $product_id ) {
        ?>
        <button type="button" class="fc-btn fc-btn-outline fc-stock-notify-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>" style="margin-top:12px;display:block;width:100%;">
            <span class="dashicons dashicons-email-alt" style="vertical-align:text-bottom;margin-right:4px;"></span>
            <?php fc_e( 'notify_availability' ); ?>
        </button>
        <?php
    }
}
