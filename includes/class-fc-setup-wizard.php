<?php
/**
 * Flavor Commerce — Setup Wizard
 *
 * Multi-step onboarding wizard shown on first plugin activation.
 * Configures language, store details, currency, tax, selling countries,
 * creates store pages and activates the companion Flavor theme.
 *
 * @package Flavor Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FC_Setup_Wizard {

    /** @var array Step definitions */
    private static $steps = array( 'welcome', 'store', 'commerce', 'appearance', 'ready' );

    /* ──────────────────────────────────────────────
     * Initialization
     * ────────────────────────────────────────────── */

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'redirect_after_activation' ), 1 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_render_wizard' ), 2 );
    }

    /**
     * Register a hidden admin page (null parent = no menu entry, but WP allows access).
     */
    public static function add_page() {
        add_submenu_page(
            null,                                               // null parent = hidden
            'Flavor Commerce Setup',
            'Setup',
            'manage_options',
            'fc-setup-wizard',
            '__return_null'
        );
    }

    /**
     * Intercept the request in admin_init (before admin-header.php is loaded)
     * to render a fully standalone wizard page without WordPress admin chrome.
     */
    public static function maybe_render_wizard() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fc-setup-wizard' ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        // Instant language switch via GET params (redirect from JS)
        if ( isset( $_GET['fc_switch_admin_lang'] ) ) {
            $new_admin = sanitize_text_field( $_GET['fc_switch_admin_lang'] );
            $allowed   = array_keys( FC_i18n::get_available_languages() );
            if ( in_array( $new_admin, $allowed, true ) ) {
                update_option( 'fc_admin_lang', $new_admin );
            }
        }
        if ( isset( $_GET['fc_switch_frontend_lang'] ) ) {
            $new_front = sanitize_text_field( $_GET['fc_switch_frontend_lang'] );
            $allowed   = array_keys( FC_i18n::get_available_languages() );
            if ( in_array( $new_front, $allowed, true ) ) {
                update_option( 'fc_frontend_lang', $new_front );
            }
        }
        if ( isset( $_GET['fc_switch_admin_lang'] ) || isset( $_GET['fc_switch_frontend_lang'] ) ) {
            FC_i18n::reload();
        }

        self::process_submissions();
        self::output_page();
        exit;
    }

    /**
     * Redirect to wizard after first activation.
     */
    public static function redirect_after_activation() {
        if ( ! get_transient( 'fc_activation_redirect' ) ) {
            return;
        }
        delete_transient( 'fc_activation_redirect' );

        // Don't redirect on multisite bulk activate, AJAX, or CLI
        if ( wp_doing_ajax() || is_network_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        if ( ! get_option( 'fc_setup_wizard_completed' ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=fc-setup-wizard' ) );
            exit;
        }
    }

    /* ──────────────────────────────────────────────
     * Form Processing
     * ────────────────────────────────────────────── */

    private static function process_submissions() {
        if ( ! isset( $_POST['fc_wizard_nonce'] ) ) {
            return;
        }

        $step = isset( $_POST['fc_wizard_step'] ) ? sanitize_key( $_POST['fc_wizard_step'] ) : '';

        if ( ! wp_verify_nonce( $_POST['fc_wizard_nonce'], 'fc_wizard_' . $step ) ) {
            wp_die( 'Invalid nonce' );
        }

        switch ( $step ) {
            case 'welcome':
                self::save_welcome();
                break;
            case 'store':
                self::save_store();
                break;
            case 'commerce':
                self::save_commerce();
                break;
            case 'appearance':
                self::save_appearance();
                break;
            case 'ready':
                self::save_ready();
                break;
        }
    }

    private static function save_welcome() {
        $frontend_lang = sanitize_text_field( $_POST['fc_frontend_lang'] ?? 'pl' );
        $admin_lang    = sanitize_text_field( $_POST['fc_admin_lang'] ?? 'pl' );

        update_option( 'fc_frontend_lang', $frontend_lang );
        update_option( 'fc_admin_lang', $admin_lang );

        wp_safe_redirect( admin_url( 'admin.php?page=fc-setup-wizard&step=store' ) );
        exit;
    }

    private static function save_store() {
        $fields = array(
            'fc_store_name',
            'fc_store_country',
            'fc_store_street',
            'fc_store_postcode',
            'fc_store_city',
            'fc_store_email',
            'fc_store_email_contact',
            'fc_store_phone_prefix',
            'fc_store_phone',
            'fc_store_tax_no',
            'fc_store_crn',
        );

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_option( $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        // Auto-set currency based on country
        $country  = get_option( 'fc_store_country', 'PL' );
        $map      = self::get_country_currency_map();
        $all_curr = self::get_currency_data();

        if ( isset( $map[ $country ] ) ) {
            $code = $map[ $country ];
            update_option( 'fc_currency', $code );
            if ( isset( $all_curr[ $code ] ) ) {
                update_option( 'fc_currency_symbol', $all_curr[ $code ]['symbol'] );
                update_option( 'fc_currency_pos', $all_curr[ $code ]['pos'] );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=fc-setup-wizard&step=commerce' ) );
        exit;
    }

    private static function save_commerce() {
        // Currency
        $currency = sanitize_text_field( $_POST['fc_currency'] ?? 'PLN' );
        $all_curr = self::get_currency_data();
        update_option( 'fc_currency', $currency );
        if ( isset( $all_curr[ $currency ] ) ) {
            update_option( 'fc_currency_symbol', sanitize_text_field( $_POST['fc_currency_symbol'] ?? $all_curr[ $currency ]['symbol'] ) );
        }
        $pos = sanitize_key( $_POST['fc_currency_pos'] ?? 'after' );
        if ( in_array( $pos, array( 'before', 'after' ), true ) ) {
            update_option( 'fc_currency_pos', $pos );
        }

        // Tax
        update_option( 'fc_tax_name', sanitize_text_field( $_POST['fc_tax_name'] ?? 'VAT' ) );
        update_option( 'fc_tax_rate', floatval( $_POST['fc_tax_rate'] ?? 23 ) );
        update_option( 'fc_tax_included', sanitize_text_field( $_POST['fc_tax_included'] ?? 'yes' ) );

        // Selling countries
        $sell_mode = sanitize_key( $_POST['fc_sell_to_mode'] ?? 'all' );
        update_option( 'fc_sell_to_mode', $sell_mode );

        if ( $sell_mode === 'include' && isset( $_POST['fc_sell_to_included'] ) ) {
            $included = array_map( 'sanitize_text_field', (array) $_POST['fc_sell_to_included'] );
            update_option( 'fc_sell_to_included', $included );
        } else {
            update_option( 'fc_sell_to_included', array() );
        }

        if ( $sell_mode === 'exclude' && isset( $_POST['fc_sell_to_excluded'] ) ) {
            $excluded = array_map( 'sanitize_text_field', (array) $_POST['fc_sell_to_excluded'] );
            update_option( 'fc_sell_to_excluded', $excluded );
        } else {
            update_option( 'fc_sell_to_excluded', array() );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=fc-setup-wizard&step=appearance' ) );
        exit;
    }

    private static function save_appearance() {
        $accent = sanitize_hex_color( $_POST['fc_accent_color'] ?? '#4a90d9' );
        $mode   = sanitize_key( $_POST['fc_theme_mode'] ?? 'light' );
        if ( ! in_array( $mode, array( 'light', 'dark' ), true ) ) {
            $mode = 'light';
        }

        set_theme_mod( 'flavor_color_accent', $accent );
        set_theme_mod( 'flavor_color_mode', $mode );

        // Auto-generate derived accent colors
        $accent_hover = self::adjust_hex_brightness( $accent, -15 );
        $accent_light = self::hex_to_rgba( $accent, 0.12 );
        set_theme_mod( 'flavor_color_accent_hover', $accent_hover );
        set_theme_mod( 'flavor_color_accent_light', $accent_light );

        wp_safe_redirect( admin_url( 'admin.php?page=fc-setup-wizard&step=ready' ) );
        exit;
    }

    private static function save_ready() {
        // Activate theme
        self::activate_theme();

        // Mark wizard as complete
        update_option( 'fc_setup_wizard_completed', 1 );

        wp_safe_redirect( admin_url( 'admin.php?page=flavor-commerce' ) );
        exit;
    }

    /* ──────────────────────────────────────────────
     * Theme Installation
     * ────────────────────────────────────────────── */

    private static function activate_theme() {
        $theme = wp_get_theme( 'flavor' );
        if ( $theme->exists() ) {
            switch_theme( 'flavor' );
        }
    }

    /* ──────────────────────────────────────────────
     * Data Maps
     * ────────────────────────────────────────────── */

    public static function get_countries() {
        return array(
            'AL' => fc__( 'country_AL', 'admin' ), 'AT' => fc__( 'country_AT', 'admin' ),
            'BY' => fc__( 'country_BY', 'admin' ), 'BE' => fc__( 'country_BE', 'admin' ),
            'BA' => fc__( 'country_BA', 'admin' ), 'BG' => fc__( 'country_BG', 'admin' ),
            'HR' => fc__( 'country_HR', 'admin' ), 'CY' => fc__( 'country_CY', 'admin' ),
            'ME' => fc__( 'country_ME', 'admin' ), 'CZ' => fc__( 'country_CZ', 'admin' ),
            'DK' => fc__( 'country_DK', 'admin' ), 'EE' => fc__( 'country_EE', 'admin' ),
            'FI' => fc__( 'country_FI', 'admin' ), 'FR' => fc__( 'country_FR', 'admin' ),
            'GR' => fc__( 'country_GR', 'admin' ), 'ES' => fc__( 'country_ES', 'admin' ),
            'NL' => fc__( 'country_NL', 'admin' ), 'IE' => fc__( 'country_IE', 'admin' ),
            'IS' => fc__( 'country_IS', 'admin' ), 'LT' => fc__( 'country_LT', 'admin' ),
            'LU' => fc__( 'country_LU', 'admin' ), 'LV' => fc__( 'country_LV', 'admin' ),
            'MK' => fc__( 'country_MK', 'admin' ), 'MT' => fc__( 'country_MT', 'admin' ),
            'MD' => fc__( 'country_MD', 'admin' ), 'DE' => fc__( 'country_DE', 'admin' ),
            'NO' => fc__( 'country_NO', 'admin' ), 'PL' => fc__( 'country_PL', 'admin' ),
            'PT' => fc__( 'country_PT', 'admin' ), 'RO' => fc__( 'country_RO', 'admin' ),
            'RS' => fc__( 'country_RS', 'admin' ), 'SK' => fc__( 'country_SK', 'admin' ),
            'SI' => fc__( 'country_SI', 'admin' ), 'CH' => fc__( 'country_CH', 'admin' ),
            'SE' => fc__( 'country_SE', 'admin' ), 'UA' => fc__( 'country_UA', 'admin' ),
            'HU' => fc__( 'country_HU', 'admin' ), 'GB' => fc__( 'country_GB', 'admin' ),
            'IT' => fc__( 'country_IT', 'admin' ),
        );
    }

    public static function get_country_currency_map() {
        return array(
            'AL' => 'ALL', 'AT' => 'EUR', 'BY' => 'BYN', 'BE' => 'EUR', 'BA' => 'BAM',
            'BG' => 'BGN', 'HR' => 'EUR', 'CY' => 'EUR', 'CZ' => 'CZK', 'DK' => 'DKK',
            'EE' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'DE' => 'EUR', 'GR' => 'EUR',
            'HU' => 'HUF', 'IS' => 'ISK', 'IE' => 'EUR', 'IT' => 'EUR', 'LV' => 'EUR',
            'LT' => 'EUR', 'LU' => 'EUR', 'MK' => 'MKD', 'MT' => 'EUR', 'MD' => 'MDL',
            'ME' => 'EUR', 'NL' => 'EUR', 'NO' => 'NOK', 'PL' => 'PLN', 'PT' => 'EUR',
            'RO' => 'RON', 'RS' => 'RSD', 'SK' => 'EUR', 'SI' => 'EUR', 'ES' => 'EUR',
            'SE' => 'SEK', 'CH' => 'CHF', 'UA' => 'UAH', 'GB' => 'GBP',
        );
    }

    public static function get_country_prefix_map() {
        return array(
            'AL' => '+355', 'AT' => '+43',  'BY' => '+375', 'BE' => '+32',  'BA' => '+387',
            'BG' => '+359', 'HR' => '+385', 'CY' => '+357', 'ME' => '+382', 'CZ' => '+420',
            'DK' => '+45',  'EE' => '+372', 'FI' => '+358', 'FR' => '+33',  'GR' => '+30',
            'ES' => '+34',  'NL' => '+31',  'IE' => '+353', 'IS' => '+354', 'LT' => '+370',
            'LU' => '+352', 'LV' => '+371', 'MK' => '+389', 'MT' => '+356', 'MD' => '+373',
            'DE' => '+49',  'NO' => '+47',  'PL' => '+48',  'PT' => '+351', 'RO' => '+40',
            'RS' => '+381', 'SK' => '+421', 'SI' => '+386', 'CH' => '+41',  'SE' => '+46',
            'UA' => '+380', 'HU' => '+36',  'GB' => '+44',  'IT' => '+39',
        );
    }

    public static function get_currency_data() {
        return array(
            'PLN' => array( 'symbol' => 'zł',   'pos' => 'after',  'label' => 'PLN — Złoty polski (zł)' ),
            'EUR' => array( 'symbol' => '€',    'pos' => 'before', 'label' => 'EUR — Euro (€)' ),
            'GBP' => array( 'symbol' => '£',    'pos' => 'before', 'label' => 'GBP — Funt szterling (£)' ),
            'CHF' => array( 'symbol' => 'CHF',  'pos' => 'after',  'label' => 'CHF — Frank szwajcarski (CHF)' ),
            'CZK' => array( 'symbol' => 'Kč',   'pos' => 'after',  'label' => 'CZK — Korona czeska (Kč)' ),
            'DKK' => array( 'symbol' => 'kr',   'pos' => 'after',  'label' => 'DKK — Korona duńska (kr)' ),
            'SEK' => array( 'symbol' => 'kr',   'pos' => 'after',  'label' => 'SEK — Korona szwedzka (kr)' ),
            'NOK' => array( 'symbol' => 'kr',   'pos' => 'after',  'label' => 'NOK — Korona norweska (kr)' ),
            'ISK' => array( 'symbol' => 'kr',   'pos' => 'after',  'label' => 'ISK — Korona islandzka (kr)' ),
            'HUF' => array( 'symbol' => 'Ft',   'pos' => 'after',  'label' => 'HUF — Forint węgierski (Ft)' ),
            'RON' => array( 'symbol' => 'lei',  'pos' => 'after',  'label' => 'RON — Lej rumuński (lei)' ),
            'BGN' => array( 'symbol' => 'лв',   'pos' => 'after',  'label' => 'BGN — Lew bułgarski (лв)' ),
            'HRK' => array( 'symbol' => 'kn',   'pos' => 'after',  'label' => 'HRK — Kuna chorwacka (kn)' ),
            'RSD' => array( 'symbol' => 'din.', 'pos' => 'after',  'label' => 'RSD — Dinar serbski (din.)' ),
            'BAM' => array( 'symbol' => 'KM',   'pos' => 'after',  'label' => 'BAM — Marka konwertowalna (KM)' ),
            'MDL' => array( 'symbol' => 'L',    'pos' => 'after',  'label' => 'MDL — Lej mołdawski (L)' ),
            'UAH' => array( 'symbol' => '₴',    'pos' => 'after',  'label' => 'UAH — Hrywna ukraińska (₴)' ),
            'BYN' => array( 'symbol' => 'Br',   'pos' => 'after',  'label' => 'BYN — Rubel białoruski (Br)' ),
            'ALL' => array( 'symbol' => 'L',    'pos' => 'after',  'label' => 'ALL — Lek albański (L)' ),
            'MKD' => array( 'symbol' => 'ден',  'pos' => 'after',  'label' => 'MKD — Denar macedoński (ден)' ),
        );
    }

    public static function get_country_tax_labels() {
        return array(
            'AL' => array( 'tax_no' => 'NIPT',                              'reg' => 'Numri i Regjistrimit (QKR)' ),
            'AT' => array( 'tax_no' => 'UID (ATU)',                          'reg' => 'Firmenbuchnummer (FN)' ),
            'BY' => array( 'tax_no' => 'УНП',                               'reg' => 'Рэгістрацыйны нумар' ),
            'BE' => array( 'tax_no' => 'BTW / TVA',                         'reg' => 'Ondernemingsnummer (KBO)' ),
            'BA' => array( 'tax_no' => 'PDV broj',                          'reg' => 'Registarski broj' ),
            'BG' => array( 'tax_no' => 'ИН по ДДС',                         'reg' => 'ЕИК (Булстат)' ),
            'HR' => array( 'tax_no' => 'OIB',                               'reg' => 'Matični broj subjekta (MBS)' ),
            'CY' => array( 'tax_no' => 'Αριθμός ΦΠΑ',                       'reg' => 'Αριθμός Εγγραφής (HE)' ),
            'ME' => array( 'tax_no' => 'PIB',                               'reg' => 'Registarski broj' ),
            'CZ' => array( 'tax_no' => 'DIČ',                               'reg' => 'Identifikační číslo (IČO)' ),
            'DK' => array( 'tax_no' => 'SE-nummer',                         'reg' => 'CVR-nummer' ),
            'EE' => array( 'tax_no' => 'KMKR number',                       'reg' => 'Registrikood' ),
            'FI' => array( 'tax_no' => 'ALV-numero',                        'reg' => 'Y-tunnus' ),
            'FR' => array( 'tax_no' => 'Numéro de TVA',                     'reg' => 'Numéro SIREN / SIRET' ),
            'GR' => array( 'tax_no' => 'ΑΦΜ',                               'reg' => 'Αριθμός ΓΕΜΗ' ),
            'ES' => array( 'tax_no' => 'NIF / CIF',                         'reg' => 'Registro Mercantil' ),
            'NL' => array( 'tax_no' => 'BTW-nummer',                        'reg' => 'KVK-nummer' ),
            'IE' => array( 'tax_no' => 'VAT Number',                        'reg' => 'Company Registration (CRO)' ),
            'IS' => array( 'tax_no' => 'Virðisaukaskattnúmer (VSK)',         'reg' => 'Kennitala' ),
            'LT' => array( 'tax_no' => 'PVM mokėtojo kodas',                'reg' => 'Įmonės kodas' ),
            'LU' => array( 'tax_no' => 'Numéro TVA',                        'reg' => 'Numéro RCS' ),
            'LV' => array( 'tax_no' => 'PVN numurs',                        'reg' => 'Reģistrācijas Nr.' ),
            'MK' => array( 'tax_no' => 'ДДВ број',                          'reg' => 'ЕМБС' ),
            'MT' => array( 'tax_no' => 'VAT Number',                        'reg' => 'Company Number (C)' ),
            'MD' => array( 'tax_no' => 'Codul TVA',                         'reg' => 'IDNO (Cod fiscal)' ),
            'DE' => array( 'tax_no' => 'Umsatzsteuer-IdNr.',                'reg' => 'Handelsregisternummer (HRB)' ),
            'NO' => array( 'tax_no' => 'MVA-nummer',                        'reg' => 'Organisasjonsnummer' ),
            'PL' => array( 'tax_no' => 'NIP',                               'reg' => 'KRS / REGON' ),
            'PT' => array( 'tax_no' => 'Número de contribuinte (NIF)',       'reg' => 'NIPC' ),
            'RO' => array( 'tax_no' => 'Cod de identificare fiscală (CIF)', 'reg' => 'Nr. Registrul Comerțului' ),
            'RS' => array( 'tax_no' => 'ПИБ',                               'reg' => 'Матични број' ),
            'SK' => array( 'tax_no' => 'IČ DPH',                            'reg' => 'Identifikačné číslo (IČO)' ),
            'SI' => array( 'tax_no' => 'Identifikacijska št. za DDV',        'reg' => 'Matična številka' ),
            'CH' => array( 'tax_no' => 'MWST-Nr. / Numéro TVA',             'reg' => 'Unternehmens-Id. (CHE/UID)' ),
            'SE' => array( 'tax_no' => 'Momsregistreringsnummer',            'reg' => 'Organisationsnummer' ),
            'UA' => array( 'tax_no' => 'ІПН',                               'reg' => 'Код ЄДРПОУ' ),
            'HU' => array( 'tax_no' => 'Adószám',                           'reg' => 'Cégjegyzékszám' ),
            'GB' => array( 'tax_no' => 'VAT Registration Number',           'reg' => 'Company Registration Number' ),
            'IT' => array( 'tax_no' => 'Partita IVA',                        'reg' => 'Numero REA' ),
        );
    }

    public static function get_default_tax_rates() {
        return array(
            'AL' => 20, 'AT' => 20, 'BY' => 20, 'BE' => 21, 'BA' => 17, 'BG' => 20,
            'HR' => 25, 'CY' => 19, 'ME' => 21, 'CZ' => 21, 'DK' => 25, 'EE' => 22,
            'FI' => 25, 'FR' => 20, 'GR' => 24, 'ES' => 21, 'NL' => 21, 'IE' => 23,
            'IS' => 24, 'LT' => 21, 'LU' => 17, 'LV' => 21, 'MK' => 18, 'MT' => 18,
            'MD' => 20, 'DE' => 19, 'NO' => 25, 'PL' => 23, 'PT' => 23, 'RO' => 19,
            'RS' => 20, 'SK' => 20, 'SI' => 22, 'CH' => 8,  'SE' => 25, 'UA' => 20,
            'HU' => 27, 'GB' => 20, 'IT' => 22,
        );
    }

    /* ──────────────────────────────────────────────
     * Page Output
     * ────────────────────────────────────────────── */

    private static function output_page() {
        $step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';
        if ( ! in_array( $step, self::$steps, true ) ) {
            $step = 'welcome';
        }
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Flavor Commerce — Setup</title>
            <?php self::render_css(); ?>
        </head>
        <body class="fc-wizard-body">
            <div class="fc-wizard-page">
                <?php self::render_header(); ?>
                <?php self::render_progress( $step ); ?>
                <div class="fc-wizard-content">
                    <?php
                    switch ( $step ) {
                        case 'store':
                            self::render_store();
                            break;
                        case 'commerce':
                            self::render_commerce();
                            break;
                        case 'appearance':
                            self::render_appearance();
                            break;
                        case 'ready':
                            self::render_ready();
                            break;
                        case 'welcome':
                        default:
                            self::render_welcome();
                            break;
                    }
                    ?>
                </div>
            </div>
            <script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
            <?php self::render_js( $step ); ?>
        </body>
        </html>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Header & Progress
     * ────────────────────────────────────────────── */

    private static function render_header() {
        ?>
        <div class="fc-wizard-header">
            <div class="fc-wizard-logo">
                <span class="fc-wizard-logo-icon dashicons dashicons-store"></span>
                <span class="fc-wizard-logo-text">Flavor Commerce</span>
            </div>
        </div>
        <?php
    }

    private static function render_progress( $current_step ) {
        $step_labels = array(
            'welcome'  => fc__( 'wizard_step_language', 'admin' ),
            'store'    => fc__( 'wizard_step_store', 'admin' ),
            'commerce' => fc__( 'wizard_step_commerce', 'admin' ),
            'appearance' => fc__( 'wizard_step_appearance', 'admin' ),
            'ready'    => fc__( 'wizard_step_ready', 'admin' ),
        );

        $current_index = array_search( $current_step, self::$steps, true );
        ?>
        <div class="fc-wizard-progress">
            <?php foreach ( self::$steps as $i => $step_key ) : ?>
                <?php
                $is_done    = $i < $current_index;
                $is_active  = $i === $current_index;
                $class      = $is_done ? 'done' : ( $is_active ? 'active' : '' );
                ?>
                <?php if ( $i > 0 ) : ?>
                    <div class="fc-wizard-progress-line <?php echo $is_done ? 'done' : ''; ?>"></div>
                <?php endif; ?>
                <div class="fc-wizard-progress-step <?php echo esc_attr( $class ); ?>">
                    <div class="fc-wizard-progress-circle">
                        <?php if ( $is_done ) : ?>
                            <svg viewBox="0 0 20 20" width="14" height="14"><polyline points="4 10 8 14 16 6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php else : ?>
                            <?php echo $i + 1; ?>
                        <?php endif; ?>
                    </div>
                    <div class="fc-wizard-progress-label"><?php echo esc_html( $step_labels[ $step_key ] ); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Step 1: Welcome & Language
     * ────────────────────────────────────────────── */

    private static function render_welcome() {
        $languages    = FC_i18n::get_available_languages();
        $frontend_lang = get_option( 'fc_frontend_lang', 'pl' );
        $admin_lang    = get_option( 'fc_admin_lang', 'pl' );

        // Map language codes to country codes for flags
        $lang_to_country = array(
            'pl' => 'PL', 'en' => 'GB', 'de' => 'DE', 'fr' => 'FR',
            'es' => 'ES', 'it' => 'IT', 'cs' => 'CZ', 'sk' => 'SK',
        );
        ?>
        <div class="fc-wizard-card">
            <div class="fc-wizard-card-header">
                <div class="fc-wizard-card-icon"><span class="dashicons dashicons-translation"></span></div>
                <h1><?php fc_e( 'wizard_welcome_title', 'admin' ); ?></h1>
                <p class="fc-wizard-card-desc"><?php fc_e( 'wizard_welcome_desc', 'admin' ); ?></p>
            </div>

            <form method="post" class="fc-wizard-form">
                <?php wp_nonce_field( 'fc_wizard_welcome', 'fc_wizard_nonce' ); ?>
                <input type="hidden" name="fc_wizard_step" value="welcome">

                <div class="fc-wizard-field">
                    <label><?php fc_e( 'wizard_frontend_lang', 'admin' ); ?></label>
                    <p class="fc-wizard-field-desc"><?php fc_e( 'wizard_frontend_lang_desc', 'admin' ); ?></p>
                    <div class="fc-wizard-lang-select" id="fc-wizard-lang-frontend">
                        <input type="hidden" name="fc_frontend_lang" value="<?php echo esc_attr( $frontend_lang ); ?>">
                        <button type="button" class="fc-wizard-lang-btn">
                            <?php echo self::get_country_flag( $lang_to_country[ $frontend_lang ] ?? 'PL' ); ?>
                            <span class="fc-wizard-lang-btn-name"><?php echo esc_html( $languages[ $frontend_lang ] ?? $frontend_lang ); ?></span>
                            <span class="fc-wizard-lang-arrow">&#9662;</span>
                        </button>
                        <div class="fc-wizard-lang-dd">
                            <ul class="fc-wizard-lang-dd-list">
                                <?php foreach ( $languages as $code => $name ) : ?>
                                    <li data-code="<?php echo esc_attr( $code ); ?>" data-country="<?php echo esc_attr( $lang_to_country[ $code ] ?? 'PL' ); ?>" class="<?php echo $code === $frontend_lang ? 'active' : ''; ?>">
                                        <?php echo self::get_country_flag( $lang_to_country[ $code ] ?? 'PL' ); ?>
                                        <span><?php echo esc_html( $name ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="fc-wizard-field">
                    <label><?php fc_e( 'wizard_admin_lang', 'admin' ); ?></label>
                    <p class="fc-wizard-field-desc"><?php fc_e( 'wizard_admin_lang_desc', 'admin' ); ?></p>
                    <div class="fc-wizard-lang-select" id="fc-wizard-lang-admin">
                        <input type="hidden" name="fc_admin_lang" value="<?php echo esc_attr( $admin_lang ); ?>">
                        <button type="button" class="fc-wizard-lang-btn">
                            <?php echo self::get_country_flag( $lang_to_country[ $admin_lang ] ?? 'PL' ); ?>
                            <span class="fc-wizard-lang-btn-name"><?php echo esc_html( $languages[ $admin_lang ] ?? $admin_lang ); ?></span>
                            <span class="fc-wizard-lang-arrow">&#9662;</span>
                        </button>
                        <div class="fc-wizard-lang-dd">
                            <ul class="fc-wizard-lang-dd-list">
                                <?php foreach ( $languages as $code => $name ) : ?>
                                    <li data-code="<?php echo esc_attr( $code ); ?>" data-country="<?php echo esc_attr( $lang_to_country[ $code ] ?? 'PL' ); ?>" class="<?php echo $code === $admin_lang ? 'active' : ''; ?>">
                                        <?php echo self::get_country_flag( $lang_to_country[ $code ] ?? 'PL' ); ?>
                                        <span><?php echo esc_html( $name ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="fc-wizard-actions">
                    <a href="<?php echo esc_url( admin_url() ); ?>" class="fc-wizard-btn fc-wizard-btn-skip"><?php fc_e( 'wizard_skip', 'admin' ); ?></a>
                    <button type="submit" class="fc-wizard-btn fc-wizard-btn-primary"><?php fc_e( 'wizard_next', 'admin' ); ?> →</button>
                </div>
            </form>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Step 2: Store Details
     * ────────────────────────────────────────────── */

    private static function render_store() {
        $countries    = self::get_countries();
        $prefixes     = self::get_country_prefix_map();
        $tax_labels   = self::get_country_tax_labels();
        $country      = get_option( 'fc_store_country', 'PL' );
        $prefix       = get_option( 'fc_store_phone_prefix', $prefixes[ $country ] ?? '+48' );
        $current_tax  = $tax_labels[ $country ] ?? array( 'tax_no' => 'NIP', 'reg' => 'KRS / REGON' );

        // Determine current phone prefix country code
        $phone_country = $country;
        foreach ( $prefixes as $cc => $p ) {
            if ( $p === $prefix ) { $phone_country = $cc; break; }
        }
        ?>
        <div class="fc-wizard-card">
            <div class="fc-wizard-card-header">
                <div class="fc-wizard-card-icon"><span class="dashicons dashicons-admin-home"></span></div>
                <h1><?php fc_e( 'wizard_store_title', 'admin' ); ?></h1>
                <p class="fc-wizard-card-desc"><?php fc_e( 'wizard_store_desc', 'admin' ); ?></p>
            </div>

            <form method="post" class="fc-wizard-form">
                <?php wp_nonce_field( 'fc_wizard_store', 'fc_wizard_nonce' ); ?>
                <input type="hidden" name="fc_wizard_step" value="store">

                <div class="fc-wizard-field">
                    <label for="fc_store_name"><?php fc_e( 'set_store_name', 'admin' ); ?> <span class="required">*</span></label>
                    <input type="text" id="fc_store_name" name="fc_store_name" value="<?php echo esc_attr( get_option( 'fc_store_name', '' ) ); ?>" required>
                </div>

                <div class="fc-wizard-field">
                    <label><?php fc_e( 'set_country', 'admin' ); ?></label>
                    <div class="fc-wizard-country-select" id="fc-wizard-country-select">
                        <input type="hidden" name="fc_store_country" id="fc_store_country" value="<?php echo esc_attr( $country ); ?>">
                        <button type="button" class="fc-wizard-country-btn">
                            <?php echo self::get_country_flag( $country ); ?>
                            <span class="fc-wizard-country-name"><?php echo esc_html( $countries[ $country ] ?? '' ); ?></span>
                            <span class="fc-wizard-country-arrow">&#9662;</span>
                        </button>
                        <div class="fc-wizard-country-dd">
                            <div class="fc-wizard-country-dd-search">
                                <input type="text" placeholder="<?php echo esc_attr( fc__( 'set_search_country', 'admin' ) ); ?>" autocomplete="off">
                            </div>
                            <ul class="fc-wizard-country-dd-list">
                                <?php foreach ( $countries as $code => $name ) : ?>
                                    <li data-code="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( $name ); ?>" class="<?php echo $code === $country ? 'active' : ''; ?>">
                                        <?php echo self::get_country_flag( $code ); ?>
                                        <span class="fc-wizard-cl-name"><?php echo esc_html( $name ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="fc-wizard-field">
                    <label for="fc_store_street"><?php fc_e( 'set_street_and_number', 'admin' ); ?></label>
                    <input type="text" id="fc_store_street" name="fc_store_street" value="<?php echo esc_attr( get_option( 'fc_store_street', '' ) ); ?>">
                </div>

                <div class="fc-wizard-row-2" style="grid-template-columns: 160px 1fr;">
                    <div class="fc-wizard-field">
                        <label for="fc_store_postcode"><?php fc_e( 'wizard_postcode', 'admin' ); ?></label>
                        <input type="text" id="fc_store_postcode" name="fc_store_postcode" value="<?php echo esc_attr( get_option( 'fc_store_postcode', '' ) ); ?>">
                    </div>
                    <div class="fc-wizard-field">
                        <label for="fc_store_city"><?php fc_e( 'wizard_city', 'admin' ); ?></label>
                        <input type="text" id="fc_store_city" name="fc_store_city" value="<?php echo esc_attr( get_option( 'fc_store_city', '' ) ); ?>">
                    </div>
                </div>

                <div class="fc-wizard-separator"></div>

                <div class="fc-wizard-row-2">
                    <div class="fc-wizard-field">
                        <label for="fc_store_email"><?php fc_e( 'set_administrator_email', 'admin' ); ?> <span class="required">*</span></label>
                        <input type="email" id="fc_store_email" name="fc_store_email" value="<?php echo esc_attr( get_option( 'fc_store_email', get_option( 'admin_email', '' ) ) ); ?>" required>
                    </div>
                    <div class="fc-wizard-field">
                        <label for="fc_store_email_contact"><?php fc_e( 'set_contact_email', 'admin' ); ?></label>
                        <input type="email" id="fc_store_email_contact" name="fc_store_email_contact" value="<?php echo esc_attr( get_option( 'fc_store_email_contact', '' ) ); ?>">
                    </div>
                </div>

                <div class="fc-wizard-field">
                    <label><?php fc_e( 'set_phone', 'admin' ); ?></label>
                    <div class="fc-wizard-phone-wrap" id="fc-wizard-phone-wrap">
                        <button type="button" class="fc-wizard-phone-btn" data-current="<?php echo esc_attr( $phone_country ); ?>">
                            <?php echo self::get_country_flag( $phone_country ); ?>
                            <span class="fc-wizard-phone-prefix"><?php echo esc_html( $prefix ); ?></span>
                            <span class="fc-wizard-phone-arrow">&#9662;</span>
                        </button>
                        <input type="hidden" name="fc_store_phone_prefix" id="fc_store_phone_prefix" value="<?php echo esc_attr( $prefix ); ?>">
                        <div class="fc-wizard-phone-dd">
                            <div class="fc-wizard-phone-dd-search">
                                <input type="text" placeholder="<?php echo esc_attr( fc__( 'set_search_country', 'admin' ) ); ?>" autocomplete="off">
                            </div>
                            <ul class="fc-wizard-phone-dd-list">
                                <?php foreach ( $countries as $code => $name ) : ?>
                                    <li data-code="<?php echo esc_attr( $code ); ?>" data-prefix="<?php echo esc_attr( $prefixes[ $code ] ?? '' ); ?>" data-name="<?php echo esc_attr( $name ); ?>" class="<?php echo $code === $phone_country ? 'active' : ''; ?>">
                                        <?php echo self::get_country_flag( $code ); ?>
                                        <span class="fc-wizard-dl-name"><?php echo esc_html( $name ); ?></span>
                                        <span class="fc-wizard-dl-prefix"><?php echo esc_html( $prefixes[ $code ] ?? '' ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <input type="tel" name="fc_store_phone" id="fc_store_phone" value="<?php echo esc_attr( get_option( 'fc_store_phone', '' ) ); ?>">
                    </div>
                </div>

                <div class="fc-wizard-separator"></div>

                <div class="fc-wizard-row-2">
                    <div class="fc-wizard-field">
                        <label for="fc_store_tax_no"><span id="fc_tax_no_label"><?php echo esc_html( $current_tax['tax_no'] ); ?></span></label>
                        <input type="text" id="fc_store_tax_no" name="fc_store_tax_no" value="<?php echo esc_attr( get_option( 'fc_store_tax_no', '' ) ); ?>">
                    </div>
                    <div class="fc-wizard-field">
                        <label for="fc_store_crn"><span id="fc_crn_label"><?php echo esc_html( $current_tax['reg'] ); ?></span></label>
                        <input type="text" id="fc_store_crn" name="fc_store_crn" value="<?php echo esc_attr( get_option( 'fc_store_crn', '' ) ); ?>">
                    </div>
                </div>

                <div class="fc-wizard-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fc-setup-wizard&step=welcome' ) ); ?>" class="fc-wizard-btn fc-wizard-btn-secondary">← <?php fc_e( 'wizard_prev', 'admin' ); ?></a>
                    <button type="submit" class="fc-wizard-btn fc-wizard-btn-primary"><?php fc_e( 'wizard_next', 'admin' ); ?> →</button>
                </div>
            </form>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Step 3: Currency, Tax & Selling Countries
     * ────────────────────────────────────────────── */

    private static function render_commerce() {
        $country      = get_option( 'fc_store_country', 'PL' );
        $currency     = get_option( 'fc_currency', 'PLN' );
        $currencies   = self::get_currency_data();
        $countries    = self::get_countries();
        $tax_rates    = self::get_default_tax_rates();
        $tax_rate     = get_option( 'fc_tax_rate', $tax_rates[ $country ] ?? 23 );
        $tax_name     = get_option( 'fc_tax_name', 'VAT' );
        $tax_included = get_option( 'fc_tax_included', 'yes' );
        $sell_mode    = get_option( 'fc_sell_to_mode', 'all' );
        $sell_included = get_option( 'fc_sell_to_included', array() );
        $sell_excluded = get_option( 'fc_sell_to_excluded', array() );
        $currency_pos  = get_option( 'fc_currency_pos', $currencies[ $currency ]['pos'] ?? 'after' );
        $currency_sym  = get_option( 'fc_currency_symbol', $currencies[ $currency ]['symbol'] ?? '' );
        if ( ! is_array( $sell_included ) ) $sell_included = array();
        if ( ! is_array( $sell_excluded ) ) $sell_excluded = array();
        ?>
        <div class="fc-wizard-card">
            <div class="fc-wizard-card-header">
                <div class="fc-wizard-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <h1><?php fc_e( 'wizard_commerce_title', 'admin' ); ?></h1>
                <p class="fc-wizard-card-desc"><?php fc_e( 'wizard_commerce_desc', 'admin' ); ?></p>
            </div>

            <form method="post" class="fc-wizard-form">
                <?php wp_nonce_field( 'fc_wizard_commerce', 'fc_wizard_nonce' ); ?>
                <input type="hidden" name="fc_wizard_step" value="commerce">

                <h3 class="fc-wizard-section-title"><?php fc_e( 'set_currency', 'admin' ); ?></h3>

                <div class="fc-wizard-field">
                    <label for="fc_currency"><?php fc_e( 'set_currency', 'admin' ); ?></label>
                    <select id="fc_currency" name="fc_currency">
                        <?php foreach ( $currencies as $code => $data ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" data-symbol="<?php echo esc_attr( $data['symbol'] ); ?>" data-pos="<?php echo esc_attr( $data['pos'] ); ?>" <?php selected( $currency, $code ); ?>><?php echo esc_html( $data['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fc-wizard-field">
                    <label><?php fc_e( 'set_symbol_position', 'admin' ); ?></label>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="text" id="fc_currency_symbol" name="fc_currency_symbol" value="<?php echo esc_attr( $currency_sym ); ?>" readonly style="text-align:center;width:60px;flex-shrink:0;">
                        <select id="fc_currency_pos" name="fc_currency_pos" style="flex:1;">
                            <option value="before" <?php selected( $currency_pos, 'before' ); ?>><?php printf( fc__( 'set_before_price_10_00', 'admin' ), esc_html( $currency_sym ) ); ?></option>
                            <option value="after" <?php selected( $currency_pos, 'after' ); ?>><?php printf( fc__( 'set_after_price_10_00', 'admin' ), esc_html( $currency_sym ) ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="fc-wizard-separator"></div>
                <h3 class="fc-wizard-section-title"><?php fc_e( 'wizard_tax_settings', 'admin' ); ?></h3>

                <div class="fc-wizard-row-3">
                    <div class="fc-wizard-field">
                        <label for="fc_tax_name"><?php fc_e( 'set_tax_name', 'admin' ); ?></label>
                        <input type="text" id="fc_tax_name" name="fc_tax_name" value="<?php echo esc_attr( $tax_name ); ?>" style="max-width:120px;">
                    </div>
                    <div class="fc-wizard-field">
                        <label for="fc_tax_rate"><?php fc_e( 'set_default_rate', 'admin' ); ?></label>
                        <div class="fc-wizard-input-suffix">
                            <input type="number" id="fc_tax_rate" name="fc_tax_rate" value="<?php echo esc_attr( $tax_rate ); ?>" min="0" max="100" step="0.01" style="max-width:100px;">
                            <span>%</span>
                        </div>
                    </div>
                    <div class="fc-wizard-field">
                        <label for="fc_tax_included"><?php fc_e( 'set_prices_include_tax', 'admin' ); ?></label>
                        <select id="fc_tax_included" name="fc_tax_included">
                            <option value="yes" <?php selected( $tax_included, 'yes' ); ?>><?php fc_e( 'set_yes_prices_in_the_store_are_gross_include_tax', 'admin' ); ?></option>
                            <option value="no" <?php selected( $tax_included, 'no' ); ?>><?php fc_e( 'set_no_prices_in_the_store_are_net_tax_added', 'admin' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="fc-wizard-separator"></div>
                <h3 class="fc-wizard-section-title"><?php fc_e( 'wizard_sell_countries', 'admin' ); ?></h3>

                <div class="fc-wizard-field fc-wizard-sell-mode">
                    <label class="fc-wizard-radio">
                        <input type="radio" name="fc_sell_to_mode" value="all" <?php checked( $sell_mode, 'all' ); ?>>
                        <span><?php fc_e( 'set_all_countries', 'admin' ); ?></span>
                    </label>
                    <label class="fc-wizard-radio">
                        <input type="radio" name="fc_sell_to_mode" value="include" <?php checked( $sell_mode, 'include' ); ?>>
                        <span><?php fc_e( 'wizard_sell_selected', 'admin' ); ?></span>
                    </label>
                    <label class="fc-wizard-radio">
                        <input type="radio" name="fc_sell_to_mode" value="exclude" <?php checked( $sell_mode, 'exclude' ); ?>>
                        <span><?php fc_e( 'wizard_sell_all_except', 'admin' ); ?></span>
                    </label>
                </div>

                <div class="fc-wizard-countries-wrap" id="fc-wizard-countries-include" style="<?php echo $sell_mode === 'include' ? '' : 'display:none;'; ?>">
                    <input type="text" class="fc-wizard-country-search" placeholder="<?php echo esc_attr( fc__( 'set_search_country', 'admin' ) ); ?>" data-target="include">
                    <div class="fc-wizard-country-grid">
                        <?php foreach ( $countries as $code => $name ) : ?>
                            <label class="fc-wizard-country-item">
                                <input type="checkbox" name="fc_sell_to_included[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $sell_included, true ) ); ?>>
                                <span><?php echo self::get_country_flag( $code ) . ' ' . esc_html( $name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="fc-wizard-countries-wrap" id="fc-wizard-countries-exclude" style="<?php echo $sell_mode === 'exclude' ? '' : 'display:none;'; ?>">
                    <input type="text" class="fc-wizard-country-search" placeholder="<?php echo esc_attr( fc__( 'set_search_country', 'admin' ) ); ?>" data-target="exclude">
                    <div class="fc-wizard-country-grid">
                        <?php foreach ( $countries as $code => $name ) : ?>
                            <label class="fc-wizard-country-item">
                                <input type="checkbox" name="fc_sell_to_excluded[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $sell_excluded, true ) ); ?>>
                                <span><?php echo self::get_country_flag( $code ) . ' ' . esc_html( $name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="fc-wizard-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fc-setup-wizard&step=store' ) ); ?>" class="fc-wizard-btn fc-wizard-btn-secondary">← <?php fc_e( 'wizard_prev', 'admin' ); ?></a>
                    <button type="submit" class="fc-wizard-btn fc-wizard-btn-primary"><?php fc_e( 'wizard_next', 'admin' ); ?> →</button>
                </div>
            </form>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Step 4: Appearance
     * ────────────────────────────────────────────── */

    private static function render_appearance() {
        $accent = get_theme_mod( 'flavor_color_accent', '#4a90d9' );
        $mode   = get_theme_mod( 'flavor_color_mode', 'light' );

        $preset_colors = array(
            '#4a90d9', '#6c5ce7', '#e84393', '#d63031',
            '#e17055', '#fdcb6e', '#00b894', '#00cec9',
            '#0984e3', '#2d3436', '#636e72', '#b2bec3',
        );
        ?>
        <div class="fc-wizard-card">
            <div class="fc-wizard-card-header">
                <div class="fc-wizard-card-icon"><span class="dashicons dashicons-art"></span></div>
                <h1><?php fc_e( 'wizard_appearance_title', 'admin' ); ?></h1>
                <p class="fc-wizard-card-desc"><?php fc_e( 'wizard_appearance_desc', 'admin' ); ?></p>
            </div>

            <form method="post" class="fc-wizard-form">
                <?php wp_nonce_field( 'fc_wizard_appearance', 'fc_wizard_nonce' ); ?>
                <input type="hidden" name="fc_wizard_step" value="appearance">

                <!-- Accent color -->
                <div class="fc-wizard-field">
                    <label><?php fc_e( 'wizard_accent_color', 'admin' ); ?></label>
                    <p class="fc-wizard-field-desc"><?php fc_e( 'wizard_accent_color_desc', 'admin' ); ?></p>

                    <div class="fc-wizard-color-wrap">
                        <div class="fc-wizard-color-presets">
                            <?php foreach ( $preset_colors as $color ) : ?>
                                <button type="button" class="fc-wizard-color-swatch<?php echo $color === $accent ? ' active' : ''; ?>" data-color="<?php echo esc_attr( $color ); ?>" style="background:<?php echo esc_attr( $color ); ?>;"></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="fc-wizard-color-custom">
                            <label class="fc-wizard-color-custom-label">
                                <input type="color" id="fc-accent-picker" value="<?php echo esc_attr( $accent ); ?>">
                                <span class="fc-wizard-color-hex" id="fc-accent-hex"><?php echo esc_attr( $accent ); ?></span>
                            </label>
                        </div>
                        <input type="hidden" name="fc_accent_color" id="fc_accent_color" value="<?php echo esc_attr( $accent ); ?>">
                    </div>

                    <!-- Preview -->
                    <div class="fc-wizard-accent-preview" id="fc-accent-preview">
                        <p style="margin:0 0 10px;font-weight:600;"><?php fc_e( 'wizard_accent_preview', 'admin' ); ?></p>
                        <button type="button" class="fc-wizard-preview-btn-primary" id="fc-preview-btn"><?php fc_e( 'wizard_next', 'admin' ); ?> →</button>
                        <a href="#" class="fc-wizard-preview-link" id="fc-preview-link">Link</a>
                    </div>
                </div>

                <!-- Theme mode -->
                <div class="fc-wizard-field">
                    <label><?php fc_e( 'wizard_theme_mode', 'admin' ); ?></label>
                    <p class="fc-wizard-field-desc"><?php fc_e( 'wizard_theme_mode_desc', 'admin' ); ?></p>

                    <div class="fc-wizard-mode-cards">
                        <label class="fc-wizard-mode-card<?php echo $mode === 'light' ? ' selected' : ''; ?>" data-mode="light">
                            <input type="radio" name="fc_theme_mode" value="light" <?php checked( $mode, 'light' ); ?>>
                            <div class="fc-wizard-mode-preview fc-wizard-mode-light">
                                <div class="fc-wizard-mode-bar"></div>
                                <div class="fc-wizard-mode-body">
                                    <div class="fc-wizard-mode-line" style="width:60%"></div>
                                    <div class="fc-wizard-mode-line" style="width:80%"></div>
                                    <div class="fc-wizard-mode-line" style="width:40%"></div>
                                </div>
                            </div>
                            <span class="fc-wizard-mode-label"><?php fc_e( 'wizard_mode_light', 'admin' ); ?></span>
                        </label>
                        <label class="fc-wizard-mode-card<?php echo $mode === 'dark' ? ' selected' : ''; ?>" data-mode="dark">
                            <input type="radio" name="fc_theme_mode" value="dark" <?php checked( $mode, 'dark' ); ?>>
                            <div class="fc-wizard-mode-preview fc-wizard-mode-dark">
                                <div class="fc-wizard-mode-bar"></div>
                                <div class="fc-wizard-mode-body">
                                    <div class="fc-wizard-mode-line" style="width:60%"></div>
                                    <div class="fc-wizard-mode-line" style="width:80%"></div>
                                    <div class="fc-wizard-mode-line" style="width:40%"></div>
                                </div>
                            </div>
                            <span class="fc-wizard-mode-label"><?php fc_e( 'wizard_mode_dark', 'admin' ); ?></span>
                        </label>
                    </div>
                </div>

                <div class="fc-wizard-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fc-setup-wizard&step=commerce' ) ); ?>" class="fc-wizard-btn fc-wizard-btn-secondary">← <?php fc_e( 'wizard_prev', 'admin' ); ?></a>
                    <button type="submit" class="fc-wizard-btn fc-wizard-btn-primary"><?php fc_e( 'wizard_next', 'admin' ); ?> →</button>
                </div>
            </form>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Step 5: Ready! Summary
     * ────────────────────────────────────────────── */

    private static function render_ready() {
        $countries   = self::get_countries();
        $currencies  = self::get_currency_data();
        $languages   = FC_i18n::get_available_languages();

        $store_name   = get_option( 'fc_store_name', '—' );
        $country_code = get_option( 'fc_store_country', 'PL' );
        $country_name = $countries[ $country_code ] ?? $country_code;
        $currency     = get_option( 'fc_currency', 'PLN' );
        $currency_lbl = $currencies[ $currency ]['label'] ?? $currency;
        $tax_rate     = get_option( 'fc_tax_rate', 23 );
        $tax_name     = get_option( 'fc_tax_name', 'VAT' );
        $tax_incl     = get_option( 'fc_tax_included', 'yes' );
        $front_lang   = get_option( 'fc_frontend_lang', 'pl' );
        $admin_lang   = get_option( 'fc_admin_lang', 'pl' );
        $front_lang_name = $languages[ $front_lang ] ?? $front_lang;
        $admin_lang_name = $languages[ $admin_lang ] ?? $admin_lang;
        $sell_mode    = get_option( 'fc_sell_to_mode', 'all' );
        $email        = get_option( 'fc_store_email', '—' );
        $phone_prefix = get_option( 'fc_store_phone_prefix', '' );
        $phone        = get_option( 'fc_store_phone', '' );
        $address      = trim( get_option( 'fc_store_street', '' ) . ', ' . get_option( 'fc_store_postcode', '' ) . ' ' . get_option( 'fc_store_city', '' ), ', ' );

        $theme_exists = wp_get_theme( 'flavor' )->exists();
        $theme_active = get_stylesheet() === 'flavor';

        // Count existing store pages
        $page_options = array(
            'fc_page_sklep', 'fc_page_koszyk', 'fc_page_zamowienie', 'fc_page_podziekowanie',
            'fc_page_moje-konto', 'fc_page_wishlist', 'fc_page_porownanie', 'fc_page_platnosc_nieudana',
        );
        $pages_count = 0;
        foreach ( $page_options as $opt ) {
            $pid = (int) get_option( $opt, 0 );
            if ( $pid && get_post_status( $pid ) === 'publish' ) {
                $pages_count++;
            }
        }
        ?>
        <div class="fc-wizard-card">
            <div class="fc-wizard-card-header">
                <div class="fc-wizard-card-icon fc-wizard-success-icon"><span class="dashicons dashicons-yes-alt"></span></div>
                <h1><?php fc_e( 'wizard_ready_title', 'admin' ); ?></h1>
                <p class="fc-wizard-card-desc"><?php fc_e( 'wizard_ready_desc', 'admin' ); ?></p>
            </div>

            <div class="fc-wizard-summary">
                <div class="fc-wizard-summary-section">
                    <h3><?php fc_e( 'wizard_step_store', 'admin' ); ?></h3>
                    <table class="fc-wizard-summary-table">
                        <tr>
                            <td><?php fc_e( 'set_store_name', 'admin' ); ?></td>
                            <td><strong><?php echo esc_html( $store_name ); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php fc_e( 'set_country', 'admin' ); ?></td>
                            <td><strong><?php echo self::get_country_flag( $country_code ) . ' ' . esc_html( $country_name ); ?></strong></td>
                        </tr>
                        <?php if ( $address && $address !== ', ' ) : ?>
                        <tr>
                            <td><?php fc_e( 'wizard_address', 'admin' ); ?></td>
                            <td><?php echo esc_html( $address ); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>E-mail</td>
                            <td><?php echo esc_html( $email ); ?></td>
                        </tr>
                        <?php if ( $phone ) : ?>
                        <tr>
                            <td><?php fc_e( 'set_phone', 'admin' ); ?></td>
                            <td><?php echo esc_html( $phone_prefix . ' ' . $phone ); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="fc-wizard-summary-section">
                    <h3><?php fc_e( 'wizard_step_commerce', 'admin' ); ?></h3>
                    <table class="fc-wizard-summary-table">
                        <tr>
                            <td><?php fc_e( 'set_currency', 'admin' ); ?></td>
                            <td><strong><?php echo esc_html( $currency_lbl ); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php echo esc_html( $tax_name ); ?></td>
                            <td><strong><?php echo esc_html( $tax_rate . '%' ); ?></strong>
                                <span class="fc-wizard-badge"><?php echo $tax_incl === 'yes' ? fc__( 'set_yes_prices_in_the_store_are_gross_include_tax', 'admin' ) : fc__( 'set_no_prices_in_the_store_are_net_tax_added', 'admin' ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><?php fc_e( 'wizard_sell_countries', 'admin' ); ?></td>
                            <td>
                                <?php
                                if ( $sell_mode === 'all' ) {
                                    fc_e( 'set_all_countries', 'admin' );
                                } elseif ( $sell_mode === 'include' ) {
                                    $included = get_option( 'fc_sell_to_included', array() );
                                    $names = array();
                                    foreach ( (array) $included as $c ) {
                                        $names[] = $countries[ $c ] ?? $c;
                                    }
                                    echo esc_html( implode( ', ', $names ) ?: '—' );
                                } else {
                                    fc_e( 'wizard_sell_all_except', 'admin' );
                                    echo ': ';
                                    $excluded = get_option( 'fc_sell_to_excluded', array() );
                                    $names = array();
                                    foreach ( (array) $excluded as $c ) {
                                        $names[] = $countries[ $c ] ?? $c;
                                    }
                                    echo esc_html( implode( ', ', $names ) ?: '—' );
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="fc-wizard-summary-section">
                    <h3><?php fc_e( 'wizard_step_appearance', 'admin' ); ?></h3>
                    <table class="fc-wizard-summary-table">
                        <tr>
                            <td><?php fc_e( 'wizard_accent_color', 'admin' ); ?></td>
                            <td>
                                <span class="fc-wizard-summary-color" style="display:inline-block;width:16px;height:16px;border-radius:4px;vertical-align:middle;margin-right:6px;background:<?php echo esc_attr( get_theme_mod( 'flavor_color_accent', '#4a90d9' ) ); ?>;"></span>
                                <strong><?php echo esc_html( get_theme_mod( 'flavor_color_accent', '#4a90d9' ) ); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td><?php fc_e( 'wizard_theme_mode', 'admin' ); ?></td>
                            <td><strong><?php echo get_theme_mod( 'flavor_color_mode', 'light' ) === 'dark' ? fc__( 'wizard_mode_dark', 'admin' ) : fc__( 'wizard_mode_light', 'admin' ); ?></strong></td>
                        </tr>
                    </table>
                </div>

                <div class="fc-wizard-summary-section">
                    <h3><?php fc_e( 'wizard_step_language', 'admin' ); ?></h3>
                    <table class="fc-wizard-summary-table">
                        <tr>
                            <td><?php fc_e( 'wizard_frontend_lang', 'admin' ); ?></td>
                            <td><strong><?php echo esc_html( $front_lang_name ); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php fc_e( 'wizard_admin_lang', 'admin' ); ?></td>
                            <td><strong><?php echo esc_html( $admin_lang_name ); ?></strong></td>
                        </tr>
                    </table>
                </div>

                <div class="fc-wizard-summary-section">
                    <h3><?php fc_e( 'wizard_auto_config', 'admin' ); ?></h3>
                    <div class="fc-wizard-checklist">
                        <div class="fc-wizard-check-item <?php echo $pages_count >= 8 ? 'done' : 'pending'; ?>">
                            <span class="dashicons <?php echo $pages_count >= 8 ? 'dashicons-yes' : 'dashicons-clock'; ?>"></span>
                            <?php printf( fc__( 'wizard_pages_status', 'admin' ), $pages_count ); ?>
                        </div>
                        <div class="fc-wizard-check-item <?php echo $theme_active ? 'done' : ( $theme_exists ? 'pending' : 'warning' ); ?>">
                            <span class="dashicons <?php echo $theme_active ? 'dashicons-yes' : ( $theme_exists ? 'dashicons-clock' : 'dashicons-warning' ); ?>"></span>
                            <?php
                            if ( $theme_active ) {
                                fc_e( 'wizard_theme_active', 'admin' );
                            } elseif ( $theme_exists ) {
                                fc_e( 'wizard_theme_will_activate', 'admin' );
                            } else {
                                fc_e( 'wizard_theme_not_found', 'admin' );
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" class="fc-wizard-form">
                <?php wp_nonce_field( 'fc_wizard_ready', 'fc_wizard_nonce' ); ?>
                <input type="hidden" name="fc_wizard_step" value="ready">

                <div class="fc-wizard-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fc-setup-wizard&step=appearance' ) ); ?>" class="fc-wizard-btn fc-wizard-btn-secondary">← <?php fc_e( 'wizard_prev', 'admin' ); ?></a>
                    <button type="submit" class="fc-wizard-btn fc-wizard-btn-primary fc-wizard-btn-complete">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php fc_e( 'wizard_complete', 'admin' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     * Helpers
     * ────────────────────────────────────────────── */

    private static function get_lang_flag( $code ) {
        $flags = array(
            'pl' => '🇵🇱',
            'en' => '🇬🇧',
            'de' => '🇩🇪',
            'fr' => '🇫🇷',
            'es' => '🇪🇸',
            'it' => '🇮🇹',
            'cs' => '🇨🇿',
            'sk' => '🇸🇰',
        );
        return $flags[ $code ] ?? '🌐';
    }

    /**
     * Return an <img> tag for the country flag using flagcdn.com.
     */
    private static function get_country_flag( $code, $size = 20 ) {
        $code = strtolower( $code );
        $url  = 'https://flagcdn.com/w40/' . $code . '.png';
        return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( strtoupper( $code ) ) . '" class="fc-wizard-flag" width="' . intval( $size ) . '" height="' . intval( round( $size * 0.75 ) ) . '">';
    }

    /**
     * Adjust hex color brightness by a percentage (-100 to 100).
     */
    private static function adjust_hex_brightness( $hex, $percent ) {
        $hex = ltrim( $hex, '#' );
        $r   = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + (int) ( 255 * $percent / 100 ) ) );
        $g   = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + (int) ( 255 * $percent / 100 ) ) );
        $b   = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + (int) ( 255 * $percent / 100 ) ) );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Convert hex color to rgba string.
     */
    private static function hex_to_rgba( $hex, $alpha = 1.0 ) {
        $hex = ltrim( $hex, '#' );
        $r   = hexdec( substr( $hex, 0, 2 ) );
        $g   = hexdec( substr( $hex, 2, 2 ) );
        $b   = hexdec( substr( $hex, 4, 2 ) );
        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    /* ──────────────────────────────────────────────
     * CSS
     * ────────────────────────────────────────────── */

    private static function render_css() {
        ?>
        <link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
        <style>
            /* Reset & Base */
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            .fc-wizard-body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                color: #1d2327;
                line-height: 1.6;
                font-size: 14px;
            }

            /* Page */
            .fc-wizard-page {
                max-width: 720px;
                margin: 0 auto;
                padding: 40px 20px 60px;
            }

            /* Header */
            .fc-wizard-header {
                text-align: center;
                margin-bottom: 32px;
            }
            .fc-wizard-logo {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                color: #fff;
            }
            .fc-wizard-logo-icon {
                font-size: 32px;
                width: 32px;
                height: 32px;
                opacity: 0.9;
            }
            .fc-wizard-logo-text {
                font-size: 24px;
                font-weight: 700;
                letter-spacing: -0.5px;
            }

            /* Progress Bar */
            .fc-wizard-progress {
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 32px;
                padding: 0 20px;
            }
            .fc-wizard-progress-step {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                position: relative;
                z-index: 1;
            }
            .fc-wizard-progress-circle {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: rgba(255,255,255,0.2);
                color: rgba(255,255,255,0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s;
                border: 2px solid transparent;
            }
            .fc-wizard-progress-step.active .fc-wizard-progress-circle {
                background: #fff;
                color: #667eea;
                border-color: #fff;
                box-shadow: 0 2px 12px rgba(0,0,0,0.15);
                transform: scale(1.1);
            }
            .fc-wizard-progress-step.done .fc-wizard-progress-circle {
                background: rgba(255,255,255,0.9);
                color: #22c55e;
                border-color: rgba(255,255,255,0.9);
            }
            .fc-wizard-progress-label {
                font-size: 12px;
                color: rgba(255,255,255,0.6);
                font-weight: 500;
                white-space: nowrap;
            }
            .fc-wizard-progress-step.active .fc-wizard-progress-label {
                color: #fff;
                font-weight: 600;
            }
            .fc-wizard-progress-step.done .fc-wizard-progress-label {
                color: rgba(255,255,255,0.85);
            }
            .fc-wizard-progress-line {
                flex: 1;
                height: 2px;
                background: rgba(255,255,255,0.2);
                margin: 0 4px;
                margin-bottom: 28px;
                max-width: 80px;
            }
            .fc-wizard-progress-line.done {
                background: rgba(255,255,255,0.7);
            }

            /* Card */
            .fc-wizard-card {
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.12);
                overflow: hidden;
            }
            .fc-wizard-card-header {
                text-align: center;
                padding: 40px 40px 24px;
                border-bottom: 1px solid #f0f0f1;
            }
            .fc-wizard-card-icon {
                margin-bottom: 16px;
            }
            .fc-wizard-card-icon .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #667eea;
                background: linear-gradient(135deg, #eef2ff 0%, #e8e0f7 100%);
                border-radius: 16px;
                padding: 16px;
                width: 80px;
                height: 80px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .fc-wizard-success-icon .dashicons {
                color: #22c55e;
                background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            }
            .fc-wizard-card-header h1 {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 8px;
                color: #1d2327;
            }
            .fc-wizard-card-desc {
                color: #646970;
                font-size: 15px;
                max-width: 480px;
                margin: 0 auto;
            }

            /* Form */
            .fc-wizard-form {
                padding: 32px 40px 32px;
            }
            .fc-wizard-field {
                margin-bottom: 20px;
            }
            .fc-wizard-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
                color: #1d2327;
                font-size: 13px;
            }
            .fc-wizard-field label .required {
                color: #d63638;
            }
            .fc-wizard-field-desc {
                color: #646970;
                font-size: 13px;
                margin: -2px 0 8px !important;
            }
            .fc-wizard-field input[type="text"],
            .fc-wizard-field input[type="email"],
            .fc-wizard-field input[type="tel"],
            .fc-wizard-field input[type="number"],
            .fc-wizard-field select {
                width: 100%;
                padding: 10px 14px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                font-size: 14px;
                line-height: 1.5;
                transition: border-color 0.2s, box-shadow 0.2s;
                background: #fff;
                font-family: inherit;
            }
            .fc-wizard-field input:focus,
            .fc-wizard-field select:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
            }

            /* Row layouts */
            .fc-wizard-row-2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
            }
            .fc-wizard-row-3 {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 16px;
            }
            @media (max-width: 600px) {
                .fc-wizard-row-2,
                .fc-wizard-row-3 {
                    grid-template-columns: 1fr;
                }
                .fc-wizard-form { padding: 24px 20px; }
                .fc-wizard-card-header { padding: 32px 20px 20px; }
            }

            /* Input suffix (e.g., %) */
            .fc-wizard-input-suffix {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .fc-wizard-input-suffix span {
                font-weight: 600;
                color: #646970;
            }

            /* Separator */
            .fc-wizard-separator {
                border-top: 1px solid #f0f0f1;
                margin: 24px 0;
            }

            /* Section title */
            .fc-wizard-section-title {
                font-size: 15px;
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 16px;
            }

            /* Language cards */
            .fc-wizard-lang-cards {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            .fc-wizard-lang-card {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 24px;
                border: 2px solid #dcdcde;
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.2s;
                flex: 1;
                min-width: 140px;
            }
            .fc-wizard-lang-card:hover {
                border-color: #667eea;
                background: #f8f9ff;
            }
            .fc-wizard-lang-card.selected {
                border-color: #667eea;
                background: #eef2ff;
                box-shadow: 0 0 0 1px #667eea;
            }
            .fc-wizard-lang-card input[type="radio"] {
                display: none;
            }
            .fc-wizard-lang-flag {
                font-size: 28px;
                line-height: 1;
            }
            .fc-wizard-lang-name {
                font-weight: 600;
                font-size: 15px;
            }

            /* Radio buttons */
            .fc-wizard-radio {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
                margin-bottom: 8px;
            }
            .fc-wizard-radio:hover {
                border-color: #667eea;
                background: #f8f9ff;
            }
            .fc-wizard-radio input:checked + span {
                font-weight: 600;
                color: #667eea;
            }

            /* Country grid */
            .fc-wizard-countries-wrap {
                margin-top: 12px;
                border: 1px solid #dcdcde;
                border-radius: 12px;
                overflow: hidden;
            }
            .fc-wizard-country-search {
                width: 100%;
                padding: 12px 16px;
                border: none;
                border-bottom: 1px solid #f0f0f1;
                font-size: 14px;
                font-family: inherit;
                outline: none;
            }
            .fc-wizard-country-search:focus {
                background: #f8f9ff;
            }
            .fc-wizard-country-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                max-height: 240px;
                overflow-y: auto;
                padding: 8px;
            }
            .fc-wizard-country-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 6px 10px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
                transition: background 0.15s;
            }
            .fc-wizard-country-item:hover {
                background: #f0f0f1;
            }

            /* Actions */
            .fc-wizard-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 24px;
                border-top: 1px solid #f0f0f1;
                margin-top: 24px;
            }
            .fc-wizard-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 28px;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                border: none;
                font-family: inherit;
                line-height: 1.4;
            }
            .fc-wizard-btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
            }
            .fc-wizard-btn-primary:hover {
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
                transform: translateY(-1px);
            }
            .fc-wizard-btn-secondary {
                background: #fff;
                color: #646970;
                border: 1px solid #dcdcde;
            }
            .fc-wizard-btn-secondary:hover {
                border-color: #667eea;
                color: #667eea;
            }
            .fc-wizard-btn-skip {
                background: transparent;
                color: rgba(255,255,255,0.7);
                font-size: 13px;
                padding: 8px 0;
            }
            .fc-wizard-btn-skip:hover {
                color: rgba(255,255,255,1);
            }
            .fc-wizard-btn-complete {
                padding: 14px 36px;
                font-size: 15px;
            }
            .fc-wizard-btn-complete .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }

            /* Summary */
            .fc-wizard-summary {
                padding: 8px 40px 0;
            }
            .fc-wizard-summary-section {
                margin-bottom: 24px;
            }
            .fc-wizard-summary-section h3 {
                font-size: 13px;
                text-transform: uppercase;
                color: #646970;
                letter-spacing: 0.5px;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 1px solid #f0f0f1;
            }
            .fc-wizard-summary-table {
                width: 100%;
                border-collapse: collapse;
            }
            .fc-wizard-summary-table td {
                padding: 6px 0;
                font-size: 14px;
                vertical-align: top;
            }
            .fc-wizard-summary-table td:first-child {
                color: #646970;
                width: 180px;
            }

            /* Badge */
            .fc-wizard-badge {
                display: inline-block;
                padding: 2px 10px;
                background: #f0f0f1;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
                color: #646970;
                margin-left: 8px;
            }

            /* Checklist */
            .fc-wizard-checklist {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .fc-wizard-check-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                border-radius: 10px;
                font-size: 14px;
            }
            .fc-wizard-check-item.done {
                background: #f0fdf4;
                color: #166534;
            }
            .fc-wizard-check-item.done .dashicons {
                color: #22c55e;
            }
            .fc-wizard-check-item.pending {
                background: #eff6ff;
                color: #1e40af;
            }
            .fc-wizard-check-item.pending .dashicons {
                color: #3b82f6;
            }
            .fc-wizard-check-item.warning {
                background: #fefce8;
                color: #854d0e;
            }
            .fc-wizard-check-item.warning .dashicons {
                color: #eab308;
            }

            /* Sell mode section */
            .fc-wizard-sell-mode {
                display: flex;
                flex-direction: column;
            }

            /* Flag images */
            .fc-wizard-flag {
                width: 20px;
                height: 15px;
                object-fit: cover;
                border-radius: 2px;
                vertical-align: middle;
                box-shadow: 0 0 0 1px rgba(0,0,0,0.1);
                flex-shrink: 0;
            }

            /* Language dropdown (reuses country dropdown styling) */
            .fc-wizard-lang-select {
                position: relative;
            }
            .fc-wizard-lang-btn {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                padding: 10px 14px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                cursor: pointer;
                font-size: 14px;
                font-family: inherit;
                transition: border-color 0.2s, box-shadow 0.2s;
                text-align: left;
            }
            .fc-wizard-lang-btn:hover {
                border-color: #667eea;
            }
            .fc-wizard-lang-btn:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
            }
            .fc-wizard-lang-btn .fc-wizard-lang-btn-name {
                flex: 1;
                font-weight: 500;
                color: #1d2327;
            }
            .fc-wizard-lang-btn .fc-wizard-lang-arrow {
                font-size: 10px;
                color: #646970;
                transition: transform 0.2s;
            }
            .fc-wizard-lang-select.open .fc-wizard-lang-arrow {
                transform: rotate(180deg);
            }
            .fc-wizard-lang-dd {
                display: none;
                position: absolute;
                top: calc(100% + 4px);
                left: 0;
                width: 100%;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                z-index: 1000;
                overflow: hidden;
            }
            .fc-wizard-lang-select.open .fc-wizard-lang-dd {
                display: block;
            }
            .fc-wizard-lang-dd-list {
                list-style: none;
                padding: 4px 0;
                margin: 0;
            }
            .fc-wizard-lang-dd-list li {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                cursor: pointer;
                font-size: 14px;
                transition: background 0.12s;
            }
            .fc-wizard-lang-dd-list li:hover,
            .fc-wizard-lang-dd-list li.active {
                background: #f0f0f1;
            }
            .fc-wizard-lang-dd-list li.active {
                font-weight: 600;
                color: #667eea;
            }

            /* Custom country dropdown */
            .fc-wizard-country-select {
                position: relative;
            }
            .fc-wizard-country-btn {
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                padding: 10px 14px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
                cursor: pointer;
                font-size: 14px;
                font-family: inherit;
                transition: border-color 0.2s, box-shadow 0.2s;
                text-align: left;
            }
            .fc-wizard-country-btn:hover {
                border-color: #667eea;
            }
            .fc-wizard-country-btn:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
            }
            .fc-wizard-country-btn .fc-wizard-country-name {
                flex: 1;
                font-weight: 400;
                color: #1d2327;
            }
            .fc-wizard-country-btn .fc-wizard-country-arrow {
                font-size: 10px;
                color: #646970;
                transition: transform 0.2s;
            }
            .fc-wizard-country-select.open .fc-wizard-country-arrow {
                transform: rotate(180deg);
            }
            .fc-wizard-country-dd {
                display: none;
                position: absolute;
                top: calc(100% + 4px);
                left: 0;
                width: 100%;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                z-index: 1000;
                overflow: hidden;
            }
            .fc-wizard-country-select.open .fc-wizard-country-dd {
                display: block;
            }
            .fc-wizard-country-dd-search {
                padding: 8px;
                border-bottom: 1px solid #f0f0f1;
                position: sticky;
                top: 0;
                background: #fff;
            }
            .fc-wizard-country-dd-search input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                font-size: 13px;
                font-family: inherit;
                outline: none;
                box-sizing: border-box;
            }
            .fc-wizard-country-dd-search input:focus {
                border-color: #667eea;
            }
            .fc-wizard-country-dd-list {
                max-height: 240px;
                overflow-y: auto;
                list-style: none;
                padding: 4px 0;
                margin: 0;
            }
            .fc-wizard-country-dd-list li {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 14px;
                cursor: pointer;
                font-size: 14px;
                transition: background 0.12s;
            }
            .fc-wizard-country-dd-list li:hover,
            .fc-wizard-country-dd-list li.active {
                background: #f0f0f1;
            }
            .fc-wizard-country-dd-list li.active {
                font-weight: 600;
                color: #667eea;
            }
            .fc-wizard-country-dd-list li.hidden {
                display: none;
            }

            /* Country grid flag spacing */
            .fc-wizard-country-item .fc-wizard-flag {
                margin-right: 2px;
            }

            /* Phone field with flag dropdown */
            .fc-wizard-phone-wrap {
                display: flex;
                align-items: stretch;
                position: relative;
            }
            .fc-wizard-phone-btn {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 0 12px;
                border: 1px solid #dcdcde;
                border-right: none;
                border-radius: 8px 0 0 8px;
                background: #fff;
                cursor: pointer;
                font-size: 14px;
                font-family: inherit;
                white-space: nowrap;
                min-width: 110px;
                transition: border-color 0.2s;
            }
            .fc-wizard-phone-btn:hover {
                border-color: #667eea;
            }
            .fc-wizard-phone-btn .fc-wizard-phone-prefix {
                font-weight: 500;
                color: #50575e;
            }
            .fc-wizard-phone-btn .fc-wizard-phone-arrow {
                font-size: 10px;
                color: #646970;
                margin-left: 2px;
            }
            .fc-wizard-phone-wrap input[type="tel"] {
                flex: 1;
                min-width: 0;
                border-radius: 0 8px 8px 0;
                margin: 0;
                padding: 10px 14px;
                border: 1px solid #dcdcde;
                font-size: 14px;
                font-family: inherit;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .fc-wizard-phone-wrap input[type="tel"]:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
            }
            .fc-wizard-phone-dd {
                display: none;
                position: absolute;
                top: calc(100% + 4px);
                left: 0;
                width: 340px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                z-index: 1000;
                overflow: hidden;
            }
            .fc-wizard-phone-wrap.open .fc-wizard-phone-dd {
                display: block;
            }
            .fc-wizard-phone-dd-search {
                padding: 8px;
                border-bottom: 1px solid #f0f0f1;
                position: sticky;
                top: 0;
                background: #fff;
            }
            .fc-wizard-phone-dd-search input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                font-size: 13px;
                font-family: inherit;
                outline: none;
                box-sizing: border-box;
            }
            .fc-wizard-phone-dd-search input:focus {
                border-color: #667eea;
            }
            .fc-wizard-phone-dd-list {
                max-height: 240px;
                overflow-y: auto;
                list-style: none;
                padding: 4px 0;
                margin: 0;
            }
            .fc-wizard-phone-dd-list li {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 14px;
                cursor: pointer;
                font-size: 14px;
                transition: background 0.12s;
            }
            .fc-wizard-phone-dd-list li:hover,
            .fc-wizard-phone-dd-list li.active {
                background: #f0f0f1;
            }
            .fc-wizard-phone-dd-list li.active {
                font-weight: 600;
                color: #667eea;
            }
            .fc-wizard-phone-dd-list li.hidden {
                display: none;
            }
            .fc-wizard-dl-name {
                flex: 1;
            }
            .fc-wizard-dl-prefix {
                color: #646970;
                font-size: 13px;
            }

            /* ── Appearance step: Color picker ── */
            .fc-wizard-color-wrap {
                display: flex;
                flex-direction: column;
                gap: 14px;
            }
            .fc-wizard-color-presets {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .fc-wizard-color-swatch {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                border: 3px solid transparent;
                cursor: pointer;
                transition: border-color .2s, transform .15s;
                outline: none;
                padding: 0;
            }
            .fc-wizard-color-swatch:hover {
                transform: scale(1.12);
            }
            .fc-wizard-color-swatch.active {
                border-color: #1d2327;
                box-shadow: 0 0 0 2px #fff, 0 0 0 4px #1d2327;
            }
            .fc-wizard-color-custom {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .fc-wizard-color-custom-label {
                display: flex;
                align-items: center;
                gap: 10px;
                cursor: pointer;
            }
            .fc-wizard-color-custom-label input[type="color"] {
                width: 40px;
                height: 40px;
                border: 2px solid #dcdcde;
                border-radius: 10px;
                padding: 2px;
                cursor: pointer;
                background: none;
            }
            .fc-wizard-color-hex {
                font-family: monospace;
                font-size: 14px;
                color: #50575e;
                text-transform: uppercase;
            }

            /* Preview */
            .fc-wizard-accent-preview {
                margin-top: 12px;
                padding: 18px 20px;
                background: #f8f9fa;
                border-radius: 10px;
                border: 1px solid #e2e4e7;
            }
            .fc-wizard-preview-btn-primary {
                display: inline-block;
                padding: 10px 24px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                color: #fff;
                cursor: default;
                margin-right: 16px;
            }
            .fc-wizard-preview-link {
                font-size: 14px;
                font-weight: 600;
                text-decoration: underline;
            }

            /* ── Appearance step: Mode cards ── */
            .fc-wizard-mode-cards {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                margin-top: 4px;
            }
            .fc-wizard-mode-card {
                position: relative;
                border: 2px solid #dcdcde;
                border-radius: 12px;
                padding: 16px;
                text-align: center;
                cursor: pointer;
                transition: border-color .2s, box-shadow .2s;
            }
            .fc-wizard-mode-card:hover {
                border-color: #a7aaad;
            }
            .fc-wizard-mode-card.selected {
                border-color: #6c5ce7;
                box-shadow: 0 0 0 2px rgba(108,92,231,.18);
            }
            .fc-wizard-mode-card input[type="radio"] {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }
            .fc-wizard-mode-preview {
                border-radius: 8px;
                overflow: hidden;
                margin-bottom: 10px;
                border: 1px solid #e2e4e7;
            }
            .fc-wizard-mode-bar {
                height: 10px;
            }
            .fc-wizard-mode-body {
                padding: 10px 12px;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .fc-wizard-mode-line {
                height: 6px;
                border-radius: 3px;
            }
            /* Light mode preview */
            .fc-wizard-mode-light {
                background: #fff;
            }
            .fc-wizard-mode-light .fc-wizard-mode-bar {
                background: #f0f0f1;
            }
            .fc-wizard-mode-light .fc-wizard-mode-line {
                background: #e2e4e7;
            }
            /* Dark mode preview */
            .fc-wizard-mode-dark {
                background: #1d2327;
            }
            .fc-wizard-mode-dark .fc-wizard-mode-bar {
                background: #2c3338;
            }
            .fc-wizard-mode-dark .fc-wizard-mode-line {
                background: #3c4349;
            }
            .fc-wizard-mode-label {
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
            }
        </style>
        <?php
    }

    /* ──────────────────────────────────────────────
     * JavaScript
     * ────────────────────────────────────────────── */

    private static function render_js( $step ) {
        ?>
        <script>
        jQuery(function($){
            // Language dropdown selection — instant page reload on change
            $('.fc-wizard-lang-select').each(function(){
                var $wrap = $(this);
                var $btn = $wrap.find('.fc-wizard-lang-btn');
                var $dd = $wrap.find('.fc-wizard-lang-dd');
                var $hidden = $wrap.find('input[type="hidden"]');
                var isAdmin = ($wrap.attr('id') === 'fc-wizard-lang-admin');
                var isFrontend = ($wrap.attr('id') === 'fc-wizard-lang-frontend');

                $btn.on('click', function(e){
                    e.preventDefault(); e.stopPropagation();
                    $('.fc-wizard-lang-select.open').not($wrap).removeClass('open');
                    $wrap.toggleClass('open');
                });

                $dd.on('click', function(e){ e.stopPropagation(); });

                $dd.find('.fc-wizard-lang-dd-list li').on('click', function(){
                    var $li = $(this);
                    var code = $li.data('code');
                    if (code === $hidden.val()) { $wrap.removeClass('open'); return; }

                    // Build redirect URL with both lang values
                    var adminVal = $('#fc-wizard-lang-admin input[type="hidden"]').val();
                    var frontVal = $('#fc-wizard-lang-frontend input[type="hidden"]').val();
                    if (isAdmin) adminVal = code;
                    if (isFrontend) frontVal = code;

                    var url = '<?php echo esc_url( admin_url( 'admin.php?page=fc-setup-wizard' ) ); ?>';
                    url += '&fc_switch_admin_lang=' + encodeURIComponent(adminVal);
                    url += '&fc_switch_frontend_lang=' + encodeURIComponent(frontVal);
                    window.location.href = url;
                });
            });
            $(document).on('click', function(){ $('.fc-wizard-lang-select.open').removeClass('open'); });

            <?php if ( $step === 'store' ) : ?>
            // Custom country dropdown
            (function(){
                var $wrap = $('#fc-wizard-country-select');
                var $btn  = $wrap.find('.fc-wizard-country-btn');
                var $dd   = $wrap.find('.fc-wizard-country-dd');
                var $hidden = $wrap.find('#fc_store_country');

                $btn.on('click', function(e){
                    e.preventDefault(); e.stopPropagation();
                    $wrap.toggleClass('open');
                    if($wrap.hasClass('open')){
                        $dd.find('.fc-wizard-country-dd-search input').val('').trigger('input').focus();
                    }
                });

                $dd.on('click', function(e){ e.stopPropagation(); });

                $(document).on('click', function(){ $wrap.removeClass('open'); });

                $dd.find('.fc-wizard-country-dd-search input').on('input', function(){
                    var q = $(this).val().toLowerCase();
                    $dd.find('.fc-wizard-country-dd-list li').each(function(){
                        var name = ($(this).data('name')||'').toLowerCase();
                        $(this).toggleClass('hidden', name.indexOf(q) === -1);
                    });
                });

                $dd.find('.fc-wizard-country-dd-list li').on('click', function(){
                    var $li = $(this);
                    var code = $li.data('code');
                    var name = $li.data('name');
                    var flagUrl = 'https://flagcdn.com/w40/' + code.toLowerCase() + '.png';
                    $btn.find('.fc-wizard-flag').attr('src', flagUrl).attr('alt', code);
                    $btn.find('.fc-wizard-country-name').text(name);
                    $hidden.val(code);
                    $dd.find('li').removeClass('active');
                    $li.addClass('active');
                    $wrap.removeClass('open');
                    $hidden.trigger('change');
                });
            })();

            // Country change → update phone prefix + tax labels
            var prefixes = <?php echo wp_json_encode( self::get_country_prefix_map() ); ?>;
            var taxLabels = <?php echo wp_json_encode( self::get_country_tax_labels() ); ?>;

            $('#fc_store_country').on('change', function(){
                var code = $(this).val();
                if (prefixes[code]) {
                    // Update phone dropdown to match country
                    var $pw = $('#fc-wizard-phone-wrap');
                    var flagUrl = 'https://flagcdn.com/w40/' + code.toLowerCase() + '.png';
                    $pw.find('.fc-wizard-phone-btn .fc-wizard-flag').attr('src', flagUrl).attr('alt', code);
                    $pw.find('.fc-wizard-phone-prefix').text(prefixes[code]);
                    $pw.find('.fc-wizard-phone-btn').attr('data-current', code);
                    $pw.find('#fc_store_phone_prefix').val(prefixes[code]);
                    $pw.find('.fc-wizard-phone-dd-list li').removeClass('active');
                    $pw.find('.fc-wizard-phone-dd-list li[data-code="'+code+'"]').addClass('active');
                }
                if (taxLabels[code]) {
                    $('#fc_tax_no_label').text(taxLabels[code].tax_no);
                    $('#fc_crn_label').text(taxLabels[code].reg);
                }
            });

            // Phone prefix dropdown
            (function(){
                var $pw = $('#fc-wizard-phone-wrap');
                var $pbtn = $pw.find('.fc-wizard-phone-btn');
                var $pdd = $pw.find('.fc-wizard-phone-dd');
                var $phidden = $pw.find('#fc_store_phone_prefix');

                $pbtn.on('click', function(e){
                    e.preventDefault(); e.stopPropagation();
                    $pw.toggleClass('open');
                    if($pw.hasClass('open')){
                        $pdd.find('.fc-wizard-phone-dd-search input').val('').trigger('input').focus();
                    }
                });

                $pdd.on('click', function(e){ e.stopPropagation(); });

                $(document).on('click', function(){ $pw.removeClass('open'); });

                $pdd.find('.fc-wizard-phone-dd-search input').on('input', function(){
                    var q = $(this).val().toLowerCase();
                    $pdd.find('.fc-wizard-phone-dd-list li').each(function(){
                        var t = (($(this).data('name')||'') + ' ' + ($(this).data('prefix')||'')).toLowerCase();
                        $(this).toggleClass('hidden', t.indexOf(q) === -1);
                    });
                });

                $pdd.find('.fc-wizard-phone-dd-list li').on('click', function(){
                    var $li = $(this);
                    var code = $li.data('code');
                    var prefix = $li.data('prefix');
                    var flagUrl = 'https://flagcdn.com/w40/' + code.toLowerCase() + '.png';
                    $pbtn.find('.fc-wizard-flag').attr('src', flagUrl).attr('alt', code);
                    $pbtn.find('.fc-wizard-phone-prefix').text(prefix);
                    $pbtn.attr('data-current', code);
                    $phidden.val(prefix);
                    $pdd.find('li').removeClass('active');
                    $li.addClass('active');
                    $pw.removeClass('open');
                });
            })();
            <?php endif; ?>

            <?php if ( $step === 'commerce' ) : ?>
            // Currency change → update symbol & position
            $('#fc_currency').on('change', function(){
                var $opt = $(this).find('option:selected');
                var sym = $opt.data('symbol') || '';
                var pos = $opt.data('pos') || 'after';
                $('#fc_currency_symbol').val(sym);
                $('#fc_currency_pos').val(pos);
                // Update position labels
                $('#fc_currency_pos option[value="before"]').text('<?php echo esc_js( fc__( 'set_before_price_10_00', 'admin' ) ); ?>'.replace('%s', sym));
                $('#fc_currency_pos option[value="after"]').text('<?php echo esc_js( fc__( 'set_after_price_10_00', 'admin' ) ); ?>'.replace('%s', sym));
            });

            // Sell mode toggle
            $('input[name="fc_sell_to_mode"]').on('change', function(){
                var val = $(this).val();
                $('#fc-wizard-countries-include').toggle(val === 'include');
                $('#fc-wizard-countries-exclude').toggle(val === 'exclude');
            });

            // Country search filter
            $('.fc-wizard-country-search').on('input', function(){
                var q = $(this).val().toLowerCase();
                $(this).siblings('.fc-wizard-country-grid').find('.fc-wizard-country-item').each(function(){
                    var name = $(this).find('span').text().toLowerCase();
                    $(this).toggle(name.indexOf(q) !== -1);
                });
            });
            <?php endif; ?>

            <?php if ( $step === 'appearance' ) : ?>
            // Color swatch selection
            function setAccentColor(color) {
                $('#fc_accent_color').val(color);
                $('#fc-accent-picker').val(color);
                $('#fc-accent-hex').text(color);
                $('.fc-wizard-color-swatch').removeClass('active');
                $('.fc-wizard-color-swatch[data-color="' + color + '"]').addClass('active');
                // Update preview
                $('#fc-preview-btn').css('background', color);
                $('#fc-preview-link').css('color', color);
            }

            $('.fc-wizard-color-swatch').on('click', function(){
                setAccentColor($(this).data('color'));
            });

            $('#fc-accent-picker').on('input change', function(){
                setAccentColor($(this).val());
            });

            // Initialize preview
            (function(){
                var c = $('#fc_accent_color').val();
                $('#fc-preview-btn').css('background', c);
                $('#fc-preview-link').css('color', c);
            })();

            // Mode card selection
            $('.fc-wizard-mode-card').on('click', function(){
                $('.fc-wizard-mode-card').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);
            });
            <?php endif; ?>
        });
        </script>
        <?php
    }
}
