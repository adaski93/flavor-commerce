<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Stripe Payment Gateway for Flavor Commerce
 *
 * Handles:
 * - Admin settings (API keys, test/live mode)
 * - Payment Intent creation via AJAX
 * - Webhook processing (payment_intent.succeeded)
 * - Order status updates after successful payment
 */
class FC_Stripe {

    /** Stripe API version */
    const API_VERSION = '2024-06-20';

    /** Stripe API base URL */
    const API_BASE = 'https://api.stripe.com/v1';

    /* ──────────────────────────────────────────────────────── *
     *  Initialization
     * ──────────────────────────────────────────────────────── */

    public static function init() {
        // Admin settings tab
        add_action( 'fc_payments_tab_after', array( __CLASS__, 'render_settings' ) );
        add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );

        // Frontend: enqueue Stripe.js on checkout, thank-you & retry pages
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        // Server-side redirect: thank-you page with redirect_status=failed → retry page
        add_action( 'template_redirect', array( __CLASS__, 'redirect_failed_to_retry_page' ) );

        // Server-side: save payment type before thank-you page renders (for redirect-based methods like iDEAL)
        add_action( 'template_redirect', array( __CLASS__, 'save_payment_type_on_thank_you' ) );

        // AJAX: create Payment Intent
        add_action( 'wp_ajax_fc_stripe_create_intent', array( __CLASS__, 'ajax_create_intent' ) );
        add_action( 'wp_ajax_nopriv_fc_stripe_create_intent', array( __CLASS__, 'ajax_create_intent' ) );

        // AJAX: confirm payment after order creation
        add_action( 'wp_ajax_fc_stripe_confirm_payment', array( __CLASS__, 'ajax_confirm_payment' ) );
        add_action( 'wp_ajax_nopriv_fc_stripe_confirm_payment', array( __CLASS__, 'ajax_confirm_payment' ) );

        // AJAX: handle failed payment — keep pending, set deadline
        add_action( 'wp_ajax_fc_stripe_payment_failed', array( __CLASS__, 'ajax_payment_failed' ) );
        add_action( 'wp_ajax_nopriv_fc_stripe_payment_failed', array( __CLASS__, 'ajax_payment_failed' ) );

        // AJAX: create new intent for retry payment
        add_action( 'wp_ajax_fc_stripe_retry_intent', array( __CLASS__, 'ajax_retry_intent' ) );
        add_action( 'wp_ajax_nopriv_fc_stripe_retry_intent', array( __CLASS__, 'ajax_retry_intent' ) );

        // Webhook endpoint
        add_action( 'rest_api_init', array( __CLASS__, 'register_webhook' ) );

        // Hook into checkout process — intercept Stripe orders
        add_action( 'fc_order_created', array( __CLASS__, 'handle_order_created' ), 10, 2 );

        // WP Cron: expire pending_payment orders after 1 hour
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
        add_action( 'fc_expire_pending_stripe_orders', array( __CLASS__, 'cron_expire_pending_orders' ) );
        if ( ! wp_next_scheduled( 'fc_expire_pending_stripe_orders' ) ) {
            wp_schedule_event( time(), 'fc_every_5_minutes', 'fc_expire_pending_stripe_orders' );
        }
    }

    /**
     * Add custom 5-minute cron interval
     */
    public static function add_cron_interval( $schedules ) {
        $schedules['fc_every_5_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 minutes',
        );
        return $schedules;
    }

    /* ──────────────────────────────────────────────────────── *
     *  Settings Helpers
     * ──────────────────────────────────────────────────────── */

    /**
     * Check if Stripe gateway is enabled
     */
    public static function is_enabled() {
        return get_option( 'fc_stripe_enabled', '0' ) === '1';
    }

    /**
     * Check if test mode is active
     */
    public static function is_test_mode() {
        return get_option( 'fc_stripe_test_mode', '1' ) === '1';
    }

    /**
     * Get the active secret key (test or live)
     */
    public static function get_secret_key() {
        if ( self::is_test_mode() ) {
            return get_option( 'fc_stripe_test_secret_key', '' );
        }
        return get_option( 'fc_stripe_live_secret_key', '' );
    }

    /**
     * Get the active publishable key (test or live)
     */
    public static function get_publishable_key() {
        if ( self::is_test_mode() ) {
            return get_option( 'fc_stripe_test_publishable_key', '' );
        }
        return get_option( 'fc_stripe_live_publishable_key', '' );
    }

    /**
     * Get the webhook secret for signature verification
     */
    public static function get_webhook_secret() {
        if ( self::is_test_mode() ) {
            return get_option( 'fc_stripe_test_webhook_secret', '' );
        }
        return get_option( 'fc_stripe_live_webhook_secret', '' );
    }

    /**
     * Map WP currency code to Stripe currency (lowercase)
     */
    private static function get_stripe_currency() {
        $currency = strtolower( get_option( 'fc_currency', 'EUR' ) );
        return $currency;
    }

    /**
     * Convert amount to Stripe's smallest unit (e.g. cents)
     * Most currencies use 2 decimal places.
     */
    private static function amount_to_stripe( $amount ) {
        $zero_decimal = array( 'bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','ugx','vnd','vuv','xaf','xof','xpf' );
        $currency = self::get_stripe_currency();
        if ( in_array( $currency, $zero_decimal, true ) ) {
            return intval( round( $amount ) );
        }
        return intval( round( $amount * 100 ) );
    }

    /* ──────────────────────────────────────────────────────── *
     *  Admin Settings UI
     * ──────────────────────────────────────────────────────── */

    /**
     * Render Stripe settings section (called from payments tab)
     */
    public static function render_settings() {
        $enabled        = self::is_enabled();
        $test_mode      = self::is_test_mode();
        $test_pk        = get_option( 'fc_stripe_test_publishable_key', '' );
        $test_sk        = get_option( 'fc_stripe_test_secret_key', '' );
        $test_wh        = get_option( 'fc_stripe_test_webhook_secret', '' );
        $live_pk        = get_option( 'fc_stripe_live_publishable_key', '' );
        $live_sk        = get_option( 'fc_stripe_live_secret_key', '' );
        $live_wh        = get_option( 'fc_stripe_live_webhook_secret', '' );
        $webhook_url    = rest_url( 'flavor-commerce/v1/stripe-webhook' );
        ?>
        <hr style="margin: 2rem 0;">
        <form method="post" style="margin-top: 2rem;">
            <?php wp_nonce_field( 'fc_stripe_settings_nonce' ); ?>

            <h2 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><rect width="32" height="32" rx="6" fill="#635BFF"/><path fill-rule="evenodd" clip-rule="evenodd" d="M15.2 11.6c0-.84.69-1.17 1.83-1.17 1.64 0 3.7.5 5.34 1.38V7.08A14.25 14.25 0 0 0 17.03 6C13.2 6 10.7 7.98 10.7 11.26c0 5.06 6.97 4.25 6.97 6.43 0 1-.87 1.32-2.08 1.32-1.8 0-4.1-.74-5.92-1.73v4.8A15.04 15.04 0 0 0 15.6 23.5c3.92 0 6.62-1.94 6.62-5.27 0-5.46-7.02-4.49-7.02-6.63z" fill="#fff"/></svg>
                Stripe
            </h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_enable_stripe' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="fc_stripe_enabled" value="1" <?php checked( $enabled ); ?>>
                            <?php fc_e( 'stripe_accept_online_payments_via_stripe' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_test_mode' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="fc_stripe_test_mode" value="1" <?php checked( $test_mode ); ?>>
                            <?php fc_e( 'stripe_use_test_keys_sandbox' ); ?>
                        </label>
                        <p class="description"><?php fc_e( 'stripe_in_test_mode_no_real_money_is_charged_use_test_car' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php fc_e( 'stripe_test_keys' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_publishable_key_test' ); ?></th>
                    <td><input type="text" name="fc_stripe_test_publishable_key" value="<?php echo esc_attr( $test_pk ); ?>" class="regular-text" placeholder="pk_test_..."></td>
                </tr>
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_secret_key_test' ); ?></th>
                    <td><input type="password" name="fc_stripe_test_secret_key" value="<?php echo esc_attr( $test_sk ); ?>" class="regular-text" placeholder="sk_test_..."></td>
                </tr>
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_webhook_secret_test' ); ?></th>
                    <td><input type="password" name="fc_stripe_test_webhook_secret" value="<?php echo esc_attr( $test_wh ); ?>" class="regular-text" placeholder="whsec_..."></td>
                </tr>
            </table>

            <h3><?php fc_e( 'stripe_production_keys_live' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_publishable_key_live' ); ?></th>
                    <td><input type="text" name="fc_stripe_live_publishable_key" value="<?php echo esc_attr( $live_pk ); ?>" class="regular-text" placeholder="pk_live_..."></td>
                </tr>
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_secret_key_live' ); ?></th>
                    <td><input type="password" name="fc_stripe_live_secret_key" value="<?php echo esc_attr( $live_sk ); ?>" class="regular-text" placeholder="sk_live_..."></td>
                </tr>
                <tr>
                    <th scope="row"><?php fc_e( 'stripe_webhook_secret_live' ); ?></th>
                    <td><input type="password" name="fc_stripe_live_webhook_secret" value="<?php echo esc_attr( $live_wh ); ?>" class="regular-text" placeholder="whsec_..."></td>
                </tr>
            </table>

            <h3><?php fc_e( 'stripe_webhook_url' ); ?></h3>
            <p class="description">
                <?php fc_e( 'stripe_copy_the_url_below_and_paste_it_in_stripe_dashboar' ); ?>
            </p>
            <p>
                <code style="padding: 8px 12px; background: #f0f0f0; display: inline-block; font-size: 13px;"><?php echo esc_html( $webhook_url ); ?></code>
                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $webhook_url ); ?>'); this.textContent='<?php echo esc_js( fc__( 'stripe_copied' ) ); ?>'; setTimeout(() => this.textContent='<?php echo esc_js( fc__( 'stripe_copy' ) ); ?>', 2000);">
                    <?php fc_e( 'stripe_copy' ); ?>
                </button>
            </p>
            <p class="description">
                <?php fc_e( 'stripe_required_webhook_events_payment_intent_succeeded_p' ); ?>
            </p>

            <?php submit_button( fc__( 'stripe_save_stripe_settings' ), 'primary', 'fc_save_stripe' ); ?>
        </form>
        <?php
    }

    /**
     * Save Stripe settings
     */
    public static function save_settings() {
        if ( ! isset( $_POST['fc_save_stripe'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! check_admin_referer( 'fc_stripe_settings_nonce' ) ) return;

        update_option( 'fc_stripe_enabled', isset( $_POST['fc_stripe_enabled'] ) ? '1' : '0' );
        update_option( 'fc_stripe_test_mode', isset( $_POST['fc_stripe_test_mode'] ) ? '1' : '0' );

        update_option( 'fc_stripe_test_publishable_key', sanitize_text_field( $_POST['fc_stripe_test_publishable_key'] ?? '' ) );
        update_option( 'fc_stripe_test_secret_key', sanitize_text_field( $_POST['fc_stripe_test_secret_key'] ?? '' ) );
        update_option( 'fc_stripe_test_webhook_secret', sanitize_text_field( $_POST['fc_stripe_test_webhook_secret'] ?? '' ) );

        update_option( 'fc_stripe_live_publishable_key', sanitize_text_field( $_POST['fc_stripe_live_publishable_key'] ?? '' ) );
        update_option( 'fc_stripe_live_secret_key', sanitize_text_field( $_POST['fc_stripe_live_secret_key'] ?? '' ) );
        update_option( 'fc_stripe_live_webhook_secret', sanitize_text_field( $_POST['fc_stripe_live_webhook_secret'] ?? '' ) );

        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'stripe_stripe_settings_have_been_saved' ) . '</p></div>';
        } );
    }

    /* ──────────────────────────────────────────────────────── *
     *  Server-side redirect: failed Stripe redirect → retry page
     *  Prevents the thank-you page from flashing before redirect.
     * ──────────────────────────────────────────────────────── */

    public static function redirect_failed_to_retry_page() {
        if ( ! self::is_enabled() ) return;

        $thank_you_page_id = get_option( 'fc_page_podziekowanie' );
        if ( ! $thank_you_page_id || ! is_page( $thank_you_page_id ) ) return;

        $redirect_status = isset( $_GET['redirect_status'] ) ? sanitize_text_field( $_GET['redirect_status'] ) : '';
        if ( $redirect_status !== 'failed' ) return;

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        if ( ! $order_id ) return;

        // Verify order access
        if ( ! self::verify_order_access( $order_id ) ) return;

        $current_status = get_post_meta( $order_id, '_fc_order_status', true );

        // Don't redirect already-paid orders
        if ( in_array( $current_status, array( 'processing', 'shipped', 'completed' ), true ) ) return;

        // Mark as pending_payment + set deadline
        update_post_meta( $order_id, '_fc_order_status', 'pending_payment' );
        if ( ! get_post_meta( $order_id, '_fc_payment_deadline', true ) ) {
            update_post_meta( $order_id, '_fc_payment_deadline', wp_date( 'Y-m-d H:i:s', time() + 3600 ) );
        }

        $intent_id = isset( $_GET['payment_intent'] ) ? sanitize_text_field( $_GET['payment_intent'] ) : '';
        if ( $intent_id ) {
            update_post_meta( $order_id, '_fc_stripe_last_error', 'Payment failed after redirect' );
        }

        // Build retry URL
        $retry_page_id = get_option( 'fc_page_platnosc_nieudana' );
        $retry_url = $retry_page_id ? get_permalink( $retry_page_id ) : get_permalink( $thank_you_page_id );
        $retry_url = add_query_arg( 'order_id', $order_id, $retry_url );

        $order_token = get_post_meta( $order_id, '_fc_order_token', true );
        if ( $order_token ) {
            $retry_url = add_query_arg( 'token', $order_token, $retry_url );
        }

        wp_safe_redirect( $retry_url );
        exit;
    }

    /* ──────────────────────────────────────────────────────── *
     *  Server-side: save payment type before thank-you renders
     *  For redirect-based methods (iDEAL, Przelewy24, etc.)
     *  the customer returns with ?payment_intent=pi_xxx in URL.
     *  We fetch the intent from Stripe and save the payment type
     *  BEFORE the shortcode renders, so the label is correct.
     * ──────────────────────────────────────────────────────── */

    public static function save_payment_type_on_thank_you() {
        if ( ! self::is_enabled() ) return;

        $thank_you_page_id = get_option( 'fc_page_podziekowanie' );
        if ( ! $thank_you_page_id || ! is_page( $thank_you_page_id ) ) return;

        $order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $intent_id = isset( $_GET['payment_intent'] ) ? sanitize_text_field( $_GET['payment_intent'] ) : '';

        if ( ! $order_id || ! $intent_id ) return;

        // Already saved — skip API call
        $existing = get_post_meta( $order_id, '_fc_stripe_payment_type', true );
        if ( $existing ) return;

        // Verify this is a Stripe order
        $payment_method = get_post_meta( $order_id, '_fc_payment_method', true );
        if ( $payment_method !== 'stripe' ) return;

        // Save intent ID if not yet saved
        $stored_intent = get_post_meta( $order_id, '_fc_stripe_intent_id', true );
        if ( ! $stored_intent ) {
            update_post_meta( $order_id, '_fc_stripe_intent_id', $intent_id );
        }

        // Fetch intent from Stripe and save type
        $intent = self::api_get_payment_intent( $intent_id );
        if ( ! is_wp_error( $intent ) ) {
            self::save_payment_type_from_intent( $order_id, $intent );
        }
    }

    /* ──────────────────────────────────────────────────────── *
     *  Frontend: Enqueue Stripe.js
     * ──────────────────────────────────────────────────────── */

    public static function enqueue_scripts() {
        if ( ! self::is_enabled() ) return;

        // Load on checkout, thank-you and retry payment pages
        $checkout_page_id  = get_option( 'fc_page_zamowienie' );
        $thank_you_page_id = get_option( 'fc_page_podziekowanie' );
        $retry_page_id     = get_option( 'fc_page_platnosc_nieudana' );
        $is_checkout  = $checkout_page_id && is_page( $checkout_page_id );
        $is_thank_you = $thank_you_page_id && is_page( $thank_you_page_id );
        $is_retry     = $retry_page_id && is_page( $retry_page_id );

        if ( ! $is_checkout && ! $is_thank_you && ! $is_retry ) return;

        $pk = self::get_publishable_key();
        if ( empty( $pk ) ) return;

        // Stripe.js v3
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );

        // Our Stripe handler
        wp_enqueue_script(
            'fc-stripe',
            FC_PLUGIN_URL . 'assets/js/fc-stripe.js',
            array( 'jquery', 'stripe-js', 'flavor-commerce' ),
            FC_VERSION,
            true
        );

        wp_localize_script( 'fc-stripe', 'fc_stripe_params', array(
            'publishable_key' => $pk,
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'fc_stripe_nonce' ),
            'currency'        => self::get_stripe_currency(),
            'locale'          => get_option( 'fc_frontend_lang', 'en' ),
            'return_url'      => get_permalink( get_option( 'fc_page_podziekowanie' ) ),
            'retry_url'       => get_permalink( get_option( 'fc_page_platnosc_nieudana' ) ) ?: get_permalink( get_option( 'fc_page_podziekowanie' ) ),
            'checkout_url'    => get_permalink( get_option( 'fc_page_zamowienie' ) ),
            'i18n' => array(
                'processing'        => fc__( 'stripe_processing' ),
                'payment_error'     => fc__( 'stripe_payment_error' ),
                'card_error'        => fc__( 'stripe_card_error' ),
                'generic_error'     => fc__( 'stripe_generic_error' ),
                'payment_success'   => fc__( 'stripe_payment_success' ),
                'payment_failed_redirect' => fc__( 'stripe_payment_failed_redirect' ),
                'payment_expired'   => fc__( 'retry_order_expired' ),
                'retry_pay_now'     => fc__( 'retry_pay_now' ),
            ),
        ) );

        // Extra CSS for Stripe card element
        wp_add_inline_style( 'flavor-commerce', self::get_inline_css() );
    }

    /**
     * Inline CSS for Stripe Payment Element
     */
    private static function get_inline_css() {
        return '
/* Stripe Payment Element wrapper */
.fc-stripe-payment-wrapper {
    display: none;
    margin-top: 1rem;
    padding: 1.25rem;
    background: var(--fc-surface, #fff);
    border: 1px solid var(--fc-border, #e2e8f0);
    border-radius: var(--fc-card-radius, 8px);
}
.fc-stripe-payment-wrapper.active {
    display: block;
}
.fc-stripe-payment-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--fc-text, #1d2327);
    margin-bottom: 0.75rem;
}
#fc-stripe-payment-element {
    min-height: 120px;
}
.fc-stripe-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 120px;
}
.fc-stripe-element-error {
    color: #e74c3c;
    font-size: 0.875rem;
    text-align: center;
    padding: 1rem;
}
.fc-stripe-errors {
    color: #e74c3c;
    font-size: 0.8125rem;
    margin-top: 0.5rem;
    min-height: 1.2em;
}
.fc-stripe-test-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #ff6b00;
    background: #fff3e6;
    padding: 3px 8px;
    border-radius: 4px;
}
.fc-stripe-powered {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 0.75rem;
    font-size: 0.75rem;
    color: var(--fc-text-light, #6b7c93);
}
.fc-stripe-powered svg {
    height: 14px;
    width: auto;
}
.fc-stripe-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}
.fc-stripe-overlay-inner {
    background: #fff;
    border-radius: 12px;
    padding: 2rem 2.5rem;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    max-width: 360px;
}
.fc-stripe-overlay-inner .fc-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: var(--fc-accent, #d4a843);
    border-radius: 50%;
    animation: fc-spin 0.8s linear infinite;
    margin: 0 auto 1rem;
}
@keyframes fc-spin {
    to { transform: rotate(360deg); }
}
.fc-checkout-form.fc-stripe-processing .fc-btn-checkout {
    pointer-events: none;
    opacity: 0.6;
}

/* ── Retry Payment Page ── */
.fc-retry-payment {
    max-width: 100%;
}
.fc-retry-header {
    text-align: center;
    margin-bottom: 1.5rem;
}
.fc-retry-header h2 {
    margin: 0.5rem 0 0.25rem;
    font-size: 1.35rem;
}
.fc-retry-header p {
    margin: 0;
    color: var(--fc-text-light, #6b7c93);
    font-size: 0.875rem;
}
.fc-retry-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    font-size: 1.75rem;
    font-weight: 700;
    background: #fff3e6;
    color: #e67e22;
    line-height: 1;
}
.fc-retry-icon.expired {
    background: #fdecea;
    color: #e74c3c;
}
.fc-retry-info {
    background: var(--fc-surface, #f9fafb);
    border: 1px solid var(--fc-border, #e2e8f0);
    border-radius: var(--fc-card-radius, 8px);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    line-height: 1.6;
}
.fc-retry-info p {
    margin: 0 0 0.5rem;
}
.fc-retry-info p:last-child {
    margin-bottom: 0;
}
.fc-retry-countdown {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--fc-text, #1d2327);
    padding: 0.5rem 0 0.25rem;
}
.fc-countdown-icon {
    font-size: 1.1rem;
}
.fc-countdown-timer {
    font-variant-numeric: tabular-nums;
    color: var(--fc-accent, #d4a843);
}
.fc-retry-account-link {
    color: var(--fc-text-light, #6b7c93);
    font-size: 0.8125rem;
}
.fc-retry-account-link a {
    color: var(--fc-accent, #d4a843);
    text-decoration: underline;
}
.fc-retry-summary {
    margin-bottom: 1.5rem;
}
.fc-retry-summary h3 {
    font-size: 1rem;
    margin: 0 0 0.75rem;
}
.fc-retry-payment-form {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
#fc-stripe-retry-element {
    min-height: 120px;
    margin-bottom: 1rem;
    width: 100%;
}
#fc-stripe-retry-btn {
    width: auto;
    margin-top: 0.75rem;
    padding: 0.85rem 1.5rem;
    font-size: 1rem;
}
#fc-stripe-retry-btn:disabled {
    opacity: 0.6;
    pointer-events: none;
}

/* Retry Expired */
.fc-retry-expired .fc-retry-icon {
    background: #fdecea;
    color: #e74c3c;
}
.fc-retry-payment {
    display: flex;
    flex-direction: column;
    align-items: stretch;
}
.fc-retry-payment > .fc-btn {
    align-self: flex-end;
    text-align: center;
    margin-top: 1.5rem;
    margin-bottom: 2rem;
}

/* Account: retry banner */
.fc-retry-banner {
    background: #fff8ee;
    border: 1px solid #f0dbb8;
    border-radius: var(--fc-card-radius, 8px);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
}
.fc-retry-banner p {
    margin: 0 0 0.5rem;
}
.fc-retry-countdown-inline {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
}
.fc-countdown-table {
    color: #b45309;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}
.fc-btn-pay {
    background: var(--fc-accent, #d4a843);
    color: #fff;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.2s;
}
.fc-btn-pay:hover {
    opacity: 0.85;
    color: #fff;
}
/* Standalone retry banner button (not in table) */
.fc-retry-banner .fc-btn-pay {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 1rem;
    border-radius: var(--fc-btn-radius, 6px);
    font-size: 0.8125rem;
}

/* Account orders table: action buttons */
.fc-order-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
';
    }

    /* ──────────────────────────────────────────────────────── *
     *  Stripe API Calls (using wp_remote_post — no SDK needed)
     * ──────────────────────────────────────────────────────── */

    /**
     * Create a Payment Intent
     */
    private static function api_create_payment_intent( $amount, $currency, $metadata = array() ) {
        $body = array(
            'amount'               => $amount,
            'currency'             => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'metadata'             => $metadata,
        );

        return self::api_request( 'POST', '/payment_intents', $body );
    }

    /**
     * Retrieve a Payment Intent
     */
    private static function api_get_payment_intent( $intent_id ) {
        return self::api_request( 'GET', '/payment_intents/' . $intent_id );
    }

    /**
     * Generic Stripe API request
     */
    private static function api_request( $method, $endpoint, $body = array() ) {
        $secret_key = self::get_secret_key();
        if ( empty( $secret_key ) ) {
            return new WP_Error( 'stripe_no_key', 'Stripe secret key is not configured.' );
        }

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization'  => 'Bearer ' . $secret_key,
                'Stripe-Version' => self::API_VERSION,
            ),
            'timeout' => 30,
        );

        if ( $method === 'POST' && ! empty( $body ) ) {
            $args['body'] = self::flatten_params( $body );
        }

        $url = self::API_BASE . $endpoint;
        if ( $method === 'GET' && ! empty( $body ) ) {
            $url = add_query_arg( self::flatten_params( $body ), $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 400 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe API error';
            return new WP_Error( 'stripe_api_error', $msg, $body );
        }

        return $body;
    }

    /**
     * Flatten nested arrays for Stripe's bracket notation
     * e.g. ['metadata' => ['order_id' => 1]] → ['metadata[order_id]' => 1]
     */
    private static function flatten_params( $params, $prefix = '' ) {
        $result = array();
        foreach ( $params as $key => $value ) {
            $full_key = $prefix ? $prefix . '[' . $key . ']' : $key;
            if ( is_array( $value ) ) {
                $result = array_merge( $result, self::flatten_params( $value, $full_key ) );
            } else {
                $result[ $full_key ] = $value;
            }
        }
        return $result;
    }

    /* ──────────────────────────────────────────────────────── *
     *  AJAX: Create Payment Intent
     * ──────────────────────────────────────────────────────── */

    public static function ajax_create_intent() {
        check_ajax_referer( 'fc_stripe_nonce', 'nonce' );

        if ( ! self::is_enabled() ) {
            wp_send_json_error( array( 'message' => fc__( 'stripe_not_enabled' ) ) );
        }

        if ( FC_Cart::is_empty() ) {
            wp_send_json_error( array( 'message' => fc__( 'cart_empty' ) ) );
        }

        // Calculate total
        $cart_total = FC_Cart::get_total();

        // Account for shipping cost (validate server-side — must not be negative)
        $shipping_cost = max( 0, floatval( $_POST['shipping_cost'] ?? 0 ) );

        // Account for coupon discount
        $coupon_discount = 0;
        if ( class_exists( 'FC_Coupons' ) ) {
            $session_coupons = FC_Coupons::get_session_coupons();
            $running_total = $cart_total;
            foreach ( $session_coupons as $sc ) {
                $result = FC_Coupons::validate( $sc['code'], $cart_total, get_current_user_id() );
                if ( $result['valid'] ) {
                    $disc = FC_Coupons::calculate_discount( $result['coupon_id'], $running_total );
                    $coupon_discount += $disc;
                    $running_total = max( 0, $running_total - $disc );
                }
            }
        }

        $total = max( 0, $cart_total - $coupon_discount ) + $shipping_cost;

        if ( $total <= 0 ) {
            wp_send_json_error( array( 'message' => fc__( 'stripe_total_zero' ) ) );
        }

        $stripe_amount = self::amount_to_stripe( $total );
        $currency      = self::get_stripe_currency();

        // Customer email for receipt
        $email = sanitize_email( $_POST['billing_email'] ?? '' );

        $metadata = array(
            'store'    => get_bloginfo( 'name' ),
            'customer' => $email,
        );

        $intent = self::api_create_payment_intent( $stripe_amount, $currency, $metadata );

        if ( is_wp_error( $intent ) ) {
            wp_send_json_error( array( 'message' => $intent->get_error_message() ) );
        }

        wp_send_json_success( array(
            'clientSecret' => $intent['client_secret'],
            'intentId'     => $intent['id'],
            'amount'       => $total,
        ) );
    }

    /* ──────────────────────────────────────────────────────── *
     *  AJAX: Confirm Payment (after order creation)
     * ──────────────────────────────────────────────────────── */

    public static function ajax_confirm_payment() {
        check_ajax_referer( 'fc_stripe_nonce', 'nonce' );

        $order_id  = absint( $_POST['order_id'] ?? 0 );
        $intent_id = sanitize_text_field( $_POST['intent_id'] ?? '' );

        if ( ! $order_id || ! $intent_id ) {
            wp_send_json_error( array( 'message' => fc__( 'missing_order_data' ) ) );
        }

        // Verify order belongs to current user or is guest order
        if ( ! self::verify_order_access( $order_id ) ) {
            wp_send_json_error( array( 'message' => fc__( 'access_denied' ) ) );
        }

        // Save intent ID on order
        update_post_meta( $order_id, '_fc_stripe_intent_id', $intent_id );

        // Check payment status
        $intent = self::api_get_payment_intent( $intent_id );

        if ( is_wp_error( $intent ) ) {
            wp_send_json_error( array( 'message' => $intent->get_error_message() ) );
        }

        if ( $intent['status'] === 'succeeded' ) {
            // Save payment method type
            self::save_payment_type_from_intent( $order_id, $intent );
            self::mark_order_paid( $order_id, $intent_id );
            wp_send_json_success( array( 'status' => 'succeeded' ) );
        } elseif ( $intent['status'] === 'requires_action' || $intent['status'] === 'requires_confirmation' ) {
            wp_send_json_success( array(
                'status'       => $intent['status'],
                'clientSecret' => $intent['client_secret'],
            ) );
        } else {
            wp_send_json_success( array( 'status' => $intent['status'] ) );
        }
    }

    /**
     * AJAX: Handle failed payment — keep order as pending_payment, set 1-hour deadline
     * Does NOT restore stock or cart — order stays valid for retry.
     */
    public static function ajax_payment_failed() {
        check_ajax_referer( 'fc_stripe_nonce', 'nonce' );

        $order_id  = absint( $_POST['order_id'] ?? 0 );
        $intent_id = sanitize_text_field( $_POST['intent_id'] ?? '' );

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => fc__( 'missing_order_data' ) ) );
        }

        if ( ! self::verify_order_access( $order_id ) ) {
            wp_send_json_error( array( 'message' => fc__( 'access_denied' ) ) );
        }

        $current_status = get_post_meta( $order_id, '_fc_order_status', true );

        // Don't touch already-paid orders
        if ( in_array( $current_status, array( 'processing', 'shipped', 'completed' ), true ) ) {
            wp_send_json_error( array( 'message' => fc__( 'order_already_paid' ) ) );
        }

        // Keep as pending_payment (don't mark as failed yet)
        update_post_meta( $order_id, '_fc_order_status', 'pending_payment' );

        // Set payment deadline: 1 hour from now (only if not already set)
        if ( ! get_post_meta( $order_id, '_fc_payment_deadline', true ) ) {
            update_post_meta( $order_id, '_fc_payment_deadline', wp_date( 'Y-m-d H:i:s', time() + 3600 ) );
        }

        if ( $intent_id ) {
            update_post_meta( $order_id, '_fc_stripe_last_error', 'Payment failed or cancelled by customer' );
        }

        // Build retry URL
        $retry_page_id = get_option( 'fc_page_platnosc_nieudana' );
        $retry_url = $retry_page_id ? get_permalink( $retry_page_id ) : get_permalink( get_option( 'fc_page_podziekowanie' ) );
        $retry_url = add_query_arg( 'order_id', $order_id, $retry_url );

        // Add token for guest orders
        $order_token = get_post_meta( $order_id, '_fc_order_token', true );
        if ( $order_token ) {
            $retry_url = add_query_arg( 'token', $order_token, $retry_url );
        }

        wp_send_json_success( array(
            'status'    => 'pending_payment',
            'retry_url' => $retry_url,
        ) );
    }

    /**
     * AJAX: Create new PaymentIntent for retry payment on existing order
     */
    public static function ajax_retry_intent() {
        check_ajax_referer( 'fc_stripe_nonce', 'nonce' );

        if ( ! self::is_enabled() ) {
            wp_send_json_error( array( 'message' => fc__( 'stripe_not_enabled' ) ) );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => fc__( 'missing_order_id' ) ) );
        }

        if ( ! self::verify_order_access( $order_id ) ) {
            wp_send_json_error( array( 'message' => fc__( 'access_denied' ) ) );
        }

        $current_status = get_post_meta( $order_id, '_fc_order_status', true );

        // Only allow retry for pending_payment orders
        if ( $current_status !== 'pending_payment' ) {
            wp_send_json_error( array( 'message' => fc__( 'retry_order_already_processed' ) ) );
        }

        // Check deadline
        $deadline = get_post_meta( $order_id, '_fc_payment_deadline', true );
        if ( $deadline && strtotime( $deadline ) < time() ) {
            // Expired — cancel the order
            self::cancel_expired_order( $order_id );
            wp_send_json_error( array( 'message' => fc__( 'retry_order_expired' ) ) );
        }

        // Get order total
        $total = floatval( get_post_meta( $order_id, '_fc_order_total', true ) );
        if ( $total <= 0 ) {
            wp_send_json_error( array( 'message' => fc__( 'invalid_order_total' ) ) );
        }

        $stripe_amount = self::amount_to_stripe( $total );
        $currency      = self::get_stripe_currency();
        $number        = get_post_meta( $order_id, '_fc_order_number', true );

        $metadata = array(
            'store'      => get_bloginfo( 'name' ),
            'order_id'   => $order_id,
            'order_no'   => $number,
            'retry'      => 'true',
        );

        $intent = self::api_create_payment_intent( $stripe_amount, $currency, $metadata );

        if ( is_wp_error( $intent ) ) {
            wp_send_json_error( array( 'message' => $intent->get_error_message() ) );
        }

        // Save new intent ID on order
        update_post_meta( $order_id, '_fc_stripe_intent_id', $intent['id'] );

        // Gather billing details from order for Payment Element
        $customer = get_post_meta( $order_id, '_fc_customer', true );
        $billing  = array();
        if ( is_array( $customer ) ) {
            $billing['name']  = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
            $billing['email'] = $customer['email'] ?? '';
            $billing['phone'] = trim( ( $customer['phone_prefix'] ?? '' ) . ' ' . ( $customer['phone'] ?? '' ) );
            $billing['address'] = array(
                'country'     => strtoupper( $customer['country'] ?? '' ),
                'city'        => $customer['city'] ?? '',
                'postal_code' => $customer['postcode'] ?? '',
                'line1'       => $customer['address'] ?? '',
            );
        }

        wp_send_json_success( array(
            'clientSecret'   => $intent['client_secret'],
            'intentId'       => $intent['id'],
            'amount'         => $total,
            'billingDetails' => $billing,
        ) );
    }

    /**
     * Cancel an expired pending_payment order — restore stock
     */
    private static function cancel_expired_order( $order_id ) {
        $current_status = get_post_meta( $order_id, '_fc_order_status', true );
        if ( in_array( $current_status, array( 'processing', 'shipped', 'completed' ), true ) ) {
            return; // Already paid
        }

        update_post_meta( $order_id, '_fc_order_status', 'cancelled' );
        update_post_meta( $order_id, '_fc_stripe_error', 'Payment deadline expired' );
        self::restore_stock( $order_id );
    }

    /**
     * WP Cron: Find and expire pending_payment Stripe orders past their deadline
     * Also cancels abandoned 'pending' Stripe orders older than 1 hour with no payment attempt.
     */
    public static function cron_expire_pending_orders() {
        // 1) Orders with pending_payment status and expired deadline
        $orders = get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => '_fc_order_status',
                    'value' => 'pending_payment',
                ),
                array(
                    'key'   => '_fc_payment_method',
                    'value' => 'stripe',
                ),
                array(
                    'key'     => '_fc_payment_deadline',
                    'value'   => current_time( 'mysql' ),
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
            'no_found_rows' => true,
        ) );

        foreach ( $orders as $order_id ) {
            self::cancel_expired_order( $order_id );
        }

        // 2) Abandoned 'pending' Stripe orders — no payment attempted within 1 hour of creation
        $cutoff = wp_date( 'Y-m-d H:i:s', time() - 3600 );
        $abandoned = get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'date_query'     => array(
                array( 'before' => $cutoff ),
            ),
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => '_fc_order_status',
                    'value' => 'pending',
                ),
                array(
                    'key'   => '_fc_payment_method',
                    'value' => 'stripe',
                ),
            ),
            'no_found_rows' => true,
        ) );

        foreach ( $abandoned as $order_id ) {
            self::cancel_expired_order( $order_id );
        }
    }

    /**
     * Check if an order is still within its payment deadline
     */
    public static function is_order_payable( $order_id ) {
        $status = get_post_meta( $order_id, '_fc_order_status', true );
        if ( $status !== 'pending_payment' ) return false;

        $payment = get_post_meta( $order_id, '_fc_payment_method', true );
        if ( $payment !== 'stripe' ) return false;

        $deadline = get_post_meta( $order_id, '_fc_payment_deadline', true );
        if ( $deadline && strtotime( $deadline ) < time() ) return false;

        return true;
    }

    /**
     * Get remaining seconds until payment deadline
     */
    public static function get_deadline_remaining( $order_id ) {
        $deadline = get_post_meta( $order_id, '_fc_payment_deadline', true );
        if ( ! $deadline ) return 3600;
        $remaining = strtotime( $deadline ) - time();
        return max( 0, $remaining );
    }

    /**
     * Verify that current user can access this order
     */
    private static function verify_order_access( $order_id ) {
        if ( get_post_type( $order_id ) !== 'fc_order' ) return false;

        $order_customer_id = get_post_meta( $order_id, '_fc_customer_id', true );

        // Logged in user — must be order owner
        if ( is_user_logged_in() && intval( $order_customer_id ) === get_current_user_id() ) {
            return true;
        }

        // Token-based access — works for guest orders AND for users whose session
        // was lost after Stripe redirect (e.g. registered during checkout)
        $token = sanitize_text_field( $_POST['order_token'] ?? ( $_GET['token'] ?? '' ) );
        $stored_token = get_post_meta( $order_id, '_fc_order_token', true );

        if ( ! empty( $stored_token ) && ! empty( $token ) && hash_equals( $stored_token, $token ) ) {
            return true;
        }

        return false;
    }

    /* ──────────────────────────────────────────────────────── *
     *  Hook: After order is created in FC_Checkout
     * ──────────────────────────────────────────────────────── */

    /**
     * When a Stripe order is created, set status to 'pending_payment'
     * and save intent reference
     */
    public static function handle_order_created( $order_id, $payment_method ) {
        if ( $payment_method !== 'stripe' ) return;

        update_post_meta( $order_id, '_fc_order_status', 'pending_payment' );
    }

    /* ──────────────────────────────────────────────────────── *
     *  Webhook Handler
     * ──────────────────────────────────────────────────────── */

    /**
     * Register REST API route for Stripe webhooks
     */
    public static function register_webhook() {
        register_rest_route( 'flavor-commerce/v1', '/stripe-webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'process_webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Process incoming Stripe webhook
     */
    public static function process_webhook( $request ) {
        $payload   = $request->get_body();
        $sig       = $request->get_header( 'stripe-signature' );
        $secret    = self::get_webhook_secret();

        // Verify signature — reject if webhook secret is not configured
        if ( empty( $secret ) ) {
            return new WP_REST_Response( array( 'error' => 'Webhook secret not configured.' ), 500 );
        }

        $verified = self::verify_webhook_signature( $payload, $sig, $secret );
        if ( is_wp_error( $verified ) ) {
            return new WP_REST_Response( array( 'error' => $verified->get_error_message() ), 400 );
        }

        $event = json_decode( $payload, true );

        if ( ! $event || ! isset( $event['type'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
        }

        $type = $event['type'];
        $data = $event['data']['object'] ?? array();

        switch ( $type ) {
            case 'payment_intent.succeeded':
                self::webhook_payment_succeeded( $data );
                break;

            case 'payment_intent.payment_failed':
                self::webhook_payment_failed( $data );
                break;
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Handle payment_intent.succeeded webhook
     */
    private static function webhook_payment_succeeded( $intent ) {
        $intent_id = $intent['id'] ?? '';
        if ( empty( $intent_id ) ) return;

        // Find order by Stripe intent ID
        $order_id = self::find_order_by_intent( $intent_id );
        if ( ! $order_id ) return;

        // Save the specific payment method type (card, ideal, p24, klarna, etc.)
        self::save_payment_type_from_intent( $order_id, $intent );

        self::mark_order_paid( $order_id, $intent_id );
    }

    /**
     * Handle payment_intent.payment_failed webhook
     * Keep order as pending_payment with deadline — don't mark as failed immediately.
     */
    private static function webhook_payment_failed( $intent ) {
        $intent_id = $intent['id'] ?? '';
        if ( empty( $intent_id ) ) return;

        $order_id = self::find_order_by_intent( $intent_id );
        if ( ! $order_id ) return;

        $current_status = get_post_meta( $order_id, '_fc_order_status', true );

        // Don't overwrite already-paid orders
        if ( in_array( $current_status, array( 'processing', 'shipped', 'completed' ), true ) ) {
            return;
        }

        $error_msg = $intent['last_payment_error']['message'] ?? 'Payment failed';

        // Keep as pending_payment — give customer time to retry
        update_post_meta( $order_id, '_fc_order_status', 'pending_payment' );
        update_post_meta( $order_id, '_fc_stripe_last_error', $error_msg );

        // Set payment deadline if not already set
        if ( ! get_post_meta( $order_id, '_fc_payment_deadline', true ) ) {
            update_post_meta( $order_id, '_fc_payment_deadline', wp_date( 'Y-m-d H:i:s', time() + 3600 ) );
        }
    }

    /**
     * Restore stock for a failed/cancelled order
     */
    private static function restore_stock( $order_id ) {
        FC_Orders::restore_stock( $order_id );
    }

    /**
     * Mark order as paid
     */
    private static function mark_order_paid( $order_id, $intent_id ) {
        $current_status = get_post_meta( $order_id, '_fc_order_status', true );

        // Don't double-process
        if ( in_array( $current_status, array( 'processing', 'shipped', 'completed' ), true ) ) {
            return;
        }

        update_post_meta( $order_id, '_fc_order_status', 'processing' );
        update_post_meta( $order_id, '_fc_stripe_intent_id', $intent_id );
        update_post_meta( $order_id, '_fc_stripe_paid_at', current_time( 'mysql' ) );

        // Clear retry-related meta
        delete_post_meta( $order_id, '_fc_payment_deadline' );
        delete_post_meta( $order_id, '_fc_stripe_last_error' );
        delete_post_meta( $order_id, '_fc_stock_restored' );

        // Send processing email
        if ( class_exists( 'FC_Settings' ) ) {
            FC_Settings::send_status_email( $order_id, 'processing' );
        }
    }

    /**
     * Extract and save payment method type from a PaymentIntent
     */
    private static function save_payment_type_from_intent( $order_id, $intent ) {
        // Already saved?
        $existing = get_post_meta( $order_id, '_fc_stripe_payment_type', true );
        if ( $existing ) return;

        $pm_type = '';

        // Try payment_method field (string ID)
        if ( ! empty( $intent['payment_method'] ) && is_string( $intent['payment_method'] ) ) {
            $pm = self::api_request( 'GET', '/payment_methods/' . $intent['payment_method'] );
            if ( ! is_wp_error( $pm ) && ! empty( $pm['type'] ) ) {
                $pm_type = $pm['type'];
            }
        }

        // Fallback: charges array (older API responses)
        if ( empty( $pm_type ) && ! empty( $intent['charges']['data'][0]['payment_method_details']['type'] ) ) {
            $pm_type = $intent['charges']['data'][0]['payment_method_details']['type'];
        }

        // Fallback: latest_charge
        if ( empty( $pm_type ) && ! empty( $intent['latest_charge'] ) && is_string( $intent['latest_charge'] ) ) {
            $charge = self::api_request( 'GET', '/charges/' . $intent['latest_charge'] );
            if ( ! is_wp_error( $charge ) && ! empty( $charge['payment_method_details']['type'] ) ) {
                $pm_type = $charge['payment_method_details']['type'];
            }
        }

        if ( $pm_type ) {
            update_post_meta( $order_id, '_fc_stripe_payment_type', sanitize_text_field( $pm_type ) );
        }
    }

    /**
     * Find FC order ID by Stripe Payment Intent ID
     */
    private static function find_order_by_intent( $intent_id ) {
        $query = new WP_Query( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 1,
            'meta_key'       => '_fc_stripe_intent_id',
            'meta_value'     => $intent_id,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );

        return ! empty( $query->posts ) ? $query->posts[0] : 0;
    }

    /**
     * Verify Stripe webhook signature (HMAC SHA256)
     */
    private static function verify_webhook_signature( $payload, $sig_header, $secret ) {
        if ( empty( $sig_header ) ) {
            return new WP_Error( 'no_signature', 'Missing Stripe-Signature header.' );
        }

        // Parse signature header
        $parts = explode( ',', $sig_header );
        $timestamp = null;
        $signatures = array();

        foreach ( $parts as $part ) {
            $kv = explode( '=', trim( $part ), 2 );
            if ( count( $kv ) !== 2 ) continue;

            if ( $kv[0] === 't' ) {
                $timestamp = $kv[1];
            } elseif ( $kv[0] === 'v1' ) {
                $signatures[] = $kv[1];
            }
        }

        if ( ! $timestamp || empty( $signatures ) ) {
            return new WP_Error( 'invalid_signature', 'Invalid signature format.' );
        }

        // Check timestamp tolerance (5 minutes)
        if ( abs( time() - intval( $timestamp ) ) > 300 ) {
            return new WP_Error( 'timestamp_expired', 'Webhook timestamp too old.' );
        }

        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac( 'sha256', $signed_payload, $secret );

        foreach ( $signatures as $sig ) {
            if ( hash_equals( $expected, $sig ) ) {
                return true;
            }
        }

        return new WP_Error( 'signature_mismatch', 'Signature verification failed.' );
    }

    /**
     * Get human-readable label for a Stripe payment method type
     *
     * @param string $type Stripe payment method type (card, ideal, p24, klarna, etc.)
     * @return string Readable label
     */
    public static function get_payment_type_label( $type ) {
        $labels = array(
            'card'            => 'Card',
            'ideal'           => 'iDEAL',
            'p24'             => 'Przelewy24',
            'blik'            => 'BLIK',
            'klarna'          => 'Klarna',
            'bancontact'      => 'Bancontact',
            'sepa_debit'      => 'SEPA Direct Debit',
            'sofort'          => 'Sofort',
            'giropay'         => 'Giropay',
            'eps'             => 'EPS',
            'apple_pay'       => 'Apple Pay',
            'google_pay'      => 'Google Pay',
            'link'            => 'Link',
            'paypal'          => 'PayPal',
            'revolut_pay'     => 'Revolut Pay',
            'affirm'          => 'Affirm',
            'afterpay_clearpay' => 'Afterpay',
            'alipay'          => 'Alipay',
            'wechat_pay'      => 'WeChat Pay',
            'multibanco'      => 'Multibanco',
            'przelewy24'      => 'Przelewy24',
        );

        return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
    }

    /**
     * Get the Stripe payment method label for a specific order
     *
     * @param int $order_id
     * @return string Label like "iDEAL", "Przelewy24", "Card" or fallback "Stripe"
     */
    public static function get_order_payment_label( $order_id ) {
        $type = get_post_meta( $order_id, '_fc_stripe_payment_type', true );
        if ( $type ) {
            return self::get_payment_type_label( $type );
        }
        return 'Stripe';
    }
}
