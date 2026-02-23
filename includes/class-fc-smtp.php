<?php
/**
 * FC_SMTP – Konfiguracja SMTP dla wysyłki e-maili.
 *
 * Hookuje się w WordPress phpmailer_init, aby zastąpić domyślny mail()
 * prawdziwym SMTP (Gmail, OVH, dowolny serwer).
 *
 * @package Flavor_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FC_SMTP {

    /** Singleton */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Konfiguruj PHPMailer tylko gdy SMTP jest włączone.
        add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 99 );

        // Nadpisz domyślne From PRZED setFrom() — zapobiega "wordpress@localhost".
        add_filter( 'wp_mail_from',      array( $this, 'filter_mail_from' ), 999 );
        add_filter( 'wp_mail_from_name',  array( $this, 'filter_mail_from_name' ), 999 );

        // AJAX: zapisz ustawienia SMTP.
        add_action( 'wp_ajax_fc_save_smtp', array( $this, 'ajax_save_smtp' ) );

        // AJAX: wyślij testowy e-mail.
        add_action( 'wp_ajax_fc_send_test_email', array( $this, 'ajax_send_test_email' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Filtry wp_mail_from / wp_mail_from_name                           */
    /* ------------------------------------------------------------------ */

    /**
     * Zwróć prawidłowy adres nadawcy zanim WordPress wywoła setFrom().
     */
    public function filter_mail_from( $from ) {
        if ( ! get_option( 'fc_smtp_enabled' ) ) {
            return $from;
        }

        $custom = get_option( 'fc_smtp_from_email', '' );
        if ( ! empty( $custom ) && is_email( $custom ) ) {
            return $custom;
        }

        // Fallback: login SMTP → fc_store_email → admin_email.
        $username = get_option( 'fc_smtp_username', '' );
        if ( ! empty( $username ) && is_email( $username ) ) {
            return $username;
        }

        $store = get_option( 'fc_store_email', '' );
        if ( ! empty( $store ) && is_email( $store ) ) {
            return $store;
        }

        return get_option( 'admin_email', $from );
    }

    /**
     * Zwróć nazwę nadawcy.
     */
    public function filter_mail_from_name( $name ) {
        if ( ! get_option( 'fc_smtp_enabled' ) ) {
            return $name;
        }

        $custom = get_option( 'fc_smtp_from_name', '' );
        if ( ! empty( $custom ) ) {
            return $custom;
        }

        $store_name = get_option( 'fc_store_name', '' );
        if ( ! empty( $store_name ) ) {
            return $store_name;
        }

        return $name;
    }

    /* ------------------------------------------------------------------ */
    /*  PHPMailer hook                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Konfiguruj PHPMailer do użycia SMTP.
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public function configure_phpmailer( $phpmailer ) {
        if ( ! get_option( 'fc_smtp_enabled' ) ) {
            return;
        }

        $host = get_option( 'fc_smtp_host', '' );
        $port = (int) get_option( 'fc_smtp_port', 587 );

        if ( empty( $host ) ) return;

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = $port;
        $phpmailer->SMTPAutoTLS = true;

        $encryption = get_option( 'fc_smtp_encryption', 'tls' );
        if ( $encryption === 'none' ) {
            $phpmailer->SMTPSecure  = '';
            $phpmailer->SMTPAutoTLS = false;
        } else {
            $phpmailer->SMTPSecure = $encryption; // 'tls' or 'ssl'
        }

        if ( get_option( 'fc_smtp_auth', 1 ) ) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = get_option( 'fc_smtp_username', '' );
            $phpmailer->Password = self::decrypt_password( get_option( 'fc_smtp_password_enc', '' ) );
        } else {
            $phpmailer->SMTPAuth = false;
        }

        // Nadpisz From / From-Name — zawsze, aby uniknąć wordpress@localhost.
        $from_email = get_option( 'fc_smtp_from_email', '' );
        if ( empty( $from_email ) ) {
            // Fallback: login SMTP (zwykle adres e-mail) → fc_store_email → admin_email.
            $from_email = get_option( 'fc_smtp_username', '' );
        }
        if ( empty( $from_email ) ) {
            $from_email = get_option( 'fc_store_email', get_option( 'admin_email' ) );
        }
        if ( ! empty( $from_email ) && is_email( $from_email ) ) {
            $phpmailer->From   = $from_email;
            $phpmailer->Sender = $from_email;
        }

        $from_name = get_option( 'fc_smtp_from_name', '' );
        if ( empty( $from_name ) ) {
            $from_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        }
        if ( ! empty( $from_name ) ) {
            $phpmailer->FromName = $from_name;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Szyfrowanie hasła (simple XOR + base64, nie plain text w DB)       */
    /* ------------------------------------------------------------------ */

    private static function get_encryption_key() {
        if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
            return AUTH_KEY;
        }
        return 'fc-smtp-default-key';
    }

    public static function encrypt_password( $plain ) {
        if ( '' === $plain ) return '';
        $key    = self::get_encryption_key();
        $result = '';
        for ( $i = 0; $i < strlen( $plain ); $i++ ) {
            $result .= chr( ord( $plain[ $i ] ) ^ ord( $key[ $i % strlen( $key ) ] ) );
        }
        return base64_encode( $result );
    }

    public static function decrypt_password( $encoded ) {
        if ( '' === $encoded ) return '';
        $decoded = base64_decode( $encoded );
        if ( false === $decoded ) return '';
        $key    = self::get_encryption_key();
        $result = '';
        for ( $i = 0; $i < strlen( $decoded ); $i++ ) {
            $result .= chr( ord( $decoded[ $i ] ) ^ ord( $key[ $i % strlen( $key ) ] ) );
        }
        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX – zapisz ustawienia SMTP                                     */
    /* ------------------------------------------------------------------ */

    public function ajax_save_smtp() {
        check_ajax_referer( 'fc_smtp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }

        update_option( 'fc_smtp_enabled',    absint( $_POST['enabled'] ?? 0 ) );
        update_option( 'fc_smtp_host',       sanitize_text_field( $_POST['host'] ?? '' ) );
        update_option( 'fc_smtp_port',       absint( $_POST['port'] ?? 587 ) );
        update_option( 'fc_smtp_encryption', sanitize_key( $_POST['encryption'] ?? 'tls' ) );
        update_option( 'fc_smtp_auth',       absint( $_POST['auth'] ?? 1 ) );
        update_option( 'fc_smtp_username',   sanitize_text_field( $_POST['username'] ?? '' ) );
        update_option( 'fc_smtp_from_email', sanitize_email( $_POST['from_email'] ?? '' ) );
        update_option( 'fc_smtp_from_name',  sanitize_text_field( $_POST['from_name'] ?? '' ) );

        // Hasło – jeśli przesłano nowe (nie placeholder).
        $raw_pass = $_POST['password'] ?? '';
        if ( $raw_pass !== '' && $raw_pass !== '••••••••' ) {
            update_option( 'fc_smtp_password_enc', self::encrypt_password( $raw_pass ) );
        }

        wp_send_json_success( array( 'message' => fc__( 'smtp_saved' ) ) );
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX – wyślij testowy e-mail                                      */
    /* ------------------------------------------------------------------ */

    public function ajax_send_test_email() {
        check_ajax_referer( 'fc_smtp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }

        $to = sanitize_email( $_POST['to'] ?? '' );
        if ( empty( $to ) ) {
            wp_send_json_error( array( 'message' => fc__( 'smtp_test_enter_email' ) ) );
        }

        $store_name = get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        $subject    = sprintf( fc__( 'smtp_test_subject' ), $store_name );
        $body       = '<div style="font-family:sans-serif;max-width:480px;margin:20px auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;">'
                    . '<h2 style="margin-top:0;color:#333;">✅ ' . esc_html( fc__( 'smtp_test_success_title' ) ) . '</h2>'
                    . '<p style="color:#555;">' . esc_html( fc__( 'smtp_test_success_body' ) ) . '</p>'
                    . '<p style="color:#888;font-size:13px;">Flavor Commerce — ' . esc_html( $store_name ) . '</p>'
                    . '</div>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        // Tymczasowo zbieramy błędy PHPMailer.
        $error = '';
        $capture_error = function( $wp_error ) use ( &$error ) {
            if ( is_wp_error( $wp_error ) ) {
                $error = $wp_error->get_error_message();
            }
        };
        add_action( 'wp_mail_failed', $capture_error );

        $sent = wp_mail( $to, $subject, $body, $headers );

        remove_action( 'wp_mail_failed', $capture_error );

        if ( $sent ) {
            wp_send_json_success( array( 'message' => sprintf( fc__( 'smtp_test_sent_to' ), $to ) ) );
        } else {
            wp_send_json_error( array(
                'message' => fc__( 'smtp_test_failed' ) . ( $error ? ': ' . $error : '' ),
            ) );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Render – sekcja SMTP w zakładce e-maili                           */
    /* ------------------------------------------------------------------ */

    public static function render_smtp_section() {
        $enabled    = get_option( 'fc_smtp_enabled', 0 );
        $host       = get_option( 'fc_smtp_host', '' );
        $port       = get_option( 'fc_smtp_port', 587 );
        $encryption = get_option( 'fc_smtp_encryption', 'tls' );
        $auth       = get_option( 'fc_smtp_auth', 1 );
        $username   = get_option( 'fc_smtp_username', '' );
        $has_pass   = get_option( 'fc_smtp_password_enc', '' ) !== '';
        $from_email = get_option( 'fc_smtp_from_email', '' );
        $from_name  = get_option( 'fc_smtp_from_name', '' );
        ?>
        <div id="fc-smtp-section" style="margin-top:24px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;overflow:hidden;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:#f6f7f7;border-bottom:1px solid #c3c4c7;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="dashicons dashicons-lock" style="color:#2271b1;font-size:20px;"></span>
                    <strong style="font-size:14px;"><?php fc_e( 'smtp_title' ); ?></strong>
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <span style="font-size:13px;color:#555;"><?php fc_e( 'smtp_enable' ); ?></span>
                    <input type="checkbox" id="fc-smtp-enabled" value="1" <?php checked( $enabled, 1 ); ?> style="margin:0;">
                </label>
            </div>

            <div id="fc-smtp-fields" style="padding:20px;<?php echo $enabled ? '' : 'display:none;'; ?>">
                <p class="description" style="margin-top:0;margin-bottom:16px;"><?php fc_e( 'smtp_description' ); ?></p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:700px;">
                    <!-- Host -->
                    <div>
                        <label for="fc-smtp-host" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_host' ); ?></label>
                        <input type="text" id="fc-smtp-host" value="<?php echo esc_attr( $host ); ?>" class="regular-text" style="width:100%;" placeholder="smtp.gmail.com">
                    </div>
                    <!-- Port -->
                    <div>
                        <label for="fc-smtp-port" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_port' ); ?></label>
                        <input type="number" id="fc-smtp-port" value="<?php echo esc_attr( $port ); ?>" class="small-text" style="width:100%;" placeholder="587">
                    </div>
                    <!-- Szyfrowanie -->
                    <div>
                        <label for="fc-smtp-encryption" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_encryption' ); ?></label>
                        <select id="fc-smtp-encryption" style="width:100%;">
                            <option value="tls" <?php selected( $encryption, 'tls' ); ?>>TLS</option>
                            <option value="ssl" <?php selected( $encryption, 'ssl' ); ?>>SSL</option>
                            <option value="none" <?php selected( $encryption, 'none' ); ?>><?php fc_e( 'smtp_encryption_none' ); ?></option>
                        </select>
                    </div>
                    <!-- Autentykacja -->
                    <div>
                        <label for="fc-smtp-auth" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_authentication' ); ?></label>
                        <select id="fc-smtp-auth" style="width:100%;">
                            <option value="1" <?php selected( $auth, 1 ); ?>><?php fc_e( 'smtp_auth_yes' ); ?></option>
                            <option value="0" <?php selected( $auth, 0 ); ?>><?php fc_e( 'smtp_auth_no' ); ?></option>
                        </select>
                    </div>
                    <!-- Login -->
                    <div id="fc-smtp-auth-fields-user">
                        <label for="fc-smtp-username" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_username' ); ?></label>
                        <input type="text" id="fc-smtp-username" value="<?php echo esc_attr( $username ); ?>" class="regular-text" style="width:100%;" autocomplete="off">
                    </div>
                    <!-- Hasło -->
                    <div id="fc-smtp-auth-fields-pass">
                        <label for="fc-smtp-password" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_password' ); ?></label>
                        <input type="password" id="fc-smtp-password" value="<?php echo $has_pass ? '••••••••' : ''; ?>" class="regular-text" style="width:100%;" autocomplete="new-password">
                    </div>
                </div>

                <hr style="margin:20px 0;border:none;border-top:1px solid #e0e0e0;">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:700px;">
                    <!-- From e-mail -->
                    <div>
                        <label for="fc-smtp-from-email" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_from_email' ); ?></label>
                        <input type="email" id="fc-smtp-from-email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                        <p class="description" style="margin-top:4px;"><?php fc_e( 'smtp_from_email_desc' ); ?></p>
                    </div>
                    <!-- From name -->
                    <div>
                        <label for="fc-smtp-from-name" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_from_name' ); ?></label>
                        <input type="text" id="fc-smtp-from-name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text" style="width:100%;" placeholder="<?php echo esc_attr( get_option( 'fc_store_name', get_bloginfo( 'name' ) ) ); ?>">
                    </div>
                </div>

                <hr style="margin:20px 0;border:none;border-top:1px solid #e0e0e0;">

                <!-- Przyciski: Zapisz + Test -->
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <button type="button" id="fc-smtp-save" class="button button-primary">
                        <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:4px;font-size:16px;line-height:28px;"></span>
                        <?php fc_e( 'smtp_save' ); ?>
                    </button>

                    <span style="color:#c3c4c7;">|</span>

                    <label for="fc-smtp-test-email" style="font-weight:600;white-space:nowrap;"><?php fc_e( 'smtp_test_label' ); ?></label>
                    <input type="email" id="fc-smtp-test-email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" style="max-width:260px;">
                    <button type="button" id="fc-smtp-test-btn" class="button">
                        <span class="dashicons dashicons-email" style="vertical-align:middle;margin-right:4px;font-size:16px;line-height:28px;"></span>
                        <?php fc_e( 'smtp_test_send' ); ?>
                    </button>

                    <span id="fc-smtp-status" style="font-size:13px;"></span>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var $  = jQuery;
            var nn = '<?php echo wp_create_nonce( "fc_smtp_nonce" ); ?>';

            // Toggle SMTP fields visibility.
            $('#fc-smtp-enabled').on('change', function(){
                $('#fc-smtp-fields').toggle(this.checked);
            });

            // Toggle auth fields.
            function toggleAuth(){
                var show = $('#fc-smtp-auth').val() === '1';
                $('#fc-smtp-auth-fields-user, #fc-smtp-auth-fields-pass').toggle(show);
            }
            $('#fc-smtp-auth').on('change', toggleAuth);
            toggleAuth();

            // Save SMTP settings.
            $('#fc-smtp-save').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true);
                $('#fc-smtp-status').html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(ajaxurl, {
                    action:     'fc_save_smtp',
                    nonce:      nn,
                    enabled:    $('#fc-smtp-enabled').is(':checked') ? 1 : 0,
                    host:       $('#fc-smtp-host').val(),
                    port:       $('#fc-smtp-port').val(),
                    encryption: $('#fc-smtp-encryption').val(),
                    auth:       $('#fc-smtp-auth').val(),
                    username:   $('#fc-smtp-username').val(),
                    password:   $('#fc-smtp-password').val(),
                    from_email: $('#fc-smtp-from-email').val(),
                    from_name:  $('#fc-smtp-from-name').val()
                }, function(r){
                    btn.prop('disabled', false);
                    if(r.success){
                        $('#fc-smtp-status').html('<span style="color:#00a32a;">✓ ' + r.data.message + '</span>');
                    } else {
                        $('#fc-smtp-status').html('<span style="color:#d63638;">✗ Error</span>');
                    }
                    setTimeout(function(){ $('#fc-smtp-status').html(''); }, 4000);
                }).fail(function(){
                    btn.prop('disabled', false);
                    $('#fc-smtp-status').html('<span style="color:#d63638;">✗ Request failed</span>');
                });
            });

            // Send test email.
            $('#fc-smtp-test-btn').on('click', function(){
                var btn = $(this);
                var to  = $('#fc-smtp-test-email').val();
                btn.prop('disabled', true);
                $('#fc-smtp-status').html('<span class="spinner is-active" style="float:none;margin:0;"></span>');

                $.post(ajaxurl, {
                    action: 'fc_send_test_email',
                    nonce:  nn,
                    to:     to
                }, function(r){
                    btn.prop('disabled', false);
                    if(r.success){
                        $('#fc-smtp-status').html('<span style="color:#00a32a;">✓ ' + r.data.message + '</span>');
                    } else {
                        $('#fc-smtp-status').html('<span style="color:#d63638;">✗ ' + (r.data.message||'Error') + '</span>');
                    }
                    setTimeout(function(){ $('#fc-smtp-status').html(''); }, 6000);
                }).fail(function(){
                    btn.prop('disabled', false);
                    $('#fc-smtp-status').html('<span style="color:#d63638;">✗ Request failed</span>');
                });
            });
        })();
        </script>
        <?php
    }
}
