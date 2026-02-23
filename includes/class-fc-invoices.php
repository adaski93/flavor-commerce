<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * System faktur PDF — generowanie, numerowanie, gotowe szablony, pobieranie
 */
class FC_Invoices {

    /** Tymczasowe ustawienia na potrzeby podglądu AJAX */
    private static $preview_settings = null;

    public static function init() {
        add_action( 'admin_post_fc_generate_invoice', array( __CLASS__, 'handle_generate' ) );
        add_action( 'admin_post_fc_download_invoice', array( __CLASS__, 'handle_admin_download' ) );
        add_action( 'template_redirect', array( __CLASS__, 'handle_frontend_download' ) );
        add_action( 'fc_order_status_changed', array( __CLASS__, 'maybe_auto_generate' ), 10, 3 );
        add_filter( 'manage_fc_order_posts_columns', array( __CLASS__, 'add_invoice_column' ), 20 );
        add_action( 'manage_fc_order_posts_custom_column', array( __CLASS__, 'invoice_column_content' ), 20, 2 );
        add_action( 'wp_ajax_fc_invoice_preview', array( __CLASS__, 'ajax_preview' ) );
        add_action( 'admin_post_fc_invoice_preview_pdf', array( __CLASS__, 'handle_preview_pdf' ) );
    }

    /* ================================================================
       USTAWIENIA
       ================================================================ */

    public static function get_settings() {
        if ( self::$preview_settings !== null ) {
            return self::$preview_settings;
        }

        $defaults = array(
            'enabled'               => 0,
            'auto_generate'         => 0,
            'auto_status'           => 'completed',
            'email_attach_statuses' => array(),
            'number_prefix'         => 'FV',
            'number_pattern'        => '{prefix}/{number}/{month}/{year}',
            'number_padding'        => 3,
            'next_number'           => 1,
            'reset_monthly'         => 0,
            'last_reset_month'      => '',
            'show_thumbnails'       => 0,
            'template_style'        => 'classic',
            'logo_id'               => 0,
            'logo_width'            => 200,
            'logo_height'           => 0,
        );
        $saved  = get_option( 'fc_invoice_settings', array() );
        $merged = wp_parse_args( $saved, $defaults );

        // Migracja: usuń stare pole template
        if ( isset( $saved['template'] ) ) {
            unset( $saved['template'] );
            if ( ! isset( $saved['template_style'] ) ) {
                $saved['template_style'] = 'classic';
            }
            update_option( 'fc_invoice_settings', $saved );
            $merged = wp_parse_args( $saved, $defaults );
        }

        return $merged;
    }

    /**
     * Renderowanie zakładki Faktury
     */
    public static function render_tab() {
        if ( isset( $_POST['fc_invoices_nonce'] ) && wp_verify_nonce( $_POST['fc_invoices_nonce'], 'fc_invoices_nonce' ) && current_user_can( 'manage_options' ) ) {
            self::save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . fc__( 'inv_invoice_settings_have_been_saved' ) . '</p></div>';
        }

        $s = self::get_settings();
        $statuses = FC_Orders::get_statuses();

        $recent_orders = get_posts( array(
            'post_type'      => 'fc_order',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        $selected_order_id = ! empty( $recent_orders ) ? $recent_orders[0] : 0;
        ?>
        <form method="post">
            <?php wp_nonce_field( 'fc_invoices_nonce', 'fc_invoices_nonce' ); ?>

            <h2 style="margin-top:0;"><?php fc_e( 'inv_invoice_settings' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'inv_enable_invoice_system' ); ?></th>
                    <td><label><input type="checkbox" name="fc_inv_enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php fc_e( 'coupon_active' ); ?></label></td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_automatic_generation' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="fc_inv_auto_generate" value="1" <?php checked( $s['auto_generate'] ); ?>> <?php fc_e( 'inv_generate_invoice_automatically_when_status_changes' ); ?></label>
                        <select name="fc_inv_auto_status" style="margin-left:8px;">
                            <?php foreach ( $statuses as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['auto_status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_email_attachment' ); ?></th>
                    <td>
                        <p class="description" style="margin-top:0;margin-bottom:8px;"><?php fc_e( 'inv_attach_invoice_pdf_to_emails_when_status_changes_t' ); ?></p>
                        <?php
                        $attach_statuses = isset( $s['email_attach_statuses'] ) && is_array( $s['email_attach_statuses'] ) ? $s['email_attach_statuses'] : array();
                        foreach ( $statuses as $key => $label ) : ?>
                            <label style="display:inline-block;margin-right:16px;margin-bottom:4px;">
                                <input type="checkbox" name="fc_inv_email_attach_statuses[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $attach_statuses ) ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php fc_e( 'inv_invoice_must_be_generated_manually_or_automaticall' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_product_thumbnails' ); ?></th>
                    <td><label><input type="checkbox" name="fc_inv_show_thumbnails" value="1" <?php checked( $s['show_thumbnails'] ); ?>> <?php fc_e( 'inv_show_product_thumbnails_in_the_invoice_table' ); ?></label></td>
                </tr>
            </table>

            <h2><?php fc_e( 'inv_numbering' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'inv_prefix' ); ?></th>
                    <td><input type="text" name="fc_inv_prefix" value="<?php echo esc_attr( $s['number_prefix'] ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_number_pattern' ); ?></th>
                    <td>
                        <input type="text" name="fc_inv_pattern" value="<?php echo esc_attr( $s['number_pattern'] ); ?>" class="regular-text">
                        <p class="description"><?php fc_e( 'inv_available_prefix_number_day_month_year' ); ?></p>
                        <p class="description">
                            <?php printf( fc__( 'inv_preview' ), esc_html( self::format_number( $s['next_number'], null, $s ) ) ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_number_of_digits' ); ?></th>
                    <td>
                        <input type="number" name="fc_inv_padding" value="<?php echo intval( $s['number_padding'] ); ?>" min="1" max="10" class="small-text">
                        <p class="description"><?php fc_e( 'inv_e_g_3_001_002_003' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_reset_monthly' ); ?></th>
                    <td><label><input type="checkbox" name="fc_inv_reset_monthly" value="1" <?php checked( $s['reset_monthly'] ); ?>> <?php fc_e( 'inv_reset_numbering_to_1_at_the_beginning_of_each_mont' ); ?></label></td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_next_number' ); ?></th>
                    <td><input type="number" name="fc_inv_next_number" value="<?php echo intval( $s['next_number'] ); ?>" min="1" class="small-text"></td>
                </tr>
            </table>

            <h2><?php fc_e( 'inv_invoice_appearance' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php fc_e( 'inv_logo_on_invoice' ); ?></th>
                    <td>
                        <?php $logo_id = absint( $s['logo_id'] ); ?>
                        <div class="fc-inv-logo-preview" id="fc_inv_logo_preview" style="margin-bottom:8px;">
                            <?php if ( $logo_id ) :
                                $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
                            ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width:200px;max-height:80px;display:block;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="fc_inv_logo_id" id="fc_inv_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
                        <button type="button" class="button" id="fc_inv_choose_logo"><?php fc_e( 'inv_select_logo' ); ?></button>
                        <button type="button" class="button" id="fc_inv_remove_logo" style="<?php echo $logo_id ? '' : 'display:none;'; ?>"><?php fc_e( 'attr_delete' ); ?></button>
                        <p class="description"><?php fc_e( 'inv_png_files_only_recommended_size_max_600_240px' ); ?></p>
                        <div style="margin-top:10px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <label>
                                <?php fc_e( 'inv_width' ); ?>
                                <input type="number" name="fc_inv_logo_width" value="<?php echo intval( $s['logo_width'] ); ?>" min="20" max="600" step="1" class="small-text" style="width:70px;"> px
                            </label>
                            <label>
                                <?php fc_e( 'inv_height' ); ?>
                                <input type="number" name="fc_inv_logo_height" value="<?php echo intval( $s['logo_height'] ); ?>" min="0" max="300" step="1" class="small-text" style="width:70px;"> px
                            </label>
                            <span class="description"><?php fc_e( 'inv_height_0_automatic_proportional' ); ?></span>
                        </div>
                        <script>
                        jQuery(function($){
                            $('#fc_inv_choose_logo').on('click', function(e){
                                e.preventDefault();
                                var frame = wp.media({title:'<?php fc_e( 'inv_select_logo_png' ); ?>',library:{type:'image/png'},multiple:false});
                                frame.on('select', function(){
                                    var a = frame.state().get('selection').first().toJSON();
                                    if ( a.mime !== 'image/png' && a.subtype !== 'png' ) {
                                        alert('<?php fc_e( 'inv_only_png_files_are_allowed' ); ?>');
                                        return;
                                    }
                                    $('#fc_inv_logo_id').val(a.id).trigger('change');
                                    var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                                    $('#fc_inv_logo_preview').html('<img src="'+url+'" style="max-width:200px;max-height:80px;display:block;">');
                                    $('#fc_inv_remove_logo').show();
                                });
                                frame.open();
                            });
                            $('#fc_inv_remove_logo').on('click', function(){
                                $('#fc_inv_logo_id').val('0').trigger('change');
                                $('#fc_inv_logo_preview').html('');
                                $(this).hide();
                            });
                        });
                        </script>
                    </td>
                </tr>
                <tr>
                    <th><?php fc_e( 'inv_template_style' ); ?></th>
                    <td>
                        <div class="fc-inv-style-picker" style="display:flex;gap:20px;flex-wrap:wrap;">
                            <?php
                            $styles = array(
                                'classic' => array(
                                    'label' => fc__( 'inv_classic' ),
                                    'desc'  => fc__( 'inv_traditional_layout_with_blue_accent' ),
                                    'color' => '#2271b1',
                                ),
                                'minimal' => array(
                                    'label' => fc__( 'inv_minimalist' ),
                                    'desc'  => fc__( 'inv_simple_black_and_white_style' ),
                                    'color' => '#444',
                                ),
                                'theme' => array(
                                    'label' => fc__( 'inv_theme' ),
                                    'desc'  => fc__( 'inv_accent_color_from_theme_settings' ),
                                    'color' => get_theme_mod( 'flavor_color_accent', '#4a90d9' ),
                                ),
                            );
                            foreach ( $styles as $key => $style ) :
                                $checked = ( $s['template_style'] === $key );
                            ?>
                            <label class="fc-inv-style-card" style="cursor:pointer;border:2px solid <?php echo $checked ? '#0073aa' : '#ddd'; ?>;border-radius:8px;padding:0;width:200px;overflow:hidden;transition:border-color 0.2s;background:#fff;">
                                <input type="radio" name="fc_inv_template_style" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?> style="display:none;">
                                <div style="height:8px;background:<?php echo esc_attr( $style['color'] ); ?>;"></div>
                                <div style="padding:12px 16px;">
                                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                                        <span class="fc-inv-check" style="display:<?php echo $checked ? 'inline' : 'none'; ?>;color:#0073aa;">
                                            <span class="dashicons dashicons-yes-alt" style="font-size:18px;"></span>
                                        </span>
                                        <strong style="font-size:13px;"><?php echo esc_html( $style['label'] ); ?></strong>
                                    </div>
                                    <div style="font-size:12px;color:#666;"><?php echo esc_html( $style['desc'] ); ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Podgląd -->
            <h3>
                <span class="dashicons dashicons-visibility" style="vertical-align:middle;margin-right:4px;"></span>
                <?php fc_e( 'inv_invoice_preview' ); ?>
                <select id="fc-invoice-preview-order" style="margin-left:12px;font-weight:normal;font-size:13px;">
                    <?php if ( empty( $recent_orders ) ) : ?>
                        <option value="0"><?php fc_e( 'inv_sample_data' ); ?></option>
                    <?php else : foreach ( $recent_orders as $oid ) :
                        $num = get_post_meta( $oid, '_fc_order_number', true ) ?: ( '#' . $oid );
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
                        <option value="<?php echo $oid; ?>" <?php selected( $selected_order_id, $oid ); ?>><?php echo esc_html( $num . ' — ' . $o_name . ' — ' . $o_total ); ?></option>
                    <?php endforeach; endif; ?>
                </select>
                <span id="fc-invoice-preview-spinner" class="spinner" style="float:none;margin-top:0;"></span>
            </h3>

            <div style="border:1px solid #c3c4c7;border-radius:4px;overflow:hidden;background:#f0f0f1;max-width:800px;">
                <?php
                $preview_url = self::generate_preview_pdf( $selected_order_id, $s['template_style'] );
                ?>
                <iframe id="fc-invoice-preview-frame" src="<?php echo esc_url( $preview_url ); ?>" style="width:100%;height:600px;border:0;background:#fff;"></iframe>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary"><?php fc_e( 'inv_save_invoice_settings' ); ?></button>
            </p>

        </form>

        <script>
        jQuery(function($){
            var previewNonce = '<?php echo wp_create_nonce( 'fc_invoice_preview' ); ?>';
            var previewBusy = false;

            function getSelectedStyle() {
                return $('input[name="fc_inv_template_style"]:checked').val() || 'classic';
            }

            var refreshTimer = null;
            function refreshPreviewPdf() {
                if ( refreshTimer ) clearTimeout( refreshTimer );
                refreshTimer = setTimeout( doRefresh, 400 );
            }
            function doRefresh() {
                if ( previewBusy ) return;
                previewBusy = true;
                $('#fc-invoice-preview-spinner').addClass('is-active');
                var orderId = $('#fc-invoice-preview-order').val() || 0;
                var style = getSelectedStyle();
                $.post( ajaxurl, {
                    action: 'fc_invoice_preview',
                    order_id: orderId,
                    style: style,
                    show_thumbnails: $('input[name="fc_inv_show_thumbnails"]').is(':checked') ? 1 : 0,
                    logo_id: $('#fc_inv_logo_id').val() || 0,
                    logo_width: $('input[name="fc_inv_logo_width"]').val() || 200,
                    logo_height: $('input[name="fc_inv_logo_height"]').val() || 0,
                    _wpnonce: previewNonce
                }, function( res ) {
                    previewBusy = false;
                    $('#fc-invoice-preview-spinner').removeClass('is-active');
                    if ( res.success && res.data.url ) {
                        $('#fc-invoice-preview-frame').attr('src', res.data.url);
                    }
                }).fail(function(){ previewBusy = false; $('#fc-invoice-preview-spinner').removeClass('is-active'); });
            }

            $('input[name="fc_inv_template_style"]').on('change', function(){
                $('.fc-inv-style-card').css('border-color', '#ddd').find('.fc-inv-check').hide();
                $(this).closest('.fc-inv-style-card').css('border-color', '#0073aa').find('.fc-inv-check').show();
                refreshPreviewPdf();
            });

            $('#fc-invoice-preview-order').on('change', function(){
                refreshPreviewPdf();
            });

            $('input[name="fc_inv_show_thumbnails"]').on('change', refreshPreviewPdf);
            $('input[name="fc_inv_logo_width"], input[name="fc_inv_logo_height"]').on('input change', refreshPreviewPdf);
            $('#fc_inv_logo_id').on('change', refreshPreviewPdf);

            // Obserwuj zmianę logo (hidden input)
            var logoObserver = new MutationObserver(function(){ refreshPreviewPdf(); });
            var logoInput = document.getElementById('fc_inv_logo_id');
            if (logoInput) logoObserver.observe(logoInput, { attributes: true, attributeFilter: ['value'] });
        });
        </script>
        <?php
    }

    private static function save_settings() {
        $s = self::get_settings();

        $s['enabled']          = isset( $_POST['fc_inv_enabled'] ) ? 1 : 0;
        $s['auto_generate']    = isset( $_POST['fc_inv_auto_generate'] ) ? 1 : 0;
        $s['auto_status']      = sanitize_text_field( $_POST['fc_inv_auto_status'] ?? 'completed' );
        $s['number_prefix']    = sanitize_text_field( $_POST['fc_inv_prefix'] ?? 'FV' );
        $s['number_pattern']   = sanitize_text_field( $_POST['fc_inv_pattern'] ?? '{prefix}/{number}/{month}/{year}' );
        $s['number_padding']   = max( 1, intval( $_POST['fc_inv_padding'] ?? 3 ) );
        $s['next_number']      = max( 1, intval( $_POST['fc_inv_next_number'] ?? 1 ) );
        $s['reset_monthly']    = isset( $_POST['fc_inv_reset_monthly'] ) ? 1 : 0;
        $s['show_thumbnails']  = isset( $_POST['fc_inv_show_thumbnails'] ) ? 1 : 0;
        $s['email_attach_statuses'] = isset( $_POST['fc_inv_email_attach_statuses'] ) && is_array( $_POST['fc_inv_email_attach_statuses'] )
            ? array_map( 'sanitize_text_field', $_POST['fc_inv_email_attach_statuses'] )
            : array();
        $s['template_style']   = in_array( $_POST['fc_inv_template_style'] ?? '', array( 'classic', 'minimal', 'theme' ) )
            ? $_POST['fc_inv_template_style']
            : 'classic';

        // Logo — walidacja że to PNG
        $logo_id = absint( $_POST['fc_inv_logo_id'] ?? 0 );
        if ( $logo_id ) {
            $mime = get_post_mime_type( $logo_id );
            if ( $mime !== 'image/png' ) {
                $logo_id = 0;
            }
        }
        $s['logo_id'] = $logo_id;
        $s['logo_width']  = max( 20, min( 600, intval( $_POST['fc_inv_logo_width'] ?? 200 ) ) );
        $s['logo_height'] = max( 0, min( 300, intval( $_POST['fc_inv_logo_height'] ?? 0 ) ) );

        unset( $s['template'] );

        update_option( 'fc_invoice_settings', $s );

        // Prze generuj podgląd PDF po zapisie
        $recent = get_posts( array( 'post_type' => 'fc_order', 'posts_per_page' => 1, 'post_status' => 'publish', 'fields' => 'ids' ) );
        $preview_order = ! empty( $recent ) ? $recent[0] : 0;
        self::generate_preview_pdf( $preview_order, $s['template_style'] );
    }

    /* ================================================================
       NUMERACJA
       ================================================================ */

    public static function format_number( $number, $date = null, $settings = null ) {
        if ( ! $settings ) $settings = self::get_settings();
        if ( ! $date ) $date = time();
        if ( is_string( $date ) ) $date = strtotime( $date );

        return str_replace(
            array( '{prefix}', '{number}', '{day}', '{month}', '{year}' ),
            array(
                $settings['number_prefix'],
                str_pad( $number, $settings['number_padding'], '0', STR_PAD_LEFT ),
                wp_date( 'd', $date ),
                wp_date( 'm', $date ),
                wp_date( 'Y', $date ),
            ),
            $settings['number_pattern']
        );
    }

    private static function consume_next_number() {
        global $wpdb;
        // Atomic lock to prevent race condition in concurrent orders
        $wpdb->query( "SELECT GET_LOCK('fc_invoice_number', 5)" );

        $s = self::get_settings();

        $current_month = wp_date( 'Y-m' );
        if ( $s['reset_monthly'] && $s['last_reset_month'] !== $current_month ) {
            $s['next_number'] = 1;
            $s['last_reset_month'] = $current_month;
        }

        $number = $s['next_number'];
        $s['next_number'] = $number + 1;

        update_option( 'fc_invoice_settings', $s );

        $wpdb->query( "SELECT RELEASE_LOCK('fc_invoice_number')" );

        return $number;
    }

    /* ================================================================
       GENEROWANIE FAKTURY
       ================================================================ */

    public static function has_invoice( $order_id ) {
        return (bool) get_post_meta( $order_id, '_fc_invoice_number', true );
    }

    public static function generate( $order_id ) {
        $s = self::get_settings();
        if ( ! $s['enabled'] ) return false;
        if ( self::has_invoice( $order_id ) ) return true;

        $number = self::consume_next_number();
        $date   = current_time( 'mysql' );
        $formatted = self::format_number( $number, $date );

        update_post_meta( $order_id, '_fc_invoice_number', $formatted );
        update_post_meta( $order_id, '_fc_invoice_date', $date );

        return $formatted;
    }

    public static function maybe_auto_generate( $post_id, $old_status, $new_status ) {
        $s = self::get_settings();
        if ( ! $s['enabled'] || ! $s['auto_generate'] ) return;
        if ( $new_status !== $s['auto_status'] ) return;
        self::generate( $post_id );
    }

    public static function should_attach_to_email( $status ) {
        $s = self::get_settings();
        if ( ! $s['enabled'] ) return false;
        $attach = isset( $s['email_attach_statuses'] ) && is_array( $s['email_attach_statuses'] ) ? $s['email_attach_statuses'] : array();
        return in_array( $status, $attach, true );
    }

    public static function get_temp_pdf_path( $order_id ) {
        if ( ! self::has_invoice( $order_id ) ) return false;

        $pdf = self::get_pdf( $order_id );
        if ( empty( $pdf ) ) return false;

        $invoice_number = get_post_meta( $order_id, '_fc_invoice_number', true ) ?: 'faktura';
        $filename = sanitize_file_name( $invoice_number ) . '.pdf';

        $upload_dir = wp_upload_dir();
        $tmp_dir = trailingslashit( $upload_dir['basedir'] ) . 'fc-invoices-tmp';
        if ( ! file_exists( $tmp_dir ) ) {
            wp_mkdir_p( $tmp_dir );
            file_put_contents( $tmp_dir . '/.htaccess', 'deny from all' );
            file_put_contents( $tmp_dir . '/index.php', '<?php // Silence is golden.' );
        }

        $path = trailingslashit( $tmp_dir ) . $filename;
        file_put_contents( $path, $pdf );

        return $path;
    }

    public static function cleanup_temp_pdf( $path ) {
        if ( $path && file_exists( $path ) ) {
            wp_delete_file( $path );
        }
    }

    /* ================================================================
       GRAYSCALE HELPER (GD)
       ================================================================ */

    private static function maybe_grayscale_url( $url ) {
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        $base_path  = $upload_dir['basedir'];

        // Konwertuj URL na ścieżkę lokalną
        if ( strpos( $url, $base_url ) === 0 ) {
            $file = $base_path . str_replace( $base_url, '', $url );
        } else {
            $file = ABSPATH . ltrim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
        }

        if ( ! file_exists( $file ) || ! function_exists( 'imagecreatefrompng' ) ) {
            return $url;
        }

        $mime = wp_check_filetype( $file )['type'] ?? '';
        switch ( $mime ) {
            case 'image/png':
                $img = @imagecreatefrompng( $file );
                break;
            case 'image/jpeg':
                $img = @imagecreatefromjpeg( $file );
                break;
            case 'image/webp':
                $img = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false;
                break;
            default:
                return $url;
        }

        if ( ! $img ) return $url;

        imagefilter( $img, IMG_FILTER_GRAYSCALE );

        ob_start();
        imagepng( $img );
        $data = ob_get_clean();
        imagedestroy( $img );

        return 'data:image/png;base64,' . base64_encode( $data );
    }

    /* ================================================================
       LOGO FAKTURY
       ================================================================ */

    public static function get_invoice_logo_html() {
        $s = self::get_settings();
        $logo_id = absint( $s['logo_id'] );
        if ( ! $logo_id ) return '';

        $mime = get_post_mime_type( $logo_id );
        if ( $mime !== 'image/png' ) return '';

        $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );
        if ( ! $logo_url ) return '';

        $w = intval( $s['logo_width'] ) ?: 200;
        $h = intval( $s['logo_height'] );

        $style = 'width:' . $w . 'px;';
        if ( $h > 0 ) {
            $style .= 'height:' . $h . 'px;';
        } else {
            $style .= 'height:auto;';
        }
        $style .= 'display:block;margin:0 auto 12px;';

        if ( $s['template_style'] === 'minimal' ) {
            $logo_url = self::maybe_grayscale_url( $logo_url );
        }

        $safe_url = ( strpos( $logo_url, 'data:' ) === 0 ) ? $logo_url : esc_url( $logo_url );
        return '<img src="' . $safe_url . '" alt="' . esc_attr( get_option( 'fc_store_name', get_bloginfo( 'name' ) ) ) . '" style="' . $style . '">';
    }

    /* ================================================================
       DANE FAKTURY
       ================================================================ */

    public static function get_invoice_data( $order_id = 0 ) {
        $store_name    = get_option( 'fc_store_name', get_bloginfo( 'name' ) );
        $store_email   = get_option( 'fc_store_email_contact', get_option( 'admin_email' ) );
        $store_phone   = trim( get_option( 'fc_store_phone_prefix', '' ) . ' ' . get_option( 'fc_store_phone', '' ) );
        $store_tax_no  = get_option( 'fc_store_tax_no', '' );
        $store_crn     = get_option( 'fc_store_crn', '' );
        $store_bank    = get_option( 'fc_bank_account', '' );
        $store_swift   = get_option( 'fc_bank_swift', '' );
        $store_country = get_option( 'fc_store_country', 'PL' );
        $store_labels  = FC_Shortcodes::get_country_tax_labels( $store_country );
        $store_addr    = implode( '<br>', array_filter( array(
            get_option( 'fc_store_street', '' ),
            trim( get_option( 'fc_store_postcode', '' ) . ' ' . get_option( 'fc_store_city', '' ) . ', ' . $store_country ),
        ) ) );

        $logo_html = self::get_invoice_logo_html();

        if ( ! $order_id || get_post_type( $order_id ) !== 'fc_order' ) {
            $s = self::get_settings();
            $preview_tax_no = $store_tax_no ?: '1234567890';
            $preview_crn    = $store_crn;
            return array(
                '{invoice_number}'      => self::format_number( $s['next_number'] ),
                '{invoice_date}'        => date_i18n( 'j F Y' ),
                '{order_number}'        => 'FC-ABC123',
                '{order_date}'          => date_i18n( 'j F Y, H:i' ),
                '{logo}'                => $logo_html,
                '{seller_name}'         => $store_name ?: fc__( 'inv_sample_company' ),
                '{seller_address}'      => $store_addr ?: fc__( 'inv_sample_address' ),
                '{seller_tax_no}'       => ! empty( $preview_tax_no ) ? $preview_tax_no . '<br>' : '',
                '{seller_crn}'          => ! empty( $preview_crn ) ? $preview_crn . '<br>' : '',
                '{seller_tax_no_label}' => ! empty( $preview_tax_no ) ? $store_labels['tax_no'] . ':' : '',
                '{seller_crn_label}'    => ! empty( $preview_crn ) ? $store_labels['crn'] . ':' : '',
                '{seller_email}'        => $store_email ?: 'kontakt@example.com',
                '{seller_phone}'        => $store_phone ?: '+48 123 456 789',
                '{seller_bank_account}' => '<br>' . fc__( 'inv_account_label' ) . ' ' . ( $store_bank ?: 'PL 00 1234 5678 9012 3456 7890 1234' ) . ( ( $store_swift ?: 'BREXPLPWXXX' ) ? '<br>SWIFT: ' . ( $store_swift ?: 'BREXPLPWXXX' ) : '' ),
                '{buyer_name}'          => fc__( 'inv_sample_buyer' ),
                '{buyer_address}'       => 'ul. Przykładowa 1<br>00-001 Warszawa, PL',
                '{buyer_tax_no}'        => '',
                '{buyer_crn}'           => '',
                '{buyer_tax_no_label}'  => '',
                '{buyer_crn_label}'     => '',
                '{buyer_email}'         => 'jan@example.com',
                '{buyer_phone}'         => '+48 123 456 789',
                '{products_table}'      => self::build_products_table( null, 15.00, fc__( 'set_sample_shipping_method' ) ),
                '{subtotal}'            => '198,00 zł',
                '{shipping_cost}'       => '15,00 zł',
                '{total}'               => '213,00 zł',
                '{tax_name}'            => get_option( 'fc_tax_name', 'VAT' ),
                '{tax_rate}'            => get_option( 'fc_tax_rate', '23' ),
                '{tax_amount}'          => fc_format_price( round( 213.00 * floatval( get_option( 'fc_tax_rate', '23' ) ) / ( 100 + floatval( get_option( 'fc_tax_rate', '23' ) ) ), 2 ) ),
                '{net_total}'           => fc_format_price( round( 213.00 - 213.00 * floatval( get_option( 'fc_tax_rate', '23' ) ) / ( 100 + floatval( get_option( 'fc_tax_rate', '23' ) ) ), 2 ) ),
                '{payment_method}'      => fc__( 'set_bank_transfer' ),
                '{shipping_method}'     => fc__( 'set_sample_shipping_method' ),
                '{notes}'               => '',
            );
        }

        $customer      = get_post_meta( $order_id, '_fc_customer', true );
        $order_number  = get_post_meta( $order_id, '_fc_order_number', true );
        $order_date    = get_post_meta( $order_id, '_fc_order_date', true );
        $order_total   = floatval( get_post_meta( $order_id, '_fc_order_total', true ) );
        $payment       = get_post_meta( $order_id, '_fc_payment_method', true );
        $shipping_name = get_post_meta( $order_id, '_fc_shipping_method', true );
        $shipping_cost = floatval( get_post_meta( $order_id, '_fc_shipping_cost', true ) );
        $items         = get_post_meta( $order_id, '_fc_order_items', true );
        $notes         = get_post_meta( $order_id, '_fc_order_notes', true );

        $invoice_number = get_post_meta( $order_id, '_fc_invoice_number', true );
        $invoice_date   = get_post_meta( $order_id, '_fc_invoice_date', true );

        if ( ! $invoice_number ) {
            $s = self::get_settings();
            $invoice_number = self::format_number( $s['next_number'] );
        }

        $payment_label  = FC_Orders::get_order_payment_label( $order_id );

        $buyer_name   = '';
        $buyer_tax_no = '';
        $buyer_crn    = '';
        if ( is_array( $customer ) ) {
            if ( ( $customer['account_type'] ?? '' ) === 'company' && ! empty( $customer['company'] ) ) {
                $buyer_name   = $customer['company'];
                $buyer_tax_no = $customer['tax_no'] ?? '';
                $buyer_crn    = $customer['crn'] ?? '';
            } else {
                $buyer_name = trim( ( $customer['first_name'] ?? '' ) . ' ' . ( $customer['last_name'] ?? '' ) );
            }
        }

        $buyer_address = '';
        if ( is_array( $customer ) ) {
            $buyer_country_code = $customer['country'] ?? '';
            $buyer_address = implode( '<br>', array_filter( array(
                ( $customer['address'] ?? '' ),
                trim( ( $customer['postcode'] ?? '' ) . ' ' . ( $customer['city'] ?? '' ) . ( $buyer_country_code ? ', ' . $buyer_country_code : '' ) ),
            ) ) );
        }

        $buyer_email   = is_array( $customer ) ? ( $customer['email'] ?? '' ) : '';
        $buyer_phone   = is_array( $customer ) ? trim( ( $customer['phone_prefix'] ?? '' ) . ' ' . ( $customer['phone'] ?? '' ) ) : '';
        $buyer_country = is_array( $customer ) ? ( $customer['country'] ?? $store_country ) : $store_country;
        $buyer_labels  = FC_Shortcodes::get_country_tax_labels( $buyer_country );

        $items_subtotal = 0;
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $items_subtotal += floatval( $item['line_total'] ?? 0 );
            }
        }

        return array(
            '{invoice_number}'      => $invoice_number ?: '',
            '{invoice_date}'        => $invoice_date ? date_i18n( 'j F Y', strtotime( $invoice_date ) ) : date_i18n( 'j F Y' ),
            '{order_number}'        => $order_number ?: '',
            '{order_date}'          => $order_date ? date_i18n( 'j F Y, H:i', strtotime( $order_date ) ) : '',
            '{logo}'                => $logo_html,
            '{seller_name}'         => $store_name,
            '{seller_address}'      => $store_addr,
            '{seller_tax_no}'       => ! empty( $store_tax_no ) ? $store_tax_no . '<br>' : '',
            '{seller_crn}'          => ! empty( $store_crn ) ? $store_crn . '<br>' : '',
            '{seller_tax_no_label}' => ! empty( $store_tax_no ) ? $store_labels['tax_no'] . ':' : '',
            '{seller_crn_label}'    => ! empty( $store_crn ) ? $store_labels['crn'] . ':' : '',
            '{seller_email}'        => $store_email,
            '{seller_phone}'        => $store_phone,
            '{seller_bank_account}' => ! empty( $store_bank ) ? '<br>' . fc__( 'inv_account_label' ) . ' ' . $store_bank . ( ! empty( $store_swift ) ? '<br>SWIFT: ' . $store_swift : '' ) : '',
            '{buyer_name}'          => $buyer_name,
            '{buyer_address}'       => $buyer_address,
            '{buyer_tax_no}'        => ! empty( $buyer_tax_no ) ? $buyer_tax_no . '<br>' : '',
            '{buyer_crn}'           => ! empty( $buyer_crn ) ? $buyer_crn . '<br>' : '',
            '{buyer_tax_no_label}'  => ! empty( $buyer_tax_no ) ? $buyer_labels['tax_no'] . ':' : '',
            '{buyer_crn_label}'     => ! empty( $buyer_crn ) ? $buyer_labels['crn'] . ':' : '',
            '{buyer_email}'         => $buyer_email,
            '{buyer_phone}'         => $buyer_phone,
            '{products_table}'      => self::build_products_table( $items, $shipping_cost, $shipping_name ),
            '{subtotal}'            => fc_format_price( $items_subtotal ),
            '{shipping_cost}'       => fc_format_price( $shipping_cost ),
            '{total}'               => fc_format_price( $order_total ),
            '{tax_name}'            => get_option( 'fc_tax_name', 'VAT' ),
            '{tax_rate}'            => get_option( 'fc_tax_rate', '23' ),
            '{tax_amount}'          => fc_format_price( round( $order_total * floatval( get_option( 'fc_tax_rate', '23' ) ) / ( 100 + floatval( get_option( 'fc_tax_rate', '23' ) ) ), 2 ) ),
            '{net_total}'           => fc_format_price( round( $order_total - $order_total * floatval( get_option( 'fc_tax_rate', '23' ) ) / ( 100 + floatval( get_option( 'fc_tax_rate', '23' ) ) ), 2 ) ),
            '{payment_method}'      => $payment_label ?: '',
            '{shipping_method}'     => $shipping_name ?: '',
            '{notes}'               => $notes ?: '',
        );
    }

    /* ================================================================
       TABELA PRODUKTÓW
       ================================================================ */

    private static function build_products_table( $items, $shipping_cost = 0, $shipping_method = '' ) {
        $show_units  = class_exists( 'FC_Units_Admin' ) && FC_Units_Admin::is_visible( 'invoice' );
        $s           = self::get_settings();
        $show_thumbs = ! empty( $s['show_thumbnails'] );
        $c           = self::get_style_colors( $s['template_style'] );
        $tax_rate    = floatval( get_option( 'fc_tax_rate', '23' ) );
        $tax_name    = get_option( 'fc_tax_name', 'VAT' );
        $tax_div     = $tax_rate > 0 ? ( 1 + $tax_rate / 100 ) : 1;

        $html  = '<table class="items" style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
        $html .= '<tr style="background:' . $c['header_bg'] . ';color:' . $c['header_text'] . ';">';
        $html .= '<th style="padding:10px 8px;text-align:center;font-size:10px;text-transform:uppercase;width:35px;">' . fc__( 'inv_no_column' ) . '</th>';
        if ( $show_thumbs ) {
            $html .= '<th style="padding:10px 8px;text-align:center;font-size:10px;text-transform:uppercase;width:50px;"></th>';
        }
        $html .= '<th style="padding:10px 12px;text-align:left;font-size:10px;text-transform:uppercase;">' . fc__( 'inv_name_column' ) . '</th>';
        $html .= '<th style="padding:10px 8px;text-align:center;font-size:10px;text-transform:uppercase;width:60px;">' . fc__( 'inv_quantity_column' ) . '</th>';
        $html .= '<th style="padding:10px 12px;text-align:right;font-size:10px;text-transform:uppercase;width:90px;">' . fc__( 'inv_net_price' ) . '</th>';
        $html .= '<th style="padding:10px 8px;text-align:center;font-size:10px;text-transform:uppercase;width:45px;">' . esc_html( $tax_name ) . '</th>';
        $html .= '<th style="padding:10px 12px;text-align:right;font-size:10px;text-transform:uppercase;width:90px;">' . fc__( 'inv_net_total' ) . '</th>';
        $html .= '</tr>';

        if ( ! is_array( $items ) || empty( $items ) ) {
            $net_price = round( 99.00 / $tax_div, 2 );
            $net_total = round( 198.00 / $tax_div, 2 );
            $html .= '<tr><td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">1</td>';
            if ( $show_thumbs ) {
                $html .= '<td style="padding:4px 8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';"><img src="https://placehold.co/40x40/f0f0f1/999?text=IMG" style="width:40px;height:40px;object-fit:cover;border-radius:3px;"></td>';
            }
            $html .= '<td style="padding:8px 12px;border-bottom:1px solid ' . $c['border'] . ';">' . fc__( 'inv_sample_product' ) . '</td>';
            $qty_text = $show_units ? '2 ' . fc__( 'inv_pcs' ) : '2';
            $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . $qty_text . '</td>';
            $html .= '<td style="padding:8px 12px;text-align:right;border-bottom:1px solid ' . $c['border'] . ';">' . fc_format_price( $net_price ) . '</td>';
            $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . number_format( $tax_rate, 0 ) . '%</td>';
            $html .= '<td style="padding:8px 12px;text-align:right;border-bottom:1px solid ' . $c['border'] . ';">' . fc_format_price( $net_total ) . '</td></tr>';
            $lp = 2;
        } else {
            $lp = 1;
            foreach ( $items as $item ) {
                $bg = ( $lp % 2 === 0 ) ? ' background:' . $c['row_alt'] . ';' : '';
                $unit_label = '';
                if ( $show_units && ! empty( $item['product_id'] ) ) {
                    $unit_label = get_post_meta( $item['product_id'], '_fc_unit', true ) ?: FC_Units_Admin::get_default();
                }

                $html .= '<tr style="' . $bg . '">';
                $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . $lp . '</td>';
                if ( $show_thumbs ) {
                    $thumb_url = '';
                    $pid = ! empty( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
                    if ( $pid && has_post_thumbnail( $pid ) ) {
                        $thumb_url = get_the_post_thumbnail_url( $pid, 'thumbnail' );
                    }
                    if ( $thumb_url && $s['template_style'] === 'minimal' ) {
                        $thumb_url = self::maybe_grayscale_url( $thumb_url );
                    }
                    $safe_thumb = $thumb_url ? ( ( strpos( $thumb_url, 'data:' ) === 0 ) ? $thumb_url : esc_url( $thumb_url ) ) : '';
                    $thumb_html = $safe_thumb
                        ? '<img src="' . $safe_thumb . '" style="width:40px;height:40px;object-fit:cover;border-radius:3px;">'
                        : '<div style="width:40px;height:40px;background:#f0f0f1;border-radius:3px;"></div>';
                    $html .= '<td style="padding:4px 8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . $thumb_html . '</td>';
                }
                $html .= '<td style="padding:8px 12px;border-bottom:1px solid ' . $c['border'] . ';">' . esc_html( $item['product_name'] );
                if ( ! empty( $item['attribute_values'] ) && is_array( $item['attribute_values'] ) ) {
                    $vp = array();
                    foreach ( $item['attribute_values'] as $an => $av ) {
                        $vp[] = esc_html( $an ) . ': ' . esc_html( $av );
                    }
                    $html .= '<br><span style="font-size:0.85em;color:' . $c['muted'] . ';">' . implode( ', ', $vp ) . '</span>';
                } elseif ( ! empty( $item['variant_name'] ) ) {
                    $html .= '<br><span style="font-size:0.85em;color:' . $c['muted'] . ';">' . esc_html( $item['variant_name'] ) . '</span>';
                } elseif ( ! empty( $item['variant_id'] ) && ! empty( $item['product_id'] ) ) {
                    $variants = get_post_meta( $item['product_id'], '_fc_variants', true );
                    $found_v = FC_Cart::find_variant( $variants, $item['variant_id'] );
                    if ( $found_v && ! empty( $found_v['attribute_values'] ) ) {
                        $vp = array();
                        foreach ( $found_v['attribute_values'] as $an => $av ) {
                            $vp[] = esc_html( $an ) . ': ' . esc_html( $av );
                        }
                        $html .= '<br><span style="font-size:0.85em;color:' . $c['muted'] . ';">' . implode( ', ', $vp ) . '</span>';
                    } elseif ( $found_v ) {
                        $html .= '<br><span style="font-size:0.85em;color:' . $c['muted'] . ';">' . esc_html( $found_v['name'] ?? '' ) . '</span>';
                    }
                }
                $html .= '</td>';
                $qty_text = intval( $item['quantity'] );
                if ( $show_units && $unit_label ) {
                    $qty_text .= ' ' . esc_html( $unit_label );
                }
                $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . $qty_text . '</td>';
                $net_price = round( floatval( $item['price'] ) / $tax_div, 2 );
                $net_line  = round( floatval( $item['line_total'] ) / $tax_div, 2 );
                $html .= '<td style="padding:8px 12px;text-align:right;border-bottom:1px solid ' . $c['border'] . ';">' . fc_format_price( $net_price ) . '</td>';
                $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . number_format( $tax_rate, 0 ) . '%</td>';
                $html .= '<td style="padding:8px 12px;text-align:right;border-bottom:1px solid ' . $c['border'] . ';">' . fc_format_price( $net_line ) . '</td>';
                $html .= '</tr>';
                $lp++;
            }
        }

        // Wysyłka jako ostatnia pozycja
        $ship_cost_val  = floatval( $shipping_cost );
        $ship_net       = round( $ship_cost_val / $tax_div, 2 );
        $ship_label     = $shipping_method ? fc__( 'inv_shipping' ) . ' (' . esc_html( $shipping_method ) . ')' : fc__( 'inv_shipping' );
        $col_count      = 4 + ( $show_thumbs ? 1 : 0 );
        $html .= '<tr style="background:' . $c['row_alt'] . ';">';
        $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . $lp . '</td>';
        if ( $show_thumbs ) {
            $html .= '<td style="padding:4px 8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';"></td>';
        }
        $html .= '<td style="padding:8px 12px;border-bottom:1px solid ' . $c['border'] . ';">' . $ship_label . '</td>';
        $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">1</td>';
        $html .= '<td style="padding:8px 12px;text-align:right;border-bottom:1px solid ' . $c['border'] . ';">' . fc_format_price( $ship_net ) . '</td>';
        $html .= '<td style="padding:8px;text-align:center;border-bottom:1px solid ' . $c['border'] . ';">' . number_format( $tax_rate, 0 ) . '%</td>';
        $html .= '<td style="padding:8px 12px;text-align:right;border-bottom:1px solid ' . $c['border'] . ';">' . fc_format_price( $ship_net ) . '</td>';
        $html .= '</tr>';

        $html .= '</table>';
        return $html;
    }

    /* ================================================================
       STYLE KOLORÓW
       ================================================================ */

    private static function get_style_colors( $style ) {
        $styles = array(
            'classic' => array(
                'accent'        => '#2271b1',
                'text'          => '#333',
                'muted'         => '#888',
                'border'        => '#eee',
                'card_bg'       => '#f0f7fc',
                'header_bg'     => '#2271b1',
                'header_text'   => '#fff',
                'row_alt'       => '#f8f8f8',
                'footer_border' => '#ddd',
            ),
            'minimal' => array(
                'accent'        => '#444',
                'text'          => '#222',
                'muted'         => '#999',
                'border'        => '#ddd',
                'card_bg'       => '#fafafa',
                'header_bg'     => '#fff',
                'header_text'   => '#222',
                'row_alt'       => '#fafafa',
                'footer_border' => '#ddd',
            ),
        );

        if ( $style === 'theme' ) {
            return self::build_theme_colors();
        }

        return $styles[ $style ] ?? $styles['classic'];
    }

    /**
     * Generuje paletę kolorów faktury na podstawie koloru akcentu motywu Flavor.
     * Niezależnie od trybu (dark/light) generuje jasną paletę — faktura jest drukowana.
     */
    private static function build_theme_colors() {
        $accent = '#4a90d9';
        if ( function_exists( 'flavor_hex_to_hsl' ) ) {
            $accent = get_theme_mod( 'flavor_color_accent', '#4a90d9' );
            $a = flavor_hex_to_hsl( $accent );
            $card_bg       = flavor_hsl_to_hex( $a['h'], flavor_clamp( round( $a['s'] * 0.5 ), 10, 50 ), 95 );
            $text          = flavor_hsl_to_hex( $a['h'], flavor_clamp( round( $a['s'] * 0.35 ), 10, 30 ), 20 );
            $muted         = flavor_hsl_to_hex( $a['h'], flavor_clamp( round( $a['s'] * 0.25 ), 8, 20 ), 50 );
            $border        = flavor_hsl_to_hex( $a['h'], flavor_clamp( round( $a['s'] * 0.15 ), 8, 20 ), 88 );
            $row_alt       = flavor_hsl_to_hex( $a['h'], flavor_clamp( round( $a['s'] * 0.12 ), 5, 15 ), 96 );
            $footer_border = flavor_hsl_to_hex( $a['h'], flavor_clamp( round( $a['s'] * 0.12 ), 5, 15 ), 82 );
        } else {
            $card_bg       = '#f0f7fc';
            $text          = '#333';
            $muted         = '#888';
            $border        = '#eee';
            $row_alt       = '#f8f8f8';
            $footer_border = '#ddd';
        }

        return array(
            'accent'        => $accent,
            'text'          => $text,
            'muted'         => $muted,
            'border'        => $border,
            'card_bg'       => $card_bg,
            'header_bg'     => $accent,
            'header_text'   => '#fff',
            'row_alt'       => $row_alt,
            'footer_border' => $footer_border,
        );
    }

    /* ================================================================
       SZABLONY HTML
       ================================================================ */

    public static function get_template_html( $style = 'classic' ) {
        $c = self::get_style_colors( $style );

        switch ( $style ) {
            case 'theme':
                return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: ' . $c['text'] . '; margin: 0; padding: 0; }
table { border-collapse: collapse; }
.items th { background: ' . $c['header_bg'] . '; color: ' . $c['header_text'] . '; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.items td { padding: 8px 12px; border-bottom: 1px solid ' . $c['border'] . '; }
</style>
</head>
<body>

<div style="margin-bottom:20px;">
    <table width="100%">
    <tr>
        <td width="50%" valign="bottom">
            {logo}
            <div style="font-size:22px;font-weight:bold;color:' . $c['accent'] . ';margin-top:8px;">' . fc__( 'inv_invoice_title' ) . '</div>
            <div style="font-size:14px;margin-top:4px;">{invoice_number}</div>
        </td>
        <td width="50%" valign="bottom" align="right" style="font-size:11px;color:' . $c['muted'] . ';">
            <div>' . fc__( 'inv_issue_date_label' ) . ' {invoice_date}</div>
            <div>' . fc__( 'inv_order_number_label' ) . ' {order_number}</div>
            <div>' . fc__( 'inv_order_date_label' ) . ' {order_date}</div>
        </td>
    </tr>
    </table>
</div>

<table width="100%" style="margin-bottom:30px;">
<tr>
    <td width="48%" valign="top" style="background:' . $c['card_bg'] . ';padding:16px;border-left:3px solid ' . $c['accent'] . ';">
        <div style="font-size:10px;color:' . $c['muted'] . ';text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . fc__( 'inv_seller' ) . '</div>
        <strong>{seller_name}</strong><br>
        {seller_address}<br>
        {seller_tax_no_label} {seller_tax_no}
        {seller_crn_label} {seller_crn}
        {seller_email}<br>
        {seller_phone}
        {seller_bank_account}
    </td>
    <td width="4%"></td>
    <td width="48%" valign="top" style="background:' . $c['card_bg'] . ';padding:16px;border-left:3px solid ' . $c['accent'] . ';">
        <div style="font-size:10px;color:' . $c['muted'] . ';text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . fc__( 'inv_buyer' ) . '</div>
        <strong>{buyer_name}</strong><br>
        {buyer_address}<br>
        {buyer_tax_no_label} {buyer_tax_no}
        {buyer_crn_label} {buyer_crn}
        {buyer_email}<br>
        {buyer_phone}
    </td>
</tr>
</table>

{products_table}

<table width="100%" style="margin-bottom:20px;">
<tr>
    <td width="55%"></td>
    <td width="45%">
        <table width="100%">
            <tr><td style="padding:6px 12px;">' . fc__( 'inv_net_subtotal' ) . '</td><td style="padding:6px 12px;text-align:right;">{net_total}</td></tr>
            <tr><td style="padding:6px 12px;">{tax_name} ({tax_rate}%):</td><td style="padding:6px 12px;text-align:right;">{tax_amount}</td></tr>
            <tr><td style="padding:10px 12px;font-weight:bold;font-size:13px;border-top:2px solid ' . $c['accent'] . ';">' . fc__( 'inv_total_gross' ) . '</td><td style="padding:10px 12px;font-weight:bold;font-size:13px;text-align:right;border-top:2px solid ' . $c['accent'] . ';">{total}</td></tr>
        </table>
    </td>
</tr>
</table>

<div style="background:' . $c['card_bg'] . ';padding:16px;margin-bottom:30px;border-left:3px solid ' . $c['accent'] . ';">
    <strong>' . fc__( 'inv_payment_info' ) . '</strong><br>
    ' . fc__( 'inv_payment_method_label' ) . ' {payment_method}
</div>

<div style="border-top:1px solid ' . $c['footer_border'] . ';padding-top:16px;color:' . $c['muted'] . ';font-size:9px;text-align:center;">
    {seller_name} &bull; {seller_address} &bull; {seller_tax_no_label} {seller_tax_no} &bull; {seller_crn_label} {seller_crn} &bull; {seller_email}
</div>
</body>
</html>';

            case 'minimal':
                return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: ' . $c['text'] . '; margin: 0; padding: 0; }
table { border-collapse: collapse; }
.items th { background: ' . $c['header_bg'] . '; color: ' . $c['header_text'] . '; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid ' . $c['accent'] . '; }
.items td { padding: 8px 12px; border-bottom: 1px solid ' . $c['border'] . '; }
</style>
</head>
<body>

<table width="100%" style="margin-bottom:30px;">
<tr>
    <td width="50%" valign="top">
        {logo}
    </td>
    <td width="50%" valign="top" align="right">
        <div style="font-size:20px;font-weight:bold;color:' . $c['accent'] . ';letter-spacing:2px;">' . fc__( 'inv_invoice_title' ) . '</div>
        <div style="font-size:13px;margin:4px 0 12px 0;">{invoice_number}</div>
        <div style="font-size:10px;color:' . $c['muted'] . ';">
            ' . fc__( 'inv_issue_date_label' ) . ' {invoice_date}<br>
            ' . fc__( 'inv_order_number_label' ) . ' {order_number}<br>
            ' . fc__( 'inv_order_date_label' ) . ' {order_date}
        </div>
    </td>
</tr>
</table>

<hr style="border:none;border-top:1px solid ' . $c['border'] . ';margin:0 0 24px 0;">

<table width="100%" style="margin-bottom:30px;font-size:11px;">
<tr>
    <td width="48%" valign="top">
        <div style="font-size:9px;color:' . $c['muted'] . ';text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . fc__( 'inv_seller' ) . '</div>
        <strong>{seller_name}</strong><br>
        {seller_address}<br>
        {seller_tax_no_label} {seller_tax_no}
        {seller_crn_label} {seller_crn}
        {seller_email}<br>
        {seller_phone}
        {seller_bank_account}
    </td>
    <td width="4%"></td>
    <td width="48%" valign="top">
        <div style="font-size:9px;color:' . $c['muted'] . ';text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . fc__( 'inv_buyer' ) . '</div>
        <strong>{buyer_name}</strong><br>
        {buyer_address}<br>
        {buyer_tax_no_label} {buyer_tax_no}
        {buyer_crn_label} {buyer_crn}
        {buyer_email}<br>
        {buyer_phone}
    </td>
</tr>
</table>

{products_table}

<table width="100%" style="margin-bottom:24px;">
<tr>
    <td width="55%"></td>
    <td width="45%">
        <table width="100%">
            <tr><td style="padding:5px 0;">' . fc__( 'inv_net_subtotal' ) . '</td><td style="padding:5px 0;text-align:right;">{net_total}</td></tr>
            <tr><td style="padding:5px 0;">{tax_name} ({tax_rate}%):</td><td style="padding:5px 0;text-align:right;">{tax_amount}</td></tr>
            <tr><td style="padding:8px 0;font-weight:bold;font-size:13px;border-top:1px solid ' . $c['accent'] . ';">' . fc__( 'inv_total_gross' ) . '</td><td style="padding:8px 0;font-weight:bold;font-size:13px;text-align:right;border-top:1px solid ' . $c['accent'] . ';">{total}</td></tr>
        </table>
    </td>
</tr>
</table>

<div style="padding:12px 0;margin-bottom:24px;border-top:1px solid ' . $c['border'] . ';border-bottom:1px solid ' . $c['border'] . ';">
    <strong>' . fc__( 'inv_payment_label' ) . '</strong> {payment_method}
</div>

<div style="color:' . $c['muted'] . ';font-size:8px;text-align:center;">
    {seller_name} &bull; {seller_address} &bull; {seller_tax_no_label} {seller_tax_no} &bull; {seller_crn_label} {seller_crn} &bull; {seller_email}
</div>

</body>
</html>';

            case 'classic':
            default:
                return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: ' . $c['text'] . '; margin: 0; padding: 0; }
h1 { font-size: 22px; color: ' . $c['accent'] . '; margin: 0 0 4px 0; }
table { border-collapse: collapse; }
.items th { background: ' . $c['header_bg'] . '; color: ' . $c['header_text'] . '; padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
.items td { padding: 8px 12px; border-bottom: 1px solid ' . $c['border'] . '; }
</style>
</head>
<body>

<table width="100%" style="margin-bottom:30px;">
<tr>
    <td width="50%" valign="top">
        {logo}
    </td>
    <td width="50%" valign="top" align="right">
        <div style="font-size:22px;font-weight:bold;color:' . $c['accent'] . ';">' . fc__( 'inv_invoice_title' ) . '</div>
        <div style="font-size:14px;font-weight:bold;margin:4px 0 12px 0;">{invoice_number}</div>
        <div>' . fc__( 'inv_issue_date_label' ) . ' {invoice_date}</div>
        <div>' . fc__( 'inv_order_number_label' ) . ' {order_number}</div>
        <div>' . fc__( 'inv_order_date_label' ) . ' {order_date}</div>
    </td>
</tr>
</table>

<table width="100%" style="margin-bottom:30px;">
<tr>
    <td width="48%" valign="top" style="background:' . $c['card_bg'] . ';padding:16px;">
        <div style="font-size:10px;color:' . $c['muted'] . ';text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . fc__( 'inv_seller' ) . '</div>
        <strong>{seller_name}</strong><br>
        {seller_address}<br>
        {seller_tax_no_label} {seller_tax_no}
        {seller_crn_label} {seller_crn}
        {seller_email}<br>
        {seller_phone}
        {seller_bank_account}
    </td>
    <td width="4%"></td>
    <td width="48%" valign="top" style="background:' . $c['card_bg'] . ';padding:16px;">
        <div style="font-size:10px;color:' . $c['muted'] . ';text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . fc__( 'inv_buyer' ) . '</div>
        <strong>{buyer_name}</strong><br>
        {buyer_address}<br>
        {buyer_tax_no_label} {buyer_tax_no}
        {buyer_crn_label} {buyer_crn}
        {buyer_email}<br>
        {buyer_phone}
    </td>
</tr>
</table>

{products_table}

<table width="100%" style="margin-bottom:20px;">
<tr>
    <td width="55%"></td>
    <td width="45%">
        <table width="100%">
            <tr><td style="padding:6px 12px;">' . fc__( 'inv_net_subtotal' ) . '</td><td style="padding:6px 12px;text-align:right;">{net_total}</td></tr>
            <tr><td style="padding:6px 12px;">{tax_name} ({tax_rate}%):</td><td style="padding:6px 12px;text-align:right;">{tax_amount}</td></tr>
            <tr><td style="padding:10px 12px;font-weight:bold;font-size:13px;border-top:2px solid ' . $c['accent'] . ';">' . fc__( 'inv_total_gross' ) . '</td><td style="padding:10px 12px;font-weight:bold;font-size:13px;text-align:right;border-top:2px solid ' . $c['accent'] . ';">{total}</td></tr>
        </table>
    </td>
</tr>
</table>

<div style="background:' . $c['card_bg'] . ';padding:16px;margin-bottom:30px;">
    <strong>' . fc__( 'inv_payment_info' ) . '</strong><br>
    ' . fc__( 'inv_payment_method_label' ) . ' {payment_method}
</div>

<div style="border-top:1px solid ' . $c['footer_border'] . ';padding-top:16px;color:' . $c['muted'] . ';font-size:9px;text-align:center;">
    {seller_name} &bull; {seller_address} &bull; {seller_tax_no_label} {seller_tax_no} &bull; {seller_crn_label} {seller_crn} &bull; {seller_email}
</div>

</body>
</html>';
        }
    }

    /* ================================================================
       HTML → PDF
       ================================================================ */

    public static function get_invoice_html( $order_id, $style = null ) {
        $s = self::get_settings();
        if ( ! $style ) $style = $s['template_style'];
        $template = self::get_template_html( $style );
        $data = self::get_invoice_data( $order_id );

        $html = str_replace( array_keys( $data ), array_values( $data ), $template );

        $html = preg_replace( '/<br\s*\/?>\s*(?=&bull;)/', ' ', $html );
        $html = preg_replace( '/<br\s*\/?>\s*(?=<\/)/', '', $html );
        $html = preg_replace( '/^[ \t]*<br\s*\/?>[ \t]*\r?\n/mi', '', $html );
        $html = preg_replace( '/(&bull;\s*){2,}/', '&bull; ', $html );
        $html = preg_replace( '/\s*&bull;\s*(?=\s*<\/)/', '', $html );

        return $html;
    }

    public static function get_pdf( $order_id, $style = null ) {
        $autoload = FC_PLUGIN_DIR . 'vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            wp_die( fc__( 'inv_required_library_is_not_installed_contact_the_admi' ), '', array( 'response' => 500 ) );
        }
        require_once $autoload;

        $html = self::get_invoice_html( $order_id, $style );

        $options = new \Dompdf\Options();
        $options->set( 'isRemoteEnabled', false );
        $options->set( 'defaultFont', 'DejaVu Sans' );
        $options->set( 'isHtml5ParserEnabled', true );
        $options->setChroot( array( ABSPATH, wp_upload_dir()['basedir'] ) );

        $dompdf = new \Dompdf\Dompdf( $options );
        $dompdf->loadHtml( $html, 'UTF-8' );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont( 'DejaVu Sans' );
        $canvas->page_text( 520, 810, 'Strona {PAGE_NUM} z {PAGE_COUNT}', $font, 8, array( 0.6, 0.6, 0.6 ) );

        return $dompdf->output();
    }

    /* ================================================================
       HANDLERY POBIERANIA / GENEROWANIA
       ================================================================ */

    public static function handle_generate() {
        $order_id = absint( $_GET['order_id'] ?? 0 );
        if ( ! $order_id || ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }
        check_admin_referer( 'fc_generate_invoice_' . $order_id );
        self::generate( $order_id );
        wp_safe_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit&fc_invoice_generated=1' ) );
        exit;
    }

    public static function handle_admin_download() {
        $order_id = absint( $_GET['order_id'] ?? 0 );
        if ( ! $order_id || ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }
        check_admin_referer( 'fc_download_invoice_' . $order_id );
        self::output_pdf( $order_id );
    }

    public static function handle_frontend_download() {
        if ( ! isset( $_GET['fc_download_invoice'] ) ) return;

        $order_id = absint( $_GET['fc_download_invoice'] );
        if ( ! $order_id || ! isset( $_GET['_wpnonce'] ) ) {
            wp_die( fc__( 'inv_invalid_link' ) );
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_invoice_front_' . $order_id ) ) {
            wp_die( fc__( 'inv_link_has_expired' ) );
        }

        $user_id    = get_current_user_id();
        $order_user = intval( get_post_meta( $order_id, '_fc_customer_id', true ) );
        if ( ! $user_id || $order_user !== $user_id ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }
        if ( ! self::has_invoice( $order_id ) ) {
            wp_die( fc__( 'inv_invoice_has_not_been_generated_yet' ) );
        }

        self::output_pdf( $order_id );
    }

    private static function output_pdf( $order_id ) {
        $pdf = self::get_pdf( $order_id );
        $invoice_number = get_post_meta( $order_id, '_fc_invoice_number', true ) ?: 'faktura';
        $filename = sanitize_file_name( $invoice_number ) . '.pdf';

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf;
        exit;
    }

    /* ================================================================
       ADRESY URL
       ================================================================ */

    public static function get_generate_url( $order_id ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=fc_generate_invoice&order_id=' . $order_id ),
            'fc_generate_invoice_' . $order_id
        );
    }

    public static function get_admin_download_url( $order_id ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=fc_download_invoice&order_id=' . $order_id ),
            'fc_download_invoice_' . $order_id
        );
    }

    public static function get_frontend_download_url( $order_id ) {
        return wp_nonce_url(
            add_query_arg( 'fc_download_invoice', $order_id, home_url( '/' ) ),
            'fc_invoice_front_' . $order_id
        );
    }

    /* ================================================================
       KOLUMNA NA LIŚCIE ZAMÓWIEŃ
       ================================================================ */

    public static function add_invoice_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $label ) {
            if ( $key === 'date' ) {
                $new_columns['fc_invoice'] = fc__( 'inv_invoice' );
            }
            $new_columns[ $key ] = $label;
        }
        return $new_columns;
    }

    public static function invoice_column_content( $column, $post_id ) {
        if ( $column !== 'fc_invoice' ) return;

        $s = self::get_settings();
        if ( ! $s['enabled'] ) {
            echo '<span style="color:#999;">—</span>';
            return;
        }

        if ( self::has_invoice( $post_id ) ) {
            $number = get_post_meta( $post_id, '_fc_invoice_number', true );
            echo '<strong>' . esc_html( $number ) . '</strong><br>';
            echo '<a href="' . esc_url( self::get_admin_download_url( $post_id ) ) . '" class="button button-small" style="margin-top:4px;" target="_blank">';
            echo '<span class="dashicons dashicons-pdf" style="font-size:14px;vertical-align:middle;margin-right:2px;"></span>';
            echo fc__( 'inv_download_pdf' );
            echo '</a>';
        } else {
            echo '<a href="' . esc_url( self::get_generate_url( $post_id ) ) . '" class="button button-small">';
            echo '<span class="dashicons dashicons-media-text" style="font-size:14px;vertical-align:middle;margin-right:2px;"></span>';
            echo fc__( 'inv_generate' );
            echo '</a>';
        }
    }

    /* ================================================================
       AJAX
       ================================================================ */

    public static function ajax_preview() {
        check_ajax_referer( 'fc_invoice_preview', '_wpnonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $style = sanitize_text_field( $_POST['style'] ?? '' );
        if ( ! in_array( $style, array( 'classic', 'minimal', 'theme' ), true ) ) {
            $s = self::get_settings();
            $style = $s['template_style'];
        }

        // Tymczasowo nadpisz ustawienia na potrzeby podglądu
        $tmp = self::get_settings();
        $tmp['template_style']  = $style;
        $tmp['show_thumbnails'] = isset( $_POST['show_thumbnails'] ) ? absint( $_POST['show_thumbnails'] ) : $tmp['show_thumbnails'];
        if ( isset( $_POST['logo_id'] ) )     $tmp['logo_id']     = absint( $_POST['logo_id'] );
        if ( isset( $_POST['logo_width'] ) )  $tmp['logo_width']  = max( 20, min( 600, intval( $_POST['logo_width'] ) ) );
        if ( isset( $_POST['logo_height'] ) ) $tmp['logo_height'] = max( 0, min( 300, intval( $_POST['logo_height'] ) ) );
        self::$preview_settings = $tmp;

        $url = self::generate_preview_pdf( $order_id, $style );
        self::$preview_settings = null;
        wp_send_json_success( array( 'url' => $url ) );
    }

    /**
     * Generuje plik PDF podglądu i zwraca jego URL
     */
    public static function generate_preview_pdf( $order_id = 0, $style = 'classic' ) {
        $upload_dir = wp_upload_dir();
        $preview_dir = trailingslashit( $upload_dir['basedir'] ) . 'fc-invoices-preview';
        if ( ! file_exists( $preview_dir ) ) {
            wp_mkdir_p( $preview_dir );
            file_put_contents( $preview_dir . '/index.php', '<?php // Silence is golden.' );
            file_put_contents( $preview_dir . '/.htaccess', "Options -Indexes" );
        }

        // Clean old preview files
        foreach ( glob( $preview_dir . '/preview-*.pdf' ) as $old ) {
            if ( filemtime( $old ) < time() - 300 ) {
                wp_delete_file( $old );
            }
        }

        $token = wp_generate_password( 16, false );
        $filename = 'preview-' . $token . '.pdf';
        $filepath = trailingslashit( $preview_dir ) . $filename;

        $pdf = self::get_pdf( $order_id, $style );
        if ( ! empty( $pdf ) ) {
            file_put_contents( $filepath, $pdf );
        }

        $url = trailingslashit( $upload_dir['baseurl'] ) . 'fc-invoices-preview/' . $filename;
        // Dodaj timestamp aby przeglądarka nie cache'owała
        return add_query_arg( 't', time(), $url );
    }

    /**
     * Podgląd PDF przez admin-post (fallback)
     */
    public static function handle_preview_pdf() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }
        check_admin_referer( 'fc_invoice_preview_pdf' );

        $order_id = absint( $_GET['order_id'] ?? 0 );
        $style = sanitize_text_field( $_GET['style'] ?? 'classic' );
        if ( ! in_array( $style, array( 'classic', 'minimal', 'theme' ), true ) ) {
            $style = 'classic';
        }

        $pdf = self::get_pdf( $order_id, $style );
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="preview.pdf"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf;
        exit;
    }
}
