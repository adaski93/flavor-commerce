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
        // Konfiguruj PHPMailer — SMTP jest jedyną metodą wysyłki.
        add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 99 );

        // Nadpisz domyślne From PRZED setFrom() — zapobiega "wordpress@localhost".
        add_filter( 'wp_mail_from',      array( $this, 'filter_mail_from' ), 999 );
        add_filter( 'wp_mail_from_name',  array( $this, 'filter_mail_from_name' ), 999 );

        // AJAX: zapisz ustawienia SMTP.
        add_action( 'wp_ajax_fc_save_smtp', array( $this, 'ajax_save_smtp' ) );

        // AJAX: wyślij testowy e-mail.
        add_action( 'wp_ajax_fc_send_test_email', array( $this, 'ajax_send_test_email' ) );

        // AJAX: wyczyść logi błędów SMTP.
        add_action( 'wp_ajax_fc_clear_smtp_errors', array( $this, 'ajax_clear_errors' ) );

        // Powiadomienie w panelu admina gdy SMTP nie jest skonfigurowane.
        add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Filtry wp_mail_from / wp_mail_from_name                           */
    /* ------------------------------------------------------------------ */

    /**
     * Zwróć prawidłowy adres nadawcy zanim WordPress wywoła setFrom().
     */
    public function filter_mail_from( $from ) {
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
    /*  Walidacja konfiguracji SMTP                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Sprawdź czy SMTP jest poprawnie skonfigurowane.
     */
    public static function is_configured() {
        $host = get_option( 'fc_smtp_host', '' );
        $port = (int) get_option( 'fc_smtp_port', 0 );

        if ( empty( $host ) || $port <= 0 ) {
            return false;
        }

        if ( get_option( 'fc_smtp_auth', 1 ) ) {
            $username = get_option( 'fc_smtp_username', '' );
            $password = self::decrypt_password( get_option( 'fc_smtp_password_enc', '' ) );
            if ( empty( $username ) || empty( $password ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sprawdź czy SMTP jest poprawnie skonfigurowane I zweryfikowane (połączenie OK).
     */
    public static function is_verified() {
        if ( ! self::is_configured() ) {
            return false;
        }
        return (bool) get_option( 'fc_smtp_verified', false );
    }

    /**
     * Zwróć listę brakujących elementów konfiguracji.
     *
     * @return string[]
     */
    public static function get_config_errors() {
        $errors = array();
        $host = get_option( 'fc_smtp_host', '' );
        $port = (int) get_option( 'fc_smtp_port', 0 );

        if ( empty( $host ) ) {
            $errors[] = fc__( 'smtp_missing_host' );
        }
        if ( $port <= 0 ) {
            $errors[] = fc__( 'smtp_missing_port' );
        }
        if ( get_option( 'fc_smtp_auth', 1 ) ) {
            if ( empty( get_option( 'fc_smtp_username', '' ) ) ) {
                $errors[] = fc__( 'smtp_missing_username' );
            }
            if ( empty( self::decrypt_password( get_option( 'fc_smtp_password_enc', '' ) ) ) ) {
                $errors[] = fc__( 'smtp_missing_password' );
            }
        }

        return $errors;
    }

    /**
     * Próba nawiązania połączenia SMTP (connect + auth) bez wysyłania maila.
     *
     * @return true|WP_Error
     */
    public static function verify_connection() {
        if ( ! class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer( true );

        try {
            $mail->isSMTP();
            $mail->Host       = get_option( 'fc_smtp_host', '' );
            $mail->Port       = (int) get_option( 'fc_smtp_port', 587 );
            $mail->SMTPAutoTLS = true;
            $mail->Timeout    = 15;

            $encryption = get_option( 'fc_smtp_encryption', 'tls' );
            if ( $encryption === 'none' ) {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = $encryption;
            }

            if ( get_option( 'fc_smtp_auth', 1 ) ) {
                $mail->SMTPAuth = true;
                $mail->Username = get_option( 'fc_smtp_username', '' );
                $mail->Password = self::decrypt_password( get_option( 'fc_smtp_password_enc', '' ) );
            } else {
                $mail->SMTPAuth = false;
            }

            // Połącz i uwierzytelnij (smtpConnect wewnątrz robi EHLO + AUTH).
            if ( ! $mail->smtpConnect() ) {
                return new \WP_Error( 'smtp_connect_failed', fc__( 'smtp_connection_failed' ) );
            }

            $mail->smtpClose();
            return true;

        } catch ( \PHPMailer\PHPMailer\Exception $e ) {
            return new \WP_Error( 'smtp_connect_failed', $e->getMessage() );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'smtp_connect_failed', $e->getMessage() );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Wrapper wysyłki e-maili — wymusza SMTP                            */
    /* ------------------------------------------------------------------ */

    /**
     * Wyślij e-mail tylko przez SMTP.
     *
     * Jeśli SMTP nie jest skonfigurowane → zwraca WP_Error i loguje błąd.
     * Jeśli wysyłka się nie powiedzie → zwraca WP_Error z komunikatem PHPMailer.
     *
     * @param string|string[] $to
     * @param string          $subject
     * @param string          $body
     * @param string|string[] $headers
     * @param string|string[] $attachments
     * @return true|WP_Error
     */
    public static function send_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
        if ( ! self::is_configured() ) {
            $config_errors = self::get_config_errors();
            $error_msg     = fc__( 'smtp_not_configured' ) . ' ' . implode( ', ', $config_errors );
            self::log_error( $error_msg . ' → ' . ( is_array( $to ) ? implode( ', ', $to ) : $to ) );
            error_log( '[Flavor Commerce SMTP] ' . $error_msg );
            return new \WP_Error( 'smtp_not_configured', $error_msg );
        }

        // Osadź lokalne obrazy jako inline CID attachments.
        $inline_images = array();
        $body = self::embed_images( $body, $inline_images );

        // Hook do PHPMailer — dodaj inline attachments.
        $add_inline = null;
        if ( ! empty( $inline_images ) ) {
            $add_inline = function( $phpmailer ) use ( $inline_images ) {
                foreach ( $inline_images as $img ) {
                    $phpmailer->addEmbeddedImage(
                        $img['path'],   // ścieżka do pliku
                        $img['cid'],    // Content-ID
                        $img['name'],   // nazwa pliku
                        'base64',       // encoding
                        $img['type']    // MIME type
                    );
                }
            };
            add_action( 'phpmailer_init', $add_inline, 100 );
        }

        // Przechwytuj błędy PHPMailer.
        $smtp_error = '';
        $capture_error = function( $wp_error ) use ( &$smtp_error ) {
            if ( is_wp_error( $wp_error ) ) {
                $smtp_error = $wp_error->get_error_message();
            }
        };
        add_action( 'wp_mail_failed', $capture_error );

        $sent = wp_mail( $to, $subject, $body, $headers, $attachments );

        remove_action( 'wp_mail_failed', $capture_error );
        if ( $add_inline ) {
            remove_action( 'phpmailer_init', $add_inline, 100 );
        }

        if ( ! $sent ) {
            $error_msg = fc__( 'smtp_send_failed' ) . ( $smtp_error ? ': ' . $smtp_error : '' );
            self::log_error( $error_msg . ' → ' . ( is_array( $to ) ? implode( ', ', $to ) : $to ) );
            error_log( '[Flavor Commerce SMTP] ' . $error_msg );
            return new \WP_Error( 'smtp_send_failed', $error_msg );
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  Osadzanie obrazów inline (CID) w treści HTML                     */
    /* ------------------------------------------------------------------ */

    /**
     * Skanuj HTML body, znajdź lokalne <img src="...">, zamień na cid: i dodaj do tablicy inline.
     *
     * @param string $body        HTML body e-maila.
     * @param array  &$inline_images Tablica do wypełnienia danymi obrazów.
     * @return string Zmodyfikowana treść HTML.
     */
    private static function embed_images( $body, &$inline_images ) {
        $upload_dir  = wp_get_upload_dir();
        $upload_url  = $upload_dir['baseurl'];  // np. http://localhost/wp-content/uploads
        $upload_path = $upload_dir['basedir'];  // np. C:\laragon\www\wp-content\uploads

        // Zamień URL-e na ścieżki bezwzględne i generuj CID.
        // Dopasuj src="..." wewnątrz tagów <img>.
        $body = preg_replace_callback(
            '/<img\b([^>]*)\bsrc=["\']([^"\']+)["\']/i',
            function( $matches ) use ( $upload_url, $upload_path, &$inline_images ) {
                $full_tag = $matches[0];
                $src      = $matches[2];

                // Tylko lokalne obrazy (z katalogu uploads).
                $relative = self::url_to_local_path( $src, $upload_url, $upload_path );
                if ( ! $relative ) {
                    return $full_tag; // Zewnętrzny URL — zostaw.
                }

                if ( ! file_exists( $relative ) ) {
                    return $full_tag; // Plik nie istnieje — zostaw.
                }

                // Sprawdź czy już dodaliśmy ten plik.
                $cid = null;
                foreach ( $inline_images as $img ) {
                    if ( $img['path'] === $relative ) {
                        $cid = $img['cid'];
                        break;
                    }
                }

                if ( ! $cid ) {
                    $cid  = 'fcimg_' . md5( $relative ) . '@flavorcommerce';
                    $name = basename( $relative );
                    $type = wp_check_filetype( $name )['type'] ?: 'image/png';

                    $inline_images[] = array(
                        'path' => $relative,
                        'cid'  => $cid,
                        'name' => $name,
                        'type' => $type,
                    );
                }

                // Zamień src na cid:
                return str_replace( $src, 'cid:' . $cid, $full_tag );
            },
            $body
        );

        return $body;
    }

    /**
     * Konwertuj URL obrazu na lokalną ścieżkę pliku.
     *
     * @param string $url         URL obrazu.
     * @param string $upload_url  Bazowy URL uploadsów.
     * @param string $upload_path Bazowa ścieżka uploadsów.
     * @return string|false       Lokalna ścieżka lub false.
     */
    private static function url_to_local_path( $url, $upload_url, $upload_path ) {
        // Obsłuż zarówno http jak i https (i warianty z/bez www).
        $url_path = parse_url( $url, PHP_URL_PATH );
        $base_path = parse_url( $upload_url, PHP_URL_PATH );

        if ( ! $url_path || ! $base_path ) return false;

        // Sprawdź czy URL zaczyna się od ścieżki uploads.
        if ( strpos( $url_path, $base_path ) !== 0 ) {
            return false;
        }

        $relative = substr( $url_path, strlen( $base_path ) );
        $local    = $upload_path . str_replace( '/', DIRECTORY_SEPARATOR, $relative );

        return file_exists( $local ) ? $local : false;
    }

    /* ------------------------------------------------------------------ */
    /*  Logowanie błędów SMTP                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Zapisz błąd do transient (max 20 ostatnich).
     */
    public static function log_error( $message ) {
        $errors = get_transient( 'fc_smtp_errors' );
        if ( ! is_array( $errors ) ) {
            $errors = array();
        }
        array_unshift( $errors, current_time( 'Y-m-d H:i:s' ) . ' — ' . $message );
        $errors = array_slice( $errors, 0, 20 );
        set_transient( 'fc_smtp_errors', $errors, 7 * DAY_IN_SECONDS );
    }

    /**
     * Pobierz ostatnie błędy SMTP.
     *
     * @return string[]
     */
    public static function get_errors() {
        $errors = get_transient( 'fc_smtp_errors' );
        return is_array( $errors ) ? $errors : array();
    }

    /**
     * Wyświetl powiadomienie w panelu admina jeśli SMTP nie jest zweryfikowane.
     */
    public function show_admin_notice() {
        if ( self::is_verified() ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Nie pokazuj na stronie ustawień FC (tam jest dedykowany status).
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'flavor-commerce' ) !== false ) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Flavor Commerce:</strong>
                <?php fc_e( 'smtp_not_configured_notice' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=flavor-commerce&tab=emails' ) ); ?>">
                    <?php fc_e( 'smtp_configure_link' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX: Wyczyść logi błędów SMTP.
     */
    public function ajax_clear_errors() {
        check_ajax_referer( 'fc_smtp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }
        delete_transient( 'fc_smtp_errors' );
        wp_send_json_success();
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX – zapisz ustawienia SMTP                                     */
    /* ------------------------------------------------------------------ */

    public function ajax_save_smtp() {
        check_ajax_referer( 'fc_smtp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }

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

        // Sprawdź czy dane są kompletne.
        if ( ! self::is_configured() ) {
            update_option( 'fc_smtp_verified', 0 );
            $errors = self::get_config_errors();
            wp_send_json_error( array(
                'message' => fc__( 'smtp_saved' ) . ' ' . fc__( 'smtp_not_configured' ) . ' ' . implode( ', ', $errors ),
            ) );
        }

        // Próba nawiązania połączenia z serwerem SMTP.
        $verify = self::verify_connection();
        if ( is_wp_error( $verify ) ) {
            update_option( 'fc_smtp_verified', 0 );
            self::log_error( fc__( 'smtp_connection_failed' ) . ': ' . $verify->get_error_message() );
            wp_send_json_error( array(
                'message' => fc__( 'smtp_saved' ) . ' ' . fc__( 'smtp_connection_failed' ) . ': ' . $verify->get_error_message(),
            ) );
        }

        update_option( 'fc_smtp_verified', 1 );
        wp_send_json_success( array( 'message' => fc__( 'smtp_saved' ) . ' ' . fc__( 'smtp_connection_ok' ) ) );
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX – wyślij testowy e-mail                                      */
    /* ------------------------------------------------------------------ */

    public function ajax_send_test_email() {
        check_ajax_referer( 'fc_smtp_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Forbidden' );
        }

        if ( ! self::is_configured() ) {
            $errors = self::get_config_errors();
            wp_send_json_error( array( 'message' => fc__( 'smtp_not_configured' ) . ' ' . implode( ', ', $errors ) ) );
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
        $host       = get_option( 'fc_smtp_host', '' );
        $port       = get_option( 'fc_smtp_port', 587 );
        $encryption = get_option( 'fc_smtp_encryption', 'tls' );
        $auth       = get_option( 'fc_smtp_auth', 1 );
        $username   = get_option( 'fc_smtp_username', '' );
        $has_pass   = get_option( 'fc_smtp_password_enc', '' ) !== '';
        $from_email = get_option( 'fc_smtp_from_email', '' );
        $from_name  = get_option( 'fc_smtp_from_name', '' );
        $configured = self::is_verified();
        ?>
        <div id="fc-smtp-section" class="fc-email-block" style="margin-top:0;">
            <div id="fc-smtp-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:<?php echo $configured ? '#f6f7f7' : '#fcf0f1'; ?>;border:1px solid <?php echo $configured ? '#c3c4c7' : '#d63638'; ?>;border-radius:4px;cursor:pointer;user-select:none;margin-bottom:0;" title="<?php echo esc_attr( fc__( 'smtp_toggle' ) ); ?>">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="dashicons dashicons-lock" style="color:<?php echo $configured ? '#2271b1' : '#d63638'; ?>;font-size:20px;"></span>
                    <strong><?php fc_e( 'smtp_title' ); ?></strong>
                    <span id="fc-smtp-arrow" class="dashicons dashicons-arrow-<?php echo $configured ? 'down' : 'up'; ?>-alt2" style="font-size:18px;color:#888;transition:transform .2s;"></span>
                </div>
                <?php if ( $configured ) : ?>
                    <span style="display:flex;align-items:center;gap:6px;color:#00a32a;font-weight:600;font-size:13px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:18px;"></span>
                        <?php fc_e( 'smtp_status_ok' ); ?>
                    </span>
                <?php else : ?>
                    <span style="display:flex;align-items:center;gap:6px;color:#d63638;font-weight:600;font-size:13px;">
                        <span class="dashicons dashicons-warning" style="font-size:18px;"></span>
                        <?php fc_e( 'smtp_status_error' ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div id="fc-smtp-fields" style="padding:16px;border:1px solid <?php echo $configured ? '#c3c4c7' : '#d63638'; ?>;border-top:none;border-radius:0 0 4px 4px;background:#fff;<?php echo $configured ? 'display:none;' : ''; ?>">
                <?php if ( ! $configured ) : ?>
                <div style="margin-bottom:16px;padding:12px 16px;background:#fcf0f1;border:1px solid #d63638;border-radius:4px;color:#8a1e1f;">
                    <strong>⚠ <?php fc_e( 'smtp_required_warning' ); ?></strong>
                </div>
                <?php endif; ?>

                <p class="description" style="margin-top:0;margin-bottom:16px;"><?php fc_e( 'smtp_description' ); ?></p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:700px;">
                    <!-- Host -->
                    <div>
                        <label for="fc-smtp-host" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_host' ); ?> <span style="color:#d63638;">*</span></label>
                        <input type="text" id="fc-smtp-host" value="<?php echo esc_attr( $host ); ?>" class="regular-text" style="width:100%;" placeholder="smtp.gmail.com">
                    </div>
                    <!-- Port -->
                    <div>
                        <label for="fc-smtp-port" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_port' ); ?> <span style="color:#d63638;">*</span></label>
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
                        <label for="fc-smtp-username" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_username' ); ?> <span style="color:#d63638;">*</span></label>
                        <input type="text" id="fc-smtp-username" value="<?php echo esc_attr( $username ); ?>" class="regular-text" style="width:100%;" autocomplete="off">
                    </div>
                    <!-- Hasło -->
                    <div id="fc-smtp-auth-fields-pass">
                        <label for="fc-smtp-password" style="display:block;font-weight:600;margin-bottom:4px;"><?php fc_e( 'smtp_password' ); ?> <span style="color:#d63638;">*</span></label>
                        <div style="position:relative;">
                            <input type="password" id="fc-smtp-password" value="<?php echo $has_pass ? '••••••••' : ''; ?>" class="regular-text" style="width:100%;padding-right:36px;" autocomplete="new-password">
                            <button type="button" id="fc-smtp-toggle-pass" tabindex="-1" style="position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#888;" title="<?php echo esc_attr( fc__( 'smtp_show_password' ) ); ?>">
                                <span class="dashicons dashicons-visibility" style="font-size:18px;width:18px;height:18px;"></span>
                            </button>
                        </div>
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
                    <button type="button" id="fc-smtp-save" class="button button-primary" style="display:inline-flex;align-items:center;gap:4px;">
                        <span class="dashicons dashicons-saved" style="font-size:16px;width:16px;height:16px;"></span>
                        <?php fc_e( 'smtp_save' ); ?>
                    </button>

                    <span style="color:#c3c4c7;">|</span>

                    <label for="fc-smtp-test-email" style="font-weight:600;white-space:nowrap;"><?php fc_e( 'smtp_test_label' ); ?></label>
                    <input type="email" id="fc-smtp-test-email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" style="max-width:260px;">
                    <button type="button" id="fc-smtp-test-btn" class="button" style="display:inline-flex;align-items:center;gap:4px;">
                        <span class="dashicons dashicons-email" style="font-size:16px;width:16px;height:16px;"></span>
                        <?php fc_e( 'smtp_test_send' ); ?>
                    </button>

                    <span id="fc-smtp-status" style="font-size:13px;"></span>
                </div>

                <?php
                $smtp_errors = self::get_errors();
                if ( ! empty( $smtp_errors ) ) : ?>
                <div id="fc-smtp-errors-box" style="margin-top:20px;padding:14px 18px;background:#fef7f1;border:1px solid #f0c8a8;border-radius:6px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <strong style="color:#b32d2e;font-size:13px;">
                            <span class="dashicons dashicons-warning" style="font-size:16px;vertical-align:text-bottom;margin-right:4px;"></span>
                            <?php fc_e( 'smtp_last_errors' ); ?>
                        </strong>
                        <button type="button" id="fc-smtp-clear-errors" class="button button-small"><?php fc_e( 'smtp_clear_errors' ); ?></button>
                    </div>
                    <ul style="margin:0;padding-left:18px;color:#72383a;font-size:13px;max-height:200px;overflow-y:auto;">
                        <?php foreach ( $smtp_errors as $err ) : ?>
                            <li style="margin-bottom:4px;"><?php echo esc_html( $err ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function(){
            var $  = jQuery;
            var nn = '<?php echo wp_create_nonce( "fc_smtp_nonce" ); ?>';

            // Toggle SMTP section collapse/expand.
            $('#fc-smtp-header').on('click', function(){
                var $body = $('#fc-smtp-fields');
                var $arrow = $('#fc-smtp-arrow');
                $body.slideToggle(200, function(){
                    var visible = $body.is(':visible');
                    $arrow.toggleClass('dashicons-arrow-down-alt2', !visible)
                          .toggleClass('dashicons-arrow-up-alt2', visible);
                });
            });

            // Toggle password visibility.
            $('#fc-smtp-toggle-pass').on('click', function(){
                var $inp = $('#fc-smtp-password');
                var $ico = $(this).find('.dashicons');
                if($inp.attr('type') === 'password'){
                    $inp.attr('type','text');
                    $ico.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    $inp.attr('type','password');
                    $ico.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
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
                        $('#fc-smtp-status').html('<span style="color:#d63638;">✗ ' + (r.data && r.data.message ? r.data.message : 'Error') + '</span>');
                    }
                    // Odśwież stronę po 2s aby zaktualizować status SMTP i wyświetlić ostrzeżenia.
                    setTimeout(function(){ location.reload(); }, 2000);
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

            // Clear SMTP errors.
            $('#fc-smtp-clear-errors').on('click', function(){
                $.post(ajaxurl, { action: 'fc_clear_smtp_errors', nonce: nn }, function(r){
                    if(r.success) $('#fc-smtp-errors-box').fadeOut(300, function(){ $(this).remove(); });
                });
            });
        })();
        </script>
        <?php
    }
}
