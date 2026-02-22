<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Import/Export produktów CSV (W10)
 */
class FC_Import_Export {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_post_fc_export_csv', array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_post_fc_import_csv', array( __CLASS__, 'handle_import' ) );
    }

    /**
     * Podmenu w produktach
     */
    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=fc_product',
            fc__( 'ie_import_export' ),
            fc__( 'ie_import_export' ),
            'manage_options',
            'fc-import-export',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Strona import/export
     */
    public static function render_page() {
        $notice = '';
        if ( isset( $_GET['imported'] ) ) {
            $count = intval( $_GET['imported'] );
            $notice = '<div class="notice notice-success is-dismissible"><p>' . sprintf( fc__( 'ie_imported_products' ), $count ) . '</p></div>';
        }
        if ( isset( $_GET['error'] ) ) {
            $notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html( urldecode( $_GET['error'] ) ) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php fc_e( 'ie_product_import_export' ); ?></h1>
            <?php echo $notice; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:20px;">
                <!-- EXPORT -->
                <div class="card" style="padding:20px;">
                    <h2><?php fc_e( 'ie_csv_export' ); ?></h2>
                    <p><?php fc_e( 'ie_download_all_products_as_a_csv_file' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'fc_export_csv', 'fc_export_nonce' ); ?>
                        <input type="hidden" name="action" value="fc_export_csv">
                        <p>
                            <label><input type="checkbox" name="include_meta" value="1" checked> <?php fc_e( 'ie_include_extended_fields_dimensions_seo_badges' ); ?></label>
                        </p>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download" style="vertical-align:text-bottom;margin-right:4px;"></span>
                            <?php fc_e( 'ie_export_csv' ); ?>
                        </button>
                    </form>
                </div>

                <!-- IMPORT -->
                <div class="card" style="padding:20px;">
                    <h2><?php fc_e( 'ie_csv_import' ); ?></h2>
                    <p><?php fc_e( 'ie_import_products_from_a_csv_file_products_with_an_e' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'fc_import_csv', 'fc_import_nonce' ); ?>
                        <input type="hidden" name="action" value="fc_import_csv">
                        <p><input type="file" name="csv_file" accept=".csv" required></p>
                        <p>
                            <label><input type="checkbox" name="update_existing" value="1" checked> <?php fc_e( 'ie_update_existing_products_by_sku' ); ?></label>
                        </p>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-upload" style="vertical-align:text-bottom;margin-right:4px;"></span>
                            <?php fc_e( 'ie_import_csv' ); ?>
                        </button>
                    </form>

                    <hr style="margin:20px 0;">
                    <h4><?php fc_e( 'ie_csv_format' ); ?></h4>
                    <p class="description"><?php fc_e( 'ie_the_first_line_must_contain_column_headers_require' ); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Kolumny eksportu
     */
    private static function get_export_columns( $extended = true ) {
        $cols = array(
            'id', 'sku', 'title', 'product_type', 'price', 'sale_price', 'sale_percent',
            'stock', 'stock_status', 'manage_stock', 'weight', 'description', 'short_description',
            'category', 'brand', 'status', 'image_url',
        );
        if ( $extended ) {
            $cols = array_merge( $cols, array(
                'length', 'width', 'height', 'tax_class', 'min_quantity', 'max_quantity',
                'meta_title', 'meta_description', 'badges', 'shipping_class',
                'external_url', 'external_text', 'purchase_note',
            ) );
        }
        return $cols;
    }

    /**
     * Eksport CSV
     */
    public static function handle_export() {
        if ( ! wp_verify_nonce( $_POST['fc_export_nonce'] ?? '', 'fc_export_csv' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $extended = ! empty( $_POST['include_meta'] );
        $columns = self::get_export_columns( $extended );

        $products = get_posts( array(
            'post_type'      => 'fc_product',
            'post_status'    => array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private' ),
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $filename = 'flavor-products-' . wp_date( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );

        $output = fopen( 'php://output', 'w' );
        // BOM for Excel UTF-8
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        fputcsv( $output, $columns, ';' );

        foreach ( $products as $product ) {
            $pid = $product->ID;
            $cats = wp_get_object_terms( $pid, 'fc_product_cat', array( 'fields' => 'names' ) );
            $brands = wp_get_object_terms( $pid, 'fc_product_brand', array( 'fields' => 'names' ) );

            $sc = wp_get_object_terms( $pid, 'fc_shipping_class', array( 'fields' => 'names' ) );

            $status_map = array(
                'fc_published' => 'published', 'fc_draft' => 'draft', 'fc_hidden' => 'hidden',
                'fc_preorder' => 'preorder', 'fc_private' => 'private',
            );

            $row = array(
                'id'                => $pid,
                'sku'               => get_post_meta( $pid, '_fc_sku', true ),
                'title'             => $product->post_title,
                'product_type'      => get_post_meta( $pid, '_fc_product_type', true ) ?: 'simple',
                'price'             => get_post_meta( $pid, '_fc_price', true ),
                'sale_price'        => get_post_meta( $pid, '_fc_sale_price', true ),
                'sale_percent'      => get_post_meta( $pid, '_fc_sale_percent', true ),
                'stock'             => get_post_meta( $pid, '_fc_stock', true ),
                'stock_status'      => get_post_meta( $pid, '_fc_stock_status', true ),
                'manage_stock'      => get_post_meta( $pid, '_fc_manage_stock', true ),
                'weight'            => get_post_meta( $pid, '_fc_weight', true ),
                'description'       => $product->post_content,
                'short_description' => $product->post_excerpt,
                'category'          => is_array( $cats ) ? implode( '|', $cats ) : '',
                'brand'             => is_array( $brands ) ? implode( '|', $brands ) : '',
                'status'            => $status_map[ $product->post_status ] ?? 'draft',
                'image_url'         => get_the_post_thumbnail_url( $pid, 'full' ) ?: '',
            );

            if ( $extended ) {
                $badges = get_post_meta( $pid, '_fc_badges', true );
                $row['length']           = get_post_meta( $pid, '_fc_length', true );
                $row['width']            = get_post_meta( $pid, '_fc_width', true );
                $row['height']           = get_post_meta( $pid, '_fc_height', true );
                $row['tax_class']        = get_post_meta( $pid, '_fc_tax_class', true );
                $row['min_quantity']     = get_post_meta( $pid, '_fc_min_quantity', true );
                $row['max_quantity']     = get_post_meta( $pid, '_fc_max_quantity', true );
                $row['meta_title']       = get_post_meta( $pid, '_fc_meta_title', true );
                $row['meta_description'] = get_post_meta( $pid, '_fc_meta_description', true );
                $row['badges']           = is_array( $badges ) ? implode( '|', $badges ) : '';
                $row['shipping_class']   = is_array( $sc ) ? implode( '|', $sc ) : '';
                $row['external_url']     = get_post_meta( $pid, '_fc_external_url', true );
                $row['external_text']    = get_post_meta( $pid, '_fc_external_text', true );
                $row['purchase_note']    = get_post_meta( $pid, '_fc_purchase_note', true );
            }

            $csv_row = array();
            foreach ( $columns as $col ) {
                $csv_row[] = $row[ $col ] ?? '';
            }
            fputcsv( $output, $csv_row, ';' );
        }

        fclose( $output );
        exit;
    }

    /**
     * Import CSV
     */
    public static function handle_import() {
        if ( ! wp_verify_nonce( $_POST['fc_import_nonce'] ?? '', 'fc_import_csv' ) ) {
            wp_die( fc__( 'coupon_security_error' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=fc-import-export&error=' . urlencode( fc__( 'ie_no_file_was_uploaded' ) ) ) );
            exit;
        }

        $update_existing = ! empty( $_POST['update_existing'] );
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            wp_safe_redirect( admin_url( 'admin.php?page=fc-import-export&error=' . urlencode( fc__( 'ie_cannot_read_the_file' ) ) ) );
            exit;
        }

        // Pomiń BOM
        $bom = fread( $handle, 3 );
        if ( $bom !== chr(0xEF) . chr(0xBB) . chr(0xBF) ) {
            rewind( $handle );
        }

        // Nagłówki
        $headers = fgetcsv( $handle, 0, ';' );
        if ( ! $headers ) {
            fclose( $handle );
            wp_safe_redirect( admin_url( 'admin.php?page=fc-import-export&error=' . urlencode( fc__( 'ie_empty_csv_file' ) ) ) );
            exit;
        }
        $headers = array_map( 'trim', $headers );
        $headers = array_map( 'strtolower', $headers );

        $status_map = array(
            'published' => 'fc_published', 'draft' => 'fc_draft', 'hidden' => 'fc_hidden',
            'preorder' => 'fc_preorder', 'private' => 'fc_private',
        );

        $imported = 0;
        while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
            if ( count( $row ) < count( $headers ) ) {
                $row = array_pad( $row, count( $headers ), '' );
            }
            $data = array_combine( $headers, array_slice( $row, 0, count( $headers ) ) );

            $title = trim( $data['title'] ?? '' );
            if ( empty( $title ) ) continue;

            $sku = trim( $data['sku'] ?? '' );
            $existing_id = 0;

            // Szukaj istniejącego produktu po SKU
            if ( $sku && $update_existing ) {
                $found = get_posts( array(
                    'post_type'      => 'fc_product',
                    'meta_key'       => '_fc_sku',
                    'meta_value'     => $sku,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'post_status'    => array( 'fc_published', 'fc_draft', 'fc_hidden', 'fc_preorder', 'fc_private', 'fc_trash' ),
                ) );
                if ( ! empty( $found ) ) $existing_id = $found[0];
            }

            $post_status = isset( $data['status'] ) ? ( $status_map[ $data['status'] ] ?? 'fc_draft' ) : 'fc_draft';
            $post_data = array(
                'post_type'    => 'fc_product',
                'post_title'   => $title,
                'post_content' => $data['description'] ?? '',
                'post_excerpt' => $data['short_description'] ?? '',
                'post_status'  => $post_status,
            );

            if ( $existing_id ) {
                $post_data['ID'] = $existing_id;
                wp_update_post( $post_data );
                $product_id = $existing_id;
            } else {
                $product_id = wp_insert_post( $post_data );
            }

            if ( ! $product_id || is_wp_error( $product_id ) ) continue;

            // Meta proste
            $meta_map = array(
                'sku'              => '_fc_sku',
                'product_type'     => '_fc_product_type',
                'price'            => '_fc_price',
                'sale_price'       => '_fc_sale_price',
                'sale_percent'     => '_fc_sale_percent',
                'stock'            => '_fc_stock',
                'stock_status'     => '_fc_stock_status',
                'manage_stock'     => '_fc_manage_stock',
                'weight'           => '_fc_weight',
                'length'           => '_fc_length',
                'width'            => '_fc_width',
                'height'           => '_fc_height',
                'tax_class'        => '_fc_tax_class',
                'min_quantity'     => '_fc_min_quantity',
                'max_quantity'     => '_fc_max_quantity',
                'meta_title'       => '_fc_meta_title',
                'meta_description' => '_fc_meta_description',
                'external_url'     => '_fc_external_url',
                'external_text'    => '_fc_external_text',
                'purchase_note'    => '_fc_purchase_note',
            );

            foreach ( $meta_map as $csv_key => $meta_key ) {
                if ( isset( $data[ $csv_key ] ) && $data[ $csv_key ] !== '' ) {
                    update_post_meta( $product_id, $meta_key, sanitize_text_field( $data[ $csv_key ] ) );
                }
            }

            // Cena efektywna
            $price_val = $data['price'] ?? '';
            $sale_val  = $data['sale_price'] ?? '';
            $eff = ( $sale_val && floatval( $sale_val ) > 0 ) ? $sale_val : $price_val;
            if ( $eff ) {
                update_post_meta( $product_id, '_fc_effective_price', $eff );
            }

            // Odznaki (pipe-separated)
            if ( isset( $data['badges'] ) && $data['badges'] !== '' ) {
                $badges = array_map( 'trim', explode( '|', $data['badges'] ) );
                update_post_meta( $product_id, '_fc_badges', $badges );
            }

            // Taksonomie (pipe-separated names)
            $tax_map = array(
                'category'       => 'fc_product_cat',
                'brand'          => 'fc_product_brand',

                'shipping_class' => 'fc_shipping_class',
            );
            foreach ( $tax_map as $csv_key => $taxonomy ) {
                if ( isset( $data[ $csv_key ] ) && $data[ $csv_key ] !== '' ) {
                    $term_names = array_map( 'trim', explode( '|', $data[ $csv_key ] ) );
                    $term_ids = array();
                    foreach ( $term_names as $tn ) {
                        if ( empty( $tn ) ) continue;
                        $term = get_term_by( 'name', $tn, $taxonomy );
                        if ( ! $term ) {
                            $result = wp_insert_term( $tn, $taxonomy );
                            if ( ! is_wp_error( $result ) ) {
                                $term_ids[] = $result['term_id'];
                            }
                        } else {
                            $term_ids[] = $term->term_id;
                        }
                    }
                    if ( ! empty( $term_ids ) ) {
                        wp_set_object_terms( $product_id, $term_ids, $taxonomy );
                    }
                }
            }

            // Obrazek z URL
            if ( isset( $data['image_url'] ) && $data['image_url'] && ! $existing_id ) {
                $image_id = self::import_image( $data['image_url'], $product_id );
                if ( $image_id ) {
                    set_post_thumbnail( $product_id, $image_id );
                }
            }

            $imported++;
        }

        fclose( $handle );
        wp_safe_redirect( admin_url( 'admin.php?page=fc-import-export&imported=' . $imported ) );
        exit;
    }

    /**
     * Import obrazka z URL
     */
    private static function import_image( $url, $post_id ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) return 0;

        $file_array = array(
            'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        );

        $id = media_handle_sideload( $file_array, $post_id );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $tmp );
            return 0;
        }

        return $id;
    }
}
