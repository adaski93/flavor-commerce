<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * System listy życzeń / ulubionych (N5)
 */
class FC_Wishlist {

    public static function init() {
        add_action( 'wp_ajax_fc_wishlist_toggle', array( __CLASS__, 'ajax_toggle' ) );
        add_action( 'wp_ajax_nopriv_fc_wishlist_toggle', array( __CLASS__, 'ajax_toggle_guest' ) );
        add_action( 'wp_ajax_fc_wishlist_get', array( __CLASS__, 'ajax_get' ) );
        add_action( 'wp_ajax_nopriv_fc_wishlist_get', array( __CLASS__, 'ajax_get' ) );
        add_action( 'wp_ajax_fc_wishlist_clear', array( __CLASS__, 'ajax_clear' ) );
        add_action( 'wp_ajax_nopriv_fc_wishlist_clear', array( __CLASS__, 'ajax_clear' ) );
        add_shortcode( 'fc_wishlist', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_footer', array( __CLASS__, 'footer_js' ) );

        // Przy logowaniu — przenieś dane z cookie do user_meta
        add_action( 'wp_login', array( __CLASS__, 'merge_cookie_on_login' ), 10, 2 );
    }

    /**
     * Pobierz listę życzeń użytkownika
     */
    public static function get_wishlist( $user_id = 0 ) {
        if ( $user_id ) {
            $list = get_user_meta( $user_id, '_fc_wishlist', true );
            $list = is_array( $list ) ? $list : array();

            // Jeśli użytkownik jest zalogowany ALE ma też cookie — merguj dane
            if ( isset( $_COOKIE['fc_wishlist'] ) ) {
                $cookie_list = json_decode( stripslashes( $_COOKIE['fc_wishlist'] ), true );
                if ( is_array( $cookie_list ) && ! empty( $cookie_list ) ) {
                    $cookie_list = array_map( 'absint', $cookie_list );
                    $merged = array_values( array_unique( array_merge( $list, $cookie_list ) ) );
                    if ( $merged !== $list ) {
                        $list = $merged;
                        update_user_meta( $user_id, '_fc_wishlist', $list );
                    }
                    // Wyczyść cookie — dane przeniesione do user_meta
                    setcookie( 'fc_wishlist', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
                    unset( $_COOKIE['fc_wishlist'] );
                }
            }

            return $list;
        }
        // Goście — cookie
        if ( isset( $_COOKIE['fc_wishlist'] ) ) {
            $decoded = json_decode( stripslashes( $_COOKIE['fc_wishlist'] ), true );
            return is_array( $decoded ) ? array_map( 'absint', $decoded ) : array();
        }
        return array();
    }

    /**
     * Przy logowaniu — przenieś dane z cookie do user_meta
     */
    public static function merge_cookie_on_login( $user_login, $user ) {
        if ( ! isset( $_COOKIE['fc_wishlist'] ) ) return;
        $cookie_list = json_decode( stripslashes( $_COOKIE['fc_wishlist'] ), true );
        if ( ! is_array( $cookie_list ) || empty( $cookie_list ) ) return;

        $cookie_list = array_map( 'absint', $cookie_list );
        $user_list = get_user_meta( $user->ID, '_fc_wishlist', true );
        $user_list = is_array( $user_list ) ? $user_list : array();

        $merged = array_values( array_unique( array_merge( $user_list, $cookie_list ) ) );
        update_user_meta( $user->ID, '_fc_wishlist', $merged );

        // Wyczyść cookie
        setcookie( 'fc_wishlist', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
    }

    /**
     * Zapisz listę życzeń
     */
    public static function save_wishlist( $list, $user_id = 0 ) {
        $list = array_values( array_unique( array_filter( array_map( 'absint', $list ) ) ) );
        if ( $user_id ) {
            update_user_meta( $user_id, '_fc_wishlist', $list );
        } else {
            setcookie( 'fc_wishlist', wp_json_encode( $list ), time() + ( 365 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
        }
        return $list;
    }

    /**
     * AJAX: Wyczyść całą listę życzeń
     */
    public static function ajax_clear() {
        check_ajax_referer( 'fc_nonce', 'nonce' );
        $user_id = get_current_user_id();
        self::save_wishlist( array(), $user_id );
        // Wyczyść też cookie dla niezalogowanych
        if ( ! $user_id ) {
            setcookie( 'fc_wishlist', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        }
        wp_send_json_success( array(
            'count'   => 0,
            'message' => fc__( 'wishlist_cleared' ),
        ) );
    }

    /**
     * Sprawdź czy produkt jest na liście
     */
    public static function is_in_wishlist( $product_id, $user_id = 0 ) {
        $list = self::get_wishlist( $user_id ?: get_current_user_id() );
        return in_array( absint( $product_id ), $list );
    }

    /**
     * AJAX: Przełącz produkt na liście (zalogowany)
     */
    public static function ajax_toggle() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) wp_send_json_error( fc__( 'missing_id' ) );

        $user_id = get_current_user_id();
        $list = self::get_wishlist( $user_id );

        if ( in_array( $product_id, $list ) ) {
            $list = array_diff( $list, array( $product_id ) );
            $added = false;
        } else {
            $list[] = $product_id;
            $added = true;
        }

        $list = self::save_wishlist( $list, $user_id );

        wp_send_json_success( array(
            'added'   => $added,
            'count'   => count( $list ),
            'message' => $added
                ? fc__( 'added_to_wishlist' )
                : fc__( 'removed_from_wishlist' ),
        ) );
    }

    /**
     * AJAX: Przełącz produkt (gość — cookie)
     */
    public static function ajax_toggle_guest() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) wp_send_json_error( fc__( 'missing_id' ) );

        $list = self::get_wishlist( 0 );

        if ( in_array( $product_id, $list ) ) {
            $list = array_diff( $list, array( $product_id ) );
            $added = false;
        } else {
            $list[] = $product_id;
            $added = true;
        }

        $list = self::save_wishlist( $list, 0 );

        wp_send_json_success( array(
            'added'   => $added,
            'count'   => count( $list ),
            'message' => $added
                ? fc__( 'added_to_wishlist' )
                : fc__( 'removed_from_wishlist' ),
        ) );
    }

    /**
     * AJAX: Pobierz listę ID (do oznaczania serduszek na frontendzie)
     */
    public static function ajax_get() {
        check_ajax_referer( 'fc_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $list = self::get_wishlist( $user_id );
        wp_send_json_success( array( 'items' => $list, 'count' => count( $list ) ) );
    }

    /**
     * Shortcode [fc_wishlist] — strona z listą życzeń
     */
    public static function shortcode( $atts ) {
        if ( ! get_option( 'fc_enable_wishlist', '1' ) ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            include get_404_template();
            exit;
        }
        $user_id = get_current_user_id();
        $list = self::get_wishlist( $user_id );

        ob_start();
        echo '<!--nocache-->';
        ?>
        <div class="fc-wishlist-page">
            <h2><?php fc_e( 'wishlist' ); ?> <span class="fc-wishlist-count">(<?php echo count( $list ); ?>)</span></h2>

            <?php if ( empty( $list ) ) : ?>
                <div class="fc-empty-cart">
                    <p><?php fc_e( 'wishlist_empty' ); ?></p>
                    <a href="<?php echo esc_url( fc_get_shop_url() ); ?>" class="fc-btn"><?php fc_e( 'go_to_shop' ); ?></a>
                </div>
            <?php else : ?>
                <div class="fc-products-grid fc-cols-<?php echo esc_attr( get_theme_mod( 'flavor_archive_columns', 3 ) ); ?>">
                    <?php foreach ( $list as $pid ) :
                        $post = get_post( $pid );
                        if ( ! $post || $post->post_type !== 'fc_product' ) continue;
                        if ( ! in_array( $post->post_status, array( 'fc_published', 'fc_preorder' ) ) ) continue;
                        FC_Shortcodes::render_product_card_static( $pid );
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderuj przycisk serduszka (do wstawienia w karcie produktu)
     */
    public static function render_heart_button( $product_id ) {
        $is_fav = self::is_in_wishlist( $product_id, get_current_user_id() );
        $cls = class_exists( 'FC_Frontend_Features' ) ? FC_Frontend_Features::get_action_btn_classes() : 'fc-action-btn fc-action-btn--circle fc-action-btn--glass';
        ?>
        <button type="button" class="<?php echo esc_attr( $cls ); ?> fc-wishlist-btn<?php echo $is_fav ? ' active' : ''; ?>"
                data-product-id="<?php echo esc_attr( $product_id ); ?>"
                title="<?php echo $is_fav ? fc__( 'remove_from_wishlist' ) : fc__( 'add_to_wishlist' ); ?>">
            <span class="fc-heart"><?php echo $is_fav ? '❤️' : '🤍'; ?></span>
        </button>
        <?php
    }

    /**
     * JS dla wishlist w stopce
     */
    public static function footer_js() {
        if ( is_admin() ) return;
        $wishlist_page_id = get_option( 'fc_page_wishlist' );
        $wishlist_url = $wishlist_page_id ? get_permalink( $wishlist_page_id ) : '';
        ?>
        <script>
        (function(){
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.fc-wishlist-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var pid = btn.getAttribute('data-product-id');
                if (!pid) return;
                var fd = new FormData();
                fd.append('action', 'fc_wishlist_toggle');
                fd.append('product_id', pid);
                fd.append('nonce', fc_ajax.nonce);
                fetch(fc_ajax.url, { method:'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (res.success) {
                            var heart = btn.querySelector('.fc-heart');
                            if (res.data.added) {
                                btn.classList.add('active');
                                if (heart) heart.textContent = '❤️';
                                btn.title = '<?php echo esc_js( fc__( 'remove_from_wishlist' ) ); ?>';
                            } else {
                                btn.classList.remove('active');
                                if (heart) heart.textContent = '🤍';
                                btn.title = '<?php echo esc_js( fc__( 'add_to_wishlist' ) ); ?>';
                            }
                            // Update label on single product button
                            var label = btn.querySelector('span:not(.fc-heart)');
                            if (label && label.className !== 'fc-heart') {
                                label.textContent = res.data.added
                                    ? '<?php echo esc_js( fc__( 'in_wishlist' ) ); ?>'
                                    : '<?php echo esc_js( fc__( 'to_wishlist' ) ); ?>';
                            }
                            // Update all instances of same product
                            document.querySelectorAll('.fc-wishlist-btn[data-product-id="'+pid+'"]').forEach(function(b){
                                if (b !== btn) {
                                    var h2 = b.querySelector('.fc-heart');
                                    if (res.data.added) {
                                        b.classList.add('active');
                                        if(h2) h2.textContent = '❤️';
                                    } else {
                                        b.classList.remove('active');
                                        if(h2) h2.textContent = '🤍';
                                    }
                                }
                            });
                            // Update wishlist page count
                            document.querySelectorAll('.fc-wishlist-count').forEach(function(el){
                                el.textContent = '(' + res.data.count + ')';
                            });
                            // Update header wishlist badge
                            document.querySelectorAll('.fc-header-wishlist-count').forEach(function(badge){
                                badge.textContent = res.data.count;
                                badge.style.display = res.data.count > 0 ? '' : 'none';
                            });
                            // Refresh wishlist panel if open
                            if (document.querySelector('.fc-panel-wishlist.fc-panel-active') && typeof window.fcRefreshWishlistPanel === 'function') {
                                window.fcRefreshWishlistPanel();
                            }
                            // Auto-open wishlist panel on add
                            if (res.data.added && fc_ajax.open_wishlist_on_add === '1' && typeof window.fcRefreshWishlistPanel === 'function') {
                                window.fcRefreshWishlistPanel(function(){
                                    if (typeof window.fcOpenWishlistPanel === 'function') window.fcOpenWishlistPanel();
                                });
                            }
                            // Show toast
                            var wishlistUrl = '<?php echo esc_js( $wishlist_url ); ?>';
                            var toastMsg = res.data.message;
                            if (res.data.added && wishlistUrl) {
                                toastMsg += ' <a href="' + wishlistUrl + '" style="color:inherit;font-weight:600;text-decoration:underline;margin-left:6px;"><?php echo esc_js( fc__( 'view_wishlist' ) ); ?></a>';
                            }
                            if (typeof window.fcShowToast === 'function') {
                                window.fcShowToast(toastMsg, 'success', true);
                            }
                            // If on wishlist page and removed — hide card
                            if (!res.data.added && btn.closest('.fc-wishlist-page')) {
                                var card = btn.closest('.fc-product-card');
                                if (card) {
                                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                                    card.style.opacity = '0';
                                    card.style.transform = 'scale(0.9)';
                                    setTimeout(function(){
                                        card.remove();
                                        if (res.data.count === 0) {
                                            var grid = document.querySelector('.fc-wishlist-page .fc-products-grid');
                                            if (grid) {
                                                grid.innerHTML = '<div class="fc-empty-cart"><p><?php echo esc_js( fc__( 'wishlist_empty' ) ); ?></p><a href="<?php echo esc_js( esc_url( fc_get_shop_url() ) ); ?>" class="fc-btn"><?php echo esc_js( fc__( 'go_to_shop' ) ); ?></a></div>';
                                            }
                                        }
                                    }, 300);
                                }
                            }
                        }
                    });
            });
        })();
        </script>
        <?php
    }
}
