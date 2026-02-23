<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Zarządzanie globalnymi atrybutami/wariantami
 * Przechowywane w wp_options jako 'fc_global_attributes'
 *
 * Struktura:
 * [
 *   [ 'id' => 'abc123', 'name' => 'Kolor', 'type' => 'color', 'values' => [...] ],
 *   ...
 * ]
 *
 * Unikalność: ta sama nazwa dozwolona raz na typ (text/color/image)
 */
class FC_Attributes_Admin {

    const OPTION_KEY = 'fc_global_attributes';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_post_fc_save_attribute', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_fc_delete_attribute', array( __CLASS__, 'handle_delete' ) );
        add_action( 'wp_ajax_fc_get_global_attributes', array( __CLASS__, 'ajax_get_attributes' ) );
    }

    /**
     * Pobierz wszystkie globalne atrybuty
     */
    public static function get_all() {
        $attrs = get_option( self::OPTION_KEY, array() );
        return is_array( $attrs ) ? $attrs : array();
    }

    /**
     * Zapisz wszystkie globalne atrybuty
     */
    public static function save_all( $attrs ) {
        update_option( self::OPTION_KEY, $attrs );
    }

    /**
     * Znajdź atrybut po ID
     */
    public static function get_by_id( $id ) {
        $attrs = self::get_all();
        foreach ( $attrs as $attr ) {
            if ( $attr['id'] === $id ) return $attr;
        }
        return null;
    }

    /**
     * Sprawdź czy nazwa+typ jest unikalna (pomijając dany ID)
     */
    public static function is_name_type_unique( $name, $type, $exclude_id = '' ) {
        $attrs = self::get_all();
        foreach ( $attrs as $attr ) {
            if ( $attr['id'] !== $exclude_id && mb_strtolower( $attr['name'] ) === mb_strtolower( $name ) && $attr['type'] === $type ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Znajdź produkty używające atrybutu (po nazwie)
     */
    public static function find_products_using( $attr_name ) {
        $products = get_posts( array(
            'post_type'      => 'fc_product',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_fc_product_type',
                    'value'   => 'variable',
                    'compare' => '=',
                ),
            ),
        ) );

        $using = array();
        foreach ( $products as $p ) {
            $attrs = get_post_meta( $p->ID, '_fc_attributes', true );
            if ( ! is_array( $attrs ) ) continue;
            foreach ( $attrs as $a ) {
                if ( mb_strtolower( $a['name'] ) === mb_strtolower( $attr_name ) ) {
                    $using[] = array( 'id' => $p->ID, 'title' => $p->post_title );
                    break;
                }
            }
        }
        return $using;
    }

    /**
     * Znajdź które wartości (labele) atrybutu są używane w wariantach produktów
     */
    public static function find_values_in_use( $attr_name ) {
        $products = get_posts( array(
            'post_type'      => 'fc_product',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_fc_product_type',
                    'value'   => 'variable',
                    'compare' => '=',
                ),
            ),
        ) );

        $used_labels = array();
        foreach ( $products as $p ) {
            $variants = get_post_meta( $p->ID, '_fc_variants', true );
            if ( ! is_array( $variants ) ) continue;
            foreach ( $variants as $v ) {
                $attr_vals = $v['attribute_values'] ?? array();
                foreach ( $attr_vals as $a_name => $a_label ) {
                    if ( mb_strtolower( $a_name ) === mb_strtolower( $attr_name ) ) {
                        $used_labels[ mb_strtolower( $a_label ) ] = $a_label;
                    }
                }
            }
        }
        return array_values( $used_labels );
    }

    /**
     * Auto-sync: zapisz atrybuty produktu jako globalne (wywoływane z handle_save produktu)
     */
    public static function sync_from_product( $product_attributes ) {
        if ( ! is_array( $product_attributes ) || empty( $product_attributes ) ) return;

        $globals = self::get_all();
        $changed = false;

        foreach ( $product_attributes as $attr ) {
            $name = $attr['name'] ?? '';
            $type = $attr['type'] ?? 'text';
            $values = $attr['values'] ?? array();

            if ( empty( $name ) ) continue;

            // Szukaj istniejącego globalnego atrybutu o tej nazwie i typie
            $found = false;
            foreach ( $globals as &$g ) {
                if ( mb_strtolower( $g['name'] ) === mb_strtolower( $name ) && $g['type'] === $type ) {
                    // Merge wartości — dodaj nowe (po label), zachowaj istniejące
                    $existing_labels = array_map( function( $v ) {
                        return mb_strtolower( $v['label'] ?? '' );
                    }, $g['values'] );

                    foreach ( $values as $val ) {
                        $label_lower = mb_strtolower( $val['label'] ?? '' );
                        if ( ! empty( $label_lower ) && ! in_array( $label_lower, $existing_labels ) ) {
                            $g['values'][] = $val;
                            $existing_labels[] = $label_lower;
                            $changed = true;
                        }
                    }
                    $found = true;
                    break;
                }
            }
            unset( $g );

            if ( ! $found ) {
                $globals[] = array(
                    'id'     => wp_generate_uuid4(),
                    'name'   => $name,
                    'type'   => $type,
                    'values' => $values,
                );
                $changed = true;
            }
        }

        if ( $changed ) {
            self::save_all( $globals );
        }
    }

    /**
     * Menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=fc_product',
            fc__( 'attr_attributes' ),
            fc__( 'attr_attributes' ),
            'manage_options',
            'fc-attributes',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * AJAX: zwróć globalne atrybuty dla podpowiedzi
     */
    public static function ajax_get_attributes() {
        check_ajax_referer( 'fc_admin_nonce' );
        $attrs = self::get_all();
        $result = array();
        foreach ( $attrs as $a ) {
            $result[] = array(
                'id'     => $a['id'],
                'name'   => $a['name'],
                'type'   => $a['type'],
                'values' => $a['values'],
            );
        }
        wp_send_json_success( $result );
    }

    /**
     * Renderuj stronę
     */
    public static function render_page() {
        $action = isset( $_GET['fc_action'] ) ? sanitize_text_field( $_GET['fc_action'] ) : 'list';
        $attr_id = isset( $_GET['attr_id'] ) ? sanitize_text_field( $_GET['attr_id'] ) : '';

        echo '<div class="wrap fc-attributes-wrap">';

        if ( $action === 'add' || $action === 'edit' ) {
            self::render_form( $action, $attr_id );
        } else {
            self::render_list();
        }

        echo '</div>';
    }

    /**
     * Lista atrybutów
     */
    private static function render_list() {
        $attrs = self::get_all();
        $saved = isset( $_GET['saved'] ) ? sanitize_text_field( $_GET['saved'] ) : '';
        $error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
        ?>
        <h1 class="wp-heading-inline"><?php fc_e( 'attr_product_attributes' ); ?></h1>
        <a href="<?php echo admin_url( 'admin.php?page=fc-attributes&fc_action=add' ); ?>" class="page-title-action"><?php fc_e( 'attr_add_attribute' ); ?></a>
        <hr class="wp-header-end">

        <?php if ( $saved === '1' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php fc_e( 'attr_attribute_has_been_saved' ); ?></p></div>
        <?php elseif ( $saved === 'deleted' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php fc_e( 'attr_attribute_has_been_deleted' ); ?></p></div>
        <?php endif; ?>

        <?php if ( $error === 'in_use' ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php fc_e( 'attr_cannot_delete_an_attribute_that_is_used_in_product' ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( empty( $attrs ) ) : ?>
            <div class="fc-empty-state">
                <p><?php fc_e( 'attr_no_attributes_defined_click_add_attribute_or_add_a' ); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped fc-attributes-table">
                <thead>
                    <tr>
                        <th class="manage-column"><?php fc_e( 'attr_name' ); ?></th>
                        <th class="manage-column"><?php fc_e( 'attr_type' ); ?></th>
                        <th class="manage-column"><?php fc_e( 'attr_values' ); ?></th>
                        <th class="manage-column"><?php fc_e( 'attr_used_in' ); ?></th>
                        <th class="manage-column column-actions"><?php fc_e( 'attr_actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $attrs as $attr ) :
                        $products_using = self::find_products_using( $attr['name'] );
                        $is_in_use = ! empty( $products_using );
                        $type_labels = array( 'text' => fc__( 'attr_text' ), 'color' => fc__( 'attr_color' ), 'image' => fc__( 'attr_image' ) );
                        $type_label = $type_labels[ $attr['type'] ] ?? $attr['type'];
                        $value_labels = array_map( function( $v ) { return $v['label'] ?? ''; }, $attr['values'] );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $attr['name'] ); ?></strong></td>
                            <td>
                                <span class="fc-attr-type-badge fc-attr-type-<?php echo esc_attr( $attr['type'] ); ?>">
                                    <?php echo esc_html( $type_label ); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fc-attr-values-preview">
                                    <?php if ( $attr['type'] === 'color' ) : ?>
                                        <?php foreach ( $attr['values'] as $val ) : ?>
                                            <span class="fc-attr-val-color" style="background: <?php echo esc_attr( $val['value'] ?? '#ccc' ); ?>" title="<?php echo esc_attr( $val['label'] ?? '' ); ?>"></span>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <?php echo esc_html( implode( ', ', array_filter( $value_labels ) ) ); ?>
                                    <?php endif; ?>
                                    <?php if ( empty( $attr['values'] ) ) : ?>
                                        <em class="fc-muted"><?php fc_e( 'attr_no_values' ); ?></em>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ( $is_in_use ) : ?>
                                    <span class="fc-usage-badge" title="<?php echo esc_attr( implode( ', ', array_column( $products_using, 'title' ) ) ); ?>">
                                        <?php echo fc_n( 'attr_d_product', 'attr_d_products', count( $products_using ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <em class="fc-muted"><?php fc_e( 'attr_unused' ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <?php if ( $is_in_use ) : ?>
                                    <a href="<?php echo admin_url( 'admin.php?page=fc-attributes&fc_action=edit&attr_id=' . urlencode( $attr['id'] ) ); ?>" class="button button-small" title="<?php echo esc_attr( fc__( 'attr_edit' ) ); ?>">
                                        <span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
                                    </a>
                                    <button type="button" class="button button-small fc-attr-action-blocked" data-products="<?php echo esc_attr( wp_json_encode( $products_using ) ); ?>" title="<?php echo esc_attr( fc__( 'attr_delete' ) ); ?>">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle; color: #b32d2e;"></span>
                                    </button>
                                <?php else : ?>
                                    <a href="<?php echo admin_url( 'admin.php?page=fc-attributes&fc_action=edit&attr_id=' . urlencode( $attr['id'] ) ); ?>" class="button button-small" title="<?php echo esc_attr( fc__( 'attr_edit' ) ); ?>">
                                        <span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
                                    </a>
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=fc_delete_attribute&attr_id=' . urlencode( $attr['id'] ) ), 'fc_delete_attr_' . $attr['id'] ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_attr( fc__( 'attr_are_you_sure_you_want_to_delete_this_attribute' ) ); ?>');" title="<?php echo esc_attr( fc__( 'attr_delete' ) ); ?>">
                                        <span class="dashicons dashicons-trash" style="vertical-align: middle; color: #b32d2e;"></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    /**
     * Formularz dodawania/edycji atrybutu
     */
    private static function render_form( $action, $attr_id ) {
        $attr = null;
        $is_in_use = false;
        $products_using = array();
        $locked_values = array();
        if ( $action === 'edit' && $attr_id ) {
            $attr = self::get_by_id( $attr_id );
            if ( ! $attr ) {
                echo '<div class="notice notice-error"><p>' . fc__( 'attr_attribute_does_not_exist' ) . '</p></div>';
                return;
            }
            $products_using = self::find_products_using( $attr['name'] );
            $is_in_use = ! empty( $products_using );
            if ( $is_in_use ) {
                $locked_values = self::find_values_in_use( $attr['name'] );
            }
        }

        $error_msg = isset( $_GET['fc_error'] ) ? sanitize_text_field( $_GET['fc_error'] ) : '';

        $form_name   = $attr ? $attr['name'] : '';
        $form_type   = $attr ? $attr['type'] : 'text';
        $form_values = $attr ? $attr['values'] : array();
        $is_edit     = $action === 'edit';
        ?>
        <h1><?php echo $is_edit ? fc__( 'attr_edit_attribute' ) : fc__( 'attr_add_attribute' ); ?></h1>
        <a href="<?php echo admin_url( 'admin.php?page=fc-attributes' ); ?>" class="page-title-action" style="margin-bottom: 15px; display: inline-block;"><?php fc_e( 'attr_back_to_list' ); ?></a>

        <?php if ( $is_in_use ) :
            $prod_names = array_map( function( $p ) {
                return '<a href="' . admin_url( 'admin.php?page=fc-product-add&product_id=' . $p['id'] ) . '">' . esc_html( $p['title'] ) . '</a>';
            }, $products_using );
        ?>
            <div class="notice notice-info">
                <p><?php printf(
                    fc__( 'attr_this_attribute_is_used_in_products_you_can_add_new' ),
                    implode( ', ', $prod_names )
                ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( $error_msg === 'duplicate' ) : ?>
            <div class="notice notice-error"><p><?php fc_e( 'attr_an_attribute_with_this_name_and_type_already_exist' ); ?></p></div>
        <?php elseif ( $error_msg === 'empty_name' ) : ?>
            <div class="notice notice-error"><p><?php fc_e( 'attr_attribute_name_is_required' ); ?></p></div>
        <?php elseif ( $error_msg === 'locked_removed' ) : ?>
            <div class="notice notice-error"><p><?php fc_e( 'attr_cannot_delete_values_that_are_used_in_product_vari' ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="fc-attr-global-form">
            <input type="hidden" name="action" value="fc_save_attribute">
            <?php wp_nonce_field( 'fc_save_attr' ); ?>
            <?php if ( $is_edit ) : ?>
                <input type="hidden" name="attr_id" value="<?php echo esc_attr( $attr_id ); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="fc_ga_name"><?php fc_e( 'attr_attribute_name' ); ?> <span class="required">*</span></label></th>
                    <td>
                        <input type="text" id="fc_ga_name" name="fc_ga_name" value="<?php echo esc_attr( $form_name ); ?>" class="regular-text" required <?php echo $is_in_use ? 'readonly style="background:#f0f0f0;cursor:not-allowed;"' : ''; ?>>
                        <?php if ( $is_in_use ) : ?>
                            <p class="description"><?php fc_e( 'attr_name_is_locked_attribute_is_used_in_products' ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fc_ga_type"><?php fc_e( 'attr_type' ); ?></label></th>
                    <td>
                        <select id="fc_ga_type" name="fc_ga_type" <?php echo $is_in_use ? 'disabled' : ''; ?>>
                            <option value="text" <?php selected( $form_type, 'text' ); ?>><?php fc_e( 'attr_text' ); ?></option>
                            <option value="color" <?php selected( $form_type, 'color' ); ?>><?php fc_e( 'attr_color' ); ?></option>
                            <option value="image" <?php selected( $form_type, 'image' ); ?>><?php fc_e( 'attr_image' ); ?></option>
                        </select>
                        <?php if ( $is_in_use ) : ?>
                            <input type="hidden" name="fc_ga_type" value="<?php echo esc_attr( $form_type ); ?>">
                            <p class="description"><?php fc_e( 'attr_type_is_locked_attribute_is_used_in_products' ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php fc_e( 'attr_values' ); ?></th>
                    <td>
                        <div id="fc_ga_values_wrap">
                            <!-- Renderowane przez JS na podstawie typu -->
                        </div>
                        <input type="hidden" id="fc_ga_values_json" name="fc_ga_values_json" value="<?php echo esc_attr( wp_json_encode( $form_values ) ); ?>">
                        <input type="hidden" id="fc_ga_locked_values" value="<?php echo esc_attr( wp_json_encode( array_map( 'mb_strtolower', $locked_values ) ) ); ?>">
                    </td>
                </tr>
            </table>

            <?php submit_button( $is_edit ? fc__( 'attr_save_changes' ) : fc__( 'attr_add_attribute' ) ); ?>
        </form>
        <?php
    }

    /**
     * Zapisz atrybut
     */
    public static function handle_save() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'fc_save_attr' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        $attr_id = isset( $_POST['attr_id'] ) ? sanitize_text_field( $_POST['attr_id'] ) : '';
        $name    = sanitize_text_field( $_POST['fc_ga_name'] ?? '' );
        $type    = sanitize_text_field( $_POST['fc_ga_type'] ?? 'text' );

        if ( empty( $name ) ) {
            $redirect = admin_url( 'admin.php?page=fc-attributes&fc_action=' . ( $attr_id ? 'edit&attr_id=' . $attr_id : 'add' ) . '&fc_error=empty_name' );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( ! in_array( $type, array( 'text', 'color', 'image' ) ) ) $type = 'text';

        // Sprawdź unikalność
        if ( ! self::is_name_type_unique( $name, $type, $attr_id ) ) {
            $redirect = admin_url( 'admin.php?page=fc-attributes&fc_action=' . ( $attr_id ? 'edit&attr_id=' . $attr_id : 'add' ) . '&fc_error=duplicate' );
            wp_safe_redirect( $redirect );
            exit;
        }

        // Wartości
        $values = array();
        $values_json = stripslashes( $_POST['fc_ga_values_json'] ?? '[]' );
        $decoded = json_decode( $values_json, true );
        if ( is_array( $decoded ) ) {
            $values = $decoded;
        }

        // Walidacja: nie pozwól usunąć wartości użytych w wariantach
        if ( $attr_id ) {
            $existing = self::get_by_id( $attr_id );
            if ( $existing ) {
                $locked = self::find_values_in_use( $existing['name'] );
                $locked_lower = array_map( 'mb_strtolower', $locked );
                $new_labels_lower = array_map( function( $v ) {
                    return mb_strtolower( $v['label'] ?? '' );
                }, $values );

                foreach ( $locked_lower as $ll ) {
                    if ( ! in_array( $ll, $new_labels_lower ) ) {
                        $redirect = admin_url( 'admin.php?page=fc-attributes&fc_action=edit&attr_id=' . $attr_id . '&fc_error=locked_removed' );
                        wp_safe_redirect( $redirect );
                        exit;
                    }
                }

                // Jeśli w użyciu, wymuś oryginalną nazwę i typ
                $products_using = self::find_products_using( $existing['name'] );
                if ( ! empty( $products_using ) ) {
                    $name = $existing['name'];
                    $type = $existing['type'];
                }
            }
        }

        $attrs = self::get_all();

        if ( $attr_id ) {
            // Edycja
            foreach ( $attrs as &$a ) {
                if ( $a['id'] === $attr_id ) {
                    $a['name']   = $name;
                    $a['type']   = $type;
                    $a['values'] = $values;
                    break;
                }
            }
            unset( $a );
        } else {
            // Nowy
            $attrs[] = array(
                'id'     => wp_generate_uuid4(),
                'name'   => $name,
                'type'   => $type,
                'values' => $values,
            );
        }

        self::save_all( $attrs );

        wp_safe_redirect( admin_url( 'admin.php?page=fc-attributes&saved=1' ) );
        exit;
    }

    /**
     * Usuń atrybut
     */
    public static function handle_delete() {
        $attr_id = isset( $_GET['attr_id'] ) ? sanitize_text_field( $_GET['attr_id'] ) : '';

        if ( ! $attr_id || ! wp_verify_nonce( $_GET['_wpnonce'], 'fc_delete_attr_' . $attr_id ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( fc__( 'attr_no_permissions' ) );
        }

        // Sprawdź czy jest w użyciu
        $attr = self::get_by_id( $attr_id );
        if ( $attr ) {
            $products_using = self::find_products_using( $attr['name'] );
            if ( ! empty( $products_using ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=fc-attributes&error=in_use' ) );
                exit;
            }
        }

        $attrs = self::get_all();
        $attrs = array_filter( $attrs, function( $a ) use ( $attr_id ) {
            return $a['id'] !== $attr_id;
        } );

        self::save_all( array_values( $attrs ) );

        wp_safe_redirect( admin_url( 'admin.php?page=fc-attributes&saved=deleted' ) );
        exit;
    }
}
